<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * 403 — generic forbidden. See the more specific
 * `TenantSuspendedException` and `QuotaExceededException` subclasses.
 */
class ForbiddenException extends ApiException
{
}
