<?php
	require_once('uther.ezfw.php');

	class node {

		/*****************************************\
		\*****************************************/

		static $table = 'nodes';
		static $identifier = 'id';
		static $fields = array (
						'id' 		=> 'id',
						'room_id'	=> 'room_id',
						'info'		=> 'info',
						'system_mac'=> 'system_mac',
					);

		/*****************************************\
		\*****************************************/

		protected $id;
		protected $room_id = false;
		protected $room;
		protected $info;
		protected $system_ip;
		protected $system_mac;

		protected $loaded = false;
		protected $changed = false;


		/*****************************************\
		\*****************************************/

		function __construct($id = false) {
			get_db_connection();

			if (!$id === false) {
				if (is_array($id)) {
					$this->load($id);
				} else {
					$this->load(array('id' => $id));
				}
			}
			$this->room = new room();
		}


		function __sleep() {
			return array('id', 'room_id', 'info', 'system_ip', 'system_mac', 'loaded', 'changed');
		}
		function __wakeup() {
			get_db_connection();
			$this->room = new room();
		}

		/*****************************************\
		\*****************************************/

		public function get_id()		{ return $this->id; }
		public function get_room_id()	{ return $this->room_id; }
		public function get_info()		{ return $this->info; }
		public function get_ip()		{ return $this->system_ip; }
		public function get_mac()		{ return $this->system_mac; }
		public function is_loaded()		{ return $this->loaded; }
		public function last_heartbeat(){
			return $GLOBALS['__db__']->fetchOne('select last_heartbeat from nodes where id = ' . $this->get_id());
		}

		private function set_id($id)		{ $this->id = $id; }
		private function set_system_mac($id){ $this->system_mac = $id; }

		/*****************************************\
		\*****************************************/

		public function is_active() {
			$hb = $GLOBALS['__db__']->fetchOne('select ' . 
										'last_heartbeat ' .
									'from ' .
										'nodes ' .
									'where ' .
										'last_heartbeat > subtime(now(), "0 0:15:0.0") ' .
										'and id = ' . $this->get_id()
									);
			if ($hb === false || $hb == '') return false;
			else return true;
		}


		public function set_room_id($id) { 
			if (!$this->room_id === $id) {
				$this->room_id = $id;
				$this->changed = true;
				if ($this->room->is_loaded()) {
					$this->room = new room();
				}
			}
		}

		public function set_info($info) {
			if (!$this->info === $info) {
				$this->info = $info;
				$this->changed = true;
			}
		}

		public function set_ip($ip)		{
			if (!$this->system_ip === $ip) {
				$this->system_ip = $ip;
				$this->changed = true;
			}
		}

		public function set_mac($mac) {
			if (!$this->system_mac === $mac) {
				$this->system_mac = $mac;
				$this->changed = true;
			}
		}

		/*****************************************\
		\*****************************************/

		public function is_changed()	{
			switch (false) {
				case($this->is_loaded()):
					return false;
				break;
				case($this->changed):
					return false;
				break;
				default:
					return true;
				break;
			}
		}

		/*****************************************\
		\*****************************************/

		public function get_room() {
			if (!is_object($this->room)) {
				$this->room = new room();
			}

			if (!(get_class($this->room) === 'room')) {
					$this->room = new room();
			}

			if (!$this->room->is_loaded()) {
				if (!$this->get_room_id() === false) {
					if ($this->room->load(array('id' => $this->get_room_id()))) {
						$this->loaded = true;
					}
				}
			}
			return $this->room;
		}

		/*****************************************\
		\*****************************************/

		public function load_from_ip($ip) {
				if ($ip == '') return false;
				$mac = exec('cat /var/lib/misc/dnsmasq.leases | grep "' . $ip . '" | cut -d" " -f2');
				return $this->load(array('system_mac' => $mac));
		}

		/*****************************************\
		\*****************************************/

		public function load ($vals = false) {
			if ($vals === false) {
				if ($this->get_id() === false) {
					return false;
				} else {
					$vals = array('id' => $this->get_id());
				}
			}

			$clause = '1=1';

			foreach ($vals as $key => $value) {
				if (isset($value)) {
					$clause .= ' AND ' . $GLOBALS['__db__']->quoteInto($key . ' = ?', $value);
				}
			}

			$node_info = $GLOBALS['__db__']->fetchAll('select ' . implode(', ', self::$fields) .
											' from ' . self::$table . 
											' where ' . $clause);
			if (count($node_info) > 0) {
				foreach (self::$fields as $key => $value) {
					if (isset($node_info[0][$value])) {
						if (property_exists($this, $key)) {
							$this->$key = $node_info[0][$value];
						}
					}
				}
				$this->loaded = true;
				return true;
			} else {
				return false;
			}
		}

		/*****************************************\
		\*****************************************/

		public function link_to_room($room) {
			return $GLOBALS['__db__']->update(self::$table, array('room_id' => $room), $GLOBALS['__db__']->quoteInto(self::$fields['room_id'] . ' = ?', $this->get_room_id()));
		}	
		public function unassign_node() {
			return $GLOBALS['__db__']->update(
						self::$table,
						array('room_id' => '-1'),
						$GLOBALS['__db__']->quoteInto(
							self::$fields['room_id'] . ' = ?',
							$this->get_room_id()
						)
					);
		}
		/*****************************************\
		\*****************************************/

		public function has_room() {
			$room = $GLOBALS['__db__']->fetchAll('select ' . self::$fields['room_id'] . 
											' from ' . self::$table . 
											' where ' . $GLOBALS['__db__']->quoteInto(self::$fields['system_mac'] . ' = ?', $this->get_mac())
										);

			if (count($room) == 0) return false;
			if ($room[0]['room_id'] == '-1') return false;
			return true;
		}

		/*****************************************\
		\*****************************************/

		static function is_registered($mac) {
			get_db_connection();
			return (
					count(
						$GLOBALS['__db__']->fetchAll(
							'select ' . self::$fields['id'] . 
							' from ' . self::$table . 
							' where ' . $GLOBALS['__db__']->quoteInto(
								self::$fields['system_mac'] . ' = ?', $mac
							)
						)
					) ==0
				)?false:true;
		}

		/*****************************************\
		\*****************************************/

		static function register_node($mac, $room = '-1') {
			get_db_connection();
			if (!node::is_registered($mac)) {
				$insert = array(self::$fields['system_mac'] => $mac,
								self::$fields['room_id'] => $room,
							);
				$GLOBALS['__db__']->insert(self::$table, $insert);
			} else {
				$update = array( self::$fields['room_id'] => $room );
				$GLOBALS['__db__']->update(self::$table, $update, $GLOBALS['__db__']->quoteInto(self::$fields['system_mac'] . ' = ?', $mac));
			}
			$n = new node();
			$n->load(array('system_mac' => $mac));
			return $n;
		}

		/*****************************************\
		\*****************************************/

		static function unregister_node($mac) {
			get_db_connection();
			$GLOBALS['__db__']->delete(self::$table, $GLOBALS['__db__']->quoteInto(self::$fields['system_mac'] . ' = ?', $mac));
		}			

		/*****************************************\
		\*****************************************/
		static function fetch($param, $value) {
			return unserialize(file_get_contents('http://server.cv-internal.com/hrc.new/fetch/node.php?p='.$param.'&v='.$value));
		}

	}
