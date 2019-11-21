<?php 
//fns/common.php
require_once 'config.php';
class fns_common {
	
	/**
	 * getRowNum($table_name)
	 * give a table name return total number rows in the table 
	 * @param $table_name string
	 * @return  $numrows integer
	 */
	
	static public function getRowNum($table_name) {
		$params = parse_ini_file('/home/cam/php/ini/db.ini');
		get_db_connection();
		$ary_row = $GLOBALS['__db__']->fetchAll("SELECT count(*) as count FROM $table_name");
		return ($ary_row[0]['count']);
	}
	
	/**
	 * isDuplicate($tbl, $field, $field_val)
	 * search table find duplicate value
	 * @param $table_name string
	 * @param $field string
	 * @param $field_val string
	 * @return  bool
	 */
	
	static public function isDuplicate($table_name, $field, $field_val) {
		$params = parse_ini_file('/home/cam/php/ini/db.ini');
		get_db_connection();
		$query = "SELECT $field FROM $table_name WHERE $field='$field_val'";
		$ary_row = $GLOBALS['__db__']->fetchAll($query);
		return (count($ary_row) > 0) ? true : false;
	}
	
	static public function build_db_array($query, $index, $value) {
		//$query = "SELECT $index, $value FROM $tbl ORDER BY $value";
		$params = parse_ini_file('/home/cam/php/ini/db.ini');
		get_db_connection();
		$ary = array();
		$ary_row = $GLOBALS['__db__']->fetchAll($query);
		$ary['0'] = '';
		foreach ($ary_row as $sub_ary) {
			$ary[$sub_ary[$index]] = $sub_ary[$value];
		}
		return $ary;
	}
	
	static public function set_css_class($j) {
		$str = ($j%2==0) ? 'odd_row' : 'even_row';
		echo 'class="'.$str.'"';
	}
	
	static function seperate_pages($limit, $start, $numrows) {
		if($start > 0) {
?>			<a href="<?=$_SERVER[PHP_SELF]?>?start=<?=($start - $limit)?>">
				<img src='../img/previous.gif' alt='previous' width='85' height='21' border='0'>
			</a>
<?		}
		if($numrows > ($start + $limit)) {
?>			<a href="<?=$_SERVER[PHP_SELF]?>?start=<?=($start + $limit)?>">
				<img src='../img/next.gif' alt='next' width='62' height='21' border='0'>
			</a>
<?		}
	}
	
	////////////////////////////////////////////////////////////////////////
	/**	Function:	   get_subdir				 
	*   Description:	get different subdirectory from different camera.
	*	Parameters:	 1.sony, panasonic, usb 2. movie, still.
	*	Return value:	subdirectory
	*///////////////////////////////////////////////////////////////////////
	
	static function get_subdir($cam_model, $cam_state='still') { 
		$came_ary = array(
			'movie' => array(
				'panasonic'	=> '/ImageViewer?Mode=Motion',
				'usb' 		=> '/hrc/scv/videocam.php'
			),
			'still' => array(
				'panasonic'	=> '/SnapshotJPEG?Resolution=160x120',
				'usb'		=> '/singleframe',
				'sony'		=> '/oneshotimage.jpg'
			)
		);
	
		$browser = $_SERVER['HTTP_USER_AGENT'];	
		if (($cam_model == "sony") && ($cam_state == "movie")) {
			if(strstr($browser,"MSIE")) {
				return ("/home/AViewer.html");
			} else {
				return ("/home/JViewer.html");
			}
		} else {
			return($came_ary[$cam_state][$cam_model]);
		}
	}
	
	/**
	* get_insert_sql($tbl_name, $array, $include_key=true)
	* produce insert sql string
	* @param $tbl_name string
	* @param $ary array
	* @param $include_key=true bool
	* @return $sql string
	*/
	
	static function get_insert_sql($tbl_name, $array, $include_key=true) {
		$str_key = '';
		$str_val = '';
		$sql =  "INSERT INTO $tbl_name";
		// implode keys of $array
		$str_key .= " (`".implode("`, `", array_keys($array))."`)";
		// implode values of $array
		$str_val .= " VALUES ('".implode("', '", $array)."')";
		$sql .= ($include_key) ? "$str_key$str_val" : "$str_val";
		return $sql;
	}
	
	/**
	* get_update_sql 
	* produce insert sql string
	* @param $tbl_name string
	* @param $ary array
	* @param $ary_where array
	* @return $sql string
	*/
	//UPDATE w_upc SET user = 'admin' WHERE upc = '084604205130' LIMIT 1 ;
	static function get_update_sql($tbl_name, $ary, $ary_where) {
		$where = ' WHERE ';
		$i=0;		
		foreach ($ary_where as $key => $value) {
			$key = trim($key);
			$value = trim($value);
			if($i>0) {
				$where .= ' AND '.$key."='".$value."'";
			} else {
				$where .= $key."='".$value."'";
			}
			++$i;
		}
		$where .= ' LIMIT 1';
		$sql =  "UPDATE $tbl_name SET ";
		foreach ($ary as $key=>$value) {
			$sql .= $key."='".$value."',";
		}
		$sql = rtrim($sql, ','); //remove the last comma.
		$sql .= $where;
		return $sql;
	}
	
	static function date2ts($str_time = '2006-08-30 11:00 CDT') {
		$str_time=trim($str_time);
		$str_time = (is_null($str_time) or (''==$str_time))? '2006-08-30 11:00 CDT' : $str_time;
		return mktime((int)substr($str_time,11,2),
					  (int)substr($str_time,14,2),
					  0,
					  (int)substr($str_time,5,2),
					  (int)substr($str_time,8,2),
					  (int)substr($str_time,0,4));
	}
}
