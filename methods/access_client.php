<?php
	require_once('uther.ezfw.php');
	load_definitions('ACCESS');

	@session_name("node_access");
	@session_start();
	
	class access_client {

	 /***************************************************\
	|    Variables:                                       |
	|        server: static IP of the permissions server  |
	|     hash_path: path to the directory we store hashes|
	|        status: set false if anything goes wrong     |
	|       message: set to describe anything gone wrong  |
	|          hash: the hash of the connecting client    |
	|        access: array of Tags, true or false values  |
	 \***************************************************/
		protected $server;
		protected $hash_path;

		protected $status = true;
		protected $message = '';
		protected $client = '';

		protected $hash = '';
		protected $access;

	 /***************************************************\
	|    getters for status and message:                  |
	|         ...if you don't know, close this file and   |
	|         go read an object oriented programming book |
	 \***************************************************/
		public function get_status() { return $this->status; }
		public function get_message() { return $this->message; }
		public function get_session() {
			if (!isset($_SESSION['_access_'])) return false;
			return $_SESSION['_access_'];
		}


	 /***************************************************\
	|    Constructor:                                     |
	|        Checks if there is already an access object  |
	|        in $_SESSION['access'] or creates one.  Then |
	|        checks whether we're being called by a client|
	|        or the server and calls the proper function  |
	|        for handling them.                           |
	 \***************************************************/
		function __construct() {
			$this->server = SERVER_HOST;
			$this->hash_path = abs_path(HASH_PATH);
			$this->key = AUTH_KEY;

			logger('Started...');

			if (!is_dir($this->hash_path)) {
				?> Server Configuration Error #100! <?
				logger('ERROR: HASH_PATH "' . $this->hash_path . '" does not exist!');
				exit;
			}
			if (strpos($this->hash_path, '/') !== strlen($this->hash_path)) $this->hash_path .= '/';



			// Are we being called via an HTTP Request
				if (!isset($_SERVER['REMOTE_ADDR'])) {
					$this->status = false;
					$this->message = "I Only Talk Through HTTP Requests";
					$this->client = 0;
					return;
				}

			// Is there an access object already in the $_SESSION?
				// is it set?
				if (isset($_SESSION['_access_'])) {
					// is it an object?
					if (is_object($_SESSION['_access_'])) {
						// is it of our class?
						if (get_class($_SESSION['_access_']) == get_class($this)) {
							// we have one, do nothing more
							if (self::get_session()->hash_file_exists()) self::get_session()->load_client_access();
							return;
						}
					}
				}


			// Sets our object into $_SESSION['__access__']
				$_SESSION['_access_'] = $this;

			// Set the client IP
				$this->client = $_SERVER['REMOTE_ADDR'];

			// Are we talking to the server?

				if ($this->is_server()) {
					// Process Server Messages
					$this->process_server_message();
				} else {
					// Load Client Access Data
					$this->load_client_access();
				}
		}


	 /***************************************************\
	|    is_server():                                     |
	|        returns true if client ip == server ip       |
	|        used for determining whether to load a hash  |
	|        access file for a client or if the server is |
	|        connecting to send more client access info   |
	 \***************************************************/
		public function is_server() { 
			if ($this->client == gethostbyname($this->server)) {
				return true;
			} else {
				return false;
			}
		}


	 /***************************************************\
	|    required_parameter(Name, Error Message):         |
	|        Returns the POST variable of Name or else    |
	|        sets status=false, message = Error Message   |
	|        and returns false
	 \***************************************************/
		private function required_parameter($name, $msg) {
			if (!isset($_POST[$name])) {
				if (!isset($_GET[$name])) {
					$this->status = false;
					$this->message = $msg;
					return false;
				} else {
					return $_GET[$name];
				}
			} else {
				return $_POST[$name];
			}
		}

	 /***************************************************\ 
	|    process_server_message():                        |
	|        Called when the connecting client is from    |
	|        the same ip as the server.  Gets action, ip  |
	|        hash and access (when applicable) from the   |
	|        HTTP $_GET variables, then writes a file in  |
	|        $this->hash_path with the name returned by   |
	|        hash_file([ip address]).  The file is loaded |
	|        by load_client_access() when a client of the |
	|        supplied IP connects and gives us the same   |
	|        hash string.                                 |
	 \***************************************************/ 
		private function process_server_message() {

			// Get Action from POST
				$action = $this->required_parameter('action', 'No Action Specified');

			// Get Client IP from POST
				$ip = $this->required_parameter('ip', 'No IP Address Specified');

			// Get Client Hash from POST
				$this->hash = $this->required_parameter('hash', 'No Hash Sent');

			// Get 'crypt'ed key from POST as test it
				$key = $this->required_parameter('key', 'No Key Sent');
				if ($key != crypt($this->hash . $this->key, $key)) {
					$this->status = false;
					$this->message = "Authentication Failed";
				}
			
			// Return false if a status flag has been raised
				if (!$this->status) {
					logger('Warning: Server Message failed from "' . $_SERVER['REMOTE_ADDR'] . '": Msg = "' . $this->message . '"');
					return false;
				}

			// Run for the different Actions
			switch ($action) {

				// We're adding client access
				case('ALLOW'):
					$access = $this->required_parameter('access', 'No Access Array');
					$access = str_replace('\"', '"', $access);
					if (!$this->status) return false;

					$this->write_hash_file($this->hash_file($ip), $access);
					$this->status = true;
					$this->message = "Allowing Access to $ip";
					return true;
				break;

				// We're removing client access
				case('DENY'):
					$this->remove_hash_file($this->hash_file($ip));
					$this->status = true;
					$this->message = "Denying Access to $ip";
					return true;
				break;

				default:			
					$this->status = false;
					$this->message = "Unknown Action '$action'";
					return false;
				break;
			}
		}
	 /**********************************************\
	|       Hash file functions                      |
	 \**********************************************/

		private function write_hash_file($filename, $access) {
			debugger('Writting hash file "' . $filename . '"');
			return file_put_contents($filename, serialize($access));
		}
		private function remove_hash_file($filename) {
			debugger('Removing hash file "' . $filename . '"');
			if (is_file($filename)) return unlink($filename);
			return true;
		}
		private function load_hash_file($filename) {
			if (!is_file($filename)) return false;
			$access = unserialize(file_get_contents($filename));
			$this->remove_hash_file($filename);
			return $access;
		}
		private function hash_file_exists() {
			return is_file($this->hash_file());
		}
	 /***************************************************\ 
	|    hash_file([IP Address]):                         |
	|        returns the hash file name.  If IP Address   |
	|        is supplied, it returns the file for that IP |
	|        and our hash (used in process_server_message |
	|        for writting new access files for clients),  |
	|        otherwize it uses $this->client for the IP.  |
	 \***************************************************/ 
		private function hash_file($ip = '') { 
			if ($ip == '') $ip = $this->client;
			return $this->hash_path . $ip . '_' . $this->hash;
		}

	 /***************************************************\ 
	|	load_client_access():                             |
	|        Gets 'hash' variable from GET Method and     |
	|        tries to load the access file. Once loaded,  |
	|        the access file is deleted and the object is |
	|        stored in $_SESSION['access'].  All failures |
	|        set $this->status = false and puts error in  |
	|        $this->message.                              |
	 \***************************************************/
		private function load_client_access() {
			
			
			if ($this->hash == '')
				$this->hash = $this->required_parameter('hash', 'No Hash Sent From Client');

			if (!$this->status) return false;

			if (!file_exists($this->hash_file())) {
				$this->status = false;
				$this->message = "No Access Found For {$this->client}";
				return false;
			}

			$this->access = $this->load_hash_file($this->hash_file());

			$this->status = true;
			$this->message = "Loaded Access For {$this->client}";
			debugger('Loaded access hash file "' . $this->hash_file() . '"');

			return true;
		}

	 /***************************************************\ 
	|	validate_permission(Tag):                         |
	|        Returns true or false for the permission to  |
	|        the supplied Tag                             |
	 \***************************************************/
		public function validate_permission($action) {
			if (!is_array($this->access)) $this->access = unserialize($this->access);
			if (!isset($this->access[$action])) return false;
			return $this->access[$action];
		}

	 /***************************************************\ 
	|	require_access(Tag):                              |
	|        Validates Access for Tag, returns true or    |
	|        calls $this->deny with error message         |
	 \***************************************************/
		public function require_access($action) {
			if (!$this->validate_permission($action)) {
				self::deny("Access Denied For '$action'");
				debugger('Access Denied for "' . $_SERVER['REMOTE_ADDR'] . '" to "' . $action . '"');
				exit;
			} else {
				return true;
			}
		}

	 /***************************************************\ 
	|	deny(Message):                                    |
	|        Prints an Access Denied Page with the given  |
	|        error Message and exits                      |
	 \***************************************************/
/*		public function deny($reason) {
			return self::deny($reason);
		}
*/
		static function deny($reason) {
?>			<html>
				<head>
					<title><?=$reason?></title>
					<link rel="stylesheet" type="text/css" href="css/hrcp_browse.css" />
				</head>
				<body>
					<h3><?=$reason?></h3><br />
					Sorry, but you do not have access to view this resource!
				</body>
			</html>
<?			exit;
		}

		static function logged_in() {
			return (self::get_session() !== false)?true:false;
		}

		static function spit_it_out() {
			if (self::logged_in() === false) return false;
			print_r(self::get_session());
		}

		static function is_allowed($action) {
			if (self::logged_in() === false) return false;
			return self::get_session()->validate_permission($action);
		}

	 /***************************************************\
	|    __wakeup():                                      |
	|        Called when the session is unserialized upon |
	|        connection.  Checks if it's passed another   |
	|        hash.  If so, we reload the hash file just   |
	|        in case we were passed updated permissions   |
	 \***************************************************/
		function __wakeup() {
			$new_hash = $this->required_parameter('hash', 'Checking for new access');
			if (!$new_hash) {
				$this->status = true;
				$this->message = '';
			} else if ($new_hash != $this->hash) {
				$this->hash = $new_hash;
				$this->load_client_access();
			}
		}

	}

 /**********************************\
|        Code Start                  |
 \**********************************/


	@session_start();
	new access_client();

	if (!isset($_SESSION['_access_'])) {
		print "Unable To Create Access Object :/\nCowardly Quitting Instead Of Falsifying Permissions!\n";
		exit;
	}

?>
