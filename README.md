# PHP Structured Logger

[![Tests](https://github.com/philiprehberger/php-structured-logger/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/php-structured-logger/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/php-structured-logger.svg)](https://packagist.org/packages/philiprehberger/php-structured-logger)
[![License](https://img.shields.io/github/license/philiprehberger/php-structured-logger)](LICENSE)

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

All PSR-3 log methods are available: `emergency()`, `alert()`, `critical()`, `error()`, `warning()`, `notice()`, `info()`, `debug()`, `log()`.

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
vendor/bin/phpstan analyse
```

## License

MIT
