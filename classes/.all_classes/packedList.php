<?php

	class packedList {
		private $seperator = ';';
		private $lpad = '';
		private $rpad = '';

		private $pad_chars = '|-;:_/\\?*+';

		/***************************************************\
		\***************************************************/

		function __construct ($seperator = ';', $lpad = '', $rpad = '') {
			$this->seperator = $seperator;
			$this->lpad = $lpad;
			$this->rpad = $rpad;

			$this->set_pad_chars($this->pad_chars);
			return;
		}

		/***************************************************\
		\***************************************************/

		public function set_pad_chars ($chars) {
			$add = '';
			if (strpos($chars, '/')) {
				$add .= '\/';
				$chars = preg_replace('/\//', '', $chars);
			}
			if (strpos($chars, '-')) {
				$add .= '\-';
				$chars = preg_replace('/\-/', '', $chars);
			}
			$this->pad_chars = '[' . preg_quote($chars) . $add . ']';	
			return;
		}

		/***************************************************\
		\***************************************************/

		public function find_packing ($string) {
			if (!is_string($string) || !strlen($string)) return false;

			$lexp = '/^('.$this->pad_chars.'+)/';

			if (preg_match($lexp, $string, $matches)) {
				$this->lpad = $matches[1];
				$string = substr($string, strlen($this->lpad));
			}

			$rexp = '/('.$this->pad_chars.'+)$/';
	
			if (preg_match($rexp, $string, $matches)) {
				$this->rpad = $matches[1];
				$string = substr($string, 0, strlen($string) - strlen($this->rpad));
			}

			$sexp = '/('.$this->pad_chars.'+)/';

			if (preg_match($sexp, $string, $matches))
				$this->seperator = $matches[1];
			else if (!$this->rpad && !$this->lpad)
				return false;
			else 
				$this->seperator = $this->rpad . $this->lpad;

			return true;
		}

		/***************************************************\
		\***************************************************/

		public function is_packed ($string) {
			if ((!$this->lpad && !$this->rpad) && strpos($string, $this->seperator) === false) return false;
			if ($this->lpad && strpos($string, $this->lpad) !== 0) return false;
			if ($this->rpad && !preg_match('/' . preg_quote($this->rpad) . '$/', $string)) return false;
			return true;
		}

		/***************************************************\
		\***************************************************/

		public function pack($list) {
			if (!is_array($list)) return false;
			return $this->lpad . implode($this->seperator, array_values($list)) . $this->rpad;
		}

		/***************************************************\
		\***************************************************/

		public function unpack($string, $remove_empty = false) {
			if (!is_string($string)) return false;
			$list = explode($this->seperator, rtrim(ltrim($string, $this->lpad), $this->rpad));
			if (!$remove_empty) return $list;
			$tmp = array();
			foreach ($list as $v)
				if ($v) $tmp[] = $v;

			return $tmp;
		}

		/***************************************************\
		\***************************************************/

	}
	

