<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * 504 — WhatsApp send did not complete within the server's 15s window.
 *
 * Outcome unknown; replay with the same `Idempotency-Key` is safe.
 * Also raised by `Messages::waitUntilDone()` when the local poll
 * deadline expires while the message is still pending.
 */
class TimeoutException extends ApiException
{
}
