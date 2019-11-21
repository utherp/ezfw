#!/usr/bin/php
<?php
	
	require_once('uther.ezfw.php');

	load_definitions('STREAM_TEST');
	load_definitions('CONVERTER');

	$locator = 'video/352x288';

 /********************************************\
|               Constants                      |
 \********************************************/
	$sleep_time = 1000000;
	$request_exit = false;
 /********************************************\
 \********************************************/

	$cache = new Memcache();
	$cache->connect('localhost');

	while (true) {
		$index = $cache->get($locator . '/index');
		if ($index === false) print "no index!\n";
		else print $index . "\n";
		usleep(100000);
	}
	/************************************************/

?>
