<?php

declare(strict_types=1);

namespace PhilipRehberger\StructuredLogger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Throwable;

final class EcsLogger extends AbstractLogger
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

    private const ECS_LEVEL_MAP = [
        LogLevel::DEBUG => 'debug',
        LogLevel::INFO => 'info',
        LogLevel::NOTICE => 'notice',
        LogLevel::WARNING => 'warning',
        LogLevel::ERROR => 'error',
        LogLevel::CRITICAL => 'critical',
        LogLevel::ALERT => 'alert',
        LogLevel::EMERGENCY => 'emergency',
    ];

    private const ECS_VERSION = '8.11';

    /** @var resource|null */
    private $handle = null;

    private string $minLevel = LogLevel::DEBUG;

    /**
     * @param  string  $output  File path or 'php://stdout' or 'php://stderr'
     * @param  string  $channel  Application/service name
     */
    public function __construct(
        private readonly string $output = 'php://stdout',
        private readonly string $channel = 'app',
    ) {}

    /**
     * Set the minimum log level. Entries below this threshold are skipped.
     */
    public function setMinLevel(string $level): void
    {
        $this->minLevel = $level;
    }

    /**
     * Log a message in ECS (Elastic Common Schema) format.
     *
     * @param  mixed  $level
     * @param  array<string, mixed>  $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $levelString = (string) $level;

        if ($this->isBelow($levelString)) {
            return;
        }

        $entry = [
            '@timestamp' => (new \DateTimeImmutable)->format('c'),
            'log.level' => self::ECS_LEVEL_MAP[$levelString] ?? $levelString,
            'message' => (string) $message,
            'ecs.version' => self::ECS_VERSION,
        ];

        if ($this->channel !== '') {
            $entry['service.name'] = $this->channel;
        }

        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $serialized = ExceptionSerializer::serialize($context['exception']);
            $context = array_merge($context, $serialized);
            unset($context['exception']);
        }

        if (! empty($context)) {
            foreach ($context as $key => $value) {
                $entry[$key] = $value;
            }
        }

        $this->writeLine(json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
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
