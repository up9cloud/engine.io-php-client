<?php

namespace eio;

interface ClientInterface{
	public function on($event, $callback);
	public function send(string $data, array $options);
	public function close();
}
