#!/usr/bin/php
<?php
	require_once('uther.ezfw.php');
	load_definitions('NODE_WEB');
	require_once('registration_controls.php');
	$type = LOCATION_TYPE;
	$location = new $type();

	$reg = unserialize(
				file_get_contents(
					'http://' . SERVER_HOST .'.'.DOMAIN_NAME. SERVER_WEB_ROOT . 
					'/setup/is_node_registered.php' .
					'?mac=' . get_mac()
				)
			);

	if (isset($reg[$type])) {
		write_object($type, $reg[$type]);
		$location = $reg[$type];
	} else
		write_object($type, $location);

	if (!isset($reg) && !is_array($reg)) {
		$error = 'Registration Error: Information Array Not Sent, ('.$reg.')';
		logger($error);
		exit('FALSE');
	}

	if (isset($reg['error'])) {
		$error = 'Registration Error: "'.$reg['error'].'"';
		logger($error);
		exit('FALSE');
	}

	if (!$reg['registered']) {
		register_node(LOCATION_TYPE, get_mac());
		exit('FALSE');
	}

	if (!isset($reg[$type]) || !$reg[$type]->get_id()) {
		exit('FALSE');
	}

	if (isset($GLOBALS['argv']) && strtolower($GLOBALS['argv'][1]) == '--no-frontend')
			exit;

	exit('TRUE');
