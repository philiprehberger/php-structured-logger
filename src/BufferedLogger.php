<?php

declare(strict_types=1);

namespace PhilipRehberger\StructuredLogger;

use Psr\Log\AbstractLogger;

final class BufferedLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string|\Stringable, context: array<string, mixed>}> */
    private array $buffer = [];

    public function __construct(
        private readonly JsonLogger $logger,
        private readonly int $bufferSize = 100,
    ) {}

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->buffer[] = [
            'level' => (string) $level,
            'message' => $message,
            'context' => $context,
        ];

        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        foreach ($this->buffer as $entry) {
            $this->logger->log($entry['level'], $entry['message'], $entry['context']);
        }

        $this->buffer = [];
    }

    public function count(): int
    {
        return count($this->buffer);
    }

    public function __destruct()
    {
        $this->flush();
    }
}
