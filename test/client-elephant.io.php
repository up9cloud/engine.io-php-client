<?php
// from https://github.com/Wisembly/elephant.io/blob/master/example/socket.io/2.x/emitter-with-headers/client.php
require __DIR__ . '/../vendor/autoload.php';

use ElephantIO\Client;
use ElephantIO\Engine\SocketIO\Version2X;

$config = json_decode(file_get_contents(__DIR__.'/config.json'), true);
$uri = sprintf('http://%s:%s/engine.io', $config['host'], $config['port']);

$client = new Client(new Version2X($uri, [
    'headers' => [
        'X-My-Header: websocket rocks',
        'Authorization: Bearer 12b3c4d5e6f7g8h9i'
    ]
]));
$client->initialize();
$client->emit('broadcast', ['foo' => 'bar']);
$client->close();
