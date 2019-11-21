<?php
	require_once('uther.ezfw.php');

	function stop_services (){
		$args = func_get_args();
		foreach ($args as $service) {
			logger('--> Stopping "'.$service.'" service...', true);
			exec('/usr/bin/svc -d /etc/service/' . $service);
		}
		sleep(5);
		return;
	}

	function start_services () {
		$args = func_get_args();
		foreach ($args as $service) {
			logger('--> Starting "'.$service.'" service...', true);
			exec('/usr/bin/svc -u /etc/service/'.$service);
		}
		sleep(5);
		return;
	}

