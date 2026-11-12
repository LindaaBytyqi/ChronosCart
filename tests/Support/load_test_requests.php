<?php

$totalRequests = (int) ($argv[1] ?? 20);
$eventId = (int) ($argv[2] ?? 4);
$concurrency = min(100, $totalRequests);

$url = "http://127.0.0.1/api/flash-sales/{$eventId}/reserve";

$results = [];
$errors = 0;

echo "Starting {$totalRequests} requests...\n";
echo "Event ID: {$eventId}\n";
echo "Concurrency: {$concurrency}\n\n";

for ($offset = 0; $offset < $totalRequests; $offset += $concurrency) {
    $batchSize = min($concurrency, $totalRequests - $offset);
    $multiHandle = curl_multi_init();
    $handles = [];

    for ($i = 0; $i < $batchSize; $i++) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['quantity' => 1]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_NOSIGNAL => true,
        ]);

        curl_multi_add_handle($multiHandle, $ch);
        $handles[] = $ch;
    }

    $running = null;

    do {
        $status = curl_multi_exec($multiHandle, $running);

        if ($running > 0) {
            curl_multi_select($multiHandle, 1.0);
        }
    } while ($running > 0);

    foreach ($handles as $ch) {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response = curl_multi_getcontent($ch);

        if ($httpCode >= 500) {
            echo "\nHTTP {$httpCode} ERROR:\n";
            echo substr(strip_tags($response), 0, 1500) . "\n";
        }

        if (curl_errno($ch)) {
            $errors++;
        } else {
            $results[$httpCode] = ($results[$httpCode] ?? 0) + 1;
        }

        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }

    curl_multi_close($multiHandle);
}

echo "========== RESULTS ==========\n";
echo "Total requests: {$totalRequests}\n";

foreach ($results as $code => $count) {
    echo "HTTP {$code}: {$count}\n";
}

echo "cURL errors: {$errors}\n";
echo "=============================\n";
