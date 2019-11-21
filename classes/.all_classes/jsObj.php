<?php
	abstract class jsObj implements ArrayAccess {

		static function list_to_js ($list) {
			$hash = false;
			foreach (array_keys($list) as $n)
				if (!is_numeric($n)) {
					$hash = true;
					break;
				}
				
			$str = '';
			foreach ($list as $n => $v) {
				if (strpos($n, '.') === 0) continue;
				$str .= ', ' . ($hash?'"'.$n.'":':'') . self::value_to_js($v);
			}
			$str[0] = $hash?'{':'[';
			$str .= $hash?'}':']';

			return $str;
		}

		static function value_to_js ($value) {
			switch (true) {
				case (is_bool($value)):return $value?'true':'false';
				case (is_array($value)): return self::list_to_js($value);
				case (is_numeric($value)): return $value;
				case (is_object($value)): return self::value_to_js($value . '');
				default: return '"'.$value.'"';
			}
		}

		function __toString() {
			$cls = ucfirst(get_class($this));
			return self::value_to_js($this->_get_data());
		}

		public function offsetExists ($name) { return $this->__isset($name); }
		public function offsetUnset  ($name) { return $this->__unset($name); }
		public function offsetGet    ($name) { return $this->__get($name); }
		public function offsetSet    ($n,$v) { return $this->__set($n,$v); }

	}
