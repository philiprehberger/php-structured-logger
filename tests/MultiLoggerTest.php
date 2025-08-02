<?php

declare(strict_types=1);

namespace PhilipRehberger\StructuredLogger\Tests;

use PhilipRehberger\StructuredLogger\JsonLogger;
use PhilipRehberger\StructuredLogger\MultiLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class MultiLoggerTest extends TestCase
{
    private function createTempFile(): string
    {
        return tempnam(sys_get_temp_dir(), 'multilogger_test_');
    }

    /**
     * @test
     */
    public function it_sends_log_to_all_loggers(): void
    {
        $file1 = $this->createTempFile();
        $file2 = $this->createTempFile();

        $logger1 = new JsonLogger(output: $file1);
        $logger2 = new JsonLogger(output: $file2);

        $multi = new MultiLogger([$logger1, $logger2]);
        $multi->info('Hello from multi');

        unset($multi, $logger1, $logger2);

        $output1 = file_get_contents($file1);
        $output2 = file_get_contents($file2);

        $this->assertNotEmpty($output1);
        $this->assertNotEmpty($output2);

        $decoded1 = json_decode(trim($output1), true);
        $decoded2 = json_decode(trim($output2), true);

        $this->assertSame('Hello from multi', $decoded1['message']);
        $this->assertSame('Hello from multi', $decoded2['message']);

        @unlink($file1);
        @unlink($file2);
    }

    /**
     * @test
     */
    public function each_logger_respects_its_own_min_level(): void
    {
        $file1 = $this->createTempFile();
        $file2 = $this->createTempFile();

        $logger1 = new JsonLogger(output: $file1);
        $logger1->setMinLevel(LogLevel::DEBUG);

        $logger2 = new JsonLogger(output: $file2);
        $logger2->setMinLevel(LogLevel::ERROR);

        $multi = new MultiLogger([$logger1, $logger2]);
        $multi->info('Info message');

        unset($multi, $logger1, $logger2);

        $output1 = file_get_contents($file1);
        $output2 = file_get_contents($file2);

        $this->assertNotEmpty($output1, 'Logger1 (debug min) should have logged the info message');
        $this->assertEmpty(trim($output2), 'Logger2 (error min) should have skipped the info message');

        @unlink($file1);
        @unlink($file2);
    }

    /**
     * @test
     */
    public function it_handles_empty_loggers_array(): void
    {
        $multi = new MultiLogger([]);
        $multi->info('No loggers to write to');

        $this->assertTrue(true, 'No exception should be thrown');
    }

    /**
     * @test
     */
    public function it_works_with_single_logger(): void
    {
        $file = $this->createTempFile();
        $logger = new JsonLogger(output: $file);

        $multi = new MultiLogger([$logger]);
        $multi->error('Single target');

        unset($multi, $logger);

        $output = file_get_contents($file);
        $decoded = json_decode(trim($output), true);

        $this->assertSame('Single target', $decoded['message']);
        $this->assertSame('error', $decoded['level']);

        @unlink($file);
    }
}
