<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * 403 + `error_code: tenant_suspended` — tenant account is suspended
 * or scheduled for deletion.
 */
class TenantSuspendedException extends ForbiddenException
{
}
