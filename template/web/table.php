<?php
	// web/table.php
	// all html table configuration (like bgcolor, cellspace etc) going to css file.
	// use tbl_class control it
	// js_string for onclick() etc. can add to config array.
	// php version 5.
	// $data can be array, asociated array or string.
	// $ary_th and $ary_td can be array or string or empty.
	
	class web_table extends web_common {
		
		/**
	     * Construct
	     * initiate table name, id and css class name.
	     * @param	$data mixed
	     * @return    void
	     */
		
		public function __construct($data='') {
			$data = $this->setdata($data); //make sure $data is array
			$this->strHtml .= '<table '.implode(' ', $this->setArgs($data)).'>'.$this->nl;
		}
		
		/**
	     * add_th
	     * add table head contents
	     * @param	$th string 
	     * @param	$th_class string
	     * @return    void
	     */
		
		public function add_th($data, $ary_th='') {
			$this->strHtml .= '<tr>'.$this->nl;
			$data = $this->setdata($data); //make sure $data is array
			foreach ($data as $value) {
				$this->strHtml .= "\t".'<th '.implode(' ', $this->setArgs($ary_th)).'>'.$value.'</th>'.$this->nl;
			}
			$this->strHtml .= '</tr>'.$this->nl;
		}
		
		/**
	     * add_td
	     * add table data contents
	     * @param	$td string 
	     * @param	$td_class string
	     * @return    void
	     */
		
		public function add_td($data, $ary_td='') {
			$this->strHtml .= '<tr>'.$this->nl;
			$data = $this->setdata($data); //make sure $data is array
			foreach ($data as $value) {
				$value = !is_null($value) ? $value : '&nbsp;';
				$this->strHtml .= $this->tab.'<td '.implode(' ', $this->setArgs($ary_td)).'>'.$value.'</td>'.$this->nl;
			}
			$this->strHtml .= '</tr>'.$this->nl;
		}
		
		//follow pear HTML rule.
		public function toHtml() {
			return parent::toHtml().'</table>'.$this->nl;
		}
		
		
		/**
	     * setArgs($ary_args)
	     * convert array from $ary['border'] = '0' to 
	     * $ary['border'] = 'border="0"'
	     * @param	$ary_args array
	     * @return    array
	     */
		
		protected function setArgs($ary_args) {
			$ary = array();
			if (is_array($ary_args)) {
				foreach ($ary_args as $key=>$value) {
					$ary[$key] = strtolower($key.'="'.$value.'"');
				}
			} 
			return $ary;
		}
		
		/**
	     * setdata($data)
	     * convert string to array.
	     * @param	$data mixed
	     * @return    array
	     */
		
		protected function setdata($data) {
			$ary = array();
			(is_array($data)) ? $ary = $data : $ary['name'] = $data;
			return $ary;
		}
		
		/**
	     * set2dArray ($ary, $num_col)
	     * convert 1d array to 2d array.
	     * @param	$ary array
	     * @param	$num_col intege
	     * @return    array
	     */
		
		static function set2dArray ($ary, $num_col, $num_row=null) {
			$ary_out = array();
			$num_ary = count($ary);
			if (null===$num_row) {
				$num_row = ceil($num_ary/$num_col);
			}
			$num_cell = $num_row*$num_col;
			//fill up empty array item.
			if ($num_ary < $num_cell) {
				for ($i=$num_ary; $i<$num_cell; $i++) {
					$ary[$i] = '&nbsp;';
				}
			}
			$index = 0;
			for ($i=0; $i<$num_row; $i++) {
				for($j=0; $j<$num_col; $j++) {
					$ary_out[$i][$j] = $ary[$index++];
				}
			}
			return $ary_out;
		}
		
		static function setlinkArray ($ary, $str=null) {
			$ary_out = array();
			foreach ($ary as $value) {
				$str_q = (null===$str) ? $value : $str;
				$ary_out[] = '<a href = "http://'.$_SERVER['SERVER_ADDR'].$_SERVER['PHP_SELF'].'?q='.$str_q.'">'.$value.'</a>';
			}
			return $ary_out;
		}
	}
?>
