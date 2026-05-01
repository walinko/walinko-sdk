<?php

declare(strict_types=1);

namespace Walinko\Http;

use Walinko\Result\RateLimitSnapshot;

/**
 * Internal value object — successful response shape returned by
 * `Transport::request()`.
 *
 * @internal
 */
final class InternalResponse
{
    /**
     * @param array<string, mixed>|null $body
     */
    public function __construct(
        public readonly int $status,
        public readonly ?array $body,
        public readonly ?string $requestId,
        public readonly ?RateLimitSnapshot $rateLimit,
        public readonly bool $idempotentReplayed,
    ) {
    }
}
