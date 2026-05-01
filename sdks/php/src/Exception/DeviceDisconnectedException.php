<?php

declare(strict_types=1);

namespace Walinko\Exception;

/**
 * 409 + `error_code: device_disconnected` — device session is not
 * connected. Reconnect from the dashboard, then retry.
 */
class DeviceDisconnectedException extends ConflictException
{
}
