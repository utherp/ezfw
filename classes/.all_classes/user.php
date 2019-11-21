<?php
	require_once('uther.ezfw.php');

	class user {
	
		/**************************************************\
		\**************************************************/
	
		static $table = 'users';
		static $identifier = 'uid';
		static $pw_field = 'passwd';
		static $fields = array (
				'uid'		=>	'uid',
				'username'	=>	'username',
				'passwd'	=>	'passwd',
				'gid'		=>	'gid',
				'name'		=>	'name',
				'site_access' => 'site_access',
				'access'	=>	'access',
		);
		static function all_fields() {
			$tmp = '';
			foreach (self::$fields as $column) $tmp .= $column . ', ';
			$tmp .= '0';
			return $tmp;
		}
	
		/**************************************************\
		\**************************************************/
	
		protected $uid;
		protected $gid;
		protected $username = '';
		protected $name;
		protected $group;
		protected $passwd;
		protected $access;
		protected $site_access;
		protected $changed = false;
		protected $loaded = false;
	
		/**********************************\
		\**********************************/
	
		public function get_id()		{ return $this->uid;	}
		public function get_username()	{ return $this->username; }
		public function get_name()		{ return $this->name; }
		public function get_gid()		{ return $this->gid; }
		public function get_access()	{ return $this->access; }
		public function get_site_access(){return $this->site_access; }
		public function get_passwd()	{ return $this->passwd; }
		public function get_group()		{
			if (!$this->group->is_loaded()) {
				if ($this->get_gid()) {
					$this->group->load(array('gid' => $this->get_gid(),));
				}
			}
			return $this->group;
		}
		public function is_loaded()		{ return $this->loaded; }
	
		/**************************************************\
		\**************************************************/
	
		public function set_passwd($pw) {
			$this->passwd = $pw;
			$this->changed = true;
		}
		public function set_username($un) {
			$this->username = $un;
			$this->changed = true;
		}
		public function set_name($name) {
			$this->name = $name;
			$this->changed = true;
		}
		public function set_gid($id) {
			$this->gid = $id;
			$this->group = new group();
			$this->changed = true;
		}
	
		/**************************************************\
		\**************************************************/
		function __sleep() {
			return array('uid', 'gid', 'username', 'name', 'passwd', 'access', 'site_access', 'loaded', 'changed', 'group');
		}
		function __wakeup() {
//			get_db_connection();
//			$this->group = new group();
		}
	
		function __construct($id = false) {
//			get_db_connection(); 
			if ($id) {
				$this->{self::$identifier} = $id;
				if ($this->load(array(self::$identifier => $id))) {
					$this->loaded = true;
				}
			}
			$this->group = new group();
		}
	
		/**************************************************\
		\**************************************************/
	
		public function load($these_fields = false) {
			if (!$these_fields) {
				if ($this->{self::$identifier}) {
					$these_fields = array(self::$identifier => $this->{self::$identifier});
				} else {
					return false;
				}
			}
	
			$where = '1=1';
	
			foreach (self::$fields as $field_name => $column) {
				if (isset($these_fields[$field_name])) {
					if ($column == self::$pw_field) {
						$where .= ' AND ((' .
							$GLOBALS['__db__']->quoteInto($column . ' = ? ', $these_fields[$field_name])
							. ') OR (' .
							$GLOBALS['__db__']->quoteInto($column . ' = password(?)', $these_fields[$field_name])
							. '))';
					} else {
						$where .= ' AND ' . $GLOBALS['__db__']->quoteInto($column . ' = ?', $these_fields[$field_name]);
					}
				}
			}
	
			$this_doc = $GLOBALS['__db__']->fetchRow('select ' . self::all_fields() . ' from ' . self::$table . ' where ' .  $where);
	
			if (!$this_doc) return false;
	
			return $this->set_all($this_doc);
		}
	
		/**************************************************\
		\**************************************************/
	
		private function set_all($doc) {
	
			foreach (self::$fields as $field_name => $column) {
				if (isset($doc[$column])) {
					$this->$field_name = $doc[$column];
				}
			}
	
			$this->loaded = true;
			return true;
		}
		/**************************************************\
		\**************************************************/
		public function save() {
			if (!$this->changed) return true;
	
			$update_fields = array();
	
			foreach (self::$fields as $field_name => $column) {
				if ($field_name != self::$identifier) {
					$update_fields[$column] = $this->$field_name;
				}
			}
	
			$rows = $GLOBALS['__db__']->update(self::$table, $update_fields, $GLOBALS['__db__']->quoteInto(self::$fields[self::$idenifier] . ' = ?', $this->{self::$identifier}));
	
			if ($rows) return true;
			return false;
		}
		/**************************************************\
		\**************************************************/
		public function get_all_patients() {
			$patients = array();
			foreach ($GLOBALS['__db__']->fetchAll('select ' . patient::$fields['id'] .
									' from ' . patient::$table .
									' where '. patient::$fields['doctor_id'] . ' like "%:' . $this->get_id() . ':%"' .
									' or ' . patient::$fields['doctor_id'] . ' = ' . $this->get_id()) as $p) {
				array_push($patients, new patient($p));
			}
			return $patients;
		}
		/**************************************************\
		\**************************************************/
		/**************************************************\
		\**************************************************/
	
		static function fetch($param, $value) {
			return unserialize(file_get_contents('http://server.cv-internal.com/hrc.new/fetch/user.php?p='.$param.'&v='.$value));
		}
	}
