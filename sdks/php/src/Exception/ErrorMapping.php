<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * Maps an HTTP status + server `error_code` to one of the typed
 * exception classes. Public so users can introspect the mapping in
 * tests if they want.
 */
final class ErrorMapping
{
    /** @var array<int, class-string<ApiException>> */
    private const BY_HTTP_STATUS = [
        400 => BadRequestException::class,
        401 => AuthenticationException::class,
        403 => ForbiddenException::class,
        404 => NotFoundException::class,
        409 => ConflictException::class,
        422 => ValidationException::class,
        429 => RateLimitException::class,
        504 => TimeoutException::class,
    ];

    /** @var array<string, class-string<ApiException>> */
    private const BY_ERROR_CODE = [
        'tenant_suspended'     => TenantSuspendedException::class,
        'quota_exceeded'       => QuotaExceededException::class,
        'device_disconnected'  => DeviceDisconnectedException::class,
        'idempotency_conflict' => IdempotencyConflictException::class,
    ];

    /**
     * @return class-string<ApiException>
     */
    public static function for(int $httpStatus, ?string $errorCode = null): string
    {
        if ($errorCode !== null && isset(self::BY_ERROR_CODE[$errorCode])) {
            return self::BY_ERROR_CODE[$errorCode];
        }

        if (isset(self::BY_HTTP_STATUS[$httpStatus])) {
            return self::BY_HTTP_STATUS[$httpStatus];
        }

        if ($httpStatus >= 500 && $httpStatus < 600) {
            return ServerException::class;
        }

        return ApiException::class;
    }
}
