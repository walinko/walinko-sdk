<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * Base class for every exception thrown by the Walinko PHP SDK. All typed
 * exceptions (BadRequestException, AuthenticationException, ...) extend this
 * so consumers can `catch (WalinkoException $e)` to handle anything the SDK
 * throws.
 *
 * Concrete subclasses land in Phase 1; see `docs/error-codes.md` for the
 * full mapping.
 */
class WalinkoException extends \RuntimeException
{
}
