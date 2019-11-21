<?php
	require_once('uther.ezfw.php');
	load_definitions('SHARED_MEM');

	class SharedMem {
		protected $shmem;
		protected $semaphore;
		
		protected $filename;
		protected $sysV_id;
		
		protected $permission;
		protected $size;
		protected $mode;
		
		protected $last_index;


		public function get_last_index() { return $this->last_index; }

		function __construct($filename, $permission = 0, $size = 0) {
			$this->filename = $filename;
			$this->permission = $permission;
			$this->size = $size;

			$this->create_sysV_id();

			$this->mode = ($permission == 0)?'a':'c';

			$this->initialize_shmem();
			$this->initialize_semaphore();

		}
		function __sleep() {
			@shmop_close($this->shmem);
			@sem_remove($this->semaphore);
			return array('filename', 'sysV_id', 'permission',
						 'size', 'mode', 'last_index');
		}
		function __wakeup() {
			$this->initialize_shmem();
			$this->initialize_semaphore();
		}

		private function sem_step_lock() {
			return sem_acquire($this->semaphore);
		}
		/***************************************/
		private function sem_step_unlock() {
			return sem_release($this->semaphore);
		}
		/***************************************/

		private function create_sysV_id() {
			$this->sysV_id = ftok($this->filename, 'I');
		}

		private function initialize_semaphore() {
			$this->semaphore = sem_get($this->sysV_id, 1);
			if ($this->semaphore === false) return false;
			return true;
		}

		private function initialize_shmem() {
			$this->shmem = shmop_open(
								$this->sysV_id + 1,	// SysV Identifier
								$this->mode,		// shmem mode:
													//   'a': read
													//   'n': create or fail
													//   'c': create or read/write
													//   'w': read/write
								$this->permission,	// posix permissions if create
								$this->size			// size in bytes if create
							);
			if ($this->shmem === false) return false;
			return true;
		}

		public function write_data_to_mem($image) {
			$data = self::pad_num_to(gettimeofday(true), 15);
			$data .= self::pad_num_to(strlen($image), 15);
			$data .= $image;
			return $this->write_to_mem($data, 0);
		}

		static function pad_num_to($data, $len) {
			return str_pad($data, $len, '0', STR_PAD_LEFT);
		}

		public function write_to_mem($data, $offset) {
			$this->sem_step_lock();
			$written = shmop_write($this->shmem, $data, $offset);
			$this->sem_step_unlock();
			if ($written !== strlen($data)) {
				logger('Warning:  Tried to write ' . strlen($data) . ' bytes to shared memory, but only wrote ' . $written . '!');
				return false;
			}
			return true;
		}

		public function read_image_from_mem() {
			$image_size = intval($this->read_from_mem(IMAGE_SIZE_OFFSET, IMAGE_SIZE_LENGTH));
			return $this->read_from_mem(IMAGE_OFFSET, $image_size);
		}

		private function read_from_mem($offset, $length) {
			$this->sem_step_lock();
			$ret = shmop_read($this->shmem, $offset, $length);
			$this->sem_step_unlock();
			return $ret;
		}

		public function read_index_from_mem() {
			$i = floatval($this->read_from_mem(INDEX_OFFSET, INDEX_LENGTH));
			if ($i !== false) $this->last_index = $i;
			return $i;
		}

		public function image_has_changed() {
			$old_index = $this->last_index;
			$i = $this->read_index_from_mem();
			if ($i != $old_index) return true;
			return false;
		}


	}

?>
