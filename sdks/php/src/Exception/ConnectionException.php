<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * Raised when the SDK couldn't reach the API at all (DNS failure,
 * connection refused, TLS handshake failed, socket reset, etc.).
 *
 * These are retried automatically up to `max_retries`; if you see
 * one in user code, the retry budget was exhausted.
 */
class ConnectionException extends WalinkoException
{
}
