<?php

namespace eio;

interface ClientInterface{
	public function on($event, $callback);
	public function send($data, $options);
	public function close();
}
