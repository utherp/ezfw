#!/usr/bin/php
<?php
	$name = "127.0.0.1";
	$port = 4224;

	$sock = socket_create(AF_INET, SOCK_DGRAM, getprotobyname('udp'));
	socket_bind($sock, $name, $port);

	while (true) {
		$buf = "";
		socket_recvfrom($sock, $buf, 100, 0, $name, $port);
		$tmp = explode(':', $buf);

		print "\033[01m" . $tmp[0] . ": \033[00m" . $tmp[1] . "\n";
	}
	

