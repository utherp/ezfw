<?php
	require_once('uther.ezfw.php');
	load_definitions('Sessions');

	class sql_session {

		/******************************************************/
		static $our_session = false;
//		static function sess_create($server_name, $session_name) {
		static function sess_create() {
			self::$our_session = new sql_session();
			return self::$our_session;
		}
		/******************************************************/

		/******************************************************/
		protected $db;
		protected $server_name;
		protected $server_user;
		protected $server_passwd;
		protected $server_database;
		protected $server_table;

		protected $cache_path;
		protected $caching = false;

		protected $session_name;
		protected $session_id;
		protected $session_data;
		protected $database_id;
		protected $connected = false;
		/******************************************************/

		/******************************************************/
		function __construct() {
		}
		public function initialize($server_name, $session_name) {
//			$this->set_session_handlers();
			
			$this->server_name = $server_name;
			$this->session_name = $session_name;

			$this->check_caching();

			$this->check_parameters();
			//$this->connect();
		}
		/******************************************************/


		private function check_caching() {
			if (!defined('Cache_Path')) {
				debugger('Cache_Path is not defined', 1);
				$this->caching = false;
			} else if (!is_dir(Cache_Path)) {
				debugger('Cache_Path "'.Cache_Path.'" is not a directory', 1);
				$this->caching = false;
			} else {
				debugger('Caching is on!', 2);
				$this->caching = true;
			}
		}

		/******************************************************/
		function __destruct() {
			@mysql_close($this->db);
		}
		/******************************************************/
		public function is_connected() {
			return $this->connected;
		}
		/******************************************************/
		private function connect() {
			$this->db = mysql_pconnect(
							$this->server_name,
							$this->server_user,
							$this->server_passwd
				);

			debugger('db == "'.$this->db.'"', 2);
			if (!$this->db)
				$this->report_error('Could not connect to server "'.$this->server_name.'": ' . mysql_error());


			mysql_select_db($this->server_database, $this->db) 
				or $this->report_error('Could not select database "'.$this->server_database .
						'" on server "'.$this->server_name.'":  ' . mysql_error());
			$this->connected = true;

		}
		/******************************************************/

		/******************************************************/
		private function check_parameters() {
			if ($this->server_name == '') {
				defined('Default_Server_Name')
					or $this->report_error('No Mysql Server Name specifed (session.save_path in php.ini) ' .
							'and no Default_Server_Name specified in Config!');
				$this->server_name = Default_Server_Name;
			}

			defined($this->server_name.'_User')
				or $this->report_error('No Mysql user specified for server "'.$this->server_name.'"!');
			$this->server_user = constant($this->server_name.'_User');

			defined($this->server_name.'_Passwd')
				or $this->report_error('No Mysql password specified for server "'.$this->server_name.'"!');
			$this->server_passwd = constant($this->server_name.'_Passwd');

			defined($this->server_name.'_Database')
				or $this->report_error('No Mysql database specified for server "'.$this->server_name.'"!');
			$this->server_database = constant($this->server_name.'_Database');

			defined($this->server_name.'_Table')
				or $this->report_error('No Mysql table specified for server "'.$this->server_name.'"!');
			$this->server_table = constant($this->server_name.'_Table');
		}
		/******************************************************/

		/******************************************************/
		private function set_session_handlers() {
			session_set_save_handler(
				array(sql_session::$our_session, "open"),
				array(sql_session::$our_session, "close"),
				array(sql_session::$our_session, "read"),
				array(sql_session::$our_session, "write"),
				array(sql_session::$our_session, "destroy"),
				array(sql_session::$our_session, "gc")
			);
			debugger('set handlers', 2);
		}
		/******************************************************/

		/******************************************************/
		public function close() {
			unset($this->session_id);
			unset($this->database_id);
			unset($this->session_data);
			debugger('closed', 2);
			return true;
		}
		/******************************************************/
		
		/******************************************************/
		public function read($id) {
			$this->session_id = $id;
			if ($this->caching) {
				$filename = Cache_Path . '/' . $_SERVER['REMOTE_ADDR'] .
											'_' . $this->session_name .
											'_' . $id . '.sess';
				if (is_file($filename)) {
					if (filectime($filename) > (gettimeofday(true)-(60*15))) {
						debugger('read from cache', 3);
						return $this->session_data = file_get_contents($filename);
					} else {
						unlink($filename);
					}
				}
			}
			if ($this->load_session() && $this->caching) {
				debugger('writting session to cache', 3);
				file_put_contents(Cache_Path . '/' . $_SERVER['REMOTE_ADDR'] .
											'_' . $this->session_name .
											'_' . $id . '.sess', $this->session_data);
			}

			return $this->read_session();
		}
		/******************************************************/
		private function create_session() {
			$this->connect();
			$query = 'insert into ' . $this->server_table . 
						' (session_id, session_name, last_accessed_from, ipaddr) values' .
					    ' ("'.$this->session_id.'", "' . 
							  $this->session_name.'", "' .
							  $_SERVER['SERVER_NAME'].'", "' .
							  $_SERVER['REMOTE_ADDR'].'")';
			debugger('create query = "'.$query.'"', 3);
			
			$result = mysql_query($query, $this->db);
			if (!mysql_affected_rows($this->db)) {
				logger('WARNING: unable to create session!');
				return false;
			} else {
				$this->database_id = mysql_insert_id($this->db);
				debugger('created session', 3);
				return true;
			}

		}
		/******************************************************/
		private function report_error($msg) {
			logger($msg);
			exit;
		}
		/******************************************************/
		private function load_session() {
			$this->connect();
			$query =  'select * from ' . $this->server_table .
						' where session_id = "' .
									mysql_real_escape_string($this->session_id, $this->db) . '"' .
							' and session_name = "' .
									mysql_real_escape_string($this->session_name, $this->db) . '"' .
							' and ipaddr = "' . $_SERVER['REMOTE_ADDR'] . '"';
			$result = mysql_query($query, $this->db);
			debugger("load query = '$query'", 5);
			if (mysql_num_rows($result)) {
				$entry = mysql_fetch_array($result, MYSQL_ASSOC);
				$this->session_data = $entry['session_data'];
				$this->database_id  = $entry['id'];
				@mysql_free_result($result);
				$this->update_activity();
				debugger('loaded session', 3);
				return true;
			} else {
				debugger('failed to load session', 3);
				return false;
			}
		}
		/******************************************************/
		private function save_session() {
			$query = 'update ' . $this->server_table .
					' set session_data = "' . mysql_real_escape_string($this->session_data, $this->db) . '"' .
					', last_activity = now()' .
					', last_accessed_from = "' . mysql_real_escape_string($_SERVER['SERVER_NAME'], $this->db) . '"' .
					' where id = ' . $this->database_id;
			debugger('save query = "'.$query.'"', 5);
			$result = mysql_query($query, $this->db);

			$rows = mysql_affected_rows($this->db);
			$ret = ($rows)?true:false;
			if ($rows) @mysql_free_result($result);
			if ($ret) debugger('saved session', 4);
			else debugger('failed to save session', 3);
			return $ret;
		}
		/******************************************************/
		private function read_session() {
			return $this->session_data;
		}
		/******************************************************/
		private function update_activity() {
			$update = mysql_query('update sessions' .
									' set last_activity = now(), ' .
									' last_accessed_from = "' . mysql_real_escape_string($_SERVER['SERVER_NAME'], $this->db) . '"' . 
									' where id = '.$this->database_id,
								$this->db
							);
			$ret = (mysql_affected_rows($this->db))?true:false;
			@mysql_free_result($update);
			if ($ret) debugger('updated activity', 4);
			else debugger('failed to update activity', 4);
			return $ret;
		}
		/******************************************************/
		public function write($id, $sess_data) {
			if ($this->caching) {
				//file_put_contents(Cache_Path . '/' . $_SERVER['REMOTE_ADDR'] . 
				//	'_' . $this->session_name . '_' . $id . '.sess', $sess_data);
			} else {
				$this->session_id = $id;
				if (!$this->load_session())
					$this->database_id = $this->create_session();
				if ($this->database_id === false) return false;
				if ($this->session_data == $sess_data) {
					debugger('nothing to write', 4);
					return $this->update_activity();
				} else {
					debugger('wrote session', 4);
					$this->session_data = $sess_data;
					return $this->save_session();
				}
			}
		}
		/******************************************************/
		
		/******************************************************/
		public function destroy($id) {
			if ($this->caching) {
				$filename = Cache_Path . '/' . $_SERVER['REMOTE_ADDR'] . 
										'_' . $this->session_name . '_' . $id . 'sess';
				if (is_file($filename)) unlink($filename);
			}
				
			$this->session_id = $id;
			if ($this->load_session()) {
				return $this->destroy_session();
			}
			return true;
		}
		/******************************************************/
		private function destroy_session() {
			$result = mysql_query('delete from ' . $this->server_table .
									' where id = ' . $this->database_id,
								$this->db
							);
			$ret = (mysql_affected_rows($this->db))?true:false;
			@mysql_free_result($result);
			if ($ret) debugger('destroyed session', 4);
			else debugger('failed to destroy session', 4);
			return $ret;
		}
		/******************************************************/
		public function gc($maxlifetime) {
			$result = mysql_query('delete from ' . $this->server_table .
									' where last_activity < (now() - INTERVAL ' . $maxlifetime . ' seconds)',
								$this->db
							);
			if ($this->caching) 
				$this->clean_cache();
			debugger('garbage collector was called, ' . mysql_affected_rows($this->db) . ' sessions were cleaned', 1);

			return true;
		}
		private function clean_cache() {
			if (!$this->caching) return true;
			if (!is_dir(Cache_Path)) return true;
			$lwd = getcwd();
			chdir(Cache_Path);
			$exp = intval(gettimeofday(true)) - (60*20);
			foreach (glob('*.sess') as $f)
				if (filectime($f) < $exp) unlink($f);
			chdir($lwd);
			return true;
		}
		/******************************************************/
	}

//$dummy = create_function('', '');


session_set_cookie_params(60*60*2, '/', '.'.DOMAIN_NAME);

$our_session = sql_session::sess_create();

session_set_save_handler(
	array($our_session, "initialize"),
	array($our_session, "close"),
	array($our_session, "read"),
	array($our_session, "write"),
	array($our_session, "destroy"),
	array($our_session, "gc")
);

?>
