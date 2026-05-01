<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * 401 — missing / malformed / expired / revoked / unknown API key.
 * All five are surfaced with the same generic message on purpose.
 */
class AuthenticationException extends ApiException
{
}
