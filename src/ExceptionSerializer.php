<?php

declare(strict_types=1);

namespace PhilipRehberger\StructuredLogger;

use Throwable;

final class ExceptionSerializer
{
    /**
     * Serialize a Throwable into a structured array suitable for logging.
     *
     * @param  int  $traceDepth  Maximum number of stack trace frames to include
     * @return array<string, mixed>
     */
    public static function serialize(Throwable $exception, int $traceDepth = 10): array
    {
        $trace = $exception->getTrace();
        $frames = [];

        $limit = min($traceDepth, count($trace));
        for ($i = 0; $i < $limit; $i++) {
            $frame = $trace[$i];
            $frames[] = [
                'file' => $frame['file'] ?? '<internal>',
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'],
                'class' => $frame['class'] ?? null,
            ];
        }

        return [
            'error.type' => get_class($exception),
            'error.message' => $exception->getMessage(),
            'error.code' => (int) $exception->getCode(),
            'error.stack_trace' => $frames,
        ];
    }
}
