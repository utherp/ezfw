<?php
/** dont use any of this!  it's not mine and it's crap **/

//  class/web/html.php
	class web_html {
		/**
	     * Sets html header
	     * 
	     * @param	$title string 
	     * @param	$css_js_string string
	     * @param	$body bool
	     * @access    static
	     * @return    void
	     */
	/****************************************************************/
		static function header($title='Untitled', $css_js_string=false, $body=false) {
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title><?=$title?></title>
		<?=($css_js_string !== false)?$css_js_string:''?>

	</head>
	<?=$body?'<body>':''?>

<?		}
	
		/**
	     * Sets html footer
	     * 
	     * @param     none
	     * @access    static
	     * @return    void
	     */
	
		static function footer() {
?>
	</body>
</html>
	<?	}
		
		/**
		* set_url_string($ary) 
		* set up html GET strings. 
		* input is associated array.
		* @param $ary array
		* @return $str string
		*/
		
		static function set_url_string($ary) {
	//		$str = '?';
	//		foreach ($ary as $key => $value) {
	//			$str .= $key.'='.$value.'&';
	//		}
	//		$str = rtrim($str,'&'); //remove last '&'
	//		return $str;
			return '?'.http_build_query($ary);
		}      
		
		/**
		* set_href($ary) 
		* all argument are optional.
		* set up html GET strings. 
		* eg. $ary = compact('year', 'mon', 'day', 'hour');
		* @param $link string
		* @param $ary array
		* @param $url string
		* @return $str string
		*/
		
		static function set_href($link='', $ary='', $url='') {
			   
			$url = (''==$url) ? $_SERVER['PHP_SELF'] : $url;
			$str = (is_array($ary) && count($ary)>0) ? self::set_url_string($ary) : '';
			$link = (''==$link) ? $url : $link;
			return "\n".'<a href="'.$url.$str.'">'.$link.'</a>'."\n";
		}
	
		/**
		* setArray($counter)
		* set basic hour or minute array(00,01,02,...,59)
		* @param $counter integer
		* @return $ary arraay
		*/
		
		static function setArray($counter) {
			for($i=0; $i<$counter; $i++) {
				$ary[]=str_pad($i, 2, '0', STR_PAD_LEFT);
			}
			return $ary;
		}
		
		/**
	     * set2dArray ($ary, $num_row)
	     * convert 1d array to 2d array.
	     * @param	$ary array
	     * @param	$num_row intege
	     * @return    array
	     */
		
		static function set2dArray ($ary, $num_row, $num_col='') {
			$ary_out = array();
			$num_ary = count($ary);
			if (''==$num_col) {
				$num_col = ceil($num_ary/$num_row);
			}
			$num_cell = $num_row*$num_col;
			//fill up empty array item.
			if ($num_ary < $num_cell) {
				for ($i=$num_ary; $i<$num_cell; $i++) {
					$ary[$i] = '&nbsp;';
				}
			}
			$counter = 0;
			for ($i=0; $i<$num_row; $i++) {
				for($j=0; $j<$num_col; $j++) {
					$ary_out[$i][$j] = $ary[$counter++];
				}
			}
			return $ary_out;
		}
		
		/**
	     * create_sel_list($array, $sel)
	     * Create a selection list from an array 
	     * @param	$array array
	     * @param	$sel string
	     * @return  None.
	     */
	
		static function create_sel_list($array, $sel) {
			$strHTML = '';
			$sel_str = "";
			while($item = each($array)) {
				if (strtolower($item["value"]) == strtolower($sel)) {
					$sel_str = 'selected';
				}
				$strHTML .= '<option value="'.$item["value"].'"'.$sel_str.'>'.$item["value"].'</option>'."\n";
				$sel_str = "";
			}
			return $strHTML;
		}
	}
?>
