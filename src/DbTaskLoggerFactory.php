<?php

declare(strict_types=1);

namespace ParallelNetteDbTaskLogger;

use Nette\Database\Explorer;
use Parallel\Logging\TaskLogger\TaskLogger;
use Parallel\Logging\TaskLogger\TaskLoggerFactory;

class DbTaskLoggerFactory implements TaskLoggerFactory
{
    /** @var Explorer */
    private $logDb;

    public function __construct(Explorer $logDb)
    {
        $this->logDb = $logDb;
    }

    public function create(string $taskName): TaskLogger
    {
        return new DbTaskLogger(
            $this->logDb,
            $taskName
        );
    }
}
