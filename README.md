# PHP Structured Logger

[![Tests](https://github.com/philiprehberger/php-structured-logger/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/php-structured-logger/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/php-structured-logger.svg)](https://packagist.org/packages/philiprehberger/php-structured-logger)
[![Last updated](https://img.shields.io/github/last-commit/philiprehberger/php-structured-logger)](https://github.com/philiprehberger/php-structured-logger/commits/main)

PSR-3 compatible logger that outputs structured JSON log lines.

## Requirements

- PHP 8.2+
- psr/log ^3.0

## Installation

```bash
composer require philiprehberger/php-structured-logger
```

## Usage

### Basic logging

```php
use PhilipRehberger\StructuredLogger\JsonLogger;

// Log to stdout (default)
$logger = new JsonLogger();
$logger->info('Application started');

// Log to a file
$logger = new JsonLogger(output: '/var/log/app.log');
$logger->info('User logged in', ['user_id' => 42]);
```

### Logging with context

```php
$logger = new JsonLogger(output: 'php://stderr');

$logger->error('Payment failed', [
    'order_id' => 'ORD-123',
    'amount' => 49.99,
    'currency' => 'USD',
]);
```

Output:

```json
{"timestamp":"2026-03-13T10:00:00+00:00","level":"error","message":"Payment failed","channel":"app","context":{"order_id":"ORD-123","amount":49.99,"currency":"USD"}}
```

### Sensitive data redaction

By default, the following context keys are automatically redacted: `password`, `token`, `secret`, `api_key`, `authorization`, `credit_card`. Redaction applies recursively to nested arrays.

```php
$logger = new JsonLogger();

$logger->info('Auth attempt', [
    'username' => 'alice',
    'password' => 's3cret',
    'token' => 'abc123',
]);
// password and token values replaced with "[REDACTED]"
```

You can provide a custom list of keys to redact:

```php
$logger = new JsonLogger(
    redactKeys: ['password', 'ssn', 'credit_card'],
);
```

### Persistent context

Use `withContext()` to create a new logger instance with fields that are automatically included in every log entry:

```php
$logger = new JsonLogger(output: '/var/log/app.log');

$requestLogger = $logger->withContext([
    'request_id' => 'req-abc-123',
    'service' => 'api',
]);

$requestLogger->info('Request received');
$requestLogger->info('Processing complete', ['duration_ms' => 42]);
// Both entries include request_id and service in context
```

### Minimum log level

Use `setMinLevel()` to filter out log entries below a severity threshold:

```php
use Psr\Log\LogLevel;

$logger = new JsonLogger();
$logger->setMinLevel(LogLevel::WARNING);

$logger->debug('This is skipped');
$logger->info('This is also skipped');
$logger->warning('This is logged');
$logger->error('This is logged');
```

### Custom channel

Use the channel parameter to identify different services or application components:

```php
$logger = new JsonLogger(
    output: '/var/log/payments.log',
    channel: 'payments',
);

$logger->info('Payment processed');
// {"timestamp":"...","level":"info","message":"Payment processed","channel":"payments"}
```

### Log sampling

Use `withSampling()` to probabilistically sample log output. Messages at `error` level and above are always logged regardless of the sampling rate:

```php
$logger = new JsonLogger();
$logger->withSampling(0.1); // Only 10% of debug/info/warning/notice messages are written

$logger->info('Might be skipped');
$logger->error('Always logged');
```

### Buffered logging

Use `BufferedLogger` to batch log entries and flush them together:

```php
use PhilipRehberger\StructuredLogger\BufferedLogger;
use PhilipRehberger\StructuredLogger\JsonLogger;

$logger = new BufferedLogger(new JsonLogger(), bufferSize: 50);

$logger->info('Buffered entry 1');
$logger->info('Buffered entry 2');
$logger->flush(); // Writes all buffered entries
```

### Correlation ID

Use `withCorrelationId()` to automatically include a correlation ID in every log entry:

```php
$logger = new JsonLogger();
$logger->withCorrelationId('req-abc-123');

$logger->info('Processing request');
// context includes "correlation_id": "req-abc-123"
```

### Multiple output targets

Use `MultiLogger` to fan out log calls to multiple logger instances:

```php
use PhilipRehberger\StructuredLogger\MultiLogger;
use PhilipRehberger\StructuredLogger\JsonLogger;
use PhilipRehberger\StructuredLogger\EcsLogger;

$jsonLogger = new JsonLogger(output: '/var/log/app.log');
$ecsLogger = new EcsLogger(output: '/var/log/app-ecs.log');

$multi = new MultiLogger([$jsonLogger, $ecsLogger]);
$multi->info('Logged to both targets', ['user_id' => 42]);
```

Each logger in the array respects its own minimum level and configuration independently.

### ECS (Elastic Common Schema) format

Use `EcsLogger` to output logs in [Elastic Common Schema](https://www.elastic.co/guide/en/ecs/current/index.html) format:

```php
use PhilipRehberger\StructuredLogger\EcsLogger;

$logger = new EcsLogger(output: '/var/log/app-ecs.log', channel: 'api');
$logger->info('Request processed', ['http.method' => 'GET', 'url.path' => '/users']);
```

Output:

```json
{"@timestamp":"2026-04-01T10:00:00+00:00","log.level":"info","message":"Request processed","ecs.version":"8.11","service.name":"api","http.method":"GET","url.path":"/users"}
```

### Structured exception formatting

When an `exception` key containing a `Throwable` is passed in context, both `JsonLogger` and `EcsLogger` automatically serialize it into structured fields:

```php
try {
    riskyOperation();
} catch (\Throwable $e) {
    $logger->error('Operation failed', ['exception' => $e]);
}
```

The exception is serialized into `error.type`, `error.message`, `error.code`, and `error.stack_trace` fields. You can also use `ExceptionSerializer` directly:

```php
use PhilipRehberger\StructuredLogger\ExceptionSerializer;

$data = ExceptionSerializer::serialize($exception, traceDepth: 5);
// ['error.type' => 'RuntimeException', 'error.message' => '...', 'error.code' => 0, 'error.stack_trace' => [...]]
```

### Output Format

Each log line is a single JSON object with the following fields:

| Field       | Type   | Description                              |
|-------------|--------|------------------------------------------|
| `timestamp` | string | ISO 8601 timestamp                       |
| `level`     | string | PSR-3 log level                          |
| `message`   | string | Log message                              |
| `channel`   | string | Application/service name (default: `app`)|
| `context`   | object | Additional data (omitted when empty)     |

## API

### `JsonLogger`

| Parameter    | Type       | Default                                                                       | Description                        |
|--------------|------------|-------------------------------------------------------------------------------|------------------------------------|
| `output`     | `string`   | `'php://stdout'`                                                              | File path or PHP stream wrapper    |
| `channel`    | `string`   | `'app'`                                                                       | Application/service identifier     |
| `redactKeys` | `string[]` | `['password', 'token', 'secret', 'api_key', 'authorization', 'credit_card']`  | Context keys to redact             |

| Method | Returns | Description |
|---|---|---|
| `withContext(array $context)` | `self` | New logger instance with persistent context fields |
| `setMinLevel(string $level)` | `void` | Set minimum log level threshold |
| `withSampling(float $rate)` | `self` | Enable probabilistic log sampling (0.0-1.0) |
| `withCorrelationId(string $id)` | `self` | Set correlation ID for all log entries |

All PSR-3 log methods are available: `emergency()`, `alert()`, `critical()`, `error()`, `warning()`, `notice()`, `info()`, `debug()`, `log()`.

### `BufferedLogger`

| Parameter    | Type         | Default | Description                          |
|--------------|--------------|---------|--------------------------------------|
| `logger`     | `JsonLogger` |         | Wrapped logger to flush entries to   |
| `bufferSize` | `int`        | `100`   | Flush when buffer reaches this size  |

| Method | Returns | Description |
|---|---|---|
| `flush()` | `void` | Write all buffered entries to the wrapped logger |
| `count()` | `int` | Number of entries currently buffered |

### `MultiLogger`

| Parameter | Type               | Default | Description                          |
|-----------|--------------------|---------|--------------------------------------|
| `loggers` | `LoggerInterface[]` | `[]`    | Logger instances to fan out calls to |

All PSR-3 log methods are available. Each call is forwarded to every logger in the array.

### `EcsLogger`

| Parameter | Type     | Default          | Description                     |
|-----------|----------|------------------|---------------------------------|
| `output`  | `string` | `'php://stdout'` | File path or PHP stream wrapper |
| `channel` | `string` | `'app'`          | Service name (`service.name`)   |

| Method | Returns | Description |
|---|---|---|
| `setMinLevel(string $level)` | `void` | Set minimum log level threshold |

All PSR-3 log methods are available. Output follows the Elastic Common Schema (ECS 8.11) format.

### `ExceptionSerializer`

| Method | Returns | Description |
|---|---|---|
| `serialize(Throwable $exception, int $traceDepth = 10)` | `array` | Serialize exception into structured fields |

Returns an array with keys: `error.type`, `error.message`, `error.code`, `error.stack_trace`.

### `LogEntry`

Immutable value object representing a single log entry. Implements `JsonSerializable`.

| Method          | Returns          | Description                    |
|-----------------|------------------|--------------------------------|
| `toArray()`     | `array`          | Entry as associative array     |
| `toJson()`      | `string`         | Entry as JSON string           |
| `jsonSerialize()`| `array`         | For `json_encode()` support    |

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
```

## Support

If you find this project useful:

⭐ [Star the repo](https://github.com/philiprehberger/php-structured-logger)

🐛 [Report issues](https://github.com/philiprehberger/php-structured-logger/issues?q=is%3Aissue+is%3Aopen+label%3Abug)

💡 [Suggest features](https://github.com/philiprehberger/php-structured-logger/issues?q=is%3Aissue+is%3Aopen+label%3Aenhancement)

❤️ [Sponsor development](https://github.com/sponsors/philiprehberger)

🌐 [All Open Source Projects](https://philiprehberger.com/open-source-packages)

💻 [GitHub Profile](https://github.com/philiprehberger)

🔗 [LinkedIn Profile](https://www.linkedin.com/in/philiprehberger)

## License

[MIT](LICENSE)
