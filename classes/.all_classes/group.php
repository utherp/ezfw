<?php
	require_once('uther.ezfw.php');
	class group {
	
		/**************************************************\
		\**************************************************/
	
		static $table = 'groups';
		static $identifier = 'gid';
		static $fields = array (
				'gid'		=>	'gid',
				'name'		=>	'name',
				'access'	=>	'access',
				'site_access' => 'site_access',
				'start_tag'	=>	'start_page',
				'timeout'	=>	'timeout',
				'has_menu'	=>	'has_menu',
		);
		static function all_fields($save = false) {
			$tmp = '';
			foreach (self::$fields as $column) {
				if (!($save && $column == 'gid')) {
					$tmp .= $column . ', ';
				}
			}
			$tmp .= '0';
			return $tmp;
		}
	
		/**************************************************\
		\**************************************************/
	
		protected $gid;
		protected $name;
		protected $access;
		protected $site_access;
		protected $start_page;
		protected $start_tag;
		protected $has_menu = false;
		protected $timeout;
		protected $changed = false;
		protected $loaded = false;
	
		/**********************************\
		\**********************************/
	
		public function get_id()		{ return $this->gid; }
		public function get_name()		{ return $this->name; }
		public function get_access()	{ return $this->access; }
		public function get_site_access(){return $this->site_access; }
		public function get_start_page(){ return $this->start_page; }
		public function get_start_tag() { return $this->start_tag; }
		public function get_timeout()	{ return $this->timeout; }
		public function show_menu()		{ return $this->has_menu; }
		public function is_loaded()		{ return $this->loaded; }
	
		/**************************************************\
		\**************************************************/
	
		public function set_name($name) {
			$this->name = $name;
			$this->changed = true;
		}
		public function set_access($access) {
			$this->access = $access;
			$this->changed = true;
		}
		public function set_site_access($access) {
			$this->site_access = $access;
			$this->changed = true;
		}
		public function set_start_tag($resource_id) {
			$this->start_tag = $resource_id;
			$this->start_page = resource::get_start_url($resource_id);
			$this->changed = true;
		}
		public function set_timeout($timeout) {
			$this->timeout = $timeout;
			$this->changed = true;
		}
	
		/**************************************************\
		\**************************************************/
		function __sleep() {
			return array('gid', 'name', 'access', 'site_access', 'start_tag', 'start_page', 'changed', 'loaded', 'timeout');
		}
		function __wakeup() {
//			get_db_connection(); 
		}
		function __construct($id = false) {
			//get_db_connection();
 
			if ($id) {
				$this->{self::$identifier} = $id;
				if ($this->load(array(self::$identifier => $id))) {
					$this->loaded = true;
				}
			}
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
					$where .= ' AND ' . $GLOBALS['__db__']->quoteInto($column . ' = ?', $these_fields[$field_name]);
				}
			}
	
			$group = $GLOBALS['__db__']->fetchRow('select ' . self::all_fields() . ' from ' . self::$table . ' where ' .  $where);
			if (!$group) return false;
	
			return $this->set_all($group);
		}
	
		/**************************************************\
		\**************************************************/
	
		private function set_all($group) {
			foreach (self::$fields as $field_name => $column) {
				if (isset($group[$column])) {
					$this->$field_name = $group[$column];
				}
			}
			$this->loaded = true;
			$this->start_page = resource::get_start_url($this->start_tag);
			$this->has_menu = ($this->has_menu === '1')?true:false;
//			$this->has_menu = resource::has_menu($this->start_tag);
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
		static function fetch($param, $value) {
			return unserialize(file_get_contents('http://server.cv-internal.com/hrc.new/fetch/group.php?p='.$param.'&v='.$value));
		}
	}
