<?php
$url = 'http://127.0.0.1:8000/api/uploads/initialize';
$data = [
    'filename' => 'test.jpg',
    'mime_type' => 'image/jpeg',
    'total_size' => 100000,
    'total_chunks' => 1,
    'checksum' => str_repeat('a', 32)
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";