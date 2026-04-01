# Changelog

All notable changes to `php-structured-logger` will be documented in this file.

## [Unreleased]

## [1.3.0] - 2026-04-01

### Added
- Multiple output targets via `MultiLogger`
- ECS (Elastic Common Schema) format via `EcsLogger`
- Structured exception formatting via `ExceptionSerializer`

## [1.2.1] - 2026-03-31

### Changed
- Standardize README to 3-badge format with emoji Support section
- Update CI checkout action to v5 for Node.js 24 compatibility

## [1.2.0] - 2026-03-27

### Added
- `withSampling()` for probabilistic log sampling with guaranteed error-level logging
- `BufferedLogger` decorator for batched log output
- `withCorrelationId()` for automatic correlation ID injection into all log entries

## [1.1.0] - 2026-03-22

### Added
- `withContext()` method for attaching persistent context fields to all log entries
- `setMinLevel()` method for filtering log entries below a severity threshold

## [1.0.2] - 2026-03-17

### Changed
- Standardized package metadata, README structure, and CI workflow per package guide

## [1.0.1] - 2026-03-16

### Changed
- Standardize composer.json: add type, homepage, scripts

## [1.0.0] - 2026-03-13

### Added

- `JsonLogger` — PSR-3 compatible logger outputting structured JSON log lines
- `LogEntry` — Immutable value object representing a single log entry
- Automatic redaction of sensitive context keys (password, token, secret, api_key, authorization, credit_card)
- Configurable output target (file path, php://stdout, php://stderr)
- Configurable channel name for multi-service environments
- Nested sensitive key redaction support
