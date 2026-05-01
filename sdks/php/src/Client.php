<?php

declare(strict_types=1);

namespace Walinko;

use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Walinko\Http\Transport;
use Walinko\Resource\Messages;
use Walinko\Result\RateLimitSnapshot;

/**
 * Walinko PHP SDK — server-to-server client for the Walinko public API.
 *
 * See `README.md` for a quick-start; the contract is pinned in
 * `walinko-sdk/docs/openapi.yaml`.
 *
 * TODO(walinko-webhooks): when the server starts emitting webhooks,
 * `$client->webhooks` (e.g. `$client->webhooks->verify($payload, $sig)`)
 * will land here. Reserving the namespace so v1 callers don't break.
 */
final class Client
{
    public const VERSION = '0.1.0';

    public readonly Configuration $config;
    public readonly Messages $messages;

    private readonly Transport $transport;

    /**
     * @param array{
     *   api_key: string,
     *   base_url?: string,
     *   timeout?: int,
     *   max_retries?: int,
     *   http_client?: HttpClientInterface,
     *   request_factory?: RequestFactoryInterface,
     *   stream_factory?: StreamFactoryInterface,
     *   logger?: LoggerInterface
     * } $options
     */
    public function __construct(array $options)
    {
        $this->config = new Configuration($options);
        $this->transport = new Transport($this->config);
        $this->messages = new Messages($this->transport);
    }

    /**
     * Snapshot of the rate-limit window from the most recent response.
     * Returns `null` until the first call has completed.
     */
    public function lastRateLimit(): ?RateLimitSnapshot
    {
        return $this->transport->lastRateLimit;
    }

    /**
     * The `X-Request-Id` from the most recent response (or `null`).
     * Useful when filing support tickets.
     */
    public function lastRequestId(): ?string
    {
        return $this->transport->lastRequestId;
    }

    /**
     * @internal Test seam — exposes the transport so tests can stub
     *           the sleep function.
     */
    public function transport(): Transport
    {
        return $this->transport;
    }
}
