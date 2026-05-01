<?php

declare(strict_types=1);

namespace Walinko\Result;

/**
 * Returned by `Messages::fetch($trackingId)`. Wraps the `data` block
 * of `GET /messages/:trackingId`.
 */
final class MessageStatus
{
    public readonly ?string $trackingId;
    public readonly ?string $status;
    public readonly ?int $deviceId;
    public readonly ?int $templateId;
    public readonly ?int $variantIndex;
    public readonly ?string $phone;
    public readonly ?string $waMessageId;
    public readonly ?string $errorCode;
    public readonly ?string $errorMessage;
    public readonly ?\DateTimeImmutable $sentAt;
    public readonly ?\DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        array $data,
        public readonly ?string $requestId,
    ) {
        $this->trackingId = self::str($data, 'tracking_id');
        $this->status = self::str($data, 'status');
        $this->deviceId = self::int($data, 'device_id');
        $this->templateId = self::int($data, 'template_id');
        $this->variantIndex = self::int($data, 'variant_index');
        $this->phone = self::str($data, 'phone');
        $this->waMessageId = self::str($data, 'wa_message_id');
        $this->errorCode = self::str($data, 'error_code');
        $this->errorMessage = self::str($data, 'error_message');
        $this->sentAt = self::time($data, 'sent_at');
        $this->createdAt = self::time($data, 'created_at');
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'queued' || $this->status === 'sending';
    }

    public function isDone(): bool
    {
        return $this->isSent() || $this->isFailed();
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
