# engine.io-php-client

The php client for [engine.io](https://github.com/socketio/engine.io).

| version | engine.io version |
| ------- | ----------------- |
| v1      | v1                |
| v2      | v2, v3            |

## Installation

```bash
composer require up9cloud/engine.io-php-client
```

## Usage

```php
require_once __DIR__.'/vendor/autoload.php';
use eio\Client;

$client = new Client('ws://localhost:9527');
$client->send(json_encode([
	'channel'=>'all',
	'message'=>'helloworld'
]));
```

### Advanced

```php
require_once __DIR__.'/vendor/autoload.php';
use eio\Client;

$options = [
	// TODO: see src/Transport.php
];
// builtin debug method
$debug = true;

// custom debug method
$debug = function ($time, $messages){
	printf('[%.4f]: %s' . PHP_EOL, $time, $messages);
};
$client = new Client($uri, $options, $debug);
```
