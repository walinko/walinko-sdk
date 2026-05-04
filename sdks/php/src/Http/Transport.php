<?php

declare(strict_types=1);

namespace Walinko\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as HttpClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Walinko\Configuration;
use Walinko\Exception\ApiException;
use Walinko\Exception\ConnectionException;
use Walinko\Exception\ErrorMapping;
use Walinko\Exception\RateLimitException;
use Walinko\Exception\WalinkoException;
use Walinko\Result\RateLimitSnapshot;

/**
 * PSR-18-based HTTP transport. Owns the wire format (JSON in/out,
 * header naming), retry policy, and error mapping.
 *
 * `Resource\Messages` is the only consumer.
 *
 * @internal
 */
final class Transport
{
    /** Statuses we consider transient and retry automatically. */
    private const RETRYABLE_HTTP_STATUSES = [429, 500, 502, 503, 504];

    /** Backoff curve (seconds). Index = attempt number. Caps at last entry. */
    private const BACKOFF_SECONDS = [0.25, 0.75, 1.75, 3.75];

    /** Hard cap on `Retry-After` we'll honour. */
    private const MAX_RETRY_AFTER_SECONDS = 60;

    private HttpClientInterface $http;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;

    public ?string $lastRequestId = null;
    public ?RateLimitSnapshot $lastRateLimit = null;

    /** @var callable(float):void */
    private $sleeper;

    public function __construct(private readonly Configuration $config)
    {
        $this->http = $config->httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $config->requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $config->streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->sleeper = static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) round($seconds * 1_000_000));
            }
        };
    }

    /**
     * Test seam — replace the sleep implementation to avoid real
     * delays in unit tests.
     *
     * @internal
     * @param callable(float):void $sleeper
     */
    public function setSleeper(callable $sleeper): void
    {
        $this->sleeper = $sleeper;
    }

    /**
     * Issues an HTTP request with the configured retry policy.
     *
     * @param string                $method  GET|POST
     * @param string                $path    e.g. "/api/v1/public/messages"
     * @param array<string, mixed>|null $body  JSON-encoded into the body
     * @param array<string, string>  $headers Extra headers to merge
     */
    public function request(string $method, string $path, ?array $body = null, array $headers = []): InternalResponse
    {
        $attempt = 0;

        while (true) {
            $outcome = $this->perform($method, $path, $body, $headers);
            if ($outcome instanceof InternalResponse) {
                return $outcome;
            }

            $attempt++;
            if ($attempt > $this->config->maxRetries) {
                throw $outcome;
            }

            $sleepSeconds = $this->sleepSeconds($outcome, $attempt);
            $this->log('warning', sprintf(
                'retrying after %.2fs (attempt %d/%d): %s',
                $sleepSeconds,
                $attempt,
                $this->config->maxRetries,
                $outcome->getMessage(),
            ));
            ($this->sleeper)($sleepSeconds);
        }
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string>     $headers
     * @return InternalResponse|WalinkoException
     */
    private function perform(string $method, string $path, ?array $body, array $headers): InternalResponse|WalinkoException
    {
        $url = $this->config->baseUrl . '/' . ltrim($path, '/');

        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Authorization', 'Bearer ' . $this->config->apiKey)
            ->withHeader('Accept', 'application/json');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $json = json_encode($body, \JSON_THROW_ON_ERROR);
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($json));
        }

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            return new ConnectionException($e->getMessage(), 0, $e);
        }

        return $this->handleResponse($response);
    }

    /**
     * @return InternalResponse|ApiException
     */
    private function handleResponse(ResponseInterface $response): InternalResponse|ApiException
    {
        $status = $response->getStatusCode();
        $requestId = $response->getHeaderLine('X-Request-Id') ?: null;
        $rateLimit = $this->parseRateLimit($response);
        $replayed = $response->getHeaderLine('Idempotent-Replayed') === 'true';
        $body = $this->decodeBody($response);

        $this->lastRequestId = $requestId;
        $this->lastRateLimit = $rateLimit;

        if ($status >= 200 && $status < 300) {
            return new InternalResponse(
                status: $status,
                body: $body,
                requestId: $requestId,
                rateLimit: $rateLimit,
                idempotentReplayed: $replayed,
            );
        }

        return $this->buildApiError($status, $body, $response);
    }

    /**
     * Builds the typed exception. For retryable statuses returns the
     * exception (caller decides whether to retry); for non-retryable
     * statuses throws immediately.
     *
     * @param array<string, mixed>|null $body
     */
    private function buildApiError(int $status, ?array $body, ResponseInterface $response): ApiException
    {
        $message = (\is_array($body) && \is_string($body['message'] ?? null)) ? $body['message'] : 'HTTP ' . $status;
        $errorCode = (\is_array($body) && \is_string($body['error_code'] ?? null)) ? $body['error_code'] : null;
        $details = (\is_array($body) && \is_array($body['details'] ?? null)) ? $body['details'] : [];
        $requestId = $response->getHeaderLine('X-Request-Id') ?: null;

        $class = ErrorMapping::for($status, $errorCode);

        if (is_a($class, RateLimitException::class, true)) {
            $retryAfter = $this->parseRetryAfter($response);
            $exception = new $class(
                $message,
                $status,
                $errorCode,
                $requestId,
                $details,
                $body,
                $retryAfter,
            );
        } else {
            $exception = new $class(
                $message,
                $status,
                $errorCode,
                $requestId,
                $details,
                $body,
            );
        }

        if (\in_array($status, self::RETRYABLE_HTTP_STATUSES, true)) {
            return $exception;
        }

        throw $exception;
    }

    private function parseRateLimit(ResponseInterface $response): ?RateLimitSnapshot
    {
        $limitHeader = $response->getHeaderLine('X-RateLimit-Limit');
        $remainingHeader = $response->getHeaderLine('X-RateLimit-Remaining');

        if ($limitHeader === '' && $remainingHeader === '') {
            return null;
        }

        return new RateLimitSnapshot(
            limit:     $limitHeader !== '' ? (int) $limitHeader : null,
            remaining: $remainingHeader !== '' ? (int) $remainingHeader : null,
        );
    }

    private function parseRetryAfter(ResponseInterface $response): ?int
    {
        $value = $response->getHeaderLine('Retry-After');
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeBody(ResponseInterface $response): ?array
    {
        $raw = (string) $response->getBody();
        if ($raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($decoded) ? $decoded : null;
    }

    private function sleepSeconds(WalinkoException $error, int $attempt): float
    {
        if ($error instanceof RateLimitException && $error->retryAfter !== null) {
            return (float) min($error->retryAfter, self::MAX_RETRY_AFTER_SECONDS);
        }

        $index = max(0, min($attempt - 1, \count(self::BACKOFF_SECONDS) - 1));
        $base = self::BACKOFF_SECONDS[$index];
        $jitter = mt_rand(0, 100) / 1000.0; // 0..0.1s jitter

        return $base + $jitter;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $this->config->logger?->log($level, '[walinko] ' . $message, $context);
    }
}
