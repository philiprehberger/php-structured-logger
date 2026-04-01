<?php

declare(strict_types=1);

namespace PhilipRehberger\StructuredLogger\Tests;

use PhilipRehberger\StructuredLogger\ExceptionSerializer;
use PhilipRehberger\StructuredLogger\JsonLogger;
use PHPUnit\Framework\TestCase;

final class ExceptionSerializerTest extends TestCase
{
    /**
     * @test
     */
    public function it_extracts_class_name(): void
    {
        $exception = new \RuntimeException('Test error');
        $result = ExceptionSerializer::serialize($exception);

        $this->assertSame('RuntimeException', $result['error.type']);
    }

    /**
     * @test
     */
    public function it_extracts_message(): void
    {
        $exception = new \InvalidArgumentException('Bad argument');
        $result = ExceptionSerializer::serialize($exception);

        $this->assertSame('Bad argument', $result['error.message']);
    }

    /**
     * @test
     */
    public function it_extracts_code(): void
    {
        $exception = new \RuntimeException('Error', 500);
        $result = ExceptionSerializer::serialize($exception);

        $this->assertSame(500, $result['error.code']);
    }

    /**
     * @test
     */
    public function it_returns_stack_trace_frames(): void
    {
        $exception = new \RuntimeException('Trace test');
        $result = ExceptionSerializer::serialize($exception);

        $this->assertIsArray($result['error.stack_trace']);

        if (count($result['error.stack_trace']) > 0) {
            $frame = $result['error.stack_trace'][0];
            $this->assertArrayHasKey('file', $frame);
            $this->assertArrayHasKey('line', $frame);
            $this->assertArrayHasKey('function', $frame);
            $this->assertArrayHasKey('class', $frame);
        }
    }

    /**
     * @test
     */
    public function it_truncates_trace_to_depth(): void
    {
        $exception = $this->createDeepException(20);
        $result = ExceptionSerializer::serialize($exception, traceDepth: 5);

        $this->assertLessThanOrEqual(5, count($result['error.stack_trace']));
    }

    /**
     * @test
     */
    public function it_handles_zero_trace_depth(): void
    {
        $exception = new \RuntimeException('No trace');
        $result = ExceptionSerializer::serialize($exception, traceDepth: 0);

        $this->assertCount(0, $result['error.stack_trace']);
    }

    /**
     * @test
     */
    public function it_handles_default_trace_depth(): void
    {
        $exception = $this->createDeepException(20);
        $result = ExceptionSerializer::serialize($exception);

        $this->assertLessThanOrEqual(10, count($result['error.stack_trace']));
    }

    /**
     * @test
     */
    public function json_logger_auto_serializes_exception_in_context(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'exserializer_test_');
        $logger = new JsonLogger(output: $file);

        $exception = new \LogicException('Logic error', 123);
        $logger->error('Something failed', ['exception' => $exception, 'extra' => 'data']);
        unset($logger);

        $output = file_get_contents($file);
        $decoded = json_decode(trim($output), true);

        $this->assertSame('Something failed', $decoded['message']);
        $context = $decoded['context'];

        $this->assertSame('LogicException', $context['error.type']);
        $this->assertSame('Logic error', $context['error.message']);
        $this->assertSame(123, $context['error.code']);
        $this->assertIsArray($context['error.stack_trace']);
        $this->assertSame('data', $context['extra']);
        $this->assertArrayNotHasKey('exception', $context);

        @unlink($file);
    }

    private function createDeepException(int $depth): \Throwable
    {
        if ($depth <= 0) {
            return new \RuntimeException('Deep exception');
        }

        return $this->recurse($depth);
    }

    private function recurse(int $depth): \Throwable
    {
        if ($depth <= 1) {
            return new \RuntimeException('Deep exception');
        }

        return $this->recurse($depth - 1);
    }
}
