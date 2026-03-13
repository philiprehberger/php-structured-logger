<?php

declare(strict_types=1);

namespace PhilipRehberger\StructuredLogger;

use Psr\Log\AbstractLogger;

final class JsonLogger extends AbstractLogger
{
    /** @var resource|null */
    private $handle = null;

    /**
     * @param  string  $output  File path or 'php://stdout' or 'php://stderr'
     * @param  string  $channel  Application/service name
     * @param  array<string>  $redactKeys  Keys to redact from context
     */
    public function __construct(
        private readonly string $output = 'php://stdout',
        private readonly string $channel = 'app',
        private readonly array $redactKeys = ['password', 'token', 'secret', 'api_key', 'authorization', 'credit_card'],
    ) {}

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $entry = new LogEntry(
            timestamp: new \DateTimeImmutable,
            level: (string) $level,
            message: (string) $message,
            context: $this->redact($context),
            channel: $this->channel,
        );

        $this->writeLine($entry->toJson());
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
