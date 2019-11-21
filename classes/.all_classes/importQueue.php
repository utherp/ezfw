<?php

	abstract class importQueue {
		abstract protected function open_queue();
		abstract protected function close_queue();
		abstract protected function read_queue();
		abstract protected function parse_params($params);

		private $buffer = array();
		private $init = false;

		function __construct() {
			$args = func_get_args();
			if (!call_user_func_array(array($this, 'parse_param'), $args)
				|| !$this->open_queue())
			return;

			$this->refill_buffer();
			return;
		}

		function __destruct () { $this->close(); }

		public function close() {
			$this->close_queue();
			return;
		}

		private function refill_buffer() {
			$rec = $this->read_queue();
			if (!is_array($rec)) return 0;
			$c = 0;
			foreach ($rec as $r) {
				$c++;
				$this->buffer[] = $r;
			}
			return $c;
		}

		public function next_record($c = 1) {
			if (!count($this->buffer))
				$this->refill_buffer();

			if ($c == 1)
				return array_shift($this->buffer);

			$rec = array();
			while ($c--)
				$rec[] = array_shift($this->buffer);
			
			return $rec;
		}
	}

?>
