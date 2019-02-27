<?php

namespace eio\Curl;

class Curl {
	//http://php.net/manual/en/function.curl-setopt.php
	//http://stackoverflow.com/questions/16220172/php-curl-ssl-verifypeer-option-doesnt-have-effect
	private static $default_options = array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.89 Safari/537.36',
		CURLOPT_VERBOSE => false
	);
	private static $user_options = [];
	/**
	 * bind options to current curl handler.
	 * @param [type] $ch [description]
	 */
	private static function _setOptions($ch){
		curl_setopt_array($ch, array_replace(self::$default_options, self::$user_options));
	}
	private static function custom($name, $url, array $params = array()) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		self::_setOptions($ch);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($name));
		if (!empty($params)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		}
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
	public static function setOption($code, $value){
		self::$user_options[$code] = $value;
	}
	public static function setOptions($options) {
		self::$user_options = $options;
	}
	public static function get($url, array $params = array()) {
		$ch = curl_init();
		if (!empty($params)) {
			$url = $url . '?' . http_build_query($params);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		self::_setOptions($ch);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
	public static function post($url, array $params = array()) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		self::_setOptions($ch);
		curl_setopt($ch, CURLOPT_POST, true);
		if (!empty($params)) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		}
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}
	public static function __callStatic($method, $args){
		array_unshift($args, $method);
		call_user_func_array(
			[$this, 'custom'],
			$args
		);
	}
}
