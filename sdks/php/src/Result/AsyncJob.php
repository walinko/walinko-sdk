<?php

declare(strict_types=1);

namespace Walinko\Result;

/**
 * Returned by `Messages::enqueue()`. Wraps the `data` block of a 202
 * Accepted response.
 */
final class AsyncJob
{
    public readonly ?string $trackingId;
    public readonly ?string $status;
    public readonly ?string $statusUrl;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        array $data,
        public readonly ?string $requestId,
        public readonly ?RateLimitSnapshot $rateLimit,
        public readonly bool $idempotentReplayed,
    ) {
        $this->trackingId = self::str($data, 'tracking_id');
        $this->status = self::str($data, 'status');
        $this->statusUrl = self::str($data, 'status_url');
    }

    public function isQueued(): bool
    {
        return $this->status === 'queued';
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function str(array $data, string $key): ?string
    {
        return isset($data[$key]) && \is_string($data[$key]) ? $data[$key] : null;
    }
}
