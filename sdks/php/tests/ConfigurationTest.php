<?php

declare(strict_types=1);

namespace Walinko\Tests;

use PHPUnit\Framework\TestCase;
use Walinko\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testAppliesSensibleDefaults(): void
    {
        $cfg = new Configuration(['api_key' => 'walk_live_x.y']);
        self::assertSame('https://api.walinko.com', $cfg->baseUrl);
        self::assertSame(30, $cfg->timeout);
        self::assertSame(2, $cfg->maxRetries);
        self::assertNull($cfg->httpClient);
        self::assertNull($cfg->logger);
    }

    public function testRejectsEmptyApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Configuration(['api_key' => '']);
    }

    public function testRejectsEmptyBaseUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Configuration(['api_key' => 'walk_live_x.y', 'base_url' => '']);
    }

    public function testRejectsNonPositiveTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Configuration(['api_key' => 'walk_live_x.y', 'timeout' => 0]);
    }

    public function testRejectsNegativeMaxRetries(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Configuration(['api_key' => 'walk_live_x.y', 'max_retries' => -1]);
    }
}
