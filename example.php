<?php

require_once __DIR__.'/vendor/autoload.php';

use eio\Client;

$debug = function ($message, $time){
	printf('%s: %f'.PHP_EOL, $message, $time);
};
$config=json_decode(file_get_contents(__DIR__.'/../env.json'), true);

$protocol = 'ws';
$host = 'localhost';
$port = 9527;
$uri = $protocol . '://' . $host . ':' . $port;
$client = new Client($uri, [], $debug);

$timestamp=time();
$client->send(json_encode([
	'isAdmin'=>true,
	'token'=>md5($timestamp.$config['SOCKET_SALT']),
	'timestamp'=>$timestamp
]));
$client->send(json_encode([
	'channel'=>'all',
	'message'=>'helloworld'
]));
