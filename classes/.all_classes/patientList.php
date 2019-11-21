<?php
	require_once('uther.ezfw.php');
	class patientList {

		protected $patients = array();
		protected $index = -1;
		protected $total_patients = 0;

		function __construct() {
			$this->all_patients();
		}

		public function all_patients() {

			get_db_connection();
			$all = $GLOBALS['__db__']->fetchAll('select ' . patient::$fields['id'] . ' from ' . patient::$table);

			$total = 0;
			foreach ($all as $p) {
				$patient = new patient();
				$patient->set_id($p['id']);
				$total = array_push($this->patients, $patient);
			}
			$this->total_patients = $total;
			return $total;
		}

		public function from_list($i) {
			if (!isset($this->patients[$i])) return false;
			if (!$this->patients[$i]->is_loaded()) $this->patients[$i]->load();
			return $this->patient[$i];
		}

		public function this() {
			if ($this->index == -1) $this->first();
			if (!isset($this->patients[$this->index])) return false;
			if (!$this->patients[$this->index]->is_loaded()) $this->patients[$this->index]->load();
			return $this->patients[$this->index];
		}

		public function next() {
			$this->index++;
			if ($this->index >= $this->total_patients) {
				$this->last();
				return false;
			}
			return true;
		}

		public function previous() {
			$this->index--;
			if ($this->index < 0) {
				$this->first();
				return false;
			}
			return true;
		}

		public function first() {
			$this->index = 0;
			return true;
		}

		public function last() {
			$this->index = $this->total_patients-1;
			return true;
		}
		static function fetch($param, $value) {
			return unserialize(file_get_contents('http://server.cv-internal.com/hrc.new/fetch/patientList.php?p='.$param.'&v='.$value));
		}
	}
