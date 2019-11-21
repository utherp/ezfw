<?php
	require_once('uther.ezfw.php');

	class objectList extends listObj {
		private $orig_list;
		private $orig_flipped;
		private $class = false;
		private $load_callback;
		private $obj_ref;

		private $added = false;
		private $removed = false;

		/***************************************************\
		\***************************************************/

		function __construct ($name, $class = NULL, $recursive = false, $load_callback = NULL) {
			$callbacks = array(
							'get'=>array($this, '_obj_list_get'),
							'set'=>array($this, '_obj_list_set'),
							'remove'=>array($this, '_obj_list_remove')
					);

			$this->load_callback = $load_callback;

			if (!is_bool($recursive)) $recursive = false;

			parent::__construct($name, $recursive, $callbacks);
			$this->class = $class;

			$this->obj_ref = new $class();

			return;

		}

		/***************************************************\
		\***************************************************/

//		public function _get_list () { return parent::_get_list(); }

		public function _set_list (&$list, $init = false) {
			$init = (!is_array($this->added) || $init);
			$this->added = array();
			$this->removed = array();

			if (!is_array($list)) return false;

			$tmp = array_keys($list);
			if (!is_object($list[$tmp[0]]) || !is_a($list[$tmp[0]], $this->class))
				$list = array_flip(array_values($list));

			if ($init) {
				parent::_set_list($list);
				$this->orig_list = array();
				foreach ($list as $k => $v)
					$this->orig_list[$k] =& $v;
				return;
			}

			if (!is_array($this->orig_list)) $this->orig_list = array();

			foreach ($this->orig_list as $k => $v)
				if (!isset($list[$k]))
					$this->removed[$k] = true;

			foreach ($list as $k => $v)
				if (!isset($this->orig_list[$k]))
					$this->added[$k] = true;

			parent::_set_list($list);
			return;
		}

		/***************************************************\
		\***************************************************/

		public function _get_added() { return $this->added; }
		public function _get_removed(){return $this->removed; }

		/***************************************************\
		\***************************************************/

		public function _obj_list_get($act, $name, $key, &$value, &$list) {
			if ($act != 'get') return NULL;

			if ($obj = $this->validate_object($list[$key]))
				return $obj;

			if (!($list[$key] = $this->load_object($key)))
				unset($list[$key]);

			return $list[$key];
		}

		/***************************************************\
		\***************************************************/

		private function validate_object ($obj) {
			return (!$this->class || (($obj instanceOf $this->class) && (!($obj instanceOf ezObj) || $obj->is_loaded())))?$obj:NULL;
		}

		private function load_object ($key) {
			if ($this->validate_object($key)) return $key;

			if (is_callable($this->load_callback))
				return $this->validate_object(call_user_func($this->load_callback, $this->class, $key, $this->name));

			if ($this->obj_ref instanceOf ezObj)
				return $this->validate_object(call_user_func(array($this->class, 'fetch'), $this->class, $key));


			$cls = $this->class;
			return $this->validate_object(new $cls($key));
		}

		public function _obj_list_set($act, $name, $key, &$value, &$list) {
			if ($act != 'set') return NULL;
			$cls = $this->class;
			if (!$key) {
				if (!($obj = $this->load_object($value)) || !($obj instanceOf ezObj))
					return false;
				$key = $obj->get_identifier();
			} else if (!$value)
				return $this->_obj_list_remove('remove', $name, $key, $value, $list);
			else if (!($obj = $this->load_object($key)))
				return false;

			$list[$key] = $obj;

			if (isset($this->removed[$key]))
				unset($this->removed[$key]);

			if (!isset($this->orig_list[$key])) {
				$this->added[$key] =& $list[$key];
			}

			return true;
		}

		/***************************************************\
		\***************************************************/

		public function _obj_list_remove ($act, $name, $key, &$value, &$list) {
			if ($act != 'remove') return NULL;
			if (!isset($list[$key])) return true;

			if (isset($this->orig_list[$key]))
				$this->removed[$key] =& $list[$key];
			else if (isset($this->added[$key]))
				unset($this->added[$key]);

			unset($list[$key]);
			return true;
		}

		/***************************************************\
		\***************************************************/

	}
	

