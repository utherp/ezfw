<?php


	function create_shmem($filename, $mode, $size) {
		$f = ftok($filename, 'I');
		return shmop_open($f+1,'c', $mode, $size);
	}

	function attach_shmem($filename) {
		$f = ftok($filename, 'I');
		return shmop_open($f+1, 'a', 0, 0);
	}


	function write_data_to_mem($shmem, $image) {

		$data = pad_num_to(gettimeofday(true), 15);
		$data .= pad_num_to(strlen($image), 15);
		$data .= $image;

		return write_to_mem($shmem, $data, 0);
	}

	function pad_num_to($data, $len) {
		return str_pad($data, $len, '0', STR_PAD_LEFT);
	}

	function write_to_mem($shmem, $data, $offset) {
		$written = shmop_write($shmem, $data, $offset);
		if ($written !== strlen($data)) {
			logger('Warning:  Tried to write ' . strlen($data) . ' bytes to shared memory, but only wrote ' . $written . '!');
			return false;
		}
		return true;
	}

	function read_image_from_mem($shmem) {
		$image_size = intval(shmop_read($shmem, IMAGE_SIZE_OFFSET, IMAGE_SIZE_LENGTH));
		return shmop_read($shmem, IMAGE_OFFSET, $image_size);
	}

	function read_index_from_mem($shmem) {
//		return shmop_read($shmem, INDEX_OFFSET, INDEX_LENGTH);
		return floatval(shmop_read($shmem, INDEX_OFFSET, INDEX_LENGTH));
	}




?>
