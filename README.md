# engine.io-php-client
php communicate with engine.io server.

## minimum client.

```php
require_once __DIR__.'/vendor/autoload.php';
use eio\Client;

$client = new Client('ws://localhost:9527');
$client->send(json_encode([
	'channel'=>'all',
	'message'=>'helloworld'
]));
```

## debug minimum.

```php
require_once __DIR__.'/vendor/autoload.php';
use eio\Client;

$debug = function ($message, $time){
	printf('%s: %f'.PHP_EOL, $message, $time);
};
$client = new Client($uri, [], $debug);
```
