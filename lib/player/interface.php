<?php
//	ini_set('include_path', ".:/usr/share/php:/usr/local/ezfw4/etc");
//	require_once('uther.ezfw.php');

	/******************************************************************************/
	function add_zero($str, $num = 2) {
		$add = $num - strlen($str);
		if ($add <= 0) return $str;
		return str_repeat('0', $add) . $str;
	}
	function js_exec($cmd) {
		return "<script type='text/javascript' language='Javascript'>$cmd;</script>\n";
	}

	/******************************************************************************/
	function video_demux_id($filename) {
		$path = abs_path('web_files', 'streams', 'playing');
		$tmp = explode('/', $filename);
		$dir = implode('_', array_slice($tmp, -5));
		if (!is_dir($path . '/' . $dir)) @mkdir($path . '/' . $dir);
//		print "<!-- filename ($filename), path ($path), dir ($dir) -->\n";
		return $dir;
	}

	function demux_video($filename, $path = false, $rate = 3) {
		if ($path == false) {
			if (!is_array($filename)) return false;
			if (!isset($filename['filename'])) return false;
			if (!isset($filename['path'])) return false;

			if (isset($filename['rate'])) $rate = $filename['rate'];
			$path = $filename['path'];
			$filename = $filename['filename'];
		}

		file_put_contents($path . '/rate', $rate);

			
		$command = 
			'nice -n 15 ' .
			'ffmpeg -an -i ' . $filename . 
			' -qmax 14 -qmin 9 -s 352x288 -r ' . $rate . ' -deinterlace ' .
			$path . '/video_%d.jpg 2>&1 ' . 
			'&& rm -f "' . $path . '/loading" ' .
			'&& touch "' . $path . '/load_complete"';

		debugger("Executing '$command'...", 3);
		//$ffmpeg = popen($command, 'r');

//		exec($command);
//		usleep(100000);



		$descriptorspec = array(
			0 => array("pipe", "r"),		// stdin is a pipe that the child will read from
			1 => array("pipe", "w"),		// stdout is a pipe that the child will write to
			2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
		);

		$cwd = '/tmp';

		$GLOBALS['_FFMPEG_'] = proc_open($command, $descriptorspec, $GLOBALS['_PIPES_']);
		if (!is_resource($GLOBALS['_FFMPEG_'])) return false;

		$demuxer_pid = intval(exec('ps axww -opid,cmd | grep "' . $filename . '" | grep -v nice |grep -v grep | cut -d" " -f1'));
		if (!$demuxer_pid) return false;
		file_put_contents($path . '/loading', $demuxer_pid);

		return $demuxer_pid;

	}
	function read_total_frames($ffmpeg, $rate) {
		if (!is_resource($ffmpeg)) return false;
		if (feof($ffmpeg)) return false;
		global $loaded_frames;
		global $total_frames;
		global $buffer;
		$total_frames = false;

		while ($total_frames === false) {
			if (feof($ffmpeg)) return false;
			$buffer .= fread($ffmpeg, 8192);

			if (preg_match('/Duration: *?([0-9:\.]+)?,/', $buffer, $matches)) {
				$dur = explode(':', $matches[1]);
				$sec = $dur[0] * 3600 + $dur[1] * 60 + $dur[2];
				$total_frames = ceil(floatval($sec) * $rate);
			}

			$f = scan_loaded_frames($buffer);
			if ($f && $f != $loaded_frames) $loaded_frames = $f;
			$buffer = substr($buffer, -50);
		}
		return $total_frames;
	}

	function check_progress($ffmpeg) {
		if (!is_resource($ffmpeg)) return false;
		global $buffer;
		global $loaded_frames;
		$buffer .= fread($ffmpeg, 8192);

		$f = scan_loaded_frames($buffer);
		if ($f && $f != $loaded_frames) {
			$loaded_frames = $f;
			file_put_contents('/tmp/demux.out', $buffer, FILE_APPEND);
			$buffer = '';
			return $f;
		}
		if (feof($ffmpeg)) close_demuxer($ffmpeg);
		return -1;
	}
	
	function close_demuxer(&$pipe = false) {
		global $loading, $preloaded, $demux_path;
		file_put_contents('/tmp/demuxing', getmypid() . " Closing demuxer...\n", FILE_APPEND);

		ob_end_flush();
		flush();
		if (!$loading && !$preloaded) {
			if (!is_resource($GLOBALS['_FFMPEG_'])) {
				file_put_contents('/tmp/demuxing', getmypid() . " already closed.\n", FILE_APPEND);
				exit;
			}
				
			file_put_contents('/tmp/demuxing', getmypid() . " writting to pipe 'qqqqqq'\n", FILE_APPEND);
			@fwrite($GLOBALS['_PIPES_'][0], 'qqqqqq');
			file_put_contents('/tmp/demuxing', getmypid() . " closing pipes\n", FILE_APPEND);
			@fclose($GLOBALS['_PIPES_'][0]);
			@fclose($GLOBALS['_PIPES_'][1]);
			file_put_contents('/tmp/demuxing', getmypid() . " closing proc\n", FILE_APPEND);
			@proc_close($GLOBALS['_FFMPEG_']);
			$GLOBALS['_FFMPEG_'] = null;
			if (file_exists($demux_path . '/loading')) unlink($demux_path . '/loading');
			file_put_contents($demux_path . '/load_complete', 'true');
			file_put_contents('/tmp/demuxing', getmypid() . " done\n", FILE_APPEND);
			if (is_resource($GLOBALS['_FFMPEG_'])) exit;
			exit;
		} else if ($loading) {
			file_put_contents('/tmp/demuxing', getmypid() . " no demuxer to close, someone else was loading...\n", FILE_APPEND);
			if (!exec('ps -opid -p ' . $loader_pid . ' | grep -v PID')) {
				if (file_exists($demux_path . '/loading')) unlink($demux_path . '/loading');
			}
			exit;
		}
		file_put_contents('/tmp/demuxing', getmypid() . " video already loaded\n", FILE_APPEND);
		exit;
	}


	function scan_loaded_frames($buffer) {
		$p = strrpos($buffer, 'frame=');
		if ($p !== false) {
			$frame = '';
			$ftmp = substr($buffer, $p + 6, 10);
			if (preg_match('/[0-9]+/', $ftmp, $matches)) $frame = $matches[0];
			unset($ftmp);
		} else {
			$frame = false;
		}
		return $frame;
	}

	/******************************************************************************/
	function create_uri($ts, $entry = false, $host = false, $rate = 2) {
		if (is_object($ts)) $ts = $ts->format('U');
		if ($host === false) $host = web_path('secureview', 'player.php');

		$uri = $host . '?ts=' . $ts;
		if ($entry !== false) $uri .= '&ENTRY=' . $entry . '&rate=' . $rate;
		return $uri;
	}
	function browser_uri($ts, $host = false) {
		if (is_object($ts)) $ts = $ts->format('U');
		if ($host === false) $host = web_path('secureview', 'browser.php');
		return $host . '?ts=' . $ts;
	}

