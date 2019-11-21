<?php
	require_once('uther.ezfw.php');

    load_libs('children.php');
	/**************************************/

	function start_copier ($input, $output) {
		global $copier;
		$child = pcntl_fork();
		if ($child) {
			sleep(1);
			return $child;
		}
		$copier = 0;

		debugger('Copier started', 1);

		copy_stream($input, $output);

		debugger('Copier exiting', 1);
		exit(0);
	}

	/**************************************/

	function stop_copier ($pid, &$ret) {
		if (!$pid) {
            logger("Warning: when attempting to stop copier, no pid was passed ('$pid')", true);
            return;
        }
	
		$i = 0;
		$c = 0;
		$sigs = array(SIGTERM, SIGKILL);

		debugger('Stopping copier ('.$pid.')', 1);

        $myret = 0;
		while (stat_child($pid,$myret) === 0) {
            debugger("sending copier '$pid' signal '{$sigs[$i]}'...", 1);
			if (posix_kill($pid, $sigs[$i]) === false)
				logger("Failed to send signal {$sigs[$i]} to pipe (pid: $pid)");

			sleep(1);
            if (++$c == 3) $i++;
			if ($c > 9) return false;
		}

        $ret = $myret;
		debugger('--> Copier stopped ('.$ret.')', 1);
        unset($GLOBALS['copier']);

		return true;
	}

	/**************************************/

	function copier_running ($pid, &$copier_ret) {
		if (!$pid) return false;
		debugger('Checking copier status ('.$pid.')', 3);
		if( stat_child($pid, $copier_ret) !== 0 )
			return false;

		return true;
	}

	/**************************************/

	function copier_shutdown ($sig) {
		global $input_fd, $exit_code;
		logger("Shutting down copier: (sig:$sig)", true);
		if ($input_fd) fclose($input_fd);
		if ($output_fd) fclose($output_fd);

		exit((int)$exit_code);
        # who commented out the above line in leu of the following? 
		#exit((int)$sig);
		return 0;
	}

	/**************************************/

	function copy_stream ($input, $output) {
		global $input_fd, $output_fd, $exit_code;
		$exit_code = 0;

		pcntl_signal(SIGTERM, "copier_shutdown");

		$dir = dirname($output);
		@mkdir($dir, 0755, true);
		$input_fd = fopen($input, "r");

		if ($input_fd === false) {
			$exit_code = INPUT_ERR | OPEN_ERR;
			$err == error_get_last();
			logger('ERROR: Failed opening input file "'.$input.'": ' . $err['message'], true);
			logger('...Exiting with code '.$exit_code.', suggesting software and ivtv module reload');
			copier_shutdown(0);
		}

		$output_fd = fopen($output, "w");

		if ($output_fd === false) {
			$exit_code = OUTPUT_ERR | OPEN_ERR;
			$err = error_get_last();
			logger('ERROR: Failed opening output file "'.$output.'": ' . $err['message'], true);
			logger('...Exiting with code '.$exit_code.', suggesting buffer device rebuild');
			copier_shutdown(0);
		}

		stream_set_timeout($input_fd,5);

		$err = 0;
		while (true) {
			$buf = fread($input_fd, 8192);
			if ($buf === false) {
				$err = error_get_last();
				logger('ERROR: Failed reading from input descriptor: ' . $err['message'], true);
                $exit_code = INPUT_ERR | IO_ERR;
				copier_shutdown(0);
			} else if (!strlen($buf)) {
				if (++$err < 20) continue;
				logger("NOTE: Max Read err reached! ($err)", true);
                $exit_code = INPUT_ERR | READ_ERR;
				copier_shutdown(0);
			} else if (fwrite($output_fd, $buf) === false) {
				$err = error_get_last();
				logger('ERROR: Failed writing to output descriptor: ' . $err['message'], true);
                $exit_code = OUTPUT_ERR | IO_ERR;
				copier_shutdown(0);
			} else
				$err = 0;
		}

		exit(-1); // this should never happen...
	}


