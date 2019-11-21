<?php
	function get_size() {
		return get_param('size', '160x120');
	}
	function get_res() {
		return get_param('res', 'HIGH');
	}
	function get_rate() {
		return get_param('rate', 4);
	}
	function get_hash() {
		return get_param('hash', '');
	}
	function get_single() {
		return (get_param('single', false) != '')?true:false;
	}
	function get_param($name, $default = false) {
		if (isset($_POST[$name])) return $_POST[$name];
		if (isset($_GET[$name])) return $_GET[$name];
		return $default;
	}

	require_once('uther.ezfw.php');
	load_definitions('FLAGS');

	if (flag_raised(PRIVACY_FLAG)) {
		print file_get_contents('../img/privacy.jpg');
	}
/***********************************************/


	$stream = new VideoStream(get_size(), get_res(), get_rate());
	$privacy = false;
	$privsize = 0;
	$chkpriv = 0;

	while (true) {
		if ($chkpriv++ > 5) {
			$chkpriv = 0;
			while (flag_raised(PRIVACY_FLAG)) {
				if ($privacy === false) {
					$privacy = file_get_contents('../img/privacy.jpg');
					$privsize = strlen($privacy);
				}
				print '-=*' . number_format(gettimeofday(true), 2, '.', '') . '*=-<<<---START--->>>';
				print $privacy;
				print '<<<---END--->>>';
				sleep(2);
			}
		}
		$stream->set_start_time();
		$img = $stream->get_new_image();
		while ($img === false) {
			usleep(100000);
			$img = $stream->get_new_image();
		}
		print '-=*' . number_format($stream->get_last_index(), 2, '.', '') . '*=-<<<---START--->>>';
		print $img;
		print "<<<---END--->>>";
		ob_flush();
		flush();
		$stream->do_delay();
	}


