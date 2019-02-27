<?php

// same as engine.io javascript client global namespace.
namespace eio;

use eio\Packet\Type;
use ElephantIO\Payload\Decoder;
use ElephantIO\Payload\Encoder;

final Class Client implements ClientInterface {
	private $conn = null;
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
	function __construct($uri = null, $options=[], $debug_callback=null) {
		$this->conn = new Transport($uri, $options, $debug_callback);
	}
	function __destruct(){
		$this->close();
	}
	protected function write($code, $data){
		$encoded=new Encoder(4 . $data, Encoder::OPCODE_TEXT, true);
		$this->conn->write((string) $encoded);
	}
	protected function read(){
		$this->conn->read();
	}
	/**
	 * [send description]
	 * @param  string $data     [description]
	 * @param  array  $options  [description]
	 * @param  [type] $callback [description]
	 * @return void
	 */
	public function send($data, $options = ['compress'=>true], $callback = null) {
		$this->write(Type::MESSAGE, $data);
		$response=$this->read();
		if($callback){
			$callback($response);
		}
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
	}
}