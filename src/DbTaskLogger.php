<?php

declare(strict_types=1);

namespace ParallelNetteDbTaskLogger;

use DateTime;
use Nette\Database\Explorer;
use Parallel\Logging\TaskLogger\TaskLogger;

class DbTaskLogger implements TaskLogger
{
    /** @var Explorer */
    private $logDb;

    /** @var string */
    private $taskName;

    /** @var array<string, mixed> */
    private $records = [];

    public function __construct(Explorer $logDb, string $taskName)
    {
        $this->logDb = $logDb;
        $this->taskName = $taskName;
    }

    /**
     * @param int $allTasksCount
     * @param array<int, string> $inputSubnets
     */
    public function prepareGlobal(int $allTasksCount, array $inputSubnets): void
    {
        $this->logDb->query('DROP TABLE IF EXISTS `parallel_tasks`');
        $this->logDb->query('CREATE TABLE `parallel_tasks` (
            id integer not null AUTO_INCREMENT,
            name varchar(512),
            table_name varchar(512),
            start_at datetime,
            end_at datetime,
            duration integer,
            memory_peak integer,
            run_with_tasks json,
            skip_count integer,
            error_count integer,
            success_count integer,
            PRIMARY KEY (id)
        )');

        $this->logDb->query('DROP TABLE IF EXISTS `parallel_stats`');
        $this->logDb->query('CREATE TABLE `parallel_stats` (
            id integer not null AUTO_INCREMENT,
            all_tasks_count integer,
            subnets json,
            start_at datetime,
            end_at datetime,
            PRIMARY KEY (id)
        )');

        $this->logDb->table('parallel_stats')->insert([
            'all_tasks_count' => $allTasksCount,
            'start_at' => new DateTime(),
            'subnets' => json_encode($inputSubnets),
        ]);
    }

    public function processGlobal(): void
    {
        $this->logDb->table('parallel_stats')->fetch()->update(['end_at' => new DateTime()]);
    }

    /**
     * @param string $name
     * @param DateTime $startAt
     * @param DateTime $endAt
     * @param int $memoryPeak
     * @param array<string, array<string, string>> $runWithTasks
     * @param array<mixed, string> $extra
     */
    public function processDoneTaskData(string $name, DateTime $startAt, DateTime $endAt, int $memoryPeak, array $runWithTasks, array $extra): void
    {
        $this->logDb->table('parallel_tasks')->insert([
            'name' => $name,
            'table_name' => $this->getTableName($name),
            'start_at' => $startAt,
            'end_at' => $endAt,
            'duration' => $endAt->getTimestamp() - $startAt->getTimestamp(),
            'memory_peak' => $memoryPeak,
            'run_with_tasks' => json_encode($runWithTasks),
            'skip_count' => $extra['skip'] ?? null,
            'error_count' => $extra['error'] ?? null,
            'success_count' => $extra['success'] ?? null,
        ]);
    }

    public function prepare(): void
    {
        $this->logDb->query(sprintf('DROP TABLE IF EXISTS `%s`', $this->getTableName()));
        $this->logDb->query(sprintf('CREATE TABLE `%s` (
                id integer not null AUTO_INCREMENT,
                type varchar(255),
                message varchar(512),
                data json,
                record_id varchar(512),
                PRIMARY KEY (id)
            )', $this->getTableName()));

        $this->logDb->query(sprintf('CREATE INDEX type_idx ON `%s`(type)', $this->getTableName()));
        $this->logDb->getStructure()->rebuild();
    }

    public function process(): void
    {
        foreach (array_chunk($this->records, 10000) as $log) {
            $this->logDb->table($this->getTableName())->insert($log);
        }
        $this->records = [];
    }

    public function addLog(string $type, string $message, array $info = []): void
    {
        $recordId = null;
        if (isset($info['record_id'])) {
            $recordId = $info['record_id'];
            unset($info['record_id']);
        }

        $record = [
            'type' => $type,
            'message' => $message,
            'data' => isset($info['data']) ? json_encode($info['data']) : null,
            'record_id' => $recordId
        ];

        $this->records[] = $record;
    }

    private function getTableName(?string $taskName = null): string
    {
        $name = $taskName ?? $this->taskName;
        return str_replace(':', '_', $name);
    }
}
