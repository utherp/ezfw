<?php
	require_once('uther.ezfw.php');
	load_definitions('DEMUXER');


	/*******************************\
	 *   NEW FLASH DEMUXER STUFF   *
	\*******************************/
	define('CACHE_PATH', abs_path('video', 'cache'));
	function movie_filename ($id) {
		$v = video::fetch($id);
		return $v->store->path . '/' . $v->filename;


		$path = abs_path('video', 'archive', date("Y/m/d", $id));

		$lwd = getcwd();
		if (!@chdir($path))
			return false;

		$tmp = glob($id . "-*.mpg");
		if (!count($tmp)) 
			return false;

		chdir($lwd);
		return $path . '/' . $tmp[0];
	}

	function movie_times($filename) {
		if (!strpos($filename, '-')) return false;
		return explode('-', preg_replace('/^.*\/([0-9\-]+)\.mpg$/', '$1', $filename));
	}

	function demux_video_to_flash($id) {
		$filename = movie_filename($id);
		//list ($start, $end) = movie_times($filename);
		$title = preg_replace('/^.*\/([0-9\-]+)\.mpg$/', '$1', $filename);
		if ($filename === false) return false;

		touch(CACHE_PATH . '/flash/' . $title . '.flv.filepart');
		$command = 
			'nice -n 15 ' . 
			'ffmpeg -an -i ' . $filename . 
			' -deinterlace -r 8 -b 16384 ' .
			CACHE_PATH . '/flash/' . $title . '.flv';

		exec($command);

		unlink(CACHE_PATH . '/flash/' . $title . '.flv.filepart');
		return;
	}



	function download_video($filename) {
		if (!file_exists($filename)) return false;
		$basename = basename($filename);
		$ts = explode('-', preg_replace('/\.mpg$/', '', $basename));
		$timestamp = new DateTime('@' . $ts[0]);
		$dl_name = $timestamp->format('M d, Y, h.i.s');
		$timestamp = new DateTime('@' . $ts[1]);
		$dl_name .= ' - ' . $timestamp->format('h.i.s') . '.mpg';

		header('Content-type: video/mpe');
		header('Content-disposition: attachment; filename="' . $dl_name . '"');
		header('Content-Length: ' . filesize($filename));
		passthru("/bin/cat '$filename'");
		return true;
	}

	function request_demux_to_flash ($id) {
		$demuxer_pid = read_flag(DEMUXER_FLAG);
		$cmd = "ps axww | awk '/^ *$demuxer_pid/' | grep demuxer";
		if (!$demuxer_pid) {
			print "alert('Demuxer Service is stopped, cannot view video');\nvar load_complete = 'failed';\n";
			return false;
		} else if (!exec($cmd)) {
			print "alert('Demuxer Service has failed! cannot view video');\nvar load_complete = 'failed';\n";
			return false;
		}
		file_put_contents(abs_path(DEMUXER_PIPE), $id . "\n");
		return true;
	}

	function start_demuxer($path, $filename) {
		global $videoid;
		$rate = isset($_GET['rate'])?$_GET['rate']:3;
		if (!is_numeric($rate)) $rate = 3;

		$demuxer_pid = read_flag(DEMUXER_FLAG);
		$cmd = "ps axww | awk '/^ *$demuxer_pid/' | grep demuxer";
		if (!$demuxer_pid) {
			print "alert('Demuxer Service is stopped, cannot view video');\nvar load_complete = 'failed';\n";
			return false;
		} else if (!exec($cmd)) {
			print "alert('Demuxer Service has failed! cannot view video');\nvar load_complete = 'failed';\n";
			return false;
		}
		$options = array(
			'path' => $path,
			'filename' => $filename,
			'rate' => $rate,
		);
		file_put_contents(abs_path(DEMUXER_PIPE), serialize($options));
		return true;
	}
	
	function finish_demuxing($path) {
		while (is_resource($GLOBALS['_FFMPEG_']) && !feof($GLOBALS['_PIPES_'][1])) {
			file_put_contents('/tmp/demuxing.log', getmypid() . "- finishing... \n", FILE_APPEND);
			$loaded_frames = check_progress($GLOBALS['_PIPES_'][1]);

			if ($loaded_frames === false) {
				file_put_contents('/tmp/demuxing.log', getmypid() . "- frame false, closing demuxer\n", FILE_APPEND);
				close_demuxer();
				file_put_contents('/tmp/demuxing.log', getmypid() . "- exit;\n", FILE_APPEND);
				exit;
			} else if ($loaded_frames === -1 || $loaded_frames == $frame_count) {
				$no_change++;
			} else {
				$no_change = 0;
				$frame_count = $loaded_frames;
			}

			if ($no_change > 10) break;
			usleep(500000);
		}
		file_put_contents($path . '/load_complete', '1');
		file_put_contents($path . '/total_frames', $loaded_frames);
		if (file_exists($path . '/loading')) @unlink($path . '/loading');
		close_demuxer();
	}

	function check_video_status($path, &$error) {
		debugger("checking '$path'", 3);
		if (!is_dir($path)) {
			if (!mkdir($path)) {
				$error = 'Could not create temporary location for demuxing';
				debugger("no dir and could not create", 3);
				return false;
			}
			debugger("no path, demuxing", 3);
			return 'demux';
		} 

		if (file_exists($path . '/load_complete')) {
			debugger("load complete, playing", 3);
			return 'play';
		}

		if (file_exists($path . '/loading')) {
			global $loader_pid;
			debugger("checking for loader", 3);
//			$loader_pid = intval(file_get_contents($path . '/loading'));
			
			$cmd = 'ps auxww | grep -v PID | grep -v grep | grep "' . $path . '" | grep ffmpeg |grep -v nice';
			$loader_pid = exec($cmd);
			if ($loader_pid) {
				debugger('doing status', 3);
				return 'status';
			}

		}
		debugger('doing demux', 3);

		$lwd = getcwd();
		chdir($path);
		foreach (glob('*') as $f) @unlink($f);
		chdir($lwd);
		return 'demux';
	}



	function send_parameters($path) {
		header('Content-type: text/javascript');
		global $videoid;
		if (file_exists($path . '/rate')) $rate = intval(file_get_contents($path . '/rate'));
		else $rate = 3;

		$lwd = getcwd();
		chdir($path);
		$files = count(glob('video_*.jpg'));
		chdir($lwd);

		$total_frames = file_exists($path . '/total_frames')?intval(file_get_contents($path . '/total_frames')):$files;

?>
		if (video_obj) {
			video_obj.frameRate = <?=$rate?>;
			video_obj.serverLocator = '<?=web_path('secureview', 'playing', $videoid)?>';
			video_opened = true;
<?
			if (file_exists($path . '/load_complete')) {
?>
				video_obj.total_frames = <?=$files?>;
				video_obj.loaded_frames = video_obj.total_frames;
				video_obj.load_complete = true;
<?			} else {
?>
				video_obj.total_frames = <?=$total_frames?>;
				video_obj.loaded_frames = <?=$files?>;
				video_obj.load_complete = false;
<?			}
?>		}
		wait_update();
<?		exit;
	}

	function monitor_status($path) {
		global $loader_pid;
		header('Content-type: text/javascript');
		$lwd = getcwd();
		chdir($path);
		$total_frames = intval(file_get_contents($path . '/total_frames'));
		$rate = intval(file_get_contents($path . '/rate'));

		$last_frames = 0;
		$frames = 0;
?>		var loaded_frames = 0;
		var rate = <?=$rate?>;
		var total_frames = <?=$total_frames?>;
<?
		do {
			$frames = count(glob('video_*.jpg'));
			if ($frames != $last_frames) {
?>				loaded_frames = <?=$frames?>;
				update_load_bar(-1);
				refresh_control_display();
<?
				print str_repeat(' ', 1500) . "\n";
				ob_flush();
				flush();
				$last_frames = $frames;
				$no_change = 0;
			} else {
				$no_change++;
				if ($no_change > 10) break;
			}
			usleep(400000);
			$proc_stat = exec('ps -opid -p ' . $loader_pid . ' |grep -v PID');
		} while (!file_exists($path . '/load_complete') && $proc_stat);
		chdir($lwd);
		exit;
	}
