<?php
	require_once('uther.ezfw.php');

	class site {
	
		static $table = 'sites';
		static $fields = array(
				'id'	=>	'id',
				'name'	=>	'name',
				'scan'	=>	'scan_duration',
				'refresh'=>	'refresh_duration',
			);
	
		static $my_access = array(
				'add_room' => 'sites',
				'remove_room' => 'sites',
			);
	
		static function remote_access($func) {
			return isset(self::$my_access[$func])?self::$my_access[$func]:'';
		}
	
		/*************************************************************\
		\*************************************************************/
	//Universal Variable
		protected $loaded = false;
		protected $id = 0;
		protected $name = '';
	
		/*************************************************************\
		\*************************************************************/
	//Class Specific Variables
		protected $scan = 0;			//Temporary
		protected $refresh = 0;			//Temporary
		protected $rooms = array();
	
		/*************************************************************\
		\*************************************************************/
	//Cat Fields for db query
		static function db_fields() {
			$fields = '';
			foreach (site::$fields as $f) { $fields .= $f . ', '; }
			$fields .= '0';
			return $fields;
		}
	
		/*************************************************************\
		\*************************************************************/
	//Clear Room Data
		private function clear() {
			$this->id = 0;
			$this->name = '';
			$this->rooms = array();
			$this->loaded = false;
			$this->scan = 0;
			$this->refresh = 0;
	
		}
	
		/*************************************************************\
		\*************************************************************/
	//Populate Data
		private function set_data($data) {
			$this->id	  = $data[site::$fields['id']];
			$this->name	  = $data[site::$fields['name']];
			$this->scan	  = $data[site::$fields['scan']];
			$this->refresh= $data[site::$fields['refresh']];
			$this->loaded = true;
			$this->load_rooms();
	
		}
	
		/*************************************************************\
		\*************************************************************/
	//Constructor
		function __construct ($id = false) {
			get_db_connection();
			$this->clear();
			if ($id) {
				$this->id = $id;
				return $this->load();
			} else {
				return true;
			}
		}
	
		/*************************************************************\
		\*************************************************************/
	//Load Room Data from ID
		public function load() {
			if ($this->loaded) $this->clear();
	
			$query = 'select ' . site::db_fields() . ' from ' . site::$table . ' where ' . $GLOBALS['__db__']->quoteInto('id = ?', $this->id);
			$this_site = $GLOBALS['__db__']->fetchRow($query);
			if (!count($this_site)) {
				return false;
			} else {
				$this->set_data($this_site);
				return true;
			}
		}
	
		/*************************************************************\
		\*************************************************************/
	
		public function is_loaded	(  )	{ return $this->loaded; }
		public function get_id		(  )	{ return $this->id;		}
	
		public function get_name	(  )	{ return $this->name;	}
		public function set_name	($n)	{ $this->name = $n;		}
	
		public function get_scan	(  )	{ return $this->scan;	}
		public function set_scan	($s)	{ $this->scan = $s;		}
	
		public function get_refresh	(  )	{ return $this->refresh;}
		public function set_refresh ($r)	{ $this->refresh = $r;	}
	
		public function get_rooms	(  )	{ return $this->rooms; }
		public function get_room	($n)	{
			if (isset($this->rooms[$n]))
				return $this->rooms[$n];
			else 
				return false;
		}
		/*************************************************************\
		\*************************************************************/
	
		public function save() {
			if (!$this->loaded) return false;
	
			$update_fields = array(	site::$fields['name'] => $this->name,
									site::$fields['scan'] => $this->scan,
									site::$fields['refresh'] => $this->refresh,
							);
			$where = $GLOBALS['__db__']->quoteInto(site::$fields['id'] . ' = ?', $this->id);
			$r = $GLOBALS['__db__']->update(site::$table, $update_fields, $where);
	
			if (count($r))
				return true;
			else
				return false;
		}

	
		/*************************************************************\
		\*************************************************************/
	
		static function add_room($site_id, $room_name) {
			$room = room::get_by_name($room_name);
			if (!$room)
				return array('error' => 'Room Not Found!');
	
			if ($room->in_site($site_id))
				return array('error' => 'Room Already In Site!');
	
			$room->add_site($site_id);
			if (!$room->save())
				return array('error' => 'Unknown Error! (during save)');
	
			return array('success' => 'Room '.$room_name.' added to site #'.$site_id.'!');
		}
	
		/*************************************************************\
		\*************************************************************/
	
		static function remove_room($site_id, $room_name) {
			$room = room::get_by_name($room_name);
			if (!$room)
				return array('error' => 'Room Not Found!');
	
			if (!$room->in_site($site_id))
				return array('error' => 'Room Not In Site!');
	
			$room->remove_site($site_id);
			if (!$room->save())
				return array('error' => 'Unknown Error! (during save)');
	
			return array('success' => 'Room '.$room_name.' removed from site #'.$site_id.'!');
		}

		/*************************************************************\
		\*************************************************************/

		static function get_rooms_in_site($site = false) {
			if ($site !== false) {
				$mysite = new site($site);
				if (!$mysite->is_loaded()) return false;
				return $mysite->get_rooms();
			} else {
				return room::get_all_rooms();
			}
		}

		private function load_rooms() {
			$this->rooms = array();	

			$q = $GLOBALS['__db__']->quoteInto(room::$fields['sites'] . ' like ?', '%;'.$this->get_id().':%');
	
			$ids = $GLOBALS['__db__']->fetchAll('select ' . room::$fields['id'] . ' from ' . room::$table . ' where ' . $q . ' order by ' . room::$fields['name']);
			foreach ($ids as $id)
				$this->rooms[$id['id']] = new room($id['id']);
	
			return true;
		}
		
		/*************************************************************\
		\*************************************************************/
		static function fetch($param, $value) {
			return unserialize(file_get_contents('http://server.cv-internal.com/hrc.new/fetch/site.php?p='.$param.'&v='.$value));
		}
	}
