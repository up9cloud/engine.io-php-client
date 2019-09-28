<?php

namespace eio;

/**
 * Protocol:
 * https://github.com/socketio/engine.io-protocol
 *
 * TODO: support other official transports:
 *       polling
 *       - jsonp
 *       - xhr
 */
Class Transport implements TraspoterInterface{
	/**
	 * for debug
	 * @var integer second
	 */
	private $start_time = 0;
	/**
	 * callback for showing debug message.
	 * @var clousre
	 */
	private $debug = false;
	private $debug_callback = null;
	/**
	 * socket resource.
	 * @var resource
	 */
	private $socket = null;
	private $fp_errno = null;
	private $fp_errstr = null;
	private $options = [
		/**
		 * connection default settings.
		 */
		'dsl' => null,
		'host' => 'localhost',
		'port' => 80,
		'path' => '/engine.io',
		/**
		 * socket_create: php extension "sockets"
		 * stream_socket_create
		 * fsockopen: TODO: fix ping failed
		 */
		'connect_method' => 'stream_socket_create',
		/**
		 * socket connect timeout.
		 * @var float second
		 */
		'connection_timeout' => 3.0,
		/**
		 * stream socket wait timeout.
		 * it usually can't less than 1000 (localhost)
		 * 
		 * @var integer ms
		 */
		'stream_wait_timeout' => 3000,
		'user_agent' => 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/77.0.3865.90 Safari/537.36',
		'is_secure' => false,
		/**
		 * engine.io protocol version
		 * @var integer
		 */
		'version' => 3,
		/**
		 * engine.io handshake variables
		 */
		'sid' => null,
		'upgrades' => [],
		'pingInterval' => 25000,
		'pingTimeout' => 60000,
		/**
		 * engin.io url and query parameters
		 */
		// 'path' => '/engine.io',
		'transport' => 'polling', // init with polling
		'transports' => ['polling', 'websocket'],
		'j' => 'callback',
		// 'sid' => null,
		'b64' => null,
		/**
		 * engine.io client settings
		 */
		'jsonp' => false,
		'timestampRequests' => true,
		'timestampParam' => 't',
		'key' => null,
	];
	function __construct($dsl = null, $options = [], $debug_callback = null) {
		$this->start_time = microtime(true);
		if (isset($dsl)) {
			$this->options['dsl'] = $dsl;
			$parsed = parse_url($dsl);
			foreach ([
				'host',
				'port',
				'path',
			] as $key) {
				if (isset($parsed[$key])) {
					$this->options[$key] = & $parsed[$key];
				}
			}
		}
		if ($debug_callback === true) {
			$this->debug = true; // default debugger
		} else if ($debug_callback) {
			$this->debug_callback = $debug_callback; // custom debugger function
		}
		if($options){
			$this->options = array_merge($this->options, $options);
		}
	}
	function __destruct(){
		if (isset($this->socket)) {
			$this->close();
		}
	}
	private function debug($template, ...$args) {
		if (isset($this->debug_callback)) {
			$time = microtime(true) - $this->start_time;
			call_user_func($this->debug_callback, $time, ...$args);
		} else if ($this->debug) {
			$time = microtime(true) - $this->start_time;
			error_log(sprintf('[%.4f]' . $template, $time, ...$args));
		}
	}
	function connect() {
		if ($this->options['transport'] === 'polling') {
			$this->debug('[%s] start', 'handshake');
			$this->handshake();
			$this->debug('[%s] end', 'handshake');
		}
		if (in_array('websocket', $this->options['upgrades'])) {
			$this->options['transport'] = 'websocket';
			$this->debug('[%s] start', 'upgradeTransport');
			$r = $this->upgradeTransport();
			$this->debug('[%s] end (%s)', 'upgradeTransport', $r);

			$this->debug('[%s] start', 'ping');
			$this->write(Payload::encode(Packet::encode(Type::PING, 'probe')));
			$r = Payload::decode($this->read());
			$this->debug('[%s] end (%s)', 'ping', $r);

			$this->debug('[%s] start', 'write upgrade signal to socket');
			// $this->write(Packet::encode(Type::UPGRADE));
			$this->write(Payload::encode(Packet::encode(Type::UPGRADE)));
			// remove message '40' from buffer
			if ($this->options['version'] === 2) {
				$r = Payload::decode($this->read());
			} else {
				$r = null;
			}
			$this->debug('[%s] end (%s)', 'write upgrade signal to socket', $r);
		}
	}
	private function handshake() {
		$response = Curl::get($this->buildPollingUri());
		$decoded = Packet::decodePayload($response);
		$result = $decoded[0]; // only one result
		if ($result[0] !== Type::OPEN) {
			throw new \Exception(sprintf('handshake receive invalid type: %s', $result[0]));
		}
		$data = $result[1];
		if (!isset($data)) {
			return;
		}
		$data = json_decode($data, true);
		foreach ([
			'sid',
			'upgrades',
			'pingInterval',
			'pingTimeout'
		] as $key) {
			if (isset($data[$key])) {
				$this->options[$key] = $data[$key];
			}
		}
	}
	private function upgradeTransport () {
		$this->debug('[%s] start', 'upgradeTransport connectSocket');
		$this->connectSocket();
		$this->debug('[%s] end', 'upgradeTransport connectSocket');

		$this->debug('[%s] start', 'upgradeTransport write');
		$this->write($this->buildWebsocketHeader());
		$this->debug('[%s] end', 'upgradeTransport write');

		//check if it's 101 switch
		$expect = 'HTTP/1.1 101';
		$length = strlen($expect);
		$response = $this->read($length);
		$compare = substr($response, 0, $length);
		if ($expect !== $compare) {
			throw new \UnexpectedValueException(sprintf('The server returned an unexpected value. Expected "HTTP/1.1 101", had "%s"', $compare));
		}
		return $this->read(); // cleaning up body
	}
	private function connectSocket () {
		if (!isset($this->options['key'])) {
			$this->options['key'] = $this->randomKey();
		}
		switch ($this->options['connect_method']) {
			case 'socket_create':
				$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
				if($socket === false){
					$errorcode = socket_last_error();
					$errormsg = socket_strerror($errorcode);
					throw new \ErrorException("socket_create() failed: [$errorcode] $errormsg", 1);
				}
				$this->socket = $socket;
				$ip = gethostbyname($this->options['host']);
				$port = $this->options['port'];
				if (!socket_connect($this->socket, $ip, $port)) {
					$errorcode = socket_last_error();
					$errormsg = socket_strerror($errorcode);
					throw new \ErrorException("socket_connect to $ip:$port failed: [$errorcode] $errormsg", 1);
				}
				break;
			case 'fsockopen':
				$fp = fsockopen($this->options['host'], $this->options['port'], $this->fp_errno, $this->fp_errstr, 30);
				if (!$fp) {
					throw new \ErrorException($this->fp_errstr . ' (' . $this->fp_errno . ')');
				}
				$this->socket = $fp;
				break;
			case 'stream_socket_create':
			case 'stream_socket_client':
			default:
				if ($this->options['is_secure']) {
					$uri = sprintf('ssl://%s:%s', $this->options['host'], $this->options['port']);
				} else {
					$uri = sprintf('%s:%s', $this->options['host'], $this->options['port']);
					// $uri = sprintf('tcp://%s:%s', $this->options['host'], $this->options['port']);
				}
				$fp = stream_socket_client($uri, $this->fp_errno, $this->fp_errstr, $this->options['connection_timeout'], STREAM_CLIENT_CONNECT);
				if (!$fp) {
					throw new \ErrorException($this->fp_errstr . ' (' . $this->fp_errno . ')');
				}
				// stream_set_blocking($fp, 1);
				stream_set_timeout($fp, 0, $this->options['stream_wait_timeout']);
				$this->socket = $fp;
				break;
		}
	}
	function write($data) {
		switch ($this->options['transport']) {
			case 'polling':
				Curl::post($this->buildPollingUri(), $data);
				break;
			default:
				switch ($this->options['connect_method']) {
					case 'socket_create':
						socket_write($this->socket, $data, strlen($data));
						break;
					case 'fsockopen':
					case 'stream_socket_create':
					case 'stream_socket_client':
					default:
						$written = fwrite($this->socket, $data);
						if ($written < strlen($data)) {
							throw new \ErrorException("Could only write $written out of " . strlen($data) . " bytes.");
						}
						break;
				}
			break;
		}
	}
	/**
	 * [read description]
	 * @param  integer $length length=0 means read all!
	 * @return [type]          [description]
	 */
	function read($length = 0) {
		$response = '';
		$fregment = 1024;
		switch ($this->options['transport']) {
			case 'polling':
				// Noop
			return;
			default:
				switch ($this->options['connect_method']) {
					case 'socket_create':
						if($length){
							// we cant wait all, otherwise websocket stream will be blocked.
							// $response=socket_read($this->socket, $length);
							$bytes = socket_recv($this->socket, $response, $length, MSG_PEEK);
							if ($bytes === false) {
								$errorcode = socket_last_error();
								$errormsg = socket_strerror($errorcode);
								throw new \ErrorException("socket_recv failed: [$errorcode] $errormsg", 1);
							}
						}else{
							while (0 != socket_recv($this->socket, $buffer, $fregment, MSG_DONTWAIT)) {
								if ($buffer != null) $response .= $buffer;
							};
						}
						break;
					case 'fsockopen':
					case 'stream_socket_create':
					case 'stream_socket_client':
					default:
						//   do {
						//     $buffer = stream_get_line($this->socket, $fregment, "\r\n");
						//     $response .= $buffer . "\n";
						//     $metadata = stream_get_meta_data($this->socket);
						//   } while (!feof($this->socket) && $metadata['unread_bytes'] > 0);
						if($length){
							$response=fread($this->socket, $length);
						} else {
							do {
								$buf = fgets($this->socket);
								$response .= $buf;
							} while ('' !== trim($buf));
						}
						break;
				}
			break;
		}
		return $response;
	}
	function close() {
		if (!isset($this->socket)) {
			return;
		}
		switch ($this->options['transport']) {
			case 'polling':
			//Noop
			break;
			default:
				$this->write(Payload::encode(Packet::encode(Type::CLOSE)));
				switch ($this->options['connect_method']) {
					case 'socket_create':
						socket_close($this->socket);
						break;
					case 'fsockopen':
					case 'stream_socket_create':
					case 'stream_socket_client':
					default:
						fclose($this->socket);
						break;
				}
			break;
		}
		unset($this->socket);
	}
	private function buildSchema() {
		if ($this->options['transport'] === 'websocket') {
			$schema = 'ws';
		} else {
			$schema = 'http';
		}
		if ($this->options['is_secure']) {
			$schema.= 's';
		}
		return $schema;
	}
	private function buildPollingUri() {
		$query = $this->buildQuery();
		$query['transport'] = 'polling';
		return sprintf(
			'%s://%s:%d/%s/%s',
			$this->options['is_secure'] ? 'https' : 'http',
			$this->options['host'],
			$this->options['port'],
			trim($this->options['path'], '/'),  // must be /engine.io/?xxx, can't be /engine.io?xxx
			empty($query) ? '' : '?' . http_build_query($query)
		);
	}
	private function buildQuery() {
		$query = [
			'EIO' => $this->options['version'],
			'transport' => $this->options['transport']
		];
		if ($this->options['transport'] === 'polling') {
			if ($this->options['jsonp'] && isset($this->options['j'])) {
				$query['j'] = $this->options['j'];
			}
		}
		if (isset($this->options['sid'])) {
			$query['sid'] = $this->options['sid'];
		}
		if ($this->options['timestampRequests']) {
			$query[$this->options['timestampParam']] = time();
		}
		return $query;
	}
	private function getContext() {
		$context = array(
			'schema' => $this->buildSchema() ,
			'host' => $this->options['host'],
			'port' => $this->options['port'],
			'query' => $this->buildQuery() ,
			'path' => $this->options['path'],
			'headers' => array(
				'Host' => $this->options['host'] . ":" . $this->options['port'],
				'Connection' => 'Upgrade',
				// 'Pragma'=>'no-cache',
				// 'Cache-Control'=>'no-cache',
				'Upgrade' => $this->options['transport'],
				'Origin' => "http://" . $this->options['host'],
				'User-Agent' => $this->options['user_agent'],
				// 'Accept-Encoding'=>'gzip, deflate, sdch',
				// 'Accept-Language'=>'zh-TW,zh;q=0.8,en-US;q=0.6,en;q=0.4,zh-CN;q=0.2',
				'Sec-WebSocket-Version' => '13',
				'Sec-WebSocket-Key' => $this->options['key'],
				'Sec-WebSocket-Extensions' => 'permessage-deflate; client_max_window_bits'
			)
		);
		if (isset($this->options['sid'])) {
			$context['headers']['Cookie']="io=" . $this->options['sid'];
		}
		return $context;
	}
	private function buildStreamContext() {
		$context = $this->getContext();
		$opt = array(
			'http' => array(
				'method' => 'GET',
				'header' => implode(
					"\r\n",
					array_map(function ($key, $value) {
						return "$key: $value";
					}, array_keys($context['headers']) , $context['headers'])
				),
				'timeout' => 10000
			)
		);
		return stream_context_create($opt);
	}
	/**
	 * GET ws://localhost/engine.io/?EIO=3&transport=websocket&sid=6cGRGiGccx4-XG2cAAAA HTTP/1.1
	 * Host: localhost
	 * Connection: Upgrade
	 * Pragma: no-cache
	 * Cache-Control: no-cache
	 * User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, * like Gecko) Chrome/77.0.3865.90 Safari/537.36
	 * Upgrade: websocket
	 * Origin: http://localhost
	 * Sec-WebSocket-Version: 13
	 * Accept-Encoding: gzip, deflate, br
	 * Accept-Language: en,zh-CN;q=0.9,zh-TW;q=0.8,zh;q=0.7,ja;q=0.6
	 * Cookie: i18n_redirected=us; PHPSESSID=778d8bee7a45a63cde1854bb3ae9cdf6; * id=63145c7aced1dfdb0d0749f6b686d67b; io=6cGRGiGccx4-XG2cAAAA
	 * Sec-WebSocket-Key: 8AXnWFgobuVpN7emb/wuuw==
	 * Sec-WebSocket-Extensions: permessage-deflate; client_max_window_bits
	 */
	private function buildWebsocketHeader() {
		$context = $this->getContext();
		// Can't be with schema://host;port, if the Host exist
		$path = sprintf(
			// '%s://%s:%s/%s/%s',
			'/%s/%s',
			// $context['schema'],
			// $context['host'],
			// $context['port'],
			trim($context['path'], '/'),
			empty($context['query']) ? '' : '?' . http_build_query($context['query']),
		);

		$header = "GET " . $path . " HTTP/1.1\r\n";
		$header.= implode(
			"\r\n",
			array_map(
				function ($key, $value) {
					return "$key: $value";
				},
				array_keys($context['headers']),
				$context['headers']
			)
		);
		$header.= "\r\n\r\n";
		return $header;
	}
	/**
	 * The key must be base64_encoded
	 */
	private function randomKey($length = 16) {
        $hash = sha1(uniqid(mt_rand(), true), true);
        if ($this->options['version'] !== 2) {
            $hash = substr($hash, 0, $length);
        }
        return base64_encode($hash);
	}
	// private function randomId($length = 16) {
	// 	$trim_chars = ['+', '/', '='];
	// 	return substr(str_replace($trim_chars, '', base64_encode(random_bytes($length))), 0, $length);
	// }
}
