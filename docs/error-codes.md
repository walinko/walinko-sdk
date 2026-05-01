# Error codes

Every non-2xx response from the public API includes a stable, machine-readable
`error_code` string in addition to the human-readable `message`. SDKs map
`error_code` (when present) and HTTP status (always) to typed exceptions.

The `error_code` value is part of the public contract — once an SDK has
shipped a release that maps it, it is treated as immutable. Adding a *new*
error code is a minor change; renaming or repurposing one requires a major
SDK bump.

## Reference

| HTTP | `error_code`           | Meaning                                                                 | Retry safe? | SDK exception (Ruby)            | SDK exception (PHP)                          |
| ---- | ---------------------- | ----------------------------------------------------------------------- | ----------- | ------------------------------- | -------------------------------------------- |
| 400  | `bad_request`          | Catch-all for malformed JSON or unknown body shape.                     | no          | `Walinko::BadRequestError`      | `Walinko\Exception\BadRequestException`      |
| 400  | `variant_out_of_range` | `variant_index` is outside the template's variant pool.                 | no          | `Walinko::BadRequestError`      | `Walinko\Exception\BadRequestException`      |
| 401  | `invalid_api_key`      | Token missing, malformed, expired, revoked, or unknown. Generic on purpose. | no       | `Walinko::AuthenticationError`  | `Walinko\Exception\AuthenticationException`  |
| 403  | `tenant_suspended`     | Tenant account is suspended or scheduled for deletion.                  | no          | `Walinko::TenantSuspendedError` | `Walinko\Exception\TenantSuspendedException` |
| 403  | `quota_exceeded`       | Tenant has hit its monthly / daily / balance message limit.             | not until reset | `Walinko::QuotaExceededError` | `Walinko\Exception\QuotaExceededException`  |
| 404  | `device_not_found`     | `device_id` does not exist (or was soft-deleted) for this tenant.       | no          | `Walinko::NotFoundError`        | `Walinko\Exception\NotFoundException`        |
| 404  | `template_not_found`   | `template_id` does not exist for this tenant.                           | no          | `Walinko::NotFoundError`        | `Walinko\Exception\NotFoundException`        |
| 404  | `delivery_not_found`   | `tracking_id` does not match any delivery for this tenant.              | no          | `Walinko::NotFoundError`        | `Walinko\Exception\NotFoundException`        |
| 409  | `device_disconnected`  | Device session is not connected. Reconnect from the dashboard.          | yes, after reconnect | `Walinko::DeviceDisconnectedError` | `Walinko\Exception\DeviceDisconnectedException` |
| 409  | `idempotency_conflict` | `Idempotency-Key` was previously used with a *different* payload.       | no — change key | `Walinko::IdempotencyConflictError` | `Walinko\Exception\IdempotencyConflictException` |
| 422  | `phone_not_on_whatsapp` | Recipient phone number is not registered on WhatsApp.                  | no          | `Walinko::ValidationError`      | `Walinko\Exception\ValidationException`      |
| 422  | `validation_error`     | DTO validation failure (one or more fields). `details.fields` is a `{field: [reason]}` map. | no | `Walinko::ValidationError`     | `Walinko\Exception\ValidationException`      |
| 429  | `rate_limited`         | Sliding-window rate limit (30 req/min/key) exceeded.                    | yes — honor `Retry-After` | `Walinko::RateLimitError` | `Walinko\Exception\RateLimitException`       |
| 500  | `send_failed`          | Internal failure during WhatsApp send. May or may not have delivered.   | yes — with `Idempotency-Key` | `Walinko::ServerError`     | `Walinko\Exception\ServerException`          |
| 500  | `internal_error`       | Anything else unexpected.                                               | yes — with `Idempotency-Key` | `Walinko::ServerError`     | `Walinko\Exception\ServerException`          |
| 504  | `send_timeout`         | WhatsApp send did not complete within 15s. **Outcome unknown.**         | yes — with `Idempotency-Key` | `Walinko::TimeoutError`    | `Walinko\Exception\TimeoutException`         |

## Retries — what's safe

The SDK retries automatically (up to `max_retries`, default `2`) on:

- Network errors (connection refused, reset, DNS, TLS).
- `429` — honoring `Retry-After`.
- `5xx` — exponential backoff with jitter.
- `504` — same as `5xx`; outcome unknown so always retry with the original `Idempotency-Key`.

The SDK never retries on `4xx` other than `429`; those represent the caller's
input, not transient infrastructure.

To make retries fully safe end-to-end, the SDK auto-generates a UUID
`Idempotency-Key` per `send` / `enqueue` call when the caller does not supply
one. This guarantees that any retried attempt is deduped by the server's
idempotency cache (24 h TTL) — even when the original network attempt may
have already reached the WhatsApp gateway.

## `details` payloads

| `error_code`           | `details` shape                                                              |
| ---------------------- | ---------------------------------------------------------------------------- |
| `validation_error`     | `{ "fields": { "phone": ["must match pattern …"], "device_id": ["must be a number"] } }` |
| `quota_exceeded`       | `{ "limit_type": "monthly" \| "daily" \| "balance", "resets_at": "2026-06-01T00:00:00Z" }` |
| `rate_limited`         | `{ "retry_after_ms": 12000 }`                                                |
| `idempotency_conflict` | `{ "first_seen_at": "2026-05-01T03:00:00Z" }`                                |

All other codes use `details: {}` or omit `details` entirely.

## Server contract checklist

For every error path the server must:

1. Set HTTP status to the value listed above.
2. Emit the `error_code` string verbatim — no localization, no rephrasing.
3. Include a human-friendly `message` (this *can* be localized later).
4. Set `success: false`.
5. Include `details` only for the codes listed in the table above.

The server's `HttpExceptionFilter` is the single place that owns this
serialization.

## Adding a new code

1. Add a new row to the table above.
2. Bump the OpenAPI spec example list under `ErrorEnvelope.error_code`.
3. Map it in both SDKs to either an existing exception class (when the
   semantics align) or a new one (rare — discuss before introducing).
4. Mention it in both SDK changelogs at the next release.
