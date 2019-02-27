<?php

namespace eio\Packet;

final Class Type {
	const OPEN = 0;
	const CLOSE = 1;
	const PING = 2;
	const PONG = 3;
	const MESSAGE = 4;
	const UPGRADE = 5;
	const NOOP = 6;
}
