<?php

declare(strict_types=1);

namespace Walinko\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Walinko\Client;
use Walinko\Resource\Messages;
use Walinko\Tests\Support\MockHttpClient;

final class ClientTest extends TestCase
{
    public function testRejectsEmptyApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/api_key/');
        new Client(['api_key' => '']);
    }

    public function testRejectsMissingApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client([]); // @phpstan-ignore-line — intentionally missing required key
    }

    public function testStripsTrailingSlashFromBaseUrl(): void
    {
        $client = $this->buildClient(['base_url' => 'https://api.example.com/']);
        self::assertSame('https://api.example.com', $client->config->baseUrl);
    }

    public function testExposesMessagesResource(): void
    {
        $client = $this->buildClient();
        self::assertInstanceOf(Messages::class, $client->messages);
    }

    public function testReturnsNullMetadataBeforeAnyCall(): void
    {
        $client = $this->buildClient();
        self::assertNull($client->lastRateLimit());
        self::assertNull($client->lastRequestId());
    }

    public function testRejectsNonsenseMaxRetries(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/max_retries/');
        $this->buildClient(['max_retries' => -1]);
    }

    public function testRejectsNonsenseTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/timeout/');
        $this->buildClient(['timeout' => 0]);
    }

    public function testVersionIsExposed(): void
    {
        self::assertSame('0.1.0', Client::VERSION);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function buildClient(array $extra = []): Client
    {
        $factory = new Psr17Factory();

        return new Client(array_merge([
            'api_key'         => 'walk_live_x.y',
            'http_client'     => new MockHttpClient(),
            'request_factory' => $factory,
            'stream_factory'  => $factory,
        ], $extra));
    }
}
