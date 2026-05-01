<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * 409 — generic conflict. See the more specific
 * `DeviceDisconnectedException` and `IdempotencyConflictException`
 * subclasses.
 */
class ConflictException extends ApiException
{
}
