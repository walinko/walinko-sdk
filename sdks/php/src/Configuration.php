<?php

declare(strict_types=1);

namespace Walinko;

use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Immutable configuration captured by `Walinko\Client::__construct()`.
 *
 * Validates required fields and applies defaults. PSR-18 / PSR-17
 * dependencies are auto-discovered via `php-http/discovery` when the
 * caller doesn't inject them explicitly.
 *
 * Note on `timeout`: PSR-18 has no standard transport-level timeout
 * setting. The SDK uses `timeout` to seed `Messages::waitUntilDone`'s
 * default deadline; if you want a per-request timeout on the wire,
 * inject a pre-configured PSR-18 client (e.g. Guzzle / Symfony with
 * `timeout` / `read_timeout` set in the constructor).
 */
final class Configuration
{
    public const DEFAULT_BASE_URL = 'https://api.walinko.com';
    public const DEFAULT_TIMEOUT = 30;
    public const DEFAULT_MAX_RETRIES = 2;

    public readonly string $apiKey;
    public readonly string $baseUrl;
    public readonly int $timeout;
    public readonly int $maxRetries;
    public readonly ?HttpClientInterface $httpClient;
    public readonly ?RequestFactoryInterface $requestFactory;
    public readonly ?StreamFactoryInterface $streamFactory;
    public readonly ?LoggerInterface $logger;

    /**
     * @param array{
     *     api_key: string,
     *     base_url?: string,
     *     timeout?: int,
     *     max_retries?: int,
     *     http_client?: HttpClientInterface,
     *     request_factory?: RequestFactoryInterface,
     *     stream_factory?: StreamFactoryInterface,
     *     logger?: LoggerInterface
     * } $options
     */
    public function __construct(array $options)
    {
        $apiKey = $options['api_key'] ?? '';
        if (!\is_string($apiKey) || $apiKey === '') {
            throw new \InvalidArgumentException('api_key is required');
        }

        $baseUrl = $options['base_url'] ?? self::DEFAULT_BASE_URL;
        if (!\is_string($baseUrl) || $baseUrl === '') {
            throw new \InvalidArgumentException('base_url must be a non-empty string');
        }

        $timeout = (int) ($options['timeout'] ?? self::DEFAULT_TIMEOUT);
        if ($timeout <= 0) {
            throw new \InvalidArgumentException('timeout must be > 0');
        }

        $maxRetries = (int) ($options['max_retries'] ?? self::DEFAULT_MAX_RETRIES);
        if ($maxRetries < 0) {
            throw new \InvalidArgumentException('max_retries must be >= 0');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
        $this->httpClient = $options['http_client'] ?? null;
        $this->requestFactory = $options['request_factory'] ?? null;
        $this->streamFactory = $options['stream_factory'] ?? null;
        $this->logger = $options['logger'] ?? null;
    }
}
