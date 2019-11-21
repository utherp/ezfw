<?php
	require_once('uther.ezfw.php');


	class note {

		/*************************************************/
		static $table = 'patient_notes';
		static $identifier = 'id';
		static $fields = array (
						'id'		=>	'id',
						'patient_id'=>	'patient_id',
						'doctor_id'	=>	'doctor_id',
						'posted'	=>	'posted',
						'message'	=>	'message',
					);
		/*************************************************/
		protected $id;
		protected $patient_id = false;
		protected $patient;
		protected $doctor_id = false;
		protected $doctor;
		protected $posted;
		protected $message;
		protected $loaded = false;

		/*************************************************/
		public function get_id()		 { return $this->id; }
		public function get_patient_id() { return $this->patient_id; }
		public function get_doctor_id()	 { return $this->doctor_id; }
		public function get_posted()	 { return $this->posted; }
		public function get_message()	 { return $this->message; }
		public function is_loaded()		 { return $this->loaded; }

		/*************************************************/
		public function get_doctor()	{
			if (!is_object($this->doctor)) $this->doctor = new user();
			if (!$this->doctor->is_loaded()) {
				if (!$this->doctor_id) return false;
				if (!$this->doctor->load(array('uid' => $this->doctor_id))) return false;
			}
			return $this->doctor;
		}
		/*************************************************/
		public function get_patient()	{
			if (!is_object($this->patient)) $this->patient = new patient();
			if (!$this->patient->is_loaded()) {
				if (!$this->patient_id) return false;
				if (!$this->patient->load(array('id' => $this->patient_id))) return false;
			}
			return $this->patient;
		}
		/*************************************************/


		/*************************************************\
		\*************************************************/
		function __construct($id = false) {
			get_db_connection();
			if (is_array($id)) {
				$this->load($id);
			} else if ($id !== false) {
				$this->load(array('id' => $id));
			}
		}
		/*************************************************/
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
			$this_note = $GLOBALS['__db__']->fetchRow('select * from ' . self::$table . ' where ' . $where);

			return $this->set_all($this_note);
		}
		/*************************************************/
		private function set_all($note) {
	
			foreach (self::$fields as $field_name => $column) {
				if (isset($note[$column])) {
					$this->$field_name = $note[$column];
				}
			}
	
			$this->loaded = true;
			return true;
		}
		/*************************************************/
		static function new_note($patient_id, $doctor_id, $message) {
			$insert_fields = array(
				self::$fields['patient_id']	=> $patient_id,
				self::$fields['doctor_id']	=> $doctor_id,
				self::$fields['message']	=> $message
			);

			get_db_connection();
			return $GLOBALS['__db__']->insert(self::$table, $insert_fields);
		}
		/*************************************************\
		\*************************************************/
		static function fetch($param, $value) {
			return unserialize(file_get_contents('http://server.cv-internal.com/hrc.new/fetch/note.php?p='.$param.'&v='.$value));
		}
	}
