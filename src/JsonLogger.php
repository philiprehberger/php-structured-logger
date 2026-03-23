<?php

declare(strict_types=1);

namespace PhilipRehberger\StructuredLogger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

final class JsonLogger extends AbstractLogger
{
    private const LEVEL_PRIORITY = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    /** @var resource|null */
    private $handle = null;

    private string $minLevel = LogLevel::DEBUG;

    /**
     * @param  string  $output  File path or 'php://stdout' or 'php://stderr'
     * @param  string  $channel  Application/service name
     * @param  array<string>  $redactKeys  Keys to redact from context
     * @param  array<string, mixed>  $persistentContext  Context fields included in every log entry
     */
    public function __construct(
        private readonly string $output = 'php://stdout',
        private readonly string $channel = 'app',
        private readonly array $redactKeys = ['password', 'token', 'secret', 'api_key', 'authorization', 'credit_card'],
        private readonly array $persistentContext = [],
    ) {}

    /**
     * Return a new logger instance with additional persistent context fields.
     *
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): self
    {
        return new self(
            output: $this->output,
            channel: $this->channel,
            redactKeys: $this->redactKeys,
            persistentContext: array_merge($this->persistentContext, $context),
        );
    }

    /**
     * Set the minimum log level. Entries below this threshold are skipped.
     */
    public function setMinLevel(string $level): void
    {
        $this->minLevel = $level;
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $levelString = (string) $level;

        if ($this->isBelow($levelString)) {
            return;
        }

        $mergedContext = array_merge($this->persistentContext, $context);

        $entry = new LogEntry(
            timestamp: new \DateTimeImmutable,
            level: $levelString,
            message: (string) $message,
            context: $this->redact($mergedContext),
            channel: $this->channel,
        );

        $this->writeLine($entry->toJson());
    }

    /**
     * Check if a level is below the minimum threshold.
     */
    private function isBelow(string $level): bool
    {
        $currentPriority = self::LEVEL_PRIORITY[$level] ?? 0;
        $minPriority = self::LEVEL_PRIORITY[$this->minLevel] ?? 0;

        return $currentPriority < $minPriority;
    }

    /**
     * Redact sensitive keys from context.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        $redacted = [];
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), $this->redactKeys, true)) {
                $redacted[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redact($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    private function writeLine(string $json): void
    {
        if ($this->handle === null) {
            $handle = fopen($this->output, 'a');
            if ($handle === false) {
                return;
            }
            $this->handle = $handle;
        }

        fwrite($this->handle, $json."\n");
    }

    public function __destruct()
    {
        if ($this->handle !== null && $this->output !== 'php://stdout' && $this->output !== 'php://stderr') {
            fclose($this->handle);
        }
    }
}
