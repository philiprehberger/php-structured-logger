<?php

declare(strict_types=1);

namespace PhilipRehberger\StructuredLogger;

use DateTimeImmutable;
use JsonSerializable;

final class LogEntry implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly DateTimeImmutable $timestamp,
        public readonly string $level,
        public readonly string $message,
        public readonly array $context = [],
        public readonly string $channel = 'app',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $entry = [
            'timestamp' => $this->timestamp->format('c'),
            'level' => $this->level,
            'message' => $this->message,
            'channel' => $this->channel,
        ];

        if (! empty($this->context)) {
            $entry['context'] = $this->context;
        }

        return $entry;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
