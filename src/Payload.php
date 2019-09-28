<?php
namespace eio;

use ElephantIO\Payload\Decoder;
use ElephantIO\Payload\Encoder;
/**
 * from https://github.com/Wisembly/elephant.io/blob/master/src/Payload/Encoder.php#L47
 * https://tools.ietf.org/html/rfc6455#page-27
 */
class Payload {
	static function encode ($data) {
		return @(string) new Encoder($data, Encoder::OPCODE_TEXT, true);
	}
	static function decode ($payload) {
		return @(string) new Decoder($payload);
	}
}
