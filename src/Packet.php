<?php
namespace eio;

/**
 * port from https://github.com/socketio/engine.io-parser/blob/master/lib/index.js
 */
class Packet {
	protected static $packetslist = [
		Type::OPEN,
		Type::CLOSE,
		Type::PING,
		Type::PONG,
		Type::MESSAGE,
		Type::UPGRADE,
		Type::NOOP,
	];
	static function encode (int $type, $data = null, bool $supportsBinary = false, bool $utf8encode = false) {
		// if ($supportsBinary) {
		//   return Packet::encodeBuffer($packet, $supportsBinary);
		// }
		$encoded = $type;
		if (isset($data)) {
			if ($utf8encode) {
				$encoded .= utf8_encode($data);
			} else {
				$encoded .= $data;
			}
		}
		return $encoded;
	}
	protected static function encodeBuffer (int $type, $data = null, bool $supportsBinary) {
		if (!$supportsBinary) {
		  return static::encodeBase64Packet($type, $data);
		}
		// TODO:
		throw new \RuntimeException('Not implemented');
	}
	protected static function encodeBase64Packet (int $type, $data) {
		return base64_encode('b' . $type . $data);
	}
	static function decode ($data, $binaryType = null, bool $utf8decode = false) {
		if (!isset($data)) {
			return [];
		}
		switch (gettype($data)) {
			case 'boolean':
			case 'integer':
			case 'double':
			// case 'float':
				throw new \Exception('Invalid data');
			case 'string':
				if ($data[0] === 'b') {
					return static::decodeBase64Packet(substr($data, 1), $binaryType);
				}
				$type = (int) $data[0];
				if ($type != $data[0]) {
					throw new \Exception('Invalid data');
				}
				if (!in_array($type, static::$packetslist)) {
					throw new \Exception('Invalid data type');
				}
				$data = substr($data, 1);
				if ($utf8decode) {
					return [$type, utf8_decode($data)];
				}
				return [$type, $data];
			case 'array':
			case 'object':
				throw new \RuntimeException('Not implemented');
			// case 'resource':
			case 'NULL':
			// case 'unknown type':
			default:
				return [];
		}
	}
	protected static function decodeBase64Packet ($data, $binaryType = null) {
		$type = (int) $data[0];
		if ($type != $data[0]) {
			throw new \Exception('Invalid data');
		}
		if (!in_array($type, static::$packetslist)) {
			throw new \Exception('Invalid data type');
		}
		$data = substr($data, 1);
		if (!$data) {
			return [$type, null];
		}
		return [$type, base64_decode($data)];
	}
	static function encodePayload ($packets, $supportsBinary) {
		if ($supportsBinary && static::hasBinary($packets)) {
			return static::encodePayloadAsBinary($packets);
		}
		if (empty($packets)) {
			return '0:';
		}
		$encodeOne = function ($packet) use ($supportsBinary) {
			$message = static::encodePacket($packet, $supportsBinary, false);
			return static::setLengthHeader($message);
		};
		$results = [];
		foreach($packets as $packet) {
			$results[] = $encodeOne($packet);
		}
		return implode($results);
	}
	protected static function setLengthHeader($message) {
		return count($message) . ':' . $message;
	}
	static function decodePayload ($data, $binaryType = null) {
		if (gettype($data) !== 'string') {
		  return static::decodePayloadAsBinary($data, $binaryType);
		}
		if ($data === '') {
			return [];
		}
		$results = [];
		$length = '';
		for ($i = 0, $l = strlen($data); $i < $l; $i++) {
			$chr = $data[$i];
			if ($chr !== ':') {
				$length .= $chr;
				continue;
			}
			$n = intval($length);
			if ($length === '' || ($length != $n)) {
				return;
			}
			$msg = substr($data, $i + 1, $n);
			if ($length != strlen($msg)) {
				return;
			}
			if (strlen($msg)) {
				$results[] = static::decode($msg, $binaryType, false);
			}
			$i += $n;
			$length = '';
		}
		if ($length !== '') {
			return;
		}
		return $results;
	}
	static function encodePayloadAsBinary ($packets) {
		if (empty($packets)) {
		  return;
		}
		$results = [];
		foreach ($packets as $packet) {
			$results[] = static::encodeOneBinaryPacket($packet);
		}
		return implode($results);
	}
	protected static function encodeOneBinaryPacket($p) {
		throw new \RuntimeException('Not implemented');
		// return static::encodePacket($p, true, true, function ($packet) {
		// 	$encodingLength = strlen($packet);
		// 	$sizeBuffer = array_fill(0, strlen($encodingLength) + 2, null);
		// 	$sizeBuffer[0] = 0; // is a string (not true binary = 0)
		// 	for ($i = 0; $i < $encodingLength; $i++) {
		// 		$sizeBuffer[$i + 1] = $encodingLength[$i];
		// 	}
		// 	$sizeBuffer[count($sizeBuffer) - 1] = 255;
		// 	if (gettype($packet) === 'string') {
		// 		return [$sizeBuffer, static::stringToBuffer($packet)];
		// 	}
		// 	return [$sizeBuffer, $packet];
		// });
	}
	// static function decodePayloadAsBinary ($data, $binaryType = null) {
	// 	$bufferTail = $data;
	// 	$buffers = [];
	// 	while (strlen($bufferTail) > 0) {
	// 	  $strLen = '';
	// 	  $isString = $bufferTail[0] === 0;
	// 	  for ($i = 1; ; $i++) {
	// 		if ($bufferTail[$i] === 255)  break;
	// 		// 310 = char length of Number.MAX_VALUE
	// 		if (strlen($strLen) > 310) {
	// 		  return;
	// 		}
	// 		$strLen .= $bufferTail[$i];
	// 	  }
	// 	  $bufferTail = substr($bufferTail, strlen($strLen) + 1);
	// 	  $msgLength = intval($strLen);
	// 	  $msg = substr($bufferTail, 1, $msgLength + 1);
	// 	  if ($isString) {
	// 		  $msg = bufferToString($msg);
	// 	  }
	// 	  $buffers[] = $msg;
	// 	  $bufferTail = substr($bufferTail, $msgLength + 1);
	// 	}
	// 	$r = [];
	// 	$total = count($buffers);
	// 	for ($i = 0; $i < $total; $i++) {
	// 	  $buffer = $buffers[$i];
	// 	  $r[] = [static::decode($buffer, $binaryType, true), $i, $total];
	// 	}
	// 	return $r;
	// }
}