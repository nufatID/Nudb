<?php
require 'vendor/autoload.php';

use WebSocket\Client;

$client = new Client("wss://nudb.nufat.id");

$client->send(json_encode([
    "type" => "set",
    "path" => 'test/' . uniqid(),
    "data" => ["name" => "Unit Test ". uniqid() ]
]));

sleep(1); // Tunggu proses simpan selesai

$client->send(json_encode([
    "type" => "get",
    "path" => "test"
]));

$response = $client->receive();
$data = json_decode($response, true);

print_r($data);