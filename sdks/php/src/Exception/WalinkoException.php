<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * Base class for every exception thrown by the Walinko PHP SDK.
 *
 * `catch (WalinkoException $e)` to handle anything the SDK throws.
 * For finer-grained control, catch one of the typed subclasses
 * (see `docs/error-codes.md`).
 */
class WalinkoException extends \RuntimeException
{
}
