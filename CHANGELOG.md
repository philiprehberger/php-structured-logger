# Changelog

All notable changes to this project will be documented in this file.

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
