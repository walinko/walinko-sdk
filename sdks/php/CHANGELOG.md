# Changelog — walinko/sdk (PHP)

All notable changes to this package are documented here. The format is based
on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] — 2026-05-01

First publishable release. Targets Walinko public API
`POST /api/v1/public/messages` and `GET /api/v1/public/messages/:trackingId`.

### Added
- `Walinko\Client::__construct(array $options)` accepting `api_key`,
  `base_url`, `timeout`, `max_retries`, plus optional PSR-18 / PSR-17 /
  PSR-3 dependencies. Auto-discovery via `php-http/discovery` when
  PSR factories aren't injected.
- `$client->messages->send(...)` — synchronous send returning
  `Walinko\Result\SyncResult`.
- `$client->messages->enqueue(...)` — async enqueue returning
  `Walinko\Result\AsyncJob`.
- `$client->messages->fetch($trackingId)` returning
  `Walinko\Result\MessageStatus`.
- `$client->messages->waitUntilDone($trackingId, timeout, interval)` —
  polls until terminal, raises `Walinko\Exception\TimeoutException` if
  the deadline is exceeded.
- Typed exception hierarchy under `Walinko\Exception\` mirroring the
  documented `error_code` contract: `AuthenticationException`,
  `BadRequestException`, `ForbiddenException` (with
  `TenantSuspendedException` / `QuotaExceededException` subclasses),
  `NotFoundException`, `ConflictException` (with
  `DeviceDisconnectedException` / `IdempotencyConflictException`
  subclasses), `ValidationException`, `RateLimitException`,
  `ServerException`, `TimeoutException`, plus `ConnectionException`
  for transport-level failures.
- Auto-generated `Idempotency-Key` (`walinko-php-<uuid>`) for every
  `send` / `enqueue` call. Override via `idempotency_key` option.
- Idempotent retry policy: network errors, 429 (honouring
  `Retry-After`, capped at 60s), and 5xx are retried up to
  `max_retries` with exponential backoff + jitter. The same
  `Idempotency-Key` is reused on every retry.
- `$client->lastRateLimit()` and `$client->lastRequestId()` reflect
  the most recent response's `X-RateLimit-*` and `X-Request-Id`
  headers.
- `Idempotent-Replayed: true` is surfaced on
  `SyncResult::$idempotentReplayed` / `AsyncJob::$idempotentReplayed`.

### Internal
- Pure PSR-18 transport — no hard dependency on Guzzle, Symfony, or
  any specific HTTP client.
- 68 PHPUnit tests / 157 assertions covering every documented error
  code, the retry matrix, idempotency, validation field parsing, and
  `waitUntilDone` happy/timeout paths.
- PHPStan level 6 clean; PHP-CS-Fixer (PSR-12 + risky) clean.
