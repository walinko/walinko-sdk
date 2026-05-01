<?php

declare(strict_types=1);

namespace Walinko\Result;

/**
 * Returned by `Messages::send()`. Wraps the `data` block of a 200 OK
 * response.
 */
final class SyncResult
{
    public readonly ?string $trackingId;
    public readonly ?string $status;
    public readonly ?int $deviceId;
    public readonly ?int $templateId;
    public readonly ?int $variantIndex;
    public readonly ?string $phone;
    public readonly ?string $waMessageId;
    public readonly ?\DateTimeImmutable $sentAt;

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
        $this->deviceId = self::int($data, 'device_id');
        $this->templateId = self::int($data, 'template_id');
        $this->variantIndex = self::int($data, 'variant_index');
        $this->phone = self::str($data, 'phone');
        $this->waMessageId = self::str($data, 'wa_message_id');
        $this->sentAt = self::time($data, 'sent_at');
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function str(array $data, string $key): ?string
    {
        return isset($data[$key]) && \is_string($data[$key]) ? $data[$key] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function int(array $data, string $key): ?int
    {
        return isset($data[$key]) && (\is_int($data[$key]) || ctype_digit((string) $data[$key]))
            ? (int) $data[$key]
            : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function time(array $data, string $key): ?\DateTimeImmutable
    {
        $raw = $data[$key] ?? null;
        if (!\is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
    }
}
