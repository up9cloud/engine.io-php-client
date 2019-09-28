<?php

namespace eio;

interface TraspoterInterface{
	public function read($data);
	public function write($length);
	public function connect();
	public function close();
}
