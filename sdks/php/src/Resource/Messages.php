<?php

declare(strict_types=1);

namespace Walinko\Resource;

use Walinko\Exception\TimeoutException;
use Walinko\Http\Transport;
use Walinko\Result\AsyncJob;
use Walinko\Result\MessageStatus;
use Walinko\Result\SyncResult;

/**
 * The `messages` resource on `Walinko\Client`. Wraps:
 *
 *   POST /api/v1/public/messages
 *   GET  /api/v1/public/messages/:trackingId
 */
final class Messages
{
    public const SEND_PATH = '/api/v1/public/messages';

    /** @var callable(float):void */
    private $sleeper;

    public function __construct(private readonly Transport $transport)
    {
        $this->sleeper = static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) round($seconds * 1_000_000));
            }
        };
    }

    /**
     * Test seam — replace the sleep implementation to avoid real
     * delays in `waitUntilDone()`.
     *
     * @internal
     * @param callable(float):void $sleeper
     */
    public function setSleeper(callable $sleeper): void
    {
        $this->sleeper = $sleeper;
    }

    /**
     * Synchronous send: blocks until the WhatsApp gateway acknowledges
     * delivery (or the server's 15s timeout fires).
     *
     * @param array{
     *   device_id: int,
     *   template_id: int,
     *   phone: string,
     *   variables?: array<string, mixed>,
     *   variant_index?: int|null,
     *   idempotency_key?: string|null
     * } $params
     */
    public function send(array $params): SyncResult
    {
        $payload = $this->buildPayload($params, async: false);
        $response = $this->post($payload, $params['idempotency_key'] ?? null);

        return new SyncResult(
            data: $this->extractData($response->body),
            requestId: $response->requestId,
            rateLimit: $response->rateLimit,
            idempotentReplayed: $response->idempotentReplayed,
        );
    }

    /**
     * Asynchronous enqueue: server returns immediately with a tracking
     * id; the actual WhatsApp send happens out-of-band.
     *
     * @param array{
     *   device_id: int,
     *   template_id: int,
     *   phone: string,
     *   variables?: array<string, mixed>,
     *   variant_index?: int|null,
     *   idempotency_key?: string|null
     * } $params
     */
    public function enqueue(array $params): AsyncJob
    {
        $payload = $this->buildPayload($params, async: true);
        $response = $this->post($payload, $params['idempotency_key'] ?? null);

        return new AsyncJob(
            data: $this->extractData($response->body),
            requestId: $response->requestId,
            rateLimit: $response->rateLimit,
            idempotentReplayed: $response->idempotentReplayed,
        );
    }

    /**
     * Look up a delivery by its tracking id.
     */
    public function fetch(string $trackingId): MessageStatus
    {
        if ($trackingId === '') {
            throw new \InvalidArgumentException('tracking_id is required');
        }

        $response = $this->transport->request('GET', self::SEND_PATH . '/' . rawurlencode($trackingId));

        return new MessageStatus(
            data: $this->extractData($response->body),
            requestId: $response->requestId,
        );
    }

    /**
     * Poll `fetch` until the delivery reaches a terminal state (sent /
     * failed) or the timeout expires.
     *
     * @throws TimeoutException if the message is still pending when
     *                          `$timeout` elapses (it is *not* failed —
     *                          continue polling later if you need to)
     */
    public function waitUntilDone(string $trackingId, int $timeout = 60, float $interval = 2.0): MessageStatus
    {
        if ($timeout <= 0) {
            throw new \InvalidArgumentException('timeout must be > 0');
        }
        if ($interval <= 0) {
            throw new \InvalidArgumentException('interval must be > 0');
        }

        $deadline = $this->monotonicNow() + $timeout;
        while (true) {
            $status = $this->fetch($trackingId);
            if ($status->isDone()) {
                return $status;
            }

            if ($this->monotonicNow() >= $deadline) {
                throw new TimeoutException(
                    sprintf(
                        'Timed out waiting for %s after %ds (still %s)',
                        $trackingId,
                        $timeout,
                        $status->status ?? 'unknown',
                    ),
                    httpStatus: 504,
                    errorCode: 'send_timeout',
                    requestId: $status->requestId,
                    details: ['last_status' => $status->status],
                );
            }

            ($this->sleeper)($interval);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(array $payload, ?string $idempotencyKey): \Walinko\Http\InternalResponse
    {
        $key = ($idempotencyKey !== null && $idempotencyKey !== '')
            ? $idempotencyKey
            : $this->generateIdempotencyKey();

        return $this->transport->request(
            'POST',
            self::SEND_PATH,
            $payload,
            ['Idempotency-Key' => $key],
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function buildPayload(array $params, bool $async): array
    {
        if (!isset($params['device_id']) || !\is_int($params['device_id'])) {
            throw new \InvalidArgumentException('device_id is required and must be an int');
        }
        if (!isset($params['template_id']) || !\is_int($params['template_id'])) {
            throw new \InvalidArgumentException('template_id is required and must be an int');
        }
        if (!isset($params['phone']) || !\is_string($params['phone']) || $params['phone'] === '') {
            throw new \InvalidArgumentException('phone is required and must be a non-empty string');
        }

        $payload = [
            'device_id'   => $params['device_id'],
            'template_id' => $params['template_id'],
            'phone'       => $params['phone'],
            'async'       => $async,
        ];

        if (\array_key_exists('variant_index', $params) && $params['variant_index'] !== null) {
            if (!\is_int($params['variant_index'])) {
                throw new \InvalidArgumentException('variant_index must be an int when provided');
            }
            $payload['variant_index'] = $params['variant_index'];
        }

        if (\array_key_exists('variables', $params) && $params['variables'] !== null) {
            if (!\is_array($params['variables'])) {
                throw new \InvalidArgumentException('variables must be an array');
            }
            $payload['variables'] = $this->stringifyVariables($params['variables']);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, string>
     */
    private function stringifyVariables(array $variables): array
    {
        $out = [];
        foreach ($variables as $key => $value) {
            if (!\is_string($key)) {
                throw new \InvalidArgumentException('variables must be a string-keyed array');
            }
            $out[$key] = $value === null ? '' : (string) $value;
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function extractData(?array $body): array
    {
        if ($body === null) {
            return [];
        }

        $data = $body['data'] ?? null;

        return \is_array($data) ? $data : [];
    }

    private function generateIdempotencyKey(): string
    {
        $bytes = random_bytes(16);
        // RFC-4122 v4 layout
        $bytes[6] = chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((\ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            'walinko-php-%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    private function monotonicNow(): float
    {
        return hrtime(true) / 1e9;
    }
}
