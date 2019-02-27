<?php

namespace eio;

use eio\Curl\Curl;
use eio\Packet\Type;

use ElephantIO\Payload\Decoder;
/**
 * protocal:
 * https://github.com/socketio/engine.io-protocol
 *
 * TODO support other official transports:
 *      flashsocket
 *      polling {jsonp|xhr}
 *
 * websocket encode, decode
 * see https://github.com/socketio/engine.io-client
 *
 * @example $trans = new Transport('ws://localhost:9527');
 *          $trans->send('helloworld');
 */
Class Transport {
	/**
	 * for debug
	 * @var integer second
	 */
	private $timestamp = 0;
	/**
	 * callback for showing debug message.
	 * @var clousre
	 */
	private $debug_callback = null;
	/**
	 * connection default settings.
	 */
	private $uri = '';
	private $host = 'localhost';
	private $port = 80;
	/**
	 * socket resource.
	 * @var resource
	 */
	private $socket = null;
	/**
	 * socket service
	 * @var string Sockets(extension), stream(stream_socket_create), fsock(fsockopen)
	 */
	private $socket_service = 'Sockets';
	/**
	 * socket connect timeout.
	 * @var float second
	 */
	private $connection_timeout = 3.0;
	/**
	 * stream socket wait timeout.
	 * it usually can't less than 1000 (localhost)
	 * 
	 * @var integer ms
	 */
	private $stream_wait_timeout = 3000;
	/**
	 * prefer transport.
	 * @var string
	 */
	private $transport = 'websocket';
	private $user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.89 Safari/537.36';
	private $isSecure = false;
	private $jsonp_index = '';
	/**
	 * engine.io client settins.
	 * see https://github.com/socketio/engine.io-client
	 */
	private $valid_transports = array(
		'websocket',
		'polling',
		'flashsocket'
	);
	/**
	 * engine.io protocol version
	 * @var integer
	 */
	public $protocol = 3;
	public $binaryType = '';
	/**
	 * engine.io init client settings.
	 */
	private $agent = false;
	private $upgrade = true;
	private $forceJSONP = false;
	private $jsonp = false;
	private $forceBase64 = false;
	private $enablesXDR = false;
	private $timestampRequests = false;
	private $timestampParam = 't';
	private $policyPort = 843;
	private $path = '/engine.io';
	private $transports = ['polling', 'websocket'];
	private $rememberUpgrade = false;
	private $pfx = '';
	private $key = '';
	private $passphrase = '';
	private $cert = '';
	private $ca = '';
	private $ciphers = '';
	private $rejectUnauthorized = false;
	private $perMessageDeflate = true;
	private $extraHeaders = [];
	/**
	 * engine.io server first response
	 */
	private $sid = '';
	private $pingInterval = 25000;
	private $pingTimeout = 60000;
	function __construct($uri = null, $options = ['socket_service'=>'Sockets'], $debug_callback = null) {
		$this->timestamp = microtime(true);
		if ($uri) {
			$this->uri = $uri;
			$uri_info = parse_url($uri);
			if (isset($uri_info['host'])) {
				$this->host = & $uri_info['host'];
			}
			if (isset($uri_info['port'])) {
				$this->port = & $uri_info['port'];
			}
			if (isset($uri_info['path'])) {
				$this->path = & $uri_info['path'];
			}
		}
		if ($debug_callback) {
			$this->setDebugCallback($debug_callback);
		}
		if(isset($options['socket_service'])){
			$this->socket_service = $options['socket_service'];
		}
		$this->connect();
	}
	/**
	 * @example
	 * $this->setDebugCallback(function($message, $time){
	 *     printf('$s: %f', $message, $time);
	 * })
	 * @param  closure $callback
	 * @return void
	 */
	public function setDebugCallback($callback) {
		$this->debug_callback = $callback;
	}
	private function debug($message) {
		if ($this->debug_callback) {
			$time = microtime(true) - $this->timestamp;
			call_user_func($this->debug_callback, $message, $time);
		}
	}
	public function write($data) {
		switch ($this->transport) {
			case 'polling':
				$url = sprintf('%s://%s%s', $this->getSchema() , $this->host, $this->port, $this->path, http_build_query($this->getQueryParameters()));
				Curl::post($url, $data);
			break;
			default:
				switch ($this->socket_service) {
					case 'Sockets':
						socket_write($this->socket, $data, strlen($data));
						break;
					case 'stream':
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
	public function read($length = 0) {
		$response = '';
		$fregment = 1024;
		switch ($this->transport) {
			case 'polling':
				//TODO
				
			break;
			default:
				switch ($this->socket_service) {
					case 'Sockets':
						if($length){
							// we cant wait all, otherwise websocket stream will be blocked.
							// $response=socket_read($this->socket, $length);
							$bytes = socket_recv($this->socket, $response, $length, MSG_PEEK);
						}else{
							while (0 != socket_recv($this->socket, $buffer, $fregment, MSG_DONTWAIT)) {
								if ($buffer != null) $response .= $buffer;
							};
						}
						break;
					case 'stream':
					default:
						//   do {
						//     $buffer = stream_get_line($this->socket, $fregment, "\r\n");
						//     $response .= $buffer . "\n";
						//     $metadata = stream_get_meta_data($this->socket);
						//   } while (!feof($this->socket) && $metadata['unread_bytes'] > 0);
						if($length){
							$response=fread($this->socket, $length);
						}else{
							$response=fgets($this->socket);
						}
						// cleaning up the stream
						// while ('' !== trim(fgets($this->socket))) {
						// }
						break;
				}
			break;
		}
		return $response;
	}
	public function close() {
		switch ($this->transport) {
			case 'polling':
				//TODO
				
			break;
			default:
				switch ($this->socket_service) {
					case 'Sockets':
						socket_close($this->socket);
						break;
					case 'stream':
					default:
						fclose($this->socket);
						break;
				}
			break;
		}
	}
	private function getSchema() {
		if ($this->transport === 'websocket') {
			$schema = 'ws';
		} 
		else {
			$schema = 'http';
		}
		if ($this->isSecure) {
			$schema.= 's';
		}
		return $schema;
	}
	private function getUri() {
		return $this->getSchema() . '://' . $this->host . ':' . $this->port . $this->path . '?' . http_build_query($this->getQueryParameters());
	}
	/**
	 * for fsockopen
	 * @return [type] [description]
	 */
	private function getHostUri() {
		if ($this->isSecure) {
			$schema = 'ssl';
		} 
		else {
			// $schema = '';
			$schema = 'tcp';
			// $schema = 'udp';
			
			
		}
		return $schema . '://' . $this->host . ':' . $this->port;
	}
	private function getQueryParameters() {
		$query = ['EIO' => $this->protocol, 'transport' => $this->transport];
		if ($this->transport === 'polling') {
			if ($this->forceJSONP) {
				$query['j'] = $this->jsonp_index;
			} 
			elseif ($this->jsonp) {
				$query['j'] = $this->jsonp_index;
			}
		}
		if ($this->sid) {
			$query['sid'] = $this->sid;
		}
		if ($this->timestampRequests) {
			$query[$this->timestampParam] = time();
		}
		return $query;
	}
	private function parseResponse($response) {
		//simple way to parse response.
		// $response = json_decode(substr($response, strpos($response, '{')), true);
		$response = (string)new Decoder($response);
		switch ($response[0]) {
			case Type::OPEN:
			break;
			case Type::CLOSE:
			break;
			case Type::PING:
			break;
			case Type::PONG:
			break;
			case Type::MESSAGE:
			break;
			case Type::UPGRADE:
			break;
			case Type::NOOP:
			break;
			default:
				throw new \UnexpectedValueException("Error code.", 1);
			break;
		}
		return json_decode(substr($response, 1) , true);
	}
	private function connect() {
		$this->debug('[handshake] start');
		$this->handshake();
		$this->debug('[handshake] end');
		//TODO heardbeat.
		//ping, pong
	}
	private function handshake() {
		switch ($this->transport) {
			case 'polling':
				$url = sprintf('%s://%s%s/?%s', $this->getSchema() , $this->host, $this->port, $this->path, http_build_query($this->getQueryParameters()));
				$response = Curl::get($url, $this->getQueryParameters());
			break;
			case 'flashsocket': //TODO
				throw new \UnexpectedValueException("not supported.", 1);
			break;
			case 'websocket':
			default:
				$this->key = $this->generateId();

				switch ($this->socket_service) {
					case 'Sockets':
						$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
						$result = @socket_connect($this->socket, $this->host, $this->port);
						if(!$result){
							throw new \ErrorException("socket connect failed.", 1);
						}
						break;
					case 'stream':
					default:
						$errors = [];
						$this->socket = stream_socket_client($this->getHostUri() , $errors[0], $errors[1], $this->connection_timeout);
						stream_set_blocking($this->socket, 1);
						stream_set_timeout($this->socket, 0, $this->stream_wait_timeout);
						break;
				}
				$this->write($this->buildHeaders());

				//check if it's 101 switch
				$expect = 'HTTP/1.1 101';
				$length = strlen($expect);
				$response=$this->read($length);
				$compare = substr($response, 0, $length);
				if ($expect !== $compare) {
					throw new \UnexpectedValueException(sprintf('The server returned an unexpected value. Expected "HTTP/1.1 101", had "%s"', $compare));
				}
				$response=$this->read();
				break;
			}
			$response = $this->parseResponse($response);
			$this->sid = $response['sid'];
			$this->pingInterval = $response['pingInterval'];
			$this->pingTimeout = $response['pingTimeout'];
	}
	// private function heardbeat(){

	// }
	private function getContext() {
		$Context = array(
			'schema' => $this->getSchema() ,
			'host' => $this->host,
			'port' => $this->port,
			'query' => $this->getQueryParameters() ,
			'path' => $this->path,
			'headers' => array(
				'Host' => $this->host . ":" . $this->port,
				'Connection' => 'Upgrade',
				// 'Pragma'=>'no-cache',
				// 'Cache-Control'=>'no-cache',
				
				'Upgrade' => $this->transport,
				'Origin' => "http://" . $this->host,
				'User-Agent' => $this->user_agent,
				// 'Accept-Encoding'=>'gzip, deflate, sdch',
				// 'Accept-Language'=>'zh-TW,zh;q=0.8,en-US;q=0.6,en;q=0.4,zh-CN;q=0.2',
				
				'Sec-WebSocket-Version' => '13',
				'Sec-WebSocket-Key' => $this->key,
				// 'Sec-WebSocket-Extensions' => 'permessage-deflate; client_max_window_bits'
				
				
			)
		);
		if($this->sid){
			$Context['headers']['Cookie']="io=" . $this->sid;
		}
		return $Context;
	}
	private function buildStreamContext() {
		$context = $this->getContext();
		$opt = array(
			'http' => array(
				'method' => 'GET',
				'header' => implode("\r\n", array_map(function ($key, $value) {
					return "$key: $value";
				}
				, array_keys($context['headers']) , $context['headers'])) ,
				'timeout' => 10000
			)
		);
		return stream_context_create($opt);
	}
	/**
	 *
	 */
	private function buildHeaders() {
		$context = $this->getContext();
		// if Host is set, path couldn't include the host.
		// $path = sprintf('%s://%s:%d%s/?%s', $context['scheme'], $context['host'], $context['port'], $context['path'], http_build_query($context['query']));
		$path = sprintf('%s/?%s', $context['path'], http_build_query($context['query']));
		
		$header = "GET " . $path . " HTTP/1.1\r\n";
		$header.= implode("\r\n", array_map(function ($key, $value) {
			return "$key: $value";
		}
		, array_keys($context['headers']) , $context['headers']));
		$header.= "\r\n\r\n";
		
		return $header;
	}
	private function generateId($length = 16) {
		// $c = 0;
		// $tmp = '';
		// while ($c++ * 16 < $length) {
		// 	$tmp.= md5(mt_rand() , true);
		// }
		// return base64_encode(substr($tmp, 0, $length));
		return base64_encode(sha1(uniqid(mt_rand() , true) , true));
	}
}

