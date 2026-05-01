<?php

declare(strict_types=1);

namespace Walinko\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Walinko\Client;
use Walinko\Exception\AuthenticationException;
use Walinko\Exception\BadRequestException;
use Walinko\Exception\ConnectionException;
use Walinko\Exception\DeviceDisconnectedException;
use Walinko\Exception\IdempotencyConflictException;
use Walinko\Exception\NotFoundException;
use Walinko\Exception\QuotaExceededException;
use Walinko\Exception\RateLimitException;
use Walinko\Exception\ServerException;
use Walinko\Exception\TenantSuspendedException;
use Walinko\Exception\TimeoutException;
use Walinko\Exception\ValidationException;
use Walinko\Resource\Messages;
use Walinko\Result\AsyncJob;
use Walinko\Result\MessageStatus;
use Walinko\Result\SyncResult;
use Walinko\Tests\Support\MockHttpClient;
use Walinko\Tests\Support\NetworkException;

final class MessagesTest extends TestCase
{
    private MockHttpClient $http;
    private Client $client;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $this->client = $this->buildClient(maxRetries: 0);
    }

    // ---- helpers ----------------------------------------------------

    private function buildClient(int $maxRetries = 0): Client
    {
        $factory = new Psr17Factory();
        $client = new Client([
            'api_key'         => 'walk_live_keyid.secret',
            'base_url'        => 'https://api.example.com',
            'max_retries'     => $maxRetries,
            'http_client'     => $this->http,
            'request_factory' => $factory,
            'stream_factory'  => $factory,
        ]);
        // Disable real backoff sleeps for the retry tests.
        $client->transport()->setSleeper(static fn () => null);

        return $client;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function successEnvelope(array $data): array
    {
        return ['success' => true, 'message' => 'ok', 'data' => $data];
    }

    /**
     * @param array<string, mixed>|null $details
     * @return array<string, mixed>
     */
    private static function errorEnvelope(string $message, ?string $errorCode = null, ?array $details = null): array
    {
        $body = ['success' => false, 'message' => $message];
        if ($errorCode !== null) {
            $body['error_code'] = $errorCode;
        }
        if ($details !== null) {
            $body['details'] = $details;
        }

        return $body;
    }

    /**
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    private static function commonHeaders(array $extra = []): array
    {
        return array_merge([
            'Content-Type'          => 'application/json',
            'X-Request-Id'          => 'req_abc123',
            'X-RateLimit-Limit'     => '30',
            'X-RateLimit-Remaining' => '29',
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private static function syncData(): array
    {
        return [
            'tracking_id'   => 'tx_abc',
            'status'        => 'sent',
            'device_id'     => 1,
            'template_id'   => 12,
            'variant_index' => 0,
            'phone'         => '+8801617738431',
            'sent_at'       => '2026-05-01T10:00:00Z',
            'wa_message_id' => 'wamid.HBgL...',
        ];
    }

    // ---- sync send --------------------------------------------------

    public function testSendPostsJsonAndReturnsSyncResult(): void
    {
        $this->http->pushResponse(200, self::successEnvelope(self::syncData()), self::commonHeaders());

        $result = $this->client->messages->send([
            'device_id'   => 1,
            'template_id' => 12,
            'phone'       => '+8801617738431',
            'variables'   => ['name' => 'Kazi'],
        ]);

        self::assertCount(1, $this->http->requests);
        $request = $this->http->requests[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.example.com/api/v1/public/messages', (string) $request->getUri());
        self::assertSame('Bearer walk_live_keyid.secret', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('walinko-php-', $request->getHeaderLine('Idempotency-Key'));

        $payload = $this->decode($request);
        self::assertSame(1, $payload['device_id']);
        self::assertSame(12, $payload['template_id']);
        self::assertSame('+8801617738431', $payload['phone']);
        self::assertFalse($payload['async']);
        self::assertSame(['name' => 'Kazi'], $payload['variables']);

        self::assertInstanceOf(SyncResult::class, $result);
        self::assertSame('tx_abc', $result->trackingId);
        self::assertTrue($result->isSent());
        self::assertInstanceOf(\DateTimeImmutable::class, $result->sentAt);
        self::assertSame('wamid.HBgL...', $result->waMessageId);
        self::assertSame('req_abc123', $result->requestId);
        self::assertFalse($result->idempotentReplayed);

        self::assertSame('req_abc123', $this->client->lastRequestId());
        $rateLimit = $this->client->lastRateLimit();
        self::assertNotNull($rateLimit);
        self::assertSame(30, $rateLimit->limit);
        self::assertSame(29, $rateLimit->remaining);
    }

    public function testSendUsesCallerProvidedIdempotencyKey(): void
    {
        $this->http->pushResponse(200, self::successEnvelope(self::syncData()), self::commonHeaders());

        $this->client->messages->send([
            'device_id'       => 1,
            'template_id'     => 12,
            'phone'           => '+8801617738431',
            'idempotency_key' => 'order-123',
        ]);

        self::assertSame('order-123', $this->http->requests[0]->getHeaderLine('Idempotency-Key'));
    }

    public function testSendReportsIdempotentReplayedFlag(): void
    {
        $this->http->pushResponse(
            200,
            self::successEnvelope(self::syncData()),
            self::commonHeaders(['Idempotent-Replayed' => 'true']),
        );

        $result = $this->client->messages->send([
            'device_id'   => 1,
            'template_id' => 12,
            'phone'       => '+8801617738431',
        ]);

        self::assertTrue($result->idempotentReplayed);
    }

    public function testSendStringifiesVariablesAndHandlesNulls(): void
    {
        $this->http->pushResponse(200, self::successEnvelope(self::syncData()), self::commonHeaders());

        $this->client->messages->send([
            'device_id'   => 1,
            'template_id' => 12,
            'phone'       => '+8801617738431',
            'variables'   => ['age' => 7, 'note' => null],
        ]);

        $payload = $this->decode($this->http->requests[0]);
        self::assertSame(['age' => '7', 'note' => ''], $payload['variables']);
    }

    public function testSendOmitsVariantIndexWhenNotProvided(): void
    {
        $this->http->pushResponse(200, self::successEnvelope(self::syncData()), self::commonHeaders());

        $this->client->messages->send([
            'device_id'   => 1,
            'template_id' => 12,
            'phone'       => '+8801617738431',
        ]);

        $payload = $this->decode($this->http->requests[0]);
        self::assertArrayNotHasKey('variant_index', $payload);
    }

    public function testSendRejectsEmptyPhoneBeforeAnyHttp(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->messages->send([
            'device_id'   => 1,
            'template_id' => 12,
            'phone'       => '',
        ]);
    }

    // ---- async enqueue ---------------------------------------------

    public function testEnqueueSetsAsyncTrueAndReturnsAsyncJob(): void
    {
        $this->http->pushResponse(202, self::successEnvelope([
            'tracking_id' => 'tx_async',
            'status'      => 'queued',
            'status_url'  => 'https://api.example.com/api/v1/public/messages/tx_async',
        ]), self::commonHeaders());

        $job = $this->client->messages->enqueue([
            'device_id'   => 1,
            'template_id' => 12,
            'phone'       => '+8801617738431',
        ]);

        $payload = $this->decode($this->http->requests[0]);
        self::assertTrue($payload['async']);

        self::assertInstanceOf(AsyncJob::class, $job);
        self::assertSame('tx_async', $job->trackingId);
        self::assertTrue($job->isQueued());
        self::assertNotNull($job->statusUrl);
        self::assertStringContainsString('tx_async', $job->statusUrl);
    }

    // ---- fetch / waitUntilDone -------------------------------------

    public function testFetchReturnsMessageStatus(): void
    {
        $this->http->pushResponse(200, self::successEnvelope([
            'tracking_id'   => 'tx_abc',
            'status'        => 'sent',
            'device_id'     => 1,
            'template_id'   => 12,
            'variant_index' => 0,
            'phone'         => '+8801617738431',
            'wa_message_id' => 'wamid.HBgL...',
            'error_code'    => null,
            'error_message' => null,
            'sent_at'       => '2026-05-01T10:01:00Z',
            'created_at'    => '2026-05-01T10:00:55Z',
        ]), self::commonHeaders());

        $status = $this->client->messages->fetch('tx_abc');
        $request = $this->http->requests[0];

        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.example.com/api/v1/public/messages/tx_abc', (string) $request->getUri());

        self::assertInstanceOf(MessageStatus::class, $status);
        self::assertTrue($status->isSent());
        self::assertTrue($status->isDone());
        self::assertInstanceOf(\DateTimeImmutable::class, $status->createdAt);
        self::assertSame('req_abc123', $status->requestId);
    }

    public function testFetchRejectsEmptyTrackingId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client->messages->fetch('');
    }

    public function testWaitUntilDoneReturnsFirstTerminalStatus(): void
    {
        // queued → sending → sent
        $this->http->pushResponse(200, self::successEnvelope(['tracking_id' => 'tx_abc', 'status' => 'queued']), self::commonHeaders());
        $this->http->pushResponse(200, self::successEnvelope(['tracking_id' => 'tx_abc', 'status' => 'sending']), self::commonHeaders());
        $this->http->pushResponse(200, self::successEnvelope([
            'tracking_id' => 'tx_abc',
            'status'      => 'sent',
            'sent_at'     => '2026-05-01T10:00:00Z',
        ]), self::commonHeaders());

        $messages = $this->nonBlockingMessages();
        $status = $messages->waitUntilDone('tx_abc', timeout: 30, interval: 0.01);
        self::assertTrue($status->isSent());
        self::assertCount(3, $this->http->requests);
    }

    public function testWaitUntilDoneRaisesTimeoutException(): void
    {
        $this->http->pushResponse(200, self::successEnvelope(['tracking_id' => 'tx_abc', 'status' => 'queued']), self::commonHeaders());

        $messages = $this->nonBlockingMessages();
        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessageMatches('/tx_abc/');
        $messages->waitUntilDone('tx_abc', timeout: 1, interval: 5);
    }

    // ---- error mapping per error_code ------------------------------

    /**
     * @return array<string, array{int, string, class-string<\Throwable>}>
     */
    public static function errorCases(): array
    {
        return [
            '401 invalid_api_key'      => [401, 'invalid_api_key',       AuthenticationException::class],
            '400 bad_request'          => [400, 'bad_request',           BadRequestException::class],
            '400 variant_out_of_range' => [400, 'variant_out_of_range',  BadRequestException::class],
            '403 tenant_suspended'     => [403, 'tenant_suspended',      TenantSuspendedException::class],
            '403 quota_exceeded'       => [403, 'quota_exceeded',        QuotaExceededException::class],
            '404 device_not_found'     => [404, 'device_not_found',      NotFoundException::class],
            '404 template_not_found'   => [404, 'template_not_found',    NotFoundException::class],
            '404 delivery_not_found'   => [404, 'delivery_not_found',    NotFoundException::class],
            '409 device_disconnected'  => [409, 'device_disconnected',   DeviceDisconnectedException::class],
            '409 idempotency_conflict' => [409, 'idempotency_conflict',  IdempotencyConflictException::class],
            '422 phone_not_on_wa'      => [422, 'phone_not_on_whatsapp', ValidationException::class],
            '422 validation_error'     => [422, 'validation_error',      ValidationException::class],
            '500 send_failed'          => [500, 'send_failed',           ServerException::class],
            '500 queue_failed'         => [500, 'queue_failed',          ServerException::class],
            '504 send_timeout'         => [504, 'send_timeout',          TimeoutException::class],
        ];
    }

    /**
     * @dataProvider errorCases
     * @param class-string<\Throwable> $expectedClass
     */
    public function testErrorMapping(int $status, string $code, string $expectedClass): void
    {
        $this->http->pushResponse($status, self::errorEnvelope("oh no: {$code}", $code), self::commonHeaders());

        try {
            $this->client->messages->send([
                'device_id'   => 1,
                'template_id' => 1,
                'phone'       => '+8801617738431',
            ]);
            self::fail('Expected ' . $expectedClass);
        } catch (\Throwable $e) {
            self::assertInstanceOf($expectedClass, $e);
            self::assertSame($status, $e->httpStatus); /** @phpstan-ignore-line */
            self::assertSame($code, $e->errorCode); /** @phpstan-ignore-line */
            self::assertSame('req_abc123', $e->requestId); /** @phpstan-ignore-line */
        }
    }

    public function testValidationErrorParsesFieldsDetail(): void
    {
        $this->http->pushResponse(
            422,
            self::errorEnvelope(
                'Validation failed',
                'validation_error',
                ['fields' => ['phone' => ['must not be empty']]],
            ),
            self::commonHeaders(),
        );

        try {
            $this->client->messages->send([
                'device_id'   => 1,
                'template_id' => 1,
                'phone'       => '+8801617738431',
            ]);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['phone' => ['must not be empty']], $e->fields());
        }
    }

    public function testRateLimitErrorParsesRetryAfter(): void
    {
        $this->http->pushResponse(
            429,
            self::errorEnvelope('rate limited', 'rate_limited'),
            self::commonHeaders(['Retry-After' => '5']),
        );

        try {
            $this->client->messages->send([
                'device_id'   => 1,
                'template_id' => 1,
                'phone'       => '+8801617738431',
            ]);
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(5, $e->retryAfter);
        }
    }

    // ---- retry policy ----------------------------------------------

    public function testRetries5xxUpToMaxRetriesWithSameIdempotencyKey(): void
    {
        $client = $this->buildClient(maxRetries: 2);

        $this->http->pushResponse(503, self::errorEnvelope('boom', 'send_failed'), self::commonHeaders());
        $this->http->pushResponse(503, self::errorEnvelope('boom', 'send_failed'), self::commonHeaders());
        $this->http->pushResponse(200, self::successEnvelope(self::syncData()), self::commonHeaders());

        $result = $client->messages->send([
            'device_id'   => 1,
            'template_id' => 1,
            'phone'       => '+8801617738431',
        ]);

        self::assertSame('tx_abc', $result->trackingId);
        self::assertCount(3, $this->http->requests);

        $keys = array_map(
            static fn (RequestInterface $r) => $r->getHeaderLine('Idempotency-Key'),
            $this->http->requests,
        );
        self::assertCount(1, array_unique($keys), 'Idempotency-Key must be reused across retries');
    }

    public function testSurfacesLastErrorAfterRetriesExhausted(): void
    {
        $client = $this->buildClient(maxRetries: 2);
        for ($i = 0; $i < 3; $i++) {
            $this->http->pushResponse(503, self::errorEnvelope('boom', 'send_failed'), self::commonHeaders());
        }

        $this->expectException(ServerException::class);
        $client->messages->send([
            'device_id'   => 1,
            'template_id' => 1,
            'phone'       => '+8801617738431',
        ]);
    }

    public function testDoesNotRetry4xxOtherThan429(): void
    {
        $client = $this->buildClient(maxRetries: 2);
        $this->http->pushResponse(400, self::errorEnvelope('bad', 'bad_request'), self::commonHeaders());

        try {
            $client->messages->send([
                'device_id'   => 1,
                'template_id' => 1,
                'phone'       => '+8801617738431',
            ]);
            self::fail('Expected BadRequestException');
        } catch (BadRequestException) {
            // expected
        }

        self::assertCount(1, $this->http->requests, 'Must not retry 4xx');
    }

    public function testRetriesOnConnectionErrors(): void
    {
        $client = $this->buildClient(maxRetries: 2);

        $factory = new Psr17Factory();
        $this->http->pushFactory(fn (RequestInterface $req) => new NetworkException('reset', $req));
        $this->http->pushResponse(200, self::successEnvelope(self::syncData()), self::commonHeaders());

        $result = $client->messages->send([
            'device_id'   => 1,
            'template_id' => 1,
            'phone'       => '+8801617738431',
        ]);
        self::assertSame('tx_abc', $result->trackingId);
        unset($factory);
    }

    public function testWrapsExhaustedConnectionErrorsAsConnectionException(): void
    {
        $client = $this->buildClient(maxRetries: 1);
        $this->http->pushFactory(fn (RequestInterface $req) => new NetworkException('reset', $req));
        $this->http->pushFactory(fn (RequestInterface $req) => new NetworkException('reset', $req));

        $this->expectException(ConnectionException::class);
        $client->messages->send([
            'device_id'   => 1,
            'template_id' => 1,
            'phone'       => '+8801617738431',
        ]);
    }

    public function testRetries429AndSleepsForRetryAfterCapped(): void
    {
        $client = $this->buildClient(maxRetries: 1);
        $sleeps = [];
        $client->transport()->setSleeper(static function (float $sec) use (&$sleeps): void {
            $sleeps[] = $sec;
        });

        $this->http->pushResponse(
            429,
            self::errorEnvelope('limited', 'rate_limited'),
            self::commonHeaders(['Retry-After' => '3']),
        );
        $this->http->pushResponse(200, self::successEnvelope(self::syncData()), self::commonHeaders());

        $client->messages->send([
            'device_id'   => 1,
            'template_id' => 1,
            'phone'       => '+8801617738431',
        ]);

        self::assertSame([3.0], $sleeps);
    }

    // ---- internals --------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function decode(RequestInterface $request): array
    {
        return json_decode((string) $request->getBody(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the client's `Messages` resource with the sleep stub
     * replaced by a no-op so `waitUntilDone()` doesn't actually wait
     * between polls.
     */
    private function nonBlockingMessages(): Messages
    {
        $this->client->messages->setSleeper(static fn () => null);

        return $this->client->messages;
    }
}
