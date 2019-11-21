<?php
	require_once('uther.ezfw.php');

	class room {

		/***************************************************\
		\***************************************************/

		static $table = 'rooms';
		static $fields = array(
				'id'		=>	'id',
				'name'		=>	'name',
				'sites'		=>	'sites',
				'phone'		=>	'phone_number',
				'hostname'	=>	'hostname',
				'registered'=>	'registered',
				'privacy'	=>	'privacy',
			);
		static $my_access = array(
				'search' => 'rooms',
			);

		/***************************************************\
		\***************************************************/

		protected $id = false;
		protected $name = '';
		protected $sites = array();
		protected $phone = '';
		protected $loaded = false;
		protected $ip = '';
		protected $patient;
		protected $patient_id;
		protected $hostname;
		protected $privacy;
		protected $registered = false;
		protected $node;
		protected $positions;
		protected $data = array();

		/***************************************************\
		\***************************************************/

		//Get Remote Access Permission
		static function remote_access($func) {
			return isset(self::$my_access[$func])?self::$my_access[$func]:'';
		}

		/***************************************************\
		\***************************************************/

		//Cat Fields for db query
		static function db_fields() {
			$fields = '';
			foreach (self::$fields as $f) { $fields .= $f . ', '; }
			$fields .= '0';
			return $fields;
		}

		/***************************************************\
		\***************************************************/

		//Clear Room Data
		private function clear() {
			$this->id = 0;
			$this->name = '';
			$this->sites = array();
			$this->phone = '';
			$this->loaded = false;
		}

		/***************************************************\
		\***************************************************/
	
		//Populate Room Data
		private function load_room($data) {
			$this->id = $data[self::$fields['id']];
			$this->name = $data[self::$fields['name']];
			$this->sites = $data[self::$fields['sites']];
			$this->phone = $data[self::$fields['phone']];
			$this->hostname = $data[self::$fields['hostname']];
			$this->privacy = ($data[self::$fields['privacy']]=='1')?true:false;
			$this->registered = ($data[self::$fields['registered']]=='1')?true:false;
			$this->loaded = true;

//			if ($this->registered) {
//				$patient_id = $GLOBALS['__db__']->fetchOne('select 

		}
	
		/***************************************************\
		\***************************************************/
	
		function __sleep() {
			return array('id', 'name', 'sites', 'phone', 'loaded', 'ip', 'patient_id', 'patient', 'privacy', 'hostname', 'data');
		}
		function __wakeup() {
			get_db_connection(); 
//			$this->patient = new patient();
		}
	
		/***************************************************\
		\***************************************************/
	
		function __construct ($id = false) {
			get_db_connection(); 
			$this->clear();
			if ($id) {
				$this->id = $id;
//				return $this->load();
			} else {
				return true;
			}
		}
	
		/***************************************************\
		\***************************************************/
	
		public function load($vals = false) {
			if ($vals === false) {
				if ($this->get_id() && !$this->loaded) {
					$vals = array('id' => $this->get_id());
				} else {
					return false;
				}
			}
			if ($this->loaded) { $this->clear(); }

            $where = '1=1';

            foreach (self::$fields as $key => $value) {
                if (isset($vals[$key])) {
                    $where .= ' AND ' . $GLOBALS['__db__']->quoteInto($key . ' = ?', $vals[$key]);
                }
            }

			$this_room = $GLOBALS['__db__']->fetchRow('select ' . implode(', ', self::$fields) .
											' from ' . self::$table . 
											' where ' . $where);
			if (count($this_room)) {
				$this->load_room($this_room);
				return true;
			}
		}
	
		/***************************************************\
		\***************************************************/
	
		public function set_id ($i) {
			if ($i != $this->id) {
				if ($this->loaded) $this->clear();
				$this->id = $i;
				return $this->load();
			}
		}
	
		/***************************************************\
		\***************************************************/
	
		public function is_loaded	(  )	{ return $this->loaded; }
		public function get_id		(  )	{ return $this->id;		}
	
		public function get_name	(  )	{ return $this->name;	}
		public function set_name	($n)	{ $this->name = $n;		}
	
		public function get_sites	(  )	{ return $this->sites;	}
		public function set_sites	($s)	{ $this->sites = $s;	}
	
		public function get_phone	(  )	{ return $this->get_data('Phone Number');	}
		public function set_phone	($p)	{ $this->phone = $p;	}
		public function get_ip		(  )	{ return $this->ip;		}

		public function get_hostname(  )	{ return $this->hostname; }
		public function set_hostname($h)	{ $this->hostname = $h; }
	
		public function get_patient_id ()	{ return $this->patient_id; }

		public function get_data($n)		{ return isset($this->data[$n])?$this->data[$n]:false; }

		public function get_patient($force=false)	{
			$this->patient= load_object('patient',$force);

			if (!is_object($this->patient))
				$this->patient = new patient();

			return $this->patient;
		}

		private function get_all_positions() {
			$tmp = $GLOBALS['__db__']->fetchAll(
							'select ' .
								'uid, site, template, x, y, z, position ' .
							'from ' .
								'positions ' .
							'where ' .
								'uid = '.$_SESSION['_login_']->get_user()->get_id() .
							' or ' .
								'uid < 0'
						);
			$this->positions = array();
			$defaults = array();
			foreach ($tmp as $pos) {
				if (intval($pos['uid']) < 0) { 
					array_push($defaults, $pos);
				} else {
					if (!isset($this->positions[$pos['site']]))
						$this->positions[$pos['site']] = array();
					$this->positions[$pos['site']][$pos['template']] = $pos;
				}
			}
			foreach ($defaults as $pos) {
				if (!isset($this->positions[$pos['site']]))
					$this->positions[$pos['site']] = array();

				if (!isset($this->positions[$pos['site']][$pos['template']]))
					$this->positions[$pos['site']][$pos['template']] = $pos;
			}
		}

		public function get_position($site, $template) {
			if (!isset($this->positions)) $this->get_all_positions();
			if (isset($this->positions[$site]))
				if (isset($this->positions[$site][$template]))
					return $this->positions[$site][$template];
				else
					return $this->positions[$site];
			return array('x'=>0, 'y'=>0,'z'=>0);
		}

		public function set_position($site, $template, $x, $y, $z) {
			if (intval($site) < 1) return false;
			$values = array();
			foreach (array('template','site','x','y','z') as $param)
				if ($$param != -1) $values[$param] = $$param;
			$values['uid'] = $_SESSION['_login_']->get_user()->get_id();
			if (!isset($this->positions)) $this->get_all_positions();
			if (isset($this->positions[$site]) && isset($this->positions[$site][$template])) {
				$GLOBALS['__db__']->update('positions', $values, $GLOBALS['__db__']->quoteInto("site = $site and template = ?", $template));
			} else {
				$GLOBALS['__db__']->insert('positions', $values);
			}
		}
		/***************************************************\
		\***************************************************/
	
		public function in_site ($site_id) {
			if (strpos('.'.$this->sites, ';'.$site_id.':'))
				return true;
			return false;
		}
	
		/***************************************************\
		\***************************************************/
		public function link_to_site ($site_id) {	
	
			if ($this->in_site($site_id)) return false;
	
			$this->sites .= ';'.$site_id.':';
			return true;
		}
		public function add_site ($site_id) { $this->link_to_site($site_id); }
		/***************************************************\
		\***************************************************/
		public function unlink_from_site ($site_id) {
			if (!$this->in_site($site_id)) return false;
	
			$this->sites = str_replace(';'.$site_id.':', '', $this->sites);
			return true;
		}
		public function remove_site ($site_id) { $this->unlink_from_site($site_id); }
		/***************************************************\
		\***************************************************/

		public function check_privacy_mode () {
			$m = $GLOBALS['__db__']->fetchOne('select ' . self::$fields['privacy'] . 
										' from ' . self::$table . 
										' where ' . self::$fields['id'] . ' = ' . $this->get_id());
			if ($m == NULL) {
				$this->privacy = false;
			} else {
				$this->privacy = true;
			}
			return $this->privacy;
		}

		public function privacy_mode ($mode = -1) {
			if (($mode !== -1) && (($mode === true) || ($mode === false))) {
				$this->privacy = $mode;
				$set_field = ($mode)?'default':'NULL';
				$GLOBALS['__db__']->query(
					'update ' . self::$table . 
						' set privacy = ' . $set_field . 
						' where ' . self::$fields['id'] . ' = ' . $this->get_id());
	//			$this->save();
			}
			return $this->privacy;
		}


		/***************************************************\
		\***************************************************/
	
		public function save() {
			if (!$this->loaded) return false;
	
			$update_fields = array(	room::$fields['name'] => $this->name,
									room::$fields['sites'] => $this->sites,
									room::$fields['phone'] => $this->phone,
									room::$fields['privacy'] => $this->privacy?'1':'0',
							);
	
			$where = $GLOBALS['__db__']->quoteInto(room::$fields['id'] . ' = ?', $this->get_id());
	
			$r = $GLOBALS['__db__']->update(room::$table, $update_fields, $where);
			if (count($r)) return true; else return false;
	
		}
	
		/***************************************************\
		\***************************************************/

		static function get_rooms_in_site($site = false) {
			return site::get_rooms_in_site($site);
		}
		static function get_all_rooms() {
			get_db_connection();
	
			$rooms = array();
	
			$ids = $GLOBALS['__db__']->fetchAll('select ' . room::$fields['id'] . ' from ' . room::$table . ' order by ' . room::$fields['name']);

			foreach ($ids as $id)
				$rooms[$id['id']] = new room($id['id']);
	
			return $rooms;
		}
	
		/***************************************************\
		\***************************************************/
	
		static function get_by_name($name) {
			get_db_connection();
			$q = $GLOBALS['__db__']->quoteInto(room::$fields['name'] . ' = ?', $name);
	
			$room = $GLOBALS['__db__']->fetchRow('select ' . room::$fields['id'] . ' from ' . room::$table . ' where ' . $q);
			if (!count($room))
				return false;
	
			return new room($room['id']);
		}
	
		/***************************************************\
		\***************************************************/
	
		//TEMPORARY FIXES
		static function search($string) {
			get_db_connection();
	
	
			$string = $GLOBALS['__db__']->quoteInto('camera_location like ?', '%'.$string.'%');
	
			$query = 'select camera_location as name, sys_ip as ip from tblsar where sys_ip != 0 and ' . $string;
			$rooms = $GLOBALS['__db__']->fetchAll($query);
	
			return array('rooms' => array('room' => $rooms));
	
		}
	
		/***************************************************\
		\***************************************************/

		static function get_unassigned_rooms() {
			get_db_connection();
			return $GLOBALS['__db__']->fetchAll('select ' .
									self::$fields['id'] . ', ' . self::$fields['name'] .
								' from ' . 
									self::$table . 
								' where ' . 
									self::$fields['registered'] . ' != 1' .
								' order by name '
								);
		}

		/***************************************************\
		\***************************************************/

/*		public function is_registered() {
			$r = $GLOBALS['__db__']->fetchOne('select '.
									self::$fields['registered'] .
								' from ' . 
									self::$table .
								' where ' . 
									$GLOBALS['__db__']->quoteInto(self::$fields['id'] . ' = ?', $this->get_id())
							);
			if ($r == 0 ) return false;
			return true;
		}
*/
		public function is_registered() { return ($this->registered)?true:false; }
		/***************************************************\
		\***************************************************/

		public function register($mac) {
			$update = array( 'registered' => 1 );
			$GLOBALS['__db__']->update(self::$table, $update, $GLOBALS['__db__']->quoteInto(self::$fields['id'] . ' = ?', $this->get_id()));

			node::register_node($mac, $this->get_id());

			if (is_file('/etc/ethers')) {
				if (is_writable('/etc/ethers')) {
					$ethers = fopen('/etc/ethers', 'a');
					fwrite($ethers, $mac . "\t" . $this->hostname . "\n");
					fclose($ethers);
					exec('printf "blah" > /usr/local/bin/restart_dnsmasq.pipe &');
//					exec('sudo /usr/local/bin/restart_dnsmasq', $o, $ret);
					return ($ret == -1)?false:$ret;
				}
				return "ERROR: Ethers file is not writtable! (" . exec('/usr/bin/whoami') . ')';
			}
			return "ERROR: Ethers file does not exist!";
		}

		/***************************************************\
		\***************************************************/
		public function get_node_id() {
			return $GLOBALS['__db__']->fetchOne('select id from nodes where room_id = ' . $this->get_id());
		}
		public function get_node() {
			if (!is_object($this->node)) {
				$nid = $this->get_node_id();
				if ( $nid === false || $nid === '' ) {
					return false;
				} else {
					$this->node = new node($nid);
				}
			}
			if (!$this->node->is_loaded()) {
				return false;
			}
			return $this->node;
		}
		/***************************************************\
		\***************************************************/

		public function unregister_room() {
			
			$update = array ('registered' => 0 );
			$GLOBALS['__db__']->update(self::$table, $update, $GLOBALS['__db__']->quoteInto(self::$fields['id'] . ' = ?', $this->get_id()));

			$lines = file('/etc/ethers');
			$lines[count($lines)-1] = preg_replace('/\n/', '', $lines[count($lines)-1]);
			$ethers = fopen('/etc/ethers', 'w');
			foreach ($lines as $l) {
				$l = preg_replace('/\x0a/', '', $l);
				if (!preg_match('/' . $this->hostname . '$/', $l)) {
					if ($l != "\n") fwrite($ethers, $l . "\n");
				}
			}
			fclose($ethers);
			//exec('printf "blah" > /usr/local/bin/restart_dnsmasq.pipe &');
			$this->get_node()->unassign_node();
		}

		/***************************************************\
		\***************************************************/

		public function delete_room() {
			return $GLOBALS['__db__']->delete(self::$table, $GLOBALS['__db__']->quoteInto(self::$fields['id'] . ' = ?', $this->get_id()));
		}

		/***************************************************\
		\***************************************************/

		static function check_old_privacy() {
			get_db_connection();
			return $GLOBALS['__db__']->update(
							self::$table,
							array('privacy'=>NULL),
							"privacy < (now() - INTERVAL 10 MINUTE)"
						);
		}

		/***************************************************\
		\***************************************************/

		static function fetch($param, $value) {
			return unserialize(file_get_contents('http://server.cv-internal.com/hrc.new/fetch/room.php?p='.$param.'&v='.$value));
		}
	}
