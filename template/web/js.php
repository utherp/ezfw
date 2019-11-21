<?php
// web/js.php

	class web_js {
		static function header() {
			echo "\n".'<script type="text/javascript">'."\n";
		}
		static function footer() {
			echo '</script>'."\n";
		}
		
		/**
	     * relocation 
	     * redirection URL after delay millisecond.
	     * @param	$file_name string
	     * @param	$delay integer
	     * @access    static
	     * @return    void
	     */
		static function relocate($file_name, $delay=3000) {
			self::header();
			echo 'setTimeout(\'document.location = "'.$file_name.'"\''.','.$delay.')'."\n";
			self::footer();
		}
		
		/**
	     * relocation 
	     * 
	     * @param	$js_ary_name string
	     * @param	$ary array
	     * @access    static
	     * @return    void
	     */
		
		static function set_js_ary($js_ary_name, $ary)
		{
			$ary_num = count($ary);
			echo "\n".'var '.$js_ary_name. ' = new Array('.$ary_num.');'."\n";
			for ($i=0; $i<$ary_num; $i++) {
				echo "\n\t";
				echo $js_ary_name.'['.$i.'] = "'.$ary[$i].'";';
			}
			echo "\n";
		}
	}
?>
