<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * Base class for every error response returned by the API.
 *
 * Carries the full diagnostic payload — HTTP status, server-side
 * `error_code`, the `X-Request-Id` (handy for support tickets),
 * and the optional `details` blob.
 */
class ApiException extends WalinkoException
{
    /**
     * @param array<string, mixed> $details
     * @param array<string, mixed>|null $body
     */
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        public readonly ?string $errorCode = null,
        public readonly ?string $requestId = null,
        public readonly array $details = [],
        public readonly ?array $body = null,
    ) {
        parent::__construct($message);
    }
}
