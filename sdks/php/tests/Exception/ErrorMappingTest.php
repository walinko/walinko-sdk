<?php

declare(strict_types=1);

namespace Walinko\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Walinko\Exception\ApiException;
use Walinko\Exception\AuthenticationException;
use Walinko\Exception\BadRequestException;
use Walinko\Exception\ConflictException;
use Walinko\Exception\DeviceDisconnectedException;
use Walinko\Exception\ErrorMapping;
use Walinko\Exception\ForbiddenException;
use Walinko\Exception\IdempotencyConflictException;
use Walinko\Exception\NotFoundException;
use Walinko\Exception\QuotaExceededException;
use Walinko\Exception\RateLimitException;
use Walinko\Exception\ServerException;
use Walinko\Exception\TenantSuspendedException;
use Walinko\Exception\TimeoutException;
use Walinko\Exception\ValidationException;

final class ErrorMappingTest extends TestCase
{
    /**
     * @return array<string, array{int, string|null, class-string<ApiException>}>
     */
    public static function statusToClassProvider(): array
    {
        return [
            '400'                  => [400, null,                  BadRequestException::class],
            '400 + variant_oor'    => [400, 'variant_out_of_range', BadRequestException::class],
            '401'                  => [401, null,                  AuthenticationException::class],
            '403 plain'            => [403, null,                  ForbiddenException::class],
            '403 tenant_suspended' => [403, 'tenant_suspended',    TenantSuspendedException::class],
            '403 quota_exceeded'   => [403, 'quota_exceeded',      QuotaExceededException::class],
            '404'                  => [404, null,                  NotFoundException::class],
            '404 device_not_found' => [404, 'device_not_found',    NotFoundException::class],
            '409 plain'            => [409, null,                  ConflictException::class],
            '409 device_disc'      => [409, 'device_disconnected', DeviceDisconnectedException::class],
            '409 idem_conflict'    => [409, 'idempotency_conflict', IdempotencyConflictException::class],
            '422'                  => [422, null,                  ValidationException::class],
            '422 phone_not_on_wa'  => [422, 'phone_not_on_whatsapp', ValidationException::class],
            '429'                  => [429, 'rate_limited',        RateLimitException::class],
            '500'                  => [500, 'send_failed',         ServerException::class],
            '503'                  => [503, null,                  ServerException::class],
            '504'                  => [504, 'send_timeout',        TimeoutException::class],
            'unknown 418'          => [418, null,                  ApiException::class],
        ];
    }

    /**
     * @dataProvider statusToClassProvider
     * @param class-string<ApiException> $expected
     */
    public function testMapping(int $status, ?string $errorCode, string $expected): void
    {
        self::assertSame($expected, ErrorMapping::for($status, $errorCode));
    }

    public function testValidationExceptionExposesFields(): void
    {
        $err = new ValidationException(
            'Validation failed',
            422,
            'validation_error',
            'req_abc',
            ['fields' => ['phone' => ['must not be empty']]],
        );
        self::assertSame(['phone' => ['must not be empty']], $err->fields());
    }

    public function testValidationExceptionFieldsDefaultsToEmptyMap(): void
    {
        $err = new ValidationException('boom', 422);
        self::assertSame([], $err->fields());
    }

    public function testRateLimitExceptionCarriesRetryAfter(): void
    {
        $err = new RateLimitException(
            'limited',
            429,
            'rate_limited',
            null,
            [],
            null,
            12,
        );
        self::assertSame(12, $err->retryAfter);
    }
}
