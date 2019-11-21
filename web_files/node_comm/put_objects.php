<?php
	require_once('uther.ezfw.php');
	require_once('registration_controls.php');
	define('LOG_FILE', 'put_objects');
	define('DEBUG', 2);

	load_definitions('ACCESS');

	if (!talking_to_server()) {
		logger('Warning:  ['.$_SERVER['REMOTE_ADDR'] . ']  Unauthorized object Post Attempted! ' . 
					' key(' . crypt(get_mac() . AUTH_KEY) . ') vs ('.$_REQUEST['key'] . ')');
		exit;
	}

	if (!isset($_REQUEST['objects'])) {
		logger('Warning:  ['.$_SERVER['REMOTE_ADDR'] . ']  No objects sent! ');
		exit;
	}
	if (!is_array($_REQUEST['objects'])) {
		logger('Warning: ['.$_SERVER['REMOTE_ADDR'] . '] Objects is not an array!');
		exit;
	}

	foreach ($_REQUEST['objects'] as $n => $v) {
		if ($v == 'NULL' || $v == 'false' || !$v) {
			debugger('Removing object named "'.$n.'"', 2);
			remove_object($n);
		} else {
			$v = base64_decode($v);
			debugger('Saving object named "'.$n.'" to "' . abs_path('objects', $n . '.ser'), 2);
			write_object($n, $v);
			break;
		}
	}

?>
