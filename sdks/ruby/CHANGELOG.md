# Changelog — walinko (Ruby)

All notable changes to this gem are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.1] — 2026-05-04

### Fixed
- `wait_until_done` could time out earlier than requested — the deadline
  check `now + interval >= deadline` has been corrected to `now >= deadline`.
- `Retry-After: 0` was discarded and fell back to exponential backoff
  instead of retrying immediately as the server instructed.
- Published gem now includes the MIT `LICENSE` file (the gemspec already
  referenced it, but the file was missing from the gem contents).

### Added
- `internal_error` (HTTP 500) is now covered in the error-mapping test
  matrix.

## [0.1.0] — 2026-05-01

First publishable release. Targets Walinko public API
`POST /api/v1/public/messages` and `GET /api/v1/public/messages/:tracking_id`.

### Added
- `Walinko::Client.new(api_key:, base_url:, timeout:, open_timeout:,
  max_retries:, logger:)`.
- `client.messages.send` (sync) returning `Walinko::SyncResult`.
- `client.messages.enqueue` (async) returning `Walinko::AsyncJob`.
- `client.messages.fetch(tracking_id)` returning `Walinko::MessageStatus`.
- `client.messages.wait_until_done(tracking_id, timeout:, interval:)` —
  polls until the delivery reaches a terminal state, raises
  `Walinko::TimeoutError` if it doesn't.
- Typed exception hierarchy (`Walinko::Error` →
  `Walinko::ApiError` / `Walinko::ConnectionError`), with specialized
  classes for every documented `error_code` (`AuthenticationError`,
  `BadRequestError`, `ForbiddenError`, `TenantSuspendedError`,
  `QuotaExceededError`, `NotFoundError`, `ConflictError`,
  `DeviceDisconnectedError`, `IdempotencyConflictError`,
  `ValidationError`, `RateLimitError`, `ServerError`,
  `TimeoutError`).
- Automatic `Idempotency-Key` generation for `send` / `enqueue` (use
  `idempotency_key:` to override).
- Idempotent retry policy: network errors, 429 (honouring `Retry-After`),
  and 5xx are retried up to `max_retries` with exponential backoff +
  jitter. The same `Idempotency-Key` is reused on every retry.
- `client.last_rate_limit` and `client.last_request_id` reflect the
  most recent response's `X-RateLimit-*` and `X-Request-Id` headers.
- `Idempotent-Replayed: true` is surfaced on `SyncResult#idempotent_replayed`
  / `AsyncJob#idempotent_replayed`.

### Internal
- 100% stdlib transport — no Faraday / HTTPX dependency.
- Ruby 3.1+ required.
- 66 RSpec examples covering every documented error code, retry path,
  and idempotency edge case.
