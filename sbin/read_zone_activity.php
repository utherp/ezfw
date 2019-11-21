#!/usr/bin/php
<?php

	$name = "127.0.0.1";
	$port = 4225;

	$sock = socket_create(AF_INET, SOCK_DGRAM, getprotobyname('udp'));
	socket_bind($sock, $name, $port);

	$c = new Memcache();
	$c->connect('localhost');


	$rails = array(
		'bed_area.upper-left-rail_3.vbr' => array(
			'state'=>0,
			'marker'=>'ul3',
			'color'=>'1'
		),
		'bed_area.upper-left-rail_1.vbr' => array(
			'state'=>0,
			'marker'=>'ul1',
			'color'=>'2'
		),
		'bed_area.lower-left-rail_3.vbr' => array(
			'state'=>0,
			'marker'=>'ll3',
			'color'=>'1'
		),
		'bed_area.lower-left-rail_1.vbr' => array(
			'state'=>0,
			'marker'=>'ll1',
			'color'=>'2'
		),
		'bed_area.bed-upper.vbr'=>array(
			'state'=>0,
			'marker'=>'bed-head',
			'color'=>4
		),
		'bed_area.bed-lower.vbr'=>array(
			'state'=>0,
			'marker'=>'bed-foot',
			'color'=>5
		),
		'bed_area.upper-right-rail_1.vbr' => array(
			'state'=>0,
			'marker'=>'ur1',
			'color'=>'2'
		),
		'bed_area.upper-right-rail_3.vbr' => array(
			'state'=>0,
			'marker'=>'ur3',
			'color'=>'1'
		),
		'bed_area.lower-right-rail_1.vbr' => array(
			'state'=>0,
			'marker'=>'lr1',
			'color'=>'2'
		),
		'bed_area.lower-right-rail_3.vbr' => array(
			'state'=>0,
			'marker'=>'lr3',
			'color'=>'1'
		)
	);

	$upperorder = array(
		'bed_area.upper-left-rail_3.vbr',
		'bed_area.upper-left-rail_1.vbr',
		'bed_area.bed-upper.vbr',
		'bed_area.upper-right-rail_1.vbr',
		'bed_area.upper-right-rail_3.vbr'
	);

	$lowerorder = array(
		'bed_area.lower-left-rail_3.vbr',
		'bed_area.lower-left-rail_1.vbr',
		'bed_area.bed-lower.vbr',
		'bed_area.lower-right-rail_1.vbr',
		'bed_area.lower-right-rail_3.vbr'
	);

	$orders = array(
		$upperorder,
		$lowerorder
	);

display_rails();


$triggers = '';
$last_trigger = 0;

//$in = fopen("php://stdin", 'r');

while (true) {//$l = fgets($in)) {
	$buf = "";
	socket_recvfrom($sock, $buf, 100, 0, $name, $port);
//	print "DEBUG: '$buf'" . time() . "\n";
	$tmp = explode(':', $buf);
	if (count($tmp) < 3) {
		print "packet '$buf' has invalid parameter count\n";
		continue;
	}

	$current_trigger = time();

	if ($last_trigger < $current_trigger - 3)
		$triggers = '';
	
	if (!isset($rails[$tmp[0]])) {
		print "zone {$tmp[0]} is not set\n";
		continue;
	}

	if ($tmp[1] == 'ACTIVE')
		$rails[$tmp[0]]['state'] = 1;
	else
		$rails[$tmp[0]]['state'] = 0;


	$triggers = '';
	foreach ($rails as $n => $v)
		if ($v['state'])
			$triggers .= $v['marker'] . ',';

	$triggers = substr($triggers, 0, -1);
	$c->set('video/active_zones', $triggers);

	display_rails();
}




function display_rails() {
	global $toporder, $bottomorder, $orders, $rails, $triggers;
	$x = 0;
	foreach ($orders as $order){
		foreach ($order as $o) {
			$prefix = "\033[3" . $rails[$o]["color"] . "m";
			$suffix = "\033[37m";
			$disp = $rails[$o]["marker"];
	
			if ($rails[$o]["state"]) {
				$disp = strtoupper($disp); ///bedlr/BEDLR/;
				$disp = "\033[01m" . $disp . "\033[00m" ;
			}
			print $prefix . $disp . $suffix . "    ";
		}
		if( $x++ == 0 )
			print "\n";
	}
	print " ($triggers)\n";
}
