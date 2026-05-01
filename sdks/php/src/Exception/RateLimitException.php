<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * 429 — sliding-window rate limit (default 30 req/min/key) exceeded.
 *
 * `$retryAfter` is the recommended sleep in seconds (parsed from the
 * `Retry-After` header).
 */
class RateLimitException extends ApiException
{
    /**
     * @param array<string, mixed> $details
     * @param array<string, mixed>|null $body
     */
    public function __construct(
        string $message,
        int $httpStatus,
        ?string $errorCode = null,
        ?string $requestId = null,
        array $details = [],
        ?array $body = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $httpStatus, $errorCode, $requestId, $details, $body);
    }
}
