<?php
	require_once('uther.ezfw.php');

	declare(ticks = 1);

	abstract class ezObj extends dbObj {

		static $_cached_objects;
		private $_loaded_objects;
		protected $data = array();
		protected $changed = array();

		private $last_refresh = 0;
		private $last_commit = 0;
		private $auto_commit_timer = false;

		/*****************************************\
		\*****************************************/

//		abstract protected function _get_ez_settings();

        protected function get__ez_settings() { return static::$_ez_settings; }

		/*****************************************\
		\*****************************************/

		function __construct($id = false, $set_state = false) {
			// if the extending class has defined an init function, call it
			if (method_exists($this, '_init'))
				$this->_init();

			if (is_array($id)) {
				if ($set_state) {
					// set all values into object
					// instead of loading the object
					$this->loaded = -1;
					$data = $this->_unpack_values($id);
					foreach ($data as $name => $value) {
						if (isset($value))
							$this->$name = $value;
					}
					$this->loaded = true;

				} else {
					// load by array of properties
					$vals = array();
					$settings = $this->_ez_settings;
					foreach ($id as $name => $value)
						$vals[$this->_translate_key_name($name, true)] = $value;
					$this->load($vals);
				}
			} else if ($id !== false) {
				// load by identifier
//				$this->set_identifier($id, true);
				$this->load($id);
			}

			// load defaults...
			if ($tmp = $this->loaded)
				$this->loaded = -1;

			$this->set_defaults();

			$this->loaded = $tmp;

			if ($this->is_loaded()) {
				$cls = get_class($this);
				if( defined('CV_CACHE_OBJECTS') && CV_CACHE_OBJECTS ){
					if (!is_array(self::$_cached_objects[$cls])) self::$_cached_objects[$cls] = array();
					self::$_cached_objects[$cls][$this->get_identifier()] =& $this;
				}
			}

			// if the extending class has defined a _post_init, call it.
			if (method_exists($this, '_post_init'))
				$this->_post_init();

			return;
		}

		function __destruct () {
			$settings = $this->_ez_settings;
			if (!$settings['commit_on_destruct'] || $this->loaded !== true)
				return;
			$this->save();
			return;
		}
		/*****************************************\
		\*****************************************/

		public function load ($params = NULL) {
			$this->last_refresh = time();
			return parent::load($params);
		}

		public function reload () {
			/*
				if its set to any value, attempt to save it here
				its important to revert any changes before reload
				if you do not wish it to be commited and you have
				an auto_commit value set (to a "true" value)
			*/
			if ($settings['auto_commit'] && $this->auto_commit_timer)
				$this->_do_auto_commit();

			$id = $this->get_identifier();
			$this->changed = array();
			$this->data = array();
			return $this->load($id);
		}

		public function save ($reload = true) {
			if ($this->auto_commit_timer)
				return $this->_do_auto_commit();
			$this->last_commit = time();
			if ($ret = parent::save() && $reload) $this->reload();
            return $ret;
		}

		/*****************************************\
		\*****************************************/

		function __sleep() {
			if (method_exists($this, '_sleep')) {
				$list = $this->_sleep();
				if (!is_array($list)) $list = array($list);
				$list = array_flip($list);

				foreach (array_keys($this->data) as $k) {
					if (isset($list[$k])) continue;
					unset($this->data[$k]);
					unset($this->changed[$k]);
				}

				foreach (array_keys($this->changed) as $k) {
					if (isset($list[$k])) continue;
					unset($this->changed[$k]);
				}
			}

			return array("\0ezObj\0_loaded_objects", 'data', 'changed', 'loaded');
		}

		function __wakeup() {
			if (method_exists($this, '_wakeup'))
				$this->_wakeup();

			return;
		}

		/*****************************************\
		\*****************************************/

		protected function _get_data() {
			return $this->data;
		}

		protected function _get_changed($clear = false) {
			$values = array();
			foreach ($this->changed as $k => $v) {
				if (is_array($v)) {
					if (count($v))
						$values[$k] =& $v[0];
				} else
					$values[$k] =& $v;

				// clear changed value if true (upon save)
				if ($clear)
					$this->changed[$k] = array();

				// don't save values which are the same as the loaded value
				if (array_key_exists($k, $this->data) && $this->data[$k] === $v)
					unset($values[$k]);
			}

			return $values;
		}

		/*****************************************\
		\*****************************************/

		/* Identifier property translation */
		public function get_id() { return $this->get_identifier(); }

		/*****************************************\
		\*****************************************/

		public function set_defaults ($overwrite = false, $settings = false) {
			if (!$settings) $settings = $this->_ez_settings;
			if (!is_array($settings['property_defaults']))
				return;

			// loaded but defaults on missing not true...
			if ($this->is_loaded() && !$settings['defaults_on_missing'])
				return;

			// not loaded, but defaults_on_create turned off (default is true if not set)
			if (!$this->is_loaded() && ($settings['defaults_on_create'] === false))
				return;

			foreach ($settings['property_defaults'] as $name => $value)
				if ($overwrite || !isset($this->$name))
					$this->$name = $value;

			return;
		}

		/*****************************************\
		\*****************************************/

		protected function _translate_key_name ($name, $settings = false) {
			if (!$settings) $settings = $this->_ez_settings;
			if (!isset($settings['property_translations'][$name])) return $name;

			return $settings['property_translations'][$name];
		}

		/*****************************************\
		\*****************************************/

		private function _check_auto_refresh ($settings = false) {
			if (!$settings) $settings = $this->_ez_settings;
			if (!$settings['auto_refresh']) return;

			if (!is_bool($settings['auto_refresh']) && (time() < ($settings['auto_refresh'] + $this->last_refresh)))
				return;

			$this->reload();
			return;
		}

		private function _check_auto_commit ($settings = false) {
			if (!$settings) $settings = $this->_ez_settings;
			if (!$settings['auto_commit'] || $this->loaded !== true) return;
			if ($this->auto_commit_timer) return;

			if (is_bool($settings['auto_commit']))
				return $this->save();

			$this->auto_commit_timer = true;

            if (function_exists('pcntl_signal')) {
    			pcntl_signal(SIGALRM, array($this, '_do_auto_commit'));
    			pcntl_alarm($settings['auto_commit']);
            }
			return;
		}

		public function _do_auto_commit ($sig = NULL) {
            if (function_exists('pcntl_signal'))
    			pcntl_signal(SIGALRM, SIG_IGN);
			$this->auto_commit_timer = false;
			$this->save();
			return;
		}

		/*****************************************\
		\*****************************************/

		public function __get ($name) {
			if ($name{0} == '_' && substr($name, 3) == '_settings')
				$get_settings = true;
			else {
				$get_settings = false;
				$settings = $this->_ez_settings;
				$name = $this->_translate_key_name($name, $settings);
			}

			if (method_exists($this, $func = 'get_'.$name)) {
				$continue = false;
				$ret = $this->$func($name, $continue);
				if ($get_settings)
					return $this->$name = $ret;
				if (!$continue) return $ret;
			}

			return $this->_get($name, NULL, $settings);
		}

		public function _get($name, $force_actual = NULL, $settings = false) {
			if (!$settings) $settings = $this->_ez_settings;
			$name = $this->_translate_key_name($name, $settings);

			if (is_array($settings['object_translations'][$name]))
				return $this->_get_object($name, $settings['object_translations'][$name]);

			if (isset($settings['cache_properties'][$name]))
				return $this->_cache($name);

			// get the changed value unless the real value is requested
			$changed = NULL;
			if ($force_actual !== true) {
				if (array_key_exists($name, $this->changed)) {
					if (!is_array($this->changed[$name]))
						$changed = $this->changed[$name];
					else if (count($this->changed[$name]))
						$changed = $this->changed[$name][0];
				}

				// if the change is wanted OR the most recent value and a change exists
				if ($force_actual === false || $changed !== NULL)
					return $changed;
			}

			$this->_check_auto_refresh($settings);

			// return the real value
			$val = array_key_exists($name, $this->data)?$this->data[$name]:NULL;

			return $val;

		}

		/*****************************************\
		\*****************************************/

		protected function _get_object ($name, $opts) {
			$o = $this->_traverse_object_opts($opts);

			if ($opts['cache']) {
				$obj = $this->_cache($name);
				if (is_object($obj)) return $obj;
			}

			$cls = $o['class'];
			$field = $o['field'];

			$id = $this->$field;

			if (!$id) return false;

			$obj = eval("return $cls::fetch($id);");

			if (!is_object($obj)) return $obj;
	
			if (!$obj->is_loaded()) return false;
			if (!is_a($obj, $cls)) return false;

			if ($opts['cache']) $this->_cache($name, $obj);

			return $obj;
		}

		/*****************************************\
		\*****************************************/

		protected function _set_object ($name, $value, $opts) {
			if (!is_object($value)) return false;

			$params = $this->_traverse_object_opts($opts);
			if (!is_a($value, $params['class'])) return false;

			$id = $value->get_identifier();

			if ($opts['cache']) {
				$obj = $this->_cache($name);
				if (is_object($obj) && $obj->get_identifier() == $id) return true;
			}

			$field = $params['field'];

			$this->$field = $value->get_identifier();

			if ($opts['cache'])
				$this->_cache($name, $value);

			return true;
		}

		/*****************************************\
		\*****************************************/

		protected function _traverse_object_opts ($opts) {
			$o = array('class' => $opts['class'], 'field' => $opts['field']);

			foreach (array_keys($o) as $f) {
				$field = $o[$f];
				if ($field[0] == '$') {
					$tmp = $field;
					$field = preg_replace('/^\$*/', '', $field);
					for ($i = 0; $tmp[$i] == '$'; $i++)
						$field = $this->$field;
				}
				$o[$f] = $field;
			}

			return $o;
		}

		/*****************************************\
		\*****************************************/

		public function __set ($name, $val) {
			if ($name[0] == '_' && substr($name, 3) == '_settings') {
				$this->$name = $val;
				return;
			}
				
			$settings = $this->_ez_settings;
			if (!($name = $this->_translate_key_name($name, $settings)))
				return false;

			if (method_exists($this, $func = 'set_'.$name)) {
				$continue = true;
				$ret = $this->$func($val, $continue);
				if (!$continue) return $ret;
				$val = $ret;
			}

			return $this->_set($name, $val, NULL, $settings);
		}

		private $read_only_table;
		private function _read_only ($name, $settings = false) {
			if (!$settings) $settings = $this->_ez_settings;
			if (!$this->read_only_table)
				$this->read_only_table = is_array($settings['read_only_properties'])?array_flip($settings['read_only_properties']):array();

			return ($this->loaded != -1 && isset($this->read_only_table[$name]));
		}

		public function _set($name, $val, $force_actual = NULL, $settings = false) {
			if (!$settings) $settings = $this->_ez_settings;

			// translate key name (property_translations)
			if (!($name = $this->_translate_key_name($name, $settings)))
				return false;

			// just in case someone attempts to change a read only value
			if ($this->_read_only($name))
				throw new Exception(get_class($this) . '(' . $this->get_identifier() . "): Attempted to change read only property '$name' to '$val'");

			if (!$force_actual && is_array($settings['object_translations'][$name]))
				return $this->_set_object($name, $val, $settings['object_translations'][$name]);

			if (isset($settings['cache_properties'][$name]))
				return $this->_cache($name, $val);

			// set in data if forced, or if force not specified and we're initializing
			if ($force_actual || ($force_actual === NULL && $this->loaded === -1))
				return $this->data[$name] = $val;

			if (!is_array($this->changed[$name])) {
				if (array_key_exists($name, $this->changed))
					$this->changed[$name] = array($this->changed[$name]);
				else
					$this->changed[$name] = array();
			}

			// don't set the value in the stack if its the same as the last value...

			if ((!count($this->changed[$name]) && @array_key_exists($name, $this->data) && $this->data === $val)) {
				return $val;
            }
  
			array_unshift($this->changed[$name], $val);

			if ($this->loaded === true) {
				// checking auto_commit
				$this->_check_auto_commit();
			}

			return $this->changed[$name][0];
		}

		public function &_cache($name, &$val = NULL) {
            //print"[{$this->identifier}]_cache($name, " . var_Export($val, true) . ")\n";
			if ($val === false) {
				$ret =& $this->_loaded_objects[$name];
				unset($this->_loaded_objects[$name]);
				return $ret;
			}
			if ($val !== NULL)
				return $this->_loaded_objects[$name] =& $val;

			return $this->_loaded_objects[$name];
		}

		/*****************************************\
		\*****************************************/

		public function __isset ($name) { return $this->_ifset('isset', $name); }
		public function __unset ($name) { return $this->_ifset('unset', $name); }
		public function _isset  ($name, $settings = false) { return $this->_ifset('isset', $name, $settings); }
		public function _unset  ($name, $settings = false) { return $this->_ifset('unset', $name, $settings); }
		public function _revert ($name, $settings = false) { return $this->_ifset('revert', $name, $settings); }
		public function _undo   ($name, $settings = false) { return $this->_ifset('undo', $name, $settings); }

		/*****************************************\
		\*****************************************/

		public function _ifset ($type, $name, $settings = false) {
			if (!$settings) $settings = $this->_ez_settings;

			if (!($name = $this->_translate_key_name($name, $settings)))
				return false;

			/*****************************************************
				check if a "$type_$name" method exists
				and if so, call it and return (e.g. unset_myval)
			*/
			if (method_exists($this, $func = $type . '_'.$name)) {
				$continue = false;
				$ret = $this->$func($name, $continue);
				if (!$continue) return $ret;
			}
			/****************************************************/


			/*****************************************
				unset: unset all values of $name
			*/
			if ($type == 'unset') {
                $this->_set($name, NULL);
//				unset($this->data[$name]);
				unset($this->_loaded_objects[$name]);
				return;
			}
			/****************************************/


			/***************************************************
				undo: remove the last change made and return it
			*/
			if ($type == 'undo') {
				if (!array_key_exists($name, $this->changed))
					return NULL;

				if (is_array($this->changed[$name]))
					return array_shift($this->changed[$name]);

				$val = $this->changed[$name];
				unset($this->changed[$name]);
				return $val;
			}

			/*******************************************************
				revert: remove all changes and revert to loaded value
				return most recent change that occurred
			*/
			if ($type == 'revert') {
				if (!array_key_exists($name, $this->changed))
					return array_key_exists($name, $this->data)?$this->data[$name]:NULL;

				$ret = is_array($this->changed[$name])?$this->changed[$name][0]:$this->changed[$name];

				unset($this->changed[$name]);

				return $ret;
			}
			/******************************************************/

			/****************************************
				check for $name in data sets
			*/
			$set = false;
			if (isset($this->_loaded_objects[$name]))
				$set = '_loaded_objects';
			else if (array_key_exists($name, $this->changed))
				$set = 'changed';
			else if (array_key_exists($name, $this->data))
				$set = 'data';
			/***************************************/


			/***********************************************
				isset: check if any value is set for $name
			*/
			if ($type == 'isset') return !!$set;
			/**********************************************/


			// unknown $type
			return false;
		}

		/*****************************************\
		\*****************************************/

		function __call ($name, $args) {
			if (strpos($name, 'get_') === 0) 
				return $this->__get(substr($name, 4));

			if (strpos($name, 'set_') === 0)
				return $this->__set(substr($name, 4), $args[0]);

			if ($this->__isset($name)) {
				if (!count($args)) return $this->$name;
				if (count($args) > 1) return false;
				return $this->$name = $args[0];
			}

			return false;
		}

		/*****************************************\
		\*****************************************/

		public function audit_instance($msg) {
//			logger('AUDIT: class('.get_class($this).') id('.$this->get_identifier().'): ' . $msg, true);
			return;
			$db = get_db_connection();
			$params = array(
				'id'	=>	$this->get_identifier(),
				'class'	=>	get_class($this),
				'username'=>	(isset($_SESSION['_login_'])?$_SESSION['_login_']->get_user()->username:'None'),
				'msg'=>$msg
			);

			if (isset($GLOBALS['__session__']['remote_addr']))
				$params['remote_addr'] = $GLOBALS['__session__']['ip'];
			else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
				$params['remote_addr'] = $_SERVER['HTTP_X_FORWARDED_FOR'] . ' via ' . $_SERVER['HTTP_X_FORWARDED_HOST'];
			else if (isset($_SERVER['REMOTE_ADDR']))
				$params['remote_addr'] = $_SERVER['REMOTE_ADDR'];	
			else 
				$params['remote_addr'] = 'None';

			$db->insert('logs', $params);

			return;
		}

		/*****************************************\
		\*****************************************/

		static function _cache_object(&$obj, $type = NULL, $id = NULL) {
			if (!$type) $type = get_class($obj);
			if (!$id || (!is_string($id) && !is_numeric($id)))
				$id = $obj->get_identifier();
			if (!is_array(self::$_cached_objects[$type]))
				self::$_cached_objects[$type] = array();
			self::$_cached_objects[$type][$id] =& $obj;
			return;
		}

		static function fetch_all ($clause = '', $values = NULL, $limit = NULL, $order = '') {
            if (static::class == self::class) {
                if (!class_exists($clause)) return null;
                $args = func_get_args();
                array_shift($args);
                return call_user_func_array([ $clause, 'fetch_all' ], $args);
            }
			$obj = new static();
			return $obj->load_all($clause, $values, $limit, $order);
		}

		public function load_all ($clause = '', $values = NULL, $limit = NULL, $order = '') {
			$objs = parent::load_all($clause, $values, $limit, $order);
			foreach ($objs as $id => $obj) 
				if ($obj->is_loaded() && defined('CV_CACHE_OBJECTS') && CV_CACHE_OBJECTS)
					self::_cache_object($obj, get_class($obj), $obj->get_identifier());

			return $objs;
		}

		static function &fetch ($id = 'new') {
            if (static::class == self::class) {
                if (!class_exists($id)) return null;
                $args = func_get_args();
                array_shift($args);
                $cls = static::class;    // we do it this way here because call_user_func 
                return $cls::fetch($args[0]); // won't return a reference
            }

			if ($id == 'new') return new static();
			if (is_array($id)) {
				$obj = new static($id);
				if (!$obj->is_loaded() || !defined('CV_CACHE_OBJECTS') || !CV_CACHE_OBJECTS) return $obj;
				self::_cache_object($obj, static::class, $id);
				return $obj;
			}

			if (is_a(self::$_cached_objects[static::class][$id], $type))
				return self::$_cached_objects[static::class][$id];

			$obj = new static($id);
			if ($obj->is_loaded() && defined('CV_CACHE_OBJECTS') && CV_CACHE_OBJECTS)
				self::_cache_object($obj, static::class, $id);

			return $obj;
		}

		/*****************************************\
		\*****************************************/

	}
