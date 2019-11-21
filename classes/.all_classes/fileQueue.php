<?php
	require_once('importQueue.php');

	class fileQueue extends importQueue {
		private $filename;
		private $handle;

		protected function open_queue() {
			$this->handle = fopen($this->filename, 'r');
			return !!$this->handle;
		}

		protected function close_queue() {
			fclose($this->handle);
			return;
		}

		protected function read_queue() {
			$rec = array();
			while ($ln = fgets($this->handle))
				$rec[] = rtrim($ln);
			ftruncate($this->handle, 0);
			fseek($this->handle, 0);
			return $rec;
		}

		protected function parse_params($filename) {
			$this->filename = $filename;
		}
	}
