<?php

declare(strict_types=1);

namespace PhilipRehberger\StructuredLogger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class MultiLogger extends AbstractLogger
{
    /** @var list<LoggerInterface> */
    private readonly array $loggers;

    /**
     * Create a logger that fans out log calls to multiple logger instances.
     *
     * @param  list<LoggerInterface>  $loggers  Logger instances to delegate to
     */
    public function __construct(array $loggers = [])
    {
        $this->loggers = $loggers;
    }

    /**
     * Log a message to all registered loggers.
     *
     * @param  mixed  $level
     * @param  array<string, mixed>  $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->log($level, $message, $context);
        }
    }
}
