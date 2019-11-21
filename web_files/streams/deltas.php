<?php
	header('Content-type: text/javascript');
	declare(ticks = 1);

	define('DEFAULT_VALNAME', 'ZoneDeltaStream');
	define('DEFAULT_TIMEOUT', 1);
	define('DELAY_USEC', 2000);
	define('MAX_TIMEOUT', 3);
	$name = "127.0.0.1";
	$port = 4224;

	function flush_data ($sig = false) {
		global $zones, $valname, $sock, $flushing;
		if ($flushing) return;
		$flushing = true;
		@socket_close($sock);

		print "$valname = {";
		$f = true;
		foreach ($zones as $n => $v) {
			if (!$f) print ', ';
				else $f = false;
			printf("'%s':%.3f", $n, $v);
		}
		print "};\n";

		exit;
	}


	$valname = isset($_GET['val'])?$_GET['val']:DEFAULT_VALNAME;

	if (!isset($_GET['to']))
		$timeout = DEFAULT_TIMEOUT;
	else {
		$timeout = floatval($_GET['to']);
		if ($timeout <= 0)
			$timeout = DEFAULT_TIMEOUT;
		else if ($timeout > MAX_TIMEOUT)
			$timeout = MAX_TIMEOUT;
	}

	$sock = socket_create(AF_INET, SOCK_DGRAM, getprotobyname('udp'));
	socket_bind($sock, $name, $port);

	$zones = array();

	socket_set_nonblock($sock);
	do {
		$start = gettimeofday(true);
		if (($ret = @socket_recvfrom($sock, $buf, 100, 0, $name, $port)) > 0) {
			$tmp = explode(':', $buf);
			if (isset($zones[$tmp[0]])) flush_data();
			$zones[$tmp[0]] = $tmp[1];
		}
		
		usleep(DELAY_USEC);
		$timeout -= (gettimeofday(true)-$start);
	} while ($timeout > 0);

	flush_data();

