# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-03-13

### Added

- `JsonLogger` — PSR-3 compatible logger outputting structured JSON log lines
- `LogEntry` — Immutable value object representing a single log entry
- Automatic redaction of sensitive context keys (password, token, secret, api_key, authorization, credit_card)
- Configurable output target (file path, php://stdout, php://stderr)
- Configurable channel name for multi-service environments
- Nested sensitive key redaction support
