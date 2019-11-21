<?php
	require_once('uther.ezfw.php');
	load_definitions('VideoStream');
	load_definitions('SHARED_MEM');

	class VideoStream {

		protected $res = DEFAULT_RES;
		protected $size = DEFAULT_SIZE;
		protected $rate = DEFAULT_RATE;
		protected $index = 0;
		protected $shmem = false;
		protected $start_time = false;
		protected $sleep_time = false;
		protected $skip_limit = 0;
		protected $filename;
		protected $file_id = false;
		protected $last_image = false;
		protected $cookies = array();
		protected $changed_cookies = array();
		protected $server_name	=	false;
		protected $domain_name	=	false;

		public function get_size() { return $this->size; }
		public function get_res()  { return $this->res; }
		public function get_rate() { return $this->rate; }
		public function get_start_time() { return $this->start_time; }

		public function set_size($size) { $this->size = $size;  $this->validate_parameters(); }
		public function set_res($res) { $this->res = $res;  $this->validate_parameters(); }
		public function set_rate($rate) { $this->rate = $rate;  $this->validate_parameters(); }
		public function set_start_time() { $this->start_time = gettimeofday(true); }

		function __construct($size = false, $res = false, $rate = false) {
			if ($size) $this->size = $size;
			if ($res) $this->res = $res;
			if ($rate) $this->rate = $rate;
			if (strpos($_SERVER['SERVER_NAME'], '.') === false) {
				$this->server_name = $_SERVER['SERVER_NAME'];
				$this->domain_name = '.cv-internal.com';
			} else {
				$tmp = explode('.', $_SERVER['SERVER_NAME']);
				$this->server_name = array_shift($tmp);
				$this->domain_name = '.' . implode('.', $tmp);
				if ($this->domain_name == '.') $this->domain_name = '.cv-internal.com';
			}
//			$this->import_existing_cookies();

			if (!$this->validate_parameters())
				logger('Unable to locate filename for stream of size("' . $this->size . '") and res("'.$this->res.'")!');
			$this->sleep_time = 1000000 / $this->rate;
		}
		function __sleep() {
//			if ($this->shmem) @shmop_close($this->shmem);
			return array('res', 'size', 'rate', 'index', 'sleep_time', 'file_id', 'cookies', 'filename', 'shmem', 'server_name', 'domain_name');
		}
		function __wakeup() {
			$this->skip_limit = 0;
			$this->validate_parameters();
		}
		function __toString() {
			return "VideoStream( \n" .
						'size  => "' . $this->size . "\",\n" .
						'res   => "' . $this->res . "\",\n" .
						'rate  => "' . $this->rate . "\"\n" .
				   ")\n";
		}

		private function validate_parameters() {
			$params = array('res', 'size');
			while (!defined('IMAGE_' . $this->size . '_' . $this->res)) {
				if (count($params) == 0) {
					debugger('could not get image file for these parameters', 1);
					return false;
				}
				$this_param = array_shift($params);
				$this->$this_param = constant('DEFAULT_'.strtoupper($this_param));
			}
			$this->filename = module_path(IMAGE_PATH, constant('IMAGE_'.$this->size.'_'.$this->res));
			if (!is_file($this->filename)) {
				debugger('could not FIND image file for these parameters (' . module_path(IMAGE_PATH, constant('IMAGE_'.$this->size.'_'.$this->res)) . ')', 1);
				return false;
			}
			$this->file_id = ftok($this->filename, 'I');
			$this->get_shmem();
//			$this->get_semaphore();
			return true;
		}

		private function get_shmem() {
			$this->shmem = new SharedMem($this->filename);
		}
/*		private function get_semaphore() {	
			$this->semaphore = sem_get($this->file_id, 1, MEM_PERM);
		}
*/		
		public function send_multipart_header() {
			header("HTTP/1.1 200 OK");
			header("Server: mpjpeg.php/0.1.0");
			header("Connection: close");
			header("Max-Age: 0");
			header("Expires: 0");
			header("Cache-Control: no-cache, private");
			header("Pragma: no-cache");
			header("Content-Type: multipart/x-mixed-replace; boundary=--BoundaryString");
		}
		public function send_single_header() {
			header("HTTP/1.1 200 OK");
			header("Max-Age: 0");
			header("Expires: 0");
			header("Cache-Control: no-cache, private");
			header("Pragma: no-cache");
			header("Content-Type: image/jpeg");
		}

		private function construct_frame($image = false) {
			$image = $this->verify_image($image);
			if ($image === false) return false;
			$frame =  "--BoundaryString\x0d\n";
			$frame .= "Content-type: image/jpeg\x0d\n";
			$frame .= "Content-Length: " . strlen($image) . "\x0d\n";
			$frame .= $this->construct_cookies();
			$frame .= "\x0d\n";
			$frame .= $image;
			$frame .= "\x0d\n";
			return $frame;
		}

		public function send_single_image($image = false) {
			$image = $this->verify_image($image);
			if ($image === false) return false;
			$this->send_single_header();
			print $image;
			$this->flush_buffer();
			return true;
		}
		public function get_image_frame($image = false) {
			$frame = $this->construct_frame($image);
			if ($frame === false) return false;
			return $frame;
		}
		public function send_image_frame($image = false) {
			$frame = $this->get_image_frame($image);
			if ($frame === false) return false;
			print $frame;
			$this->flush_buffer();
			return true;
		}
		public function send_next_frame() {
			$this->get_new_image();
			return $this->send_image_frame();
		}
		private function flush_buffer() {
			ob_flush();
			flush();
		}
		private function verify_image($image) {
			if (!$image) $image =& $this->last_image;
			if (!$image) $image = $this->get_new_image();
			if (!$image) return false;
			return $image;
		}
		/***************************************/
		private function construct_cookies() {
			$this->add_forced_cookies();
			$this->set_cookie('index', $this->index, time()+(60*40));
			foreach ($this->changed_cookies as $cookie_name => $cookie) {
				$cookie_string .= $this->make_cookie(
											$cookie_name,
											$cookie['value'],
											$cookie['timestamp']
										);
			}
			$this->changed_cookies = array();
			return $cookie_string;
		}
		private function make_cookie($varname, $value, $timestamp = false) {
			$timestamp = ($timestamp===false)?'':' expires='.date('r', $timestamp). ';';
			return "Set-Cookie: {$this->server_name}_$varname=$value;$timestamp path=/; domain={$this->domain_name}\x0d\n";
		}
		/***************************************/
		private function add_forced_cookies() {
			foreach ($this->cookies as $name => $data) {
				if ($data['force']) {
					$this->changed_cookies[$name] =& $this->cookies[$name];
					if (!$data['always']) $data['force'] = false;
				}
			}

		}
		public function set_cookie($name, $value = false, $timestamp = false, $force = false, $always = false) {
			if (is_array($this->cookies[$name])) {
				$value		= ($value===false)?$this->cookies[$name]['value']:$value;
				$timestamp	= ($timestamp===false)?$this->cookies[$name]['timestamp']:$timestamp;
			}
			$this->cookies[$name] = array(
					'value' 	=> $value,
					'timestamp' => $timestamp,
					'force'		=> $force | $always,
					'always'	=> $always,
				);
			$this->changed_cookies[$name] =& $this->cookies[$name];
		}
		private function import_existing_cookies() {
			if (is_array($_COOKIE)) {
				foreach ($_COOKIE as $name => $value) {
//					if (strpos($name, $this->server_name . '_')===0)
//						$name = preg_replace('/^'.$this->server_name.'_(.*)$/', '$1', $name);
					$this->cookies[$name] = array(
						'value'		=> $value,
						'timestamp' => false,
						'force'		=> false,
						'always'	=> false,
					);
				}
				file_put_contents('/tmp/test_cookies.txt', serialize($this->cookies));
			} else { file_put_contents('/tmp/test_cookies.txt', 'no cookies'); }
		}
		/***************************************/
		public function get_cookie($name) {
			return (isset($this->cookies[$name]))?$this->cookies[$name]:false;
		}
		/***************************************/
		public function unset_cookie($name) {
			if (isset($this->changed_cookies[$name])) unset($this->changed_cookies[$name]);
			if (isset($this->cookies[$name])) unset($this->cookies[$name]);
		}
		/***************************************/
		public function get_new_image() {
//			if (!$this->is_image_available()) return false;
			if (!$this->shmem->image_has_changed()) return false;
			debugger("Getting Image from SHM", 3);
			$image = $this->shmem->read_image_from_mem();
			if ($image === false) {
				debugger("VideoStream: Could Not Get IMAGE_DATA from SHM!!!", 3);
				return false;
			}
			$this->last_image = $image;
			$this->index = $this->shmem->get_last_index();
			return $image;
		}
		/***************************************/
		public function do_delay() {
			$delay = $this->sleep_time - (gettimeofday(true) - $this->start_time);
			if ($delay > 0) {
				usleep($delay);
				$this->skip_limit--;
				if ($this->skip_limit < -4) {
					$this->skip_limit = 0;
					if ($this->rate < 10) {
						$this->rate++;
						$this->sleep_time = 1000000 / $this->rate;
						debugger('-->Increasing Framerate to ' . $this->rate, 3);
					}
	
				}
			} else {
				$this->skip_limit++;
				if ($this->skip_limit > 4) {
					$this->skip_limit = 0;
					if ($this->rate > 1) {
						$this->rate--;
						$this->sleep_time = 1000000 / $this->rate;
						debugger('-->Dropping Framerate to ' . $this->rate, 2);
					}
				}
			}
		}
	}
?>
