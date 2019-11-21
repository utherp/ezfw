<?php
	require_once('uther.ezfw.php');

	class flagsList {

		private $available;
		private $packing;
		public $name;
		private $list;

		/***************************************************\
		\***************************************************/

		function __construct($name, $available) {
			$this->name = $name;
			$this->list = new listObj($name, false, array('set' => array($this, 'validate_flag')));
			$this->packing = new packedList(',');
			$this->available = $available;

			$tmp = array();
			foreach ($this->available as $f)
				$tmp[$f] = false;

			$this->list->_set_list($tmp);
			return;
		}

		/***************************************************\
		\***************************************************/

		public function &get_listObj () { return $this->list; }
		public function &get_array () { return $this->list->_get_list(); }
		public function get_packed () {
			$set = array();
			foreach ($this->list->_get_list() as $k => $v)
				if ($v) $set[] = $k;
			return $this->packing->pack($set);
		}

		/****************************************************/
		
		public function set_flags ($flags) {
			if (is_string($flags))
				$flags = $this->packing->unpack($flags);

			if (is_numeric($flags)) { 
				foreach ($this->available as $k => $f)
					$this->list->$f = !!($flags & 1<<$k);

				return true;
			}

			if (!is_array($flags))
				return false;


			$numbered = true;
			foreach (array_keys($flags) as $i) {
				if (is_numeric($i)) continue;
				$numbered = false;
				break;
			}

			foreach ($this->available as $k => $f) {
				if ($numbered)
					$this->list->$f = !(array_search($f, $flags) === false);
				else if (isset($flags[$f]))
					$this->list->$f = !!$flags[$f];

				if (!isset($this->list->$f))
					$this->list->$f = false;
			}

			return true;
		}

		/****************************************************/
		
		protected function validate_flag ($action, $listName, $propName, &$value, &$list) {
			// if we're not setting on flags, return NULL to let the list do what it should
			if ($action != 'set' || $listName != $this->name) return NULL;

			if (!$propName || is_numeric($propName)) {
				if (array_search($value, $this->available) === false)
					return false;
				return $this->list->$value = true;
			}

			if (array_search($propName, $this->available) === false)
				return false;

			if (!is_bool($value))
				$value = !!$value;

			// we've verified the flag name, and ensured the flag value to boolean
			// now let the listObj do what it does...
			return NULL;
		}
	
	}


