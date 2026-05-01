<?php

declare(strict_types=1);

namespace Walinko\Tests\Support;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Minimal PSR-18 NetworkException for tests. Lets us simulate
 * connection-level failures (DNS, refused, reset, …).
 */
final class NetworkException extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(
        string $message,
        private readonly RequestInterface $request,
    ) {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
