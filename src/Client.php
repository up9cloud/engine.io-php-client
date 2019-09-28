<?php

// same as engine.io javascript client global namespace.
namespace eio;

use ElephantIO\Payload\Decoder;
use ElephantIO\Payload\Encoder;

final Class Client implements ClientInterface {
	private $conn;
	/**
	 * event callbacks.
	 * @var array
	 */
	private $_event_callbacks = [];
	/**
	 * current exist events,
	 * @var array
	 */
	private $events = array(
		'open',
		'message',
		'close',
		'error',
		'flush',
		'drain',
		'upgradeError',
		'upgrade',
		'ping',
		'pong'
	);
	function __construct(?string $uri = null, array $options = [], $debug_callback = null) {
		$this->conn = new Transport($uri, $options, $debug_callback);
		$this->conn->connect();
	}
	/**
	 * [send description]
	 * @param  string $data     [description]
	 * @param  array  $options  [description]
	 * @return void
	 */
	public function send(string $data, array $options = []) {
		$encoded = Payload::encode(Packet::encode(Type::MESSAGE, $data));
		$this->conn->write($encoded);
		$res = $this->conn->read();
		return json_decode(Payload::decode($res), true);
	}
	public function ping() {
		$encoded = Payload::encode(Packet::encode(Type::PING));
		$this->conn->write($encoded);
		$res = $this->conn->read();
		return json_decode(Payload::decode($res), true);
	}
	/**
	 * bind events.
	 * TODO
	 * @param  [type] $event    [description]
	 * @param  [type] $callback [description]
	 * @return [type]           [description]
	 */
	public function on($event, $callback) {
		if (in_array($event, $this->events)) {
			$this->_event_callbacks[$event] = $callback;
		}
	}
	public function close() {
		$this->conn->close();
		unset($this->conn);
	}
}