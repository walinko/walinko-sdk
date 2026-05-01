# walinko/sdk (PHP)

Official PHP client for the [Walinko](https://walinko.com) public API.

> **Status:** scaffolding. The first usable release is `0.1.0` — the stub in
> `src/Client.php` is wired up just enough that `composer require walinko/sdk`
> followed by `new Walinko\Client([...])` succeeds.

## Install

```bash
composer require walinko/sdk
```

The SDK targets PSR-18 for HTTP, PSR-17 for factories, and PSR-3 for
logging. Any compatible HTTP client works; if you don't pass one in, the
SDK auto-discovers via [`php-http/discovery`](https://docs.php-http.org/en/latest/discovery.html).

## Quick start (target API for `0.1.0`)

```php
use Walinko\Client;

$client = new Client([
    'api_key'     => getenv('WALINKO_API_KEY'),
    'base_url'    => 'https://api.walinko.com', // optional
    'timeout'     => 30,                         // optional, seconds
    'max_retries' => 2,                          // optional
    // 'http_client' => $psr18Client,            // optional
    // 'logger'      => $psrLogger,              // optional
]);

// Sync send.
$result = $client->messages->send([
    'device_id'     => 1,
    'template_id'   => 12,
    'variant_index' => 0,                                          // optional
    'phone'         => '+8801617738431',
    'variables'     => ['name' => 'Kazi', 'dist' => 'Dhaka'],
]);

echo $result->tracking_id;     // tx_...
echo $result->wa_message_id;   // 3EB0...
echo $result->status;          // "sent"

// Async enqueue + poll.
$job = $client->messages->enqueue([
    'device_id'   => 1,
    'template_id' => 12,
    'phone'       => '+8801617738431',
    'variables'   => ['name' => 'Kazi', 'dist' => 'Dhaka'],
]);

$final = $client->messages->waitUntilDone($job->tracking_id, timeout: 60);
echo $final->status;  // "sent" | "failed"
```

## Errors

Every exception extends `Walinko\Exception\WalinkoException`. See
[`docs/error-codes.md`](../../docs/error-codes.md) for the full mapping.

```php
use Walinko\Exception;

try {
    $client->messages->send([...]);
} catch (Exception\RateLimitException $e) {
    sleep($e->retryAfter);
    // retry
} catch (Exception\ValidationException $e) {
    $logger->warning('validation', $e->fields);
} catch (Exception\DeviceDisconnectedException) {
    // tell the user to reconnect their device
} catch (Exception\WalinkoException $e) {
    $logger->error('Walinko send failed', ['error' => $e->getMessage()]);
}
```

## Idempotency

The SDK auto-generates a UUID `Idempotency-Key` for every `send` / `enqueue`
call so retries are safe end-to-end. Pass `idempotency_key` in the args to
set your own (e.g. tying a send to your domain object).

## Rate limits

The server enforces 30 req/min/key. The SDK exposes the latest known
window state via `$client->lastRateLimit()`.

## Development

```bash
cd sdks/php
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run
```

## License

[MIT](../../LICENSE)
