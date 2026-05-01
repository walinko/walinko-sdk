<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * 400 — malformed request, unknown body shape, or `variant_index` out
 * of range. Validation errors are raised as `ValidationException`.
 */
class BadRequestException extends ApiException
{
}
