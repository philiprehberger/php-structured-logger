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

    private const ALWAYS_LOG_LEVELS = [
        LogLevel::ERROR,
        LogLevel::CRITICAL,
        LogLevel::ALERT,
        LogLevel::EMERGENCY,
    ];

    /** @var resource|null */
    private $handle = null;

    private string $minLevel = LogLevel::DEBUG;

    private float $samplingRate = 1.0;

    private ?string $correlationId = null;

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
        $new = new self(
            output: $this->output,
            channel: $this->channel,
            redactKeys: $this->redactKeys,
            persistentContext: array_merge($this->persistentContext, $context),
        );
        $new->minLevel = $this->minLevel;
        $new->samplingRate = $this->samplingRate;
        $new->correlationId = $this->correlationId;

        return $new;
    }

    /**
     * Set the minimum log level. Entries below this threshold are skipped.
     */
    public function setMinLevel(string $level): void
    {
        $this->minLevel = $level;
    }

    /**
     * Enable probabilistic log sampling. Error-level and above are always logged.
     *
     * @param  float  $rate  Sampling rate between 0.0 and 1.0
     */
    public function withSampling(float $rate): self
    {
        $this->samplingRate = max(0.0, min(1.0, $rate));

        return $this;
    }

    /**
     * Set a correlation ID that is automatically included in every log entry's context.
     */
    public function withCorrelationId(string $id): self
    {
        $this->correlationId = $id;

        return $this;
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $levelString = (string) $level;

        if ($this->isBelow($levelString)) {
            return;
        }

        if (! $this->shouldSample($levelString)) {
            return;
        }

        $mergedContext = $this->persistentContext;

        if ($this->correlationId !== null) {
            $mergedContext['correlation_id'] = $this->correlationId;
        }

        $mergedContext = array_merge($mergedContext, $context);

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
     * Determine if a log entry should be written based on sampling rate.
     */
    private function shouldSample(string $level): bool
    {
        if (in_array($level, self::ALWAYS_LOG_LEVELS, true)) {
            return true;
        }

        if ($this->samplingRate >= 1.0) {
            return true;
        }

        if ($this->samplingRate <= 0.0) {
            return false;
        }

        return mt_rand() / mt_getrandmax() < $this->samplingRate;
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
