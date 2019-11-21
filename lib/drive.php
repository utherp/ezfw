<?php
	require_once('uther.ezfw.php');

/*******************************\
 * Library for functions for   *
 * checking drive usage levels *
\*******************************/

/**********************************************************************/
/**********************************************************************/
	
	function get_drive_free($path) {
		return disk_free_space($path);
	}

	/******************************************************/

	function get_drive_size($path) {
		return disk_total_space($path);
	}

	/******************************************************/

	function get_drive_percent($path) {
		$total = get_drive_size($path);
		return intval(($total - get_drive_free($path)) / $total * 100);
	}

	/******************************************************/

	function drive_full($path, $watermark) {
		$per = get_drive_percent($path);
		$mark = intval(preg_replace('/%/', '', $watermark));
		if ($per > $watermark) {
			logger("Drive '$path' reports to be over watermark: used: $per, mark: $mark", true);
			return true;
		}
		false;
	}

	/******************************************************/


