# walinko/sdk (PHP)

Official PHP client for the [Walinko](https://walinko.com) public API.

Send transactional WhatsApp messages from PHP with idempotent retries,
structured errors, and a tiny dependency footprint (PSR-18 HTTP).

* PHP 8.1+
* PSR-18 HTTP, PSR-17 factories, PSR-3 logging — bring your own
  Guzzle/Symfony/whatever, or let `php-http/discovery` find one
* MIT licensed

## Install

```bash
composer require walinko/sdk
```

If your project doesn't already pull in a PSR-18 client (e.g. via
Guzzle, Symfony HTTP Client, or `php-http/curl-client`), add one too:

```bash
composer require symfony/http-client nyholm/psr7
```

## Quick start

```php
use Walinko\Client;

$client = new Client([
    'api_key'     => getenv('WALINKO_API_KEY'),
    'base_url'    => 'https://api.walinko.com', // optional
    'timeout'     => 30,                         // optional, seconds (used by waitUntilDone)
    'max_retries' => 2,                          // optional
    // 'http_client'     => $psr18Client,        // optional
    // 'request_factory' => $psr17Factory,       // optional
    // 'stream_factory'  => $psr17Factory,       // optional
    // 'logger'          => $psrLogger,          // optional
]);

// Sync send — blocks until the message is delivered (or 504 timeout).
$result = $client->messages->send([
    'device_id'     => 1,
    'template_id'   => 12,
    'variant_index' => 0,                                       // optional, null = primary
    'phone'         => '+8801617738431',
    'variables'     => ['name' => 'Kazi', 'dist' => 'Dhaka'],
]);

echo $result->trackingId;    // tx_...
echo $result->waMessageId;   // 3EB0...
echo $result->status;        // "sent"

// Async enqueue + poll.
$job = $client->messages->enqueue([
    'device_id'   => 1,
    'template_id' => 12,
    'phone'       => '+8801617738431',
    'variables'   => ['name' => 'Kazi', 'dist' => 'Dhaka'],
]);

$final = $client->messages->waitUntilDone($job->trackingId, timeout: 60);
echo $final->status;  // "sent" | "failed"
```

## Looking up a delivery

```php
$status = $client->messages->fetch('tx_767fd2faca0f4037b2a2bbcb91e5735f');

$status->isSent();        // bool
$status->errorCode;       // null if sent, e.g. "phone_not_on_whatsapp" on failure
$status->waMessageId;     // WhatsApp's id, set on success
$status->createdAt;       // DateTimeImmutable
$status->sentAt;          // DateTimeImmutable, null while pending
```

## Errors

Every exception extends `Walinko\Exception\WalinkoException`. See
[`docs/error-codes.md`](../../docs/error-codes.md) for the full mapping.

```php
use Walinko\Exception;

try {
    $client->messages->send([...]);
} catch (Exception\RateLimitException $e) {
    sleep($e->retryAfter ?? 1);
    // retry
} catch (Exception\ValidationException $e) {
    $logger->warning('validation failed', ['fields' => $e->fields()]);
} catch (Exception\DeviceDisconnectedException) {
    // tell the user to reconnect their device from the dashboard
} catch (Exception\WalinkoException $e) {
    $logger->error('Walinko send failed', ['error' => $e->getMessage()]);
}
```

## Retries

The SDK auto-retries idempotently on:

| Trigger              | Behaviour                                       |
| -------------------- | ----------------------------------------------- |
| Network errors       | Exponential backoff with jitter                 |
| HTTP 429             | Honours `Retry-After` (capped at 60s)           |
| HTTP 500/502/503/504 | Exponential backoff with jitter                 |

`max_retries` (default 2) controls how many additional attempts are made.
4xx responses (other than 429) are surfaced immediately — the request is
malformed or the server has rejected it on application grounds, and no
amount of retrying will help.

## Idempotency

The SDK auto-generates a UUID `Idempotency-Key` for every `send` /
`enqueue` call so retries are safe end-to-end. Pass `idempotency_key`
in the args to set your own (e.g. tying a send to your domain object).
The same key is reused on every retry within a single call.

## Rate limits

The server enforces 30 req/min/key (sliding window). The SDK exposes
the latest known window state via:

```php
$client->lastRateLimit();   // ?Walinko\Result\RateLimitSnapshot
$client->lastRequestId();   // ?string — handy for support tickets
```

## Development

```bash
cd sdks/php
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
```

## License

[MIT](../../LICENSE)
