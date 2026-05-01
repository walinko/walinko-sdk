<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * 409 + `error_code: idempotency_conflict` — `Idempotency-Key` was
 * previously used with a *different* payload. Use a new key.
 */
class IdempotencyConflictException extends ConflictException
{
}
