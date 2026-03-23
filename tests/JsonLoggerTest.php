<?php

declare(strict_types=1);

namespace PhilipRehberger\StructuredLogger\Tests;

use PhilipRehberger\StructuredLogger\JsonLogger;
use PhilipRehberger\StructuredLogger\LogEntry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class JsonLoggerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = tempnam(sys_get_temp_dir(), 'structured_logger_test_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    private function createLogger(string $channel = 'app', array $redactKeys = []): JsonLogger
    {
        if (empty($redactKeys)) {
            return new JsonLogger(output: $this->logFile, channel: $channel);
        }

        return new JsonLogger(output: $this->logFile, channel: $channel, redactKeys: $redactKeys);
    }

    /**
     * @return array<string, mixed>
     */
    private function readLastLogEntry(): array
    {
        $lines = array_filter(explode("\n", file_get_contents($this->logFile)));
        $lastLine = end($lines);

        return json_decode($lastLine, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readAllLogEntries(): array
    {
        $lines = array_filter(explode("\n", file_get_contents($this->logFile)));

        return array_map(
            fn (string $line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            array_values($lines),
        );
    }

    public function test_logs_info_message(): void
    {
        $logger = $this->createLogger();
        $logger->info('User logged in');
        unset($logger);

        $entry = $this->readLastLogEntry();

        $this->assertSame('info', $entry['level']);
        $this->assertSame('User logged in', $entry['message']);
        $this->assertArrayHasKey('timestamp', $entry);
    }

    public function test_logs_error_message(): void
    {
        $logger = $this->createLogger();
        $logger->error('Something went wrong');
        unset($logger);

        $entry = $this->readLastLogEntry();

        $this->assertSame('error', $entry['level']);
        $this->assertSame('Something went wrong', $entry['message']);
    }

    public function test_logs_debug_with_context(): void
    {
        $logger = $this->createLogger();
        $logger->debug('Processing request', ['request_id' => 'abc-123']);
        unset($logger);

        $entry = $this->readLastLogEntry();

        $this->assertSame('debug', $entry['level']);
        $this->assertSame('Processing request', $entry['message']);
        $this->assertSame(['request_id' => 'abc-123'], $entry['context']);
    }

    public function test_all_psr3_log_levels_accepted(): void
    {
        $levels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];

        $logger = $this->createLogger();
        foreach ($levels as $level) {
            $logger->log($level, "Message at {$level}");
        }
        unset($logger);

        $entries = $this->readAllLogEntries();

        $this->assertCount(8, $entries);

        foreach ($levels as $i => $level) {
            $this->assertSame($level, $entries[$i]['level']);
        }
    }

    public function test_context_included_in_output(): void
    {
        $logger = $this->createLogger();
        $logger->info('Order placed', ['order_id' => 42, 'total' => 99.95]);
        unset($logger);

        $entry = $this->readLastLogEntry();

        $this->assertSame(42, $entry['context']['order_id']);
        $this->assertSame(99.95, $entry['context']['total']);
    }

    public function test_sensitive_keys_redacted(): void
    {
        $logger = $this->createLogger();
        $logger->info('Auth attempt', [
            'username' => 'alice',
            'password' => 's3cret',
            'token' => 'abc123',
            'secret' => 'xyz',
            'api_key' => 'key-456',
        ]);
        unset($logger);

        $entry = $this->readLastLogEntry();

        $this->assertSame('alice', $entry['context']['username']);
        $this->assertSame('[REDACTED]', $entry['context']['password']);
        $this->assertSame('[REDACTED]', $entry['context']['token']);
        $this->assertSame('[REDACTED]', $entry['context']['secret']);
        $this->assertSame('[REDACTED]', $entry['context']['api_key']);
    }

    public function test_nested_sensitive_keys_redacted(): void
    {
        $logger = $this->createLogger();
        $logger->info('Nested data', [
            'user' => [
                'name' => 'alice',
                'password' => 'hidden',
                'credentials' => [
                    'token' => 'should-be-redacted',
                ],
            ],
        ]);
        unset($logger);

        $entry = $this->readLastLogEntry();

        $this->assertSame('alice', $entry['context']['user']['name']);
        $this->assertSame('[REDACTED]', $entry['context']['user']['password']);
        $this->assertSame('[REDACTED]', $entry['context']['user']['credentials']['token']);
    }

    public function test_custom_channel_name(): void
    {
        $logger = $this->createLogger(channel: 'payments');
        $logger->info('Payment processed');
        unset($logger);

        $entry = $this->readLastLogEntry();

        $this->assertSame('payments', $entry['channel']);
    }

    public function test_log_entry_json_format(): void
    {
        $timestamp = new \DateTimeImmutable('2026-03-13T10:00:00+00:00');
        $entry = new LogEntry(
            timestamp: $timestamp,
            level: 'info',
            message: 'Test message',
            context: ['key' => 'value'],
            channel: 'test',
        );

        $json = $entry->toJson();
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('2026-03-13T10:00:00+00:00', $decoded['timestamp']);
        $this->assertSame('info', $decoded['level']);
        $this->assertSame('Test message', $decoded['message']);
        $this->assertSame('test', $decoded['channel']);
        $this->assertSame(['key' => 'value'], $decoded['context']);
    }

    public function test_log_entry_json_serializable(): void
    {
        $timestamp = new \DateTimeImmutable('2026-03-13T12:00:00+00:00');
        $entry = new LogEntry(
            timestamp: $timestamp,
            level: 'warning',
            message: 'Serializable test',
            channel: 'app',
        );

        $json = json_encode($entry, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('warning', $decoded['level']);
        $this->assertSame('Serializable test', $decoded['message']);
        $this->assertArrayNotHasKey('context', $decoded);
    }

    public function test_with_context_includes_persistent_fields(): void
    {
        $logger = $this->createLogger();
        $contextLogger = $logger->withContext(['request_id' => 'req-001', 'service' => 'api']);

        $contextLogger->info('First message');
        $contextLogger->warning('Second message', ['extra' => 'data']);
        unset($contextLogger);

        $entries = $this->readAllLogEntries();

        $this->assertCount(2, $entries);
        $this->assertSame('req-001', $entries[0]['context']['request_id']);
        $this->assertSame('api', $entries[0]['context']['service']);
        $this->assertSame('req-001', $entries[1]['context']['request_id']);
        $this->assertSame('api', $entries[1]['context']['service']);
        $this->assertSame('data', $entries[1]['context']['extra']);
    }

    public function test_with_context_returns_new_instance(): void
    {
        $logger = $this->createLogger();
        $contextLogger = $logger->withContext(['request_id' => 'req-001']);

        $this->assertNotSame($logger, $contextLogger);
    }

    public function test_set_min_level_filters_below_threshold(): void
    {
        $logger = $this->createLogger();
        $logger->setMinLevel(LogLevel::WARNING);

        $logger->debug('Should be skipped');
        $logger->info('Should be skipped');
        $logger->notice('Should be skipped');
        $logger->warning('Should appear');
        $logger->error('Should appear');
        unset($logger);

        $entries = $this->readAllLogEntries();

        $this->assertCount(2, $entries);
        $this->assertSame('warning', $entries[0]['level']);
        $this->assertSame('error', $entries[1]['level']);
    }

    public function test_set_min_level_allows_all_at_debug(): void
    {
        $logger = $this->createLogger();
        $logger->setMinLevel(LogLevel::DEBUG);

        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->emergency('Emergency message');
        unset($logger);

        $entries = $this->readAllLogEntries();

        $this->assertCount(3, $entries);
    }

    public function test_set_min_level_at_emergency_filters_all_but_emergency(): void
    {
        $logger = $this->createLogger();
        $logger->setMinLevel(LogLevel::EMERGENCY);

        $logger->alert('Should be skipped');
        $logger->critical('Should be skipped');
        $logger->error('Should be skipped');
        $logger->emergency('Should appear');
        unset($logger);

        $entries = $this->readAllLogEntries();

        $this->assertCount(1, $entries);
        $this->assertSame('emergency', $entries[0]['level']);
    }
}
