<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * 5xx — server-side failure. Outcome of the underlying send may or may
 * not have happened; replay with the same `Idempotency-Key` is safe.
 */
class ServerException extends ApiException
{
}
