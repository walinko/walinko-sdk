<?php

declare(strict_types=1);

namespace Walinko\Result;

/**
 * Snapshot of the server-reported rate-limit window from the most
 * recent response.
 */
final class RateLimitSnapshot
{
    public function __construct(
        public readonly ?int $limit,
        public readonly ?int $remaining,
    ) {
    }

    public function isSaturated(): bool
    {
        return $this->remaining !== null && $this->remaining <= 0;
    }
}
