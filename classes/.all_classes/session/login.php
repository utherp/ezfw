<?php
	require_once('uther.ezfw.php');
	class session_login {

		protected $user;
	
		protected $isFirstLogin = false;
		protected $just_check = true;
		protected $expiry = -1;
		protected $loaded = false;
		protected $permissions = array();
		protected $menus = array();
		protected $sites = array();
	
		/**********************************\
		\**********************************/
	
		public function get_user()		{ return $this->user; }
		public function firstLogin()	{ return $this->isFirstLogin; }
		public function isLoggedIn()	{ return $this->just_check; }
		public function is_loaded()		{ return $this->loaded; }
	
		/**********************************\
		\**********************************/
		function __sleep() {
			return array('user', 'expiry', 'loaded', 'permissions', 'menus', 'sites');
		}
	
		function __wakeup() {
			get_db_connection();
		}
	
	
		function __construct($username = '', $password = '', $quiet = false) {
	
			get_db_connection();
	
			if (isset($_SESSION['_login_'])) {
				if (is_object($_SESSION['_login_'])) {
					if (get_class($_SESSION['_login_']) === get_class($this)) {
						if ($_SESSION['_login_']->new_password()) {
							$_SESSION['_login_']->update_password();
						}
						return true;
					}
				}
			}
	
			if ($username == '' && isset($_POST['username'])) {
				$username = $_POST['username'];
			}
			if ($password == '' && isset($_POST['password'])) {
				$password = $_POST['password'];
			}

			debugger("username($username), password($password)");
	
			switch (true) {
	
				case ($username == '' || $password == ''):
					if (strpos($_SERVER['PHP_SELF'], 'logout.php') !== false) {
						fns_output::redirect('/', true);
						exit;
					}
					if (!$quiet) self::fail_login('Please Login!');
					exit;
				break;
	
				case (!$this->login_user($username, $password)):
					if (!$quiet) self::fail_login('Invalid Login Credentials');
					exit;
				break;
	
				case ($this->firstLogin()):
					$_SESSION['_login_'] = $this;
					if (!$quiet) self::change_doc_passwd();
					exit;
				break;


				default:
					$_SESSION['_login_'] = $this;
				break;
	
			}
	
		}
	
		/**********************************\
		\**********************************/
	
		static function change_doc_passwd() {
?>			<html>
				<head>
					<title><?=PHYSICIANVIEW?></title>
					<?=STR_CSS?>
					<script type="text/javascript">
						function verify_passwords() {
							var s1 = document.forms[0].newpswd;
							var s2 = document.forms[0].confpswd;
							if (s1.value == "") {
								alert("Please enter a new password.");
								s1.focus();
								return false;
							}
							if (s2.value == "") {
								alert("Please enter confirm the password.");
								s2.focus();
								return false;
							}
							if (s1.value != s2.value) {
								alert("The password and the confirmation do not match. Please try again.");
								s1.value = "";
								s2.value = "";
								s1.focus();
								return false;
							}
						}
					</script>
				</head>
				<body>
<?					fns_output::show_login_header(CV_FULLNAME, PHYSICIANVIEW);
//					<div style="position: absolute; top: 50%; left: 30%;">';
?>					<div align="center">';
						<h3 style="color: Blue;">Please change your password</h3>
<?						fns_output::display_change_passwd_form(); 
?>					</div>
<?				web_html::footer();
			exit;
		}
	
		/**********************************\
		\**********************************/
	
		static function fail_login($error_info, $view_name='Login', $type = 'html') {
			@session_destroy();
			switch ($type) {
				case('script'):
					?>document.location = "<?=server_web_path('php', 'login.php?sendback=true&msg=' . $error_info)?>";
					<?
					exit;
				default:
					?><html>
						<head><title>Please Wait...</title></head>
						<body onLoad='document.location = "<?=server_web_path('php', 'login.php?sendback=true&msg=' . $error_info)?>"'></body>
					</html><?
					exit;

			}

			web_html::header($view_name, STR_CSS);
			fns_output::show_login_header(CV_FULLNAME, $view_name); 

?>
			<div align="center">
				<h3 style="color: Blue;">
					<?=$error_info?>
				</h3>
				<form name=login action='<?=$_SERVER['REQUEST_URI']?>' method='POST' target='_TOP'>
					<table>
						<tr>
							<td>Username:</td>
							<td><input type=text id=username name=username /></td>
						</tr><tr>
							<td>Password:</td>
							<td><input type=password name=password /></td>
						</tr><tr>
							<td colspan=2><input type=submit value='login' /></td>
						</tr>
					</table>
				</form>
			</div>
			<script type='text/javascript'>
				document.body.setAttribute('onLoad', 'document.getElementById("username").focus()');
			</script>
			<br /><br />
			<center>
				<b>In order to view the patient with streaming video you must have firefox.</b><br />
				<a href="http://getfirefox.com">
					<img width=350 border="0" alt="Firefox 2" title="Firefox 2" src="http://sfx-images.mozilla.org/affiliates/Buttons/firefox2/468x60FF2_Orange.png" />
				</a>
			</center>

<?			web_html::footer();
			@session_destroy();
			exit;
		}
	
	
	/***************************************************\
	|	The Next several functions were added by me,	|
	|	Stephen Ecker, to handle user and group			|
	|	permissions. Refer to the prototypes for info.	|
	\***************************************************/
	
		private function check_expiry() {
			
			if (intval($this->get_user()->get_group()->get_timeout()) > 0) {
	
				if (($this->expiry > 0) && ($this->expiry < time())) {
					return false;					// Time expired
				}
				return $this->update_expiry($this->get_user()->get_group()->get_timeout());
	
			} else {
				$this->expiry = -1;
				return true;
			}
		}
	
		/**********************************\
		\**********************************/
	
		private function update_expiry($timeout = -1) {
	
			if (intval($timeout) < 1) {
				$this->expiry = -1;
				return true;
			} else {
				$this->expiry = (time() + (60 * intval($timeout)));
				return true;
			}
		}
	
	/***************************************************\
	|	login_user(Username, Password)					|
	|													|
	|		This validates the user's credentials.		|
	|		Returns true for success, false for fail.	|
	|													|
	\***************************************************/
	
	
		private function login_user($username, $password) {
	
			$this->user = new user();
	
	
			if (!$this->user->load(array('username' => $username,
										 'passwd' => $password,))) {
				return false;
			}
	
			$this->get_user();
			$this->get_user()->get_group();
	
			$timeout = $this->get_user()->get_group()->get_timeout();
	
			$this->update_expiry($this->get_user()->get_group()->get_timeout());
	
			if ($this->get_user()->get_passwd() == $password) {
				$this->isFirstLogin = true;
			}
	
			fns_log::log('User Login', $this->get_user()->get_username());

			$this->load_permissions();
			$this->load_site_permissions();
			return true;
	
		}
	
/*
		private function load_permissions() {
			$perm = $GLOBALS['__db__']->fetchAll(
							'select ' .
								'p.tag, p.name, p.menu_membership ' .
							'from ' .
								'permissions p, users u ' .
							'where ' .
								'u.access_list like "%:p.tag;%"');
		}
*/
	
	/***************************************************\
	|	retrieve_permissions(Menu Identifier)			|
	|													|
	|		Returns an array of resources which the		|
	|		user has permission to access along with	|
	|		url of the resource.						|
	|		If you pass it a Menu Identifier, it will	|
	|		return only those entries which the user	|
	|		can access AND which are entries of the		|
	|		specified Menu.								|
	|		(Menu Identifier refers to an entry within	|
	|		a colon-dileminated list in the database	|
	|		under (hrc.permissions.menu_membership)		|
	|													|
	\***************************************************/
		public function retrieve_permissions($menu = false) {
			if (!$menu) {
				return $this->permissions;
			} else {
				if (isset($this->menus[$menu])) {
					return $this->menus[$menu];
				} else {
					return false;
				}
			}
		}

		private function load_permissions() {
			$access_query = "select p.tag, p.name, p.url, p.menu_membership from ".
							resource::$table	.	" p, " .
							   group::$table	.	" g, " .
								user::$table	.	" u "  .
							" where	((1 << (p.access-1)) & (g.access | u.access)) ".
							"	and	(u.uid = " . $this->get_user()->get_id() . ") ".
							"	and	(u.gid = g.gid) " . 
							"	order by p.access";
	
			$perm = $GLOBALS['__db__']->fetchAll($access_query);
			foreach ($perm as $p) {
				$this->permissions[$p['tag']] = array(
													'name' => $p['name'],
													'url'  => $p['url'],
													'menus'=> $p['menu_membership'],
												);
				foreach (explode(':', $p['menu_membership']) as $m) {
					if ($m == '') continue;
					if (!isset($this->menus[$m])) $this->menus[$m] = array();
					$this->menus[$m][$p['tag']] =& $this->permissions[$p['tag']];
				}
			}
			return true;
		}
	
	/***************************************************\
	|	validate_permission(page tag)			   		|
	|													|
	|		This is the query to test your group's		|
	|		permissions for the requested resource.  It	|
	|		does a bitwise OR on the group and user's	|
	|		access #'s, then a bitwize AND against the	|
	|		access # of resource matching the page tag  |
	|		If a match is found, it returns the name of	|
	|		the resource, else it returns '!!!DENY!!!'	|
	|													|
	\***************************************************/
		public function validate_permission($access_tag, $logit = true) {
			if (isset($this->permissions[$access_tag])) {
				return true;
			} else {
				if ($logit) {
					fns_log::log("Denied Access to Resource \'$access_tag\'", $this->get_user()->get_username());
				}
				return false;
			}
		}
		public function get_title($access_tag) {
			if (isset($this->permissions[$access_tag]))
				return $this->permissions[$access_tag]['name'];
			else return false;
		}
		public function get_permission($access_tag = false) {
			if (!$access_tag) return $this->permissions;
			if (isset($this->permissions[$access_tag])) {
				return $this->permissions[$access_tag];
			} else {
				return false;
			}
		}
	/***************************************************\
	|	validate_site_permissions(Site ID)				|
	|													|
	|		This is performs the same function as the	|
	|		validate_permission function above, but		|
	|		for validating site permission of the given	|
	|		Site ID.  Returns the site label if access	|
	|		is granted, or '!!!DENY!!!' otherwize.		| 
	|													|
	\***************************************************/
		public function validate_site_permission($site_id) {
			if (isset($this->sites[$site_id])) {
				return true;
			} else {
				fns_log::log("Denied Access to Site \'$site_id\'", $this->get_user()->get_username());
			}
		}

		public function get_site_title($site_id) {
			if (isset($this->sites[$site_id]))
				return $this->sites[$site_id];
			else return false;
		}

	/***************************************************\
	|	retrieve_site_permissions()						|
	|													|
	|		This function returns all sites the user	|
	|		has access to.								|
	|													|
	\***************************************************/

		public function retrieve_site_permissions() {
			return $this->sites;
		}
	
		private function load_site_permissions() {
			$access_query = 'select s.id, s.name from ' .
						 site::$table	.	' s, ' .
						group::$table	.	' g, ' .
						 user::$table	.	' u ' .
				'where	((1 << (s.id-1)) & (g.site_access | u.site_access)) '.
				'  and	(u.uid = ' . $this->get_user()->get_id() . ') ' .
				'  and	(u.gid = g.gid)';
	
			$sites = $GLOBALS['__db__']->fetchAll($access_query);
			foreach ($sites as $s) {
				$this->sites[$s['id']] = $s['name'];
			}
			return true;
		}
	/***************************************************\
	|	update_password()								|
	|													|
	|		This function changed the password to what	|
	|		was received through form vars newpswd and	|
	|		confpswd									|
	|													|
	\***************************************************/
		public function update_password() {
			if (!isset($_POST['newpswd']) || !isset($_POST['confpswd'])) return false;
			$passwd = $_POST['newpswd'];
			if ($passwd != $_POST['confpswd']) return false;
			$updated = $GLOBALS['__db__']->query('update ' . user::$table . ' set ' . user::$fields['passwd'] . ' = password("' . $passwd . '") where ' . user::$fields['uid'] . ' = ' . $this->get_user()->get_id());
			return true;
		}
	/***************************************************\
	|	new_password()									|
	|													|
	|		This function checks for form vars used for	|
	|		changing the users password					|
	|													|
	\***************************************************/
	public function new_password() {
			if (!isset($_POST['change_password'])) return false;
			if (!isset($_POST['newpswd'])) return false;
			if (!isset($_POST['confpswd'])) return false;
			return true;
		}	
	
	}

	@session_name('cvuser');
	@session_start();


?>
