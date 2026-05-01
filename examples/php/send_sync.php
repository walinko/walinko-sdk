<?php

declare(strict_types=1);

// Run with: WALINKO_API_KEY=walk_live_... php examples/php/send_sync.php
//
// Synchronous send: blocks until the WhatsApp gateway acknowledges
// delivery (or the server's 15s timeout fires).

require __DIR__ . '/../../sdks/php/vendor/autoload.php';

use Walinko\Client;

$apiKey = getenv('WALINKO_API_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(\STDERR, "Set WALINKO_API_KEY first.\n");
    exit(1);
}

$client = new Client(['api_key' => $apiKey]);

$result = $client->messages->send([
    'device_id'     => 1,
    'template_id'   => 12,
    'variant_index' => 0,
    'phone'         => '+8801617738431',
    'variables'     => ['name' => 'Kazi', 'dist' => 'Dhaka'],
]);

echo "tracking_id:   {$result->trackingId}\n";
echo "wa_message_id: {$result->waMessageId}\n";
echo "status:        {$result->status}\n";
