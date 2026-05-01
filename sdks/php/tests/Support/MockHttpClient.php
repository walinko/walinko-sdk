<?php

declare(strict_types=1);

namespace Walinko\Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Tiny PSR-18 fake. Records every outgoing request and returns the
 * next queued response (or invokes a queued callable for full
 * control over the response per attempt).
 */
final class MockHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface|callable(RequestInterface):ResponseInterface|\Throwable> */
    private array $queue = [];

    /** @var list<RequestInterface> */
    public array $requests = [];

    public function pushResponse(int $status, mixed $body = null, array $headers = []): void
    {
        $this->queue[] = $this->buildResponse($status, $body, $headers);
    }

    /**
     * @param callable(RequestInterface):(ResponseInterface|\Throwable) $factory
     */
    public function pushFactory(callable $factory): void
    {
        $this->queue[] = $factory;
    }

    public function pushException(\Throwable $exception): void
    {
        $this->queue[] = $exception;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new \LogicException('MockHttpClient ran out of queued responses');
        }

        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        if (\is_callable($next)) {
            $result = $next($request);
            if ($result instanceof \Throwable) {
                throw $result;
            }

            return $result;
        }

        return $next;
    }

    public function buildResponse(int $status, mixed $body = null, array $headers = []): ResponseInterface
    {
        $serialized = match (true) {
            $body === null    => '',
            \is_string($body) => $body,
            default           => json_encode($body, \JSON_THROW_ON_ERROR),
        };

        return new Response($status, $headers, $serialized);
    }
}
