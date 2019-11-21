<?php
	require_once('uther.ezfw.php');
	require_once('registration_controls.php');

	load_definitions('ACCESS');
	load_definitions('FLAGS');

	$response = array();
	if (!talking_to_server()) {
		logger('Warning:  ['.$_SERVER['REMOTE_ADDR'] . ']  Unauthorized Flag Set Attempted! ' . 
					' key(' . crypt(get_mac() . AUTH_KEY) . ') vs ('.$_REQUEST['key'] . ')');
		print base64_encode(serialize(array('ERROR' => 'Unauthorized')));
		exit;
	}

	if (!isset($_POST['flags'])) {
		logger('Warning:  ['.$_SERVER['REMOTE_ADDR'] . '] No Flags Requested With Post!');
		print base64_encode(serialize(array('ERROR' => 'No Flags Requested')));
		exit;
	}

//	$flags = unserialize(base64_decode($_POST['flags']));
	$flags =& $_POST['flags'];
//	print_r($_POST['flags']);

	if (!$flags) {
		logger('Warning: failed to unserialize flags data!');
		print base64_encode(serialize(array('ERROR' => 'Data Corrupted')));
		exit;
	}


	$response['flags'] = array();
	foreach ($flags as $name) {
		$response['flags'][$name] = array();
		if (flag_raised($name . '.flag')) {
			$response['flags'][$name]['state'] = true;
			$data = read_flag($name . '.flag');
			if ($data) $response['flags'][$name]['data'] = $data;
		} else
			$response['flags'][$name]['state'] = false;
	}

	print base64_encode(serialize($response));
?>
