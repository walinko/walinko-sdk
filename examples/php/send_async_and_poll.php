<?php

declare(strict_types=1);

// Run with: WALINKO_API_KEY=walk_live_... php examples/php/send_async_and_poll.php

require __DIR__ . '/../../sdks/php/vendor/autoload.php';

use Walinko\Client;

$apiKey = getenv('WALINKO_API_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(\STDERR, "Set WALINKO_API_KEY first.\n");
    exit(1);
}

$client = new Client(['api_key' => $apiKey]);

$job = $client->messages->enqueue([
    'device_id'   => 1,
    'template_id' => 12,
    'phone'       => '+8801617738431',
    'variables'   => ['name' => 'Kazi', 'dist' => 'Dhaka'],
]);

echo "queued: {$job->trackingId} (poll {$job->statusUrl})\n";

$final = $client->messages->waitUntilDone($job->trackingId, timeout: 60, interval: 2);

if ($final->isSent()) {
    echo "delivered (wa_message_id={$final->waMessageId})\n";
} else {
    fwrite(\STDERR, "failed: {$final->errorCode} - {$final->errorMessage}\n");
}
