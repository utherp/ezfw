<?php
// fns/ptv.php
	require_once('config.php');
	class fns_ptv {
		static function get_pathos_id() {
			get_db_connection();
			$cam_ip = $_SERVER["REMOTE_ADDR"];
			$sql = "SELECT campatid 
					FROM tblcam, tblsar 
					WHERE tblsar.sys_ip='$cam_ip' AND tblsar.camera_location=tblcam.camloc";
			$rows = $GLOBALS['__db__']->fetchAll($sql);
			$campatid = $rows[0]['campatid'];

			$ary_rows = $GLOBALS['__db__']->fetchAll("SELECT pathosid FROM tblpat WHERE patid='$campatid'");
			$num_rows = count($ary_rows);
			if ($num_rows == 1) {
				return ($ary_rows[0]['pathosid']);
			}
			return 0;
		}
	
	/**	Function:	   update_record			   
	*   Description:	use visitor's name update db tblvisitor.
	*	Parameters:	 $bool can be y (yes), n (no) and r (refuse)
	*/
	
		static function update_record($vname, $bool='n') {
			get_db_connection();
			$query = "UPDATE LOW_PRIORITY tblvisitor SET vpermission='$bool'  WHERE vname='$vname'";
			$GLOBALS['__db__']->query($query);
		}
	}
?>
