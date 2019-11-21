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
		logger('Warning:  ['.$_SERVER['REMOTE_ADDR'] . '] No Flags Sent With Post!');
		print base64_encode(serialize(array('ERROR' => 'No Flags Sent')));
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
	foreach ($flags as $name => $values) {
		$check_name = $name;
		if (!preg_match('/\.flag$/', $check_name)) $check_name .= '.flag'; //strpos($na
		if (!isset($values['action'])) {
			logger('Warning: Flag "'.$name.'" has no action');
			$response['flags'][$name] = array('ACK' => 'FAIL', 'MSG' => 'No Action');
			continue;
		}

		switch(strtolower($values['action'])) {
			case('raise'):
				if (isset($values['data']))
					raise_flag($check_name, $flag_values['data']);
				else
					raise_flag($check_name);
				$response['flags'][$name] = array('ACK' => 'SUCCESS', 'MSG' => 'Flag Raised');
				break;
			case('lower'):
				if (!flag_raised($check_name))
					$response['flags'][$name] = array('ACK' => 'WARN', 'MSG' => 'Flag Not Raised');
				else {
					lower_flag($check_name);
					$response['flags'][$name] = array('ACK' => 'SUCCESS', 'MSG' => 'Flag Lowered');
				}
				break;
			case('read'):
				if (!flag_raised($check_name))
					$response['flags'][$name] = array('ACK' => 'FAIL', 'MSG' => 'Flag Not Raised');
				else 
					$response['flags'][$name] = array('ACK' => 'SUCCESS', 'MSG' => 'Flag Read', 'DATA' => read_flag($name));
				break;
			default:
				$response['flags'][$name] = array('ACK' => 'FAIL', 'MSG' => 'Unknown Action "'.$values['action'].'"');
				break;
		}
	}

	print base64_encode(serialize($response));
?>
