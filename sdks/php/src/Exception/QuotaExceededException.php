<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * 403 + `error_code: quota_exceeded` — tenant has hit its monthly /
 * daily / balance message limit. `$details['resets_at']` (when set)
 * tells you when the limit resets.
 */
class QuotaExceededException extends ForbiddenException
{
}
