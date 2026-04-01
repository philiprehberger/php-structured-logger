<?php

declare(strict_types=1);

namespace PhilipRehberger\StructuredLogger\Tests;

use PhilipRehberger\StructuredLogger\EcsLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class EcsLoggerTest extends TestCase
{
    private function createTempFile(): string
    {
        return tempnam(sys_get_temp_dir(), 'ecslogger_test_');
    }

    /**
     * @test
     */
    public function it_outputs_valid_json(): void
    {
        $file = $this->createTempFile();
        $logger = new EcsLogger(output: $file);

        $logger->info('Test message');
        unset($logger);

        $output = file_get_contents($file);
        $decoded = json_decode(trim($output), true);

        $this->assertIsArray($decoded);
        $this->assertSame('Test message', $decoded['message']);

        @unlink($file);
    }

    /**
     * @test
     */
    public function it_includes_required_ecs_fields(): void
    {
        $file = $this->createTempFile();
        $logger = new EcsLogger(output: $file);

        $logger->warning('Something happened');
        unset($logger);

        $output = file_get_contents($file);
        $decoded = json_decode(trim($output), true);

        $this->assertArrayHasKey('@timestamp', $decoded);
        $this->assertArrayHasKey('log.level', $decoded);
        $this->assertArrayHasKey('message', $decoded);
        $this->assertArrayHasKey('ecs.version', $decoded);

        $this->assertSame('8.11', $decoded['ecs.version']);
        $this->assertSame('warning', $decoded['log.level']);
        $this->assertSame('Something happened', $decoded['message']);

        @unlink($file);
    }

    /**
     * @test
     */
    public function it_validates_timestamp_format(): void
    {
        $file = $this->createTempFile();
        $logger = new EcsLogger(output: $file);

        $logger->info('Timestamp test');
        unset($logger);

        $output = file_get_contents($file);
        $decoded = json_decode(trim($output), true);

        $timestamp = $decoded['@timestamp'];
        $parsed = new \DateTimeImmutable($timestamp);

        $this->assertInstanceOf(\DateTimeImmutable::class, $parsed, 'Timestamp should be valid ISO 8601');

        @unlink($file);
    }

    /**
     * @test
     */
    public function it_includes_context_fields(): void
    {
        $file = $this->createTempFile();
        $logger = new EcsLogger(output: $file);

        $logger->info('With context', ['user_id' => 42, 'action' => 'login']);
        unset($logger);

        $output = file_get_contents($file);
        $decoded = json_decode(trim($output), true);

        $this->assertSame(42, $decoded['user_id']);
        $this->assertSame('login', $decoded['action']);

        @unlink($file);
    }

    /**
     * @test
     */
    public function it_maps_psr3_levels_to_ecs(): void
    {
        $levels = [
            LogLevel::DEBUG => 'debug',
            LogLevel::INFO => 'info',
            LogLevel::NOTICE => 'notice',
            LogLevel::WARNING => 'warning',
            LogLevel::ERROR => 'error',
            LogLevel::CRITICAL => 'critical',
            LogLevel::ALERT => 'alert',
            LogLevel::EMERGENCY => 'emergency',
        ];

        foreach ($levels as $psr3Level => $ecsLevel) {
            $file = $this->createTempFile();
            $logger = new EcsLogger(output: $file);

            $logger->log($psr3Level, 'Level test');
            unset($logger);

            $output = file_get_contents($file);
            $decoded = json_decode(trim($output), true);

            $this->assertSame($ecsLevel, $decoded['log.level'], "PSR-3 level '$psr3Level' should map to ECS level '$ecsLevel'");

            @unlink($file);
        }
    }

    /**
     * @test
     */
    public function it_respects_min_level(): void
    {
        $file = $this->createTempFile();
        $logger = new EcsLogger(output: $file);
        $logger->setMinLevel(LogLevel::WARNING);

        $logger->info('Should be skipped');
        $logger->warning('Should be logged');
        unset($logger);

        $output = file_get_contents($file);
        $lines = array_filter(explode("\n", trim($output)));

        $this->assertCount(1, $lines);
        $decoded = json_decode($lines[0], true);
        $this->assertSame('Should be logged', $decoded['message']);

        @unlink($file);
    }

    /**
     * @test
     */
    public function it_auto_serializes_exception_in_context(): void
    {
        $file = $this->createTempFile();
        $logger = new EcsLogger(output: $file);

        $exception = new \RuntimeException('Something broke', 42);
        $logger->error('Error occurred', ['exception' => $exception]);
        unset($logger);

        $output = file_get_contents($file);
        $decoded = json_decode(trim($output), true);

        $this->assertSame('RuntimeException', $decoded['error.type']);
        $this->assertSame('Something broke', $decoded['error.message']);
        $this->assertSame(42, $decoded['error.code']);
        $this->assertIsArray($decoded['error.stack_trace']);
        $this->assertArrayNotHasKey('exception', $decoded);

        @unlink($file);
    }
}
