<?php

declare(strict_types=1);

// Run with: WALINKO_API_KEY=walk_live_... php examples/php/send_sync.php
//
// This example demonstrates the *target* API for Walinko\Client::messages.
// It will start working when the PHP SDK reaches 0.1.0.

require __DIR__ . '/../../sdks/php/vendor/autoload.php';

use Walinko\Client;

$client = new Client(['api_key' => getenv('WALINKO_API_KEY')]);

$result = $client->messages->send([
    'device_id'     => 1,
    'template_id'   => 12,
    'variant_index' => 0,
    'phone'         => '+8801617738431',
    'variables'     => ['name' => 'Kazi', 'dist' => 'Dhaka'],
]);

echo "tracking_id:   {$result->tracking_id}\n";
echo "wa_message_id: {$result->wa_message_id}\n";
echo "status:        {$result->status}\n";
