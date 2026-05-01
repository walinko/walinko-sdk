<?php

declare(strict_types=1);

namespace Walinko\Tests;

use PHPUnit\Framework\TestCase;
use Walinko\Client;

final class ClientTest extends TestCase
{
    public function testRequiresApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client(['api_key' => '']);
    }

    public function testAcceptsBaseUrlOverride(): void
    {
        $client = new Client([
            'api_key'  => 'walk_test_x.y',
            'base_url' => 'https://api.example.com',
        ]);
        self::assertInstanceOf(Client::class, $client);
    }

    public function testMessagesResourceIsStubbed(): void
    {
        $client = new Client(['api_key' => 'walk_test_x.y']);
        $this->expectException(\LogicException::class);
        $client->messages();
    }

    public function testVersionIsExposed(): void
    {
        self::assertIsString(Client::VERSION);
    }
}
