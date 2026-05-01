<?php

declare(strict_types=1);

// Run with: WALINKO_API_KEY=walk_live_... php examples/php/send_async_and_poll.php

require __DIR__ . '/../../sdks/php/vendor/autoload.php';

use Walinko\Client;

$client = new Client(['api_key' => getenv('WALINKO_API_KEY')]);

$job = $client->messages->enqueue([
    'device_id'   => 1,
    'template_id' => 12,
    'phone'       => '+8801617738431',
    'variables'   => ['name' => 'Kazi', 'dist' => 'Dhaka'],
]);

echo "queued: {$job->tracking_id} (poll {$job->status_url})\n";

$final = $client->messages->waitUntilDone($job->tracking_id, timeout: 60, interval: 2);

if ($final->status === 'sent') {
    echo "delivered (wa_message_id={$final->wa_message_id})\n";
} else {
    fwrite(STDERR, "failed: {$final->error_code} - {$final->error_message}\n");
}
