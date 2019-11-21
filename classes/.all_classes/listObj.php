<?php
	class listObj implements ArrayAccess {
		protected $callbacks = array(
				'set'	=>	NULL,
				'get'	=>	NULL, 
				'remove'=>	NULL,  
				'keys'	=>	NULL, 
				'isset'	=>	NULL
		);
		protected $ref = array('list'=>false);
		protected $name = '';
		protected $recursive = false;
		protected $sublists = array();
		private $single = false;

		/*
			Update callback gets called with these parameters:

			update($ACTION, $NAME, $KEY, &$VALUE, &$LIST);

				ACTION:	[add, remove], action upon list
				NAME:	Name of the list,
						given as first param to the constructor
				KEY:	affected key name of the list value
				VALUE:	value to set if action is 'set'
				LIST:	reference to the array
						givin as second param to constructor

		*/

		function __construct ($name, $recursive = false, $callbacks = NULL) {
			$this->name = $name;
			$this->recursive = $recursive;

			do {
				if (!isset($callbacks)) break;
				if (!is_array($callbacks)) break;
				if (is_object($callbacks[0])) break;
				if (count($callbacks) == 2 && isset($callbacks[1]) && isset($callbacks[0])) {
					$classes = array_flip(get_declared_classes());
					if (isset($classes[$callbacks[0]])) break;
				}

				foreach (array_keys($this->callbacks) as $k)
					$this->callbacks[$k] = $callbacks[$k];

				return;
			} while (0);

			$this->single = true;
			$this->callbacks = $callbacks; 
			return;
		}

		public function &_get_list() {
			return $this->ref['list'];
		}

		public function _set_list(&$list) {
			unset($this->ref['list']);
			$this->ref['list'] = &$list;
			return;
		}

		public function offsetExists ($offset) { return $this->__isset($offset); }
		public function offsetUnset ($offset) { return $this->__unset($offset); }
		public function offsetGet ($offset) { return $this->__get($offset); }
		public function offsetSet ($offset, $value) { return $offset?$this->set($offset, $value):$this->set($value); }

		public function __get($name) {
			if (!$this->ref['list']) return false;
			return $this->_update('get', $name, $val=false, $this->ref['list']);
		}

		public function __set($name, $value) {
			if (!$this->ref['list']) return false;
			$this->_update('set', $name, $value, $this->ref['list']);
			return;
		}

		public function __unset($name) {
			if (!$this->ref['list']) return false;
			$this->_update('remove', $name, $val=false, $this->ref['list']);
		}

		public function __isset($name) {
			if (!$this->ref['list']) return false;
			return $this->_update('isset', $name, $val=false, $this->ref['list']);
		}

		public function set($name, $value = '!!!NONE!!!') {
			if (!is_array($this->ref['list'])) $this->ref['list'] = array();
			if ($value === '!!!NONE!!!') {
				$value = $name;
				$name = '';
			}
			return $this->_update('set', $name, $value, $this->ref['list']);
		}

		public function add($value) {
			if (!$this->ref['list']) return false;
			return $this->_update('set', '', $value, $this->ref['list']);
		}

		public function remove($name) {
			if (!$this->ref['list']) return false;
			return $this->_update('remove', $name, $val=false, $this->ref['list']); 
		}

		public function keys() {
			if (!$this->ref['list']) return array(); 
			return $this->_update('keys', '', $val=false, $this->ref['list']);
		}

		private function &get_sublist($name, &$list) {
			if (!isset($this->sublists[$name])) {
//				print "making sublist '$name'\n";
				$this->sublists[$name] = new listObj($name, true, array($this, '_sublist_update'));
				$this->sublists[$name]->_set_list($list[$name]);
			}

			return $this->sublists[$name];
		}

		public function _sublist_update ($action, $name, $key, &$value, &$list) {
			if (!$this->ref['list']) return false;
			$called = $name . ($key?'':"::$key");

//			print "sublist update called($called), act($action), name($name), key($key)\n";
			return $this->_update($action, $called, $value, $list);
		}

		private function _update ($action, $name, &$value, &$list) {
			$cb = $this->single?$this->callbacks:$this->callbacks[$action];
			if (is_callable($cb)) {
				$ret = $cb($action, $this->name, $name, $value, $list);
				if ($ret !== NULL) return $ret;
			}

			if ($name && strpos($name, '::') !== false) { 
				$keys = explode('::', $name);
				$name = array_pop($keys);
			}

			switch ($action) {
				case ('set'):
					if (!$name) $list[] =& $value;
					else $list[$name] =& $value;
					return true;
				case ('get'):
					if (!isset($list[$name])) return false;
					if (!$this->recursive || !is_array($this->ref['list'][$name]))
						return $list[$name];
					return $this->get_sublist($name, $list);
				case ('remove'):
					if (!$name) return array_pop($list);
					$ret = $list[$name];
					unset($list[$name]);
					return $ret;
				case ('keys'):
					return array_keys($list);
				case ('isset'):
					return ($name)?isset($list[$name]):!!count($list);
			}

			return false;
		}



	}
