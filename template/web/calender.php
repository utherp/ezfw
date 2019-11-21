<?php
	// web/calendar.php
	//require_once('html.php');
	//require_once('table.php');
	define("DAYS_OF_WEEK", 7);
	class web_calendar extends web_common {
		
		protected $strCalendar = '';
		protected $strHour = '';
		protected $strMinute = '';
		protected $year;
		protected $mon;
		protected $day;
		protected $hour;
		protected $min;
	//	protected $sec;
	
		public function __construct($year='', $mon='', $day='', $hour='', $min='') {
			$this->year = ('' != $year) ? $year : date("Y");
			$this->mon = ('' != $mon) ? $mon : date("m");
			$this->day = ('' != $day) ? $day : date("d");;
			$this->hour = ('' != $hour) ? $hour : date("H");
			$this->min = ('' != $min) ? $min : date("i");
		}
		
		/**
		* seturl() 
		* set up url link strings. 
		* input is associated array.
		* @param $link string
		* @param $ary array
		* @return $str string
		*/
		
	//	protected function set_url($link, $ary) {
	//		return $this->nl.'<a href="'.$_SERVER['PHP_SELF'].urlget($ary).'">'.$link.'</a>'.$this->nl;
		protected function set_url($link, $year, $mon, $day=1, $hour=0, $min=0) {
			$mon = str_pad($mon, 2, '0', STR_PAD_LEFT);
			$day = str_pad($day, 2, '0', STR_PAD_LEFT);
			$hour = str_pad($hour, 2, '0', STR_PAD_LEFT);
			$min = str_pad($min, 2, '0', STR_PAD_LEFT);
			
			return $this->nl.'<a href="'.$_SERVER['PHP_SELF'].'?year='.$year.'&mon='.$mon.
					'&day='.$day.'&hour='.$hour.'&min='.$min.'">'.$link.'</a>'.$this->nl;
		}
		
		
		public function setCalendar($ary=null) {
			if (!is_array($ary) or !isset($ary) or (null === $ary)) {
				$ary = array();
			}
			$next_mon = ('12' == $this->mon ) ? '01' : $this->mon + 1;
			$last_mon = ('01' == $this->mon ) ? '12' : $this->mon - 1;
			
			$last_link = '&lt;&lt;&nbsp;&nbsp;';  //show <<
			$next_link = '&nbsp;&nbsp;&gt;&gt;';  //show >>
			
			$ts_first = mktime (0, 0, 0, $this->mon, 1, $this->year); //get first day of month timestamp.
			$mon_name = date("F", $ts_first); // get month name.
			// set up year and month.
			$output = '<div class="calyear">';
			$output .= ('01' == $this->mon ) ? $this->set_url($last_link, $this->year-1, $last_mon) : $this->set_url($last_link, $this->year, $last_mon);
			$output .= '<b>'.$mon_name.'&nbsp;&nbsp;'.$this->year.'</b>';
			$output .= ('12' == $this->mon ) ? $this->set_url($next_link, $this->year+1, $next_mon) : $this->set_url($next_link, $this->year, $next_mon);
			$output .= '</div>';
			
			$date_array = getdate( mktime (0, 0, 0, $this->mon, 1, $this->year)); //get first day of the month date array.
			$week_day = $date_array['wday']; //get the first (week) day of month.
		
			$date_array = getdate( mktime (0, 0, 0, $next_mon, 0, $this->year)); //get last day of the month date array.
			$last_day = $date_array['mday']; //last day of month
		
			$max_weeks_of_month = ceil(($week_day + $last_day) / DAYS_OF_WEEK); //how many weeks a month?
		
			//set up calendar days.
			$day_name = array("Su", "M", "Tu", "W", "Th", "Fr", "Sa");
			$output .= '<table border="0">'.$this->nl;
			$output .= '<tr>'.$this->nl;
				foreach ($day_name as $x)  //write Monday to Saturday
				{
					$output .= "\t<th class='calHeader'>&nbsp;$x&nbsp;</th>\n";
				}
			$output .= '</tr>'.$this->nl;
			//set up calendar date.
			$set_day = 1;         //first day of month. 
			for ($tr=0; $tr<$max_weeks_of_month; $tr++)          //$tr for write <tr>; 5 or 6 row.
			{
				$output .= '<tr>'.$this->nl;
				for ($td=0; $td<DAYS_OF_WEEK; $td++)      //$td for write <td>; 7 column.
				{
					if ( ($week_day%DAYS_OF_WEEK == $td) && ($set_day<=$last_day) )
					{
						$output .= ($set_day == $this->day) ? ('<td class="choosed">') : ('<td class="calendar">');
						if (in_array($set_day, $ary)) //if match folder then write link.
						{
							$output .= $this->set_url($set_day, $this->year, $this->mon, $set_day);  //link is day.
						}
						else
						{
							$output .= $set_day;
						}
						$output .= '</td>'.$this->nl;
						$set_day++;
						$week_day++;
					}
					else
					{
						$output .= "\t<td bgcolor='#CCFFCC'>&nbsp;</td>\n";
					}
				}
				$output .= '</tr>'.$this->nl;
			}
			$output .= '</table>'.$this->nl;
			$this->strCalendar .= $output;
			}
			
			/**
			* setHour($ary, $row=2, $column=12)
			* set up hour html table 
			* input is  array.
			* @param $ary array
			* @param $row integer
			* @param $column integer
			* @return $str string
			*/
			
			public function setHour($ary=null, $row=2, $column=12)
			{
				if (!is_array($ary) or !isset($ary) or (null === $ary)) {
					$ary = array();
				}
				$output = '<table border="1">'.$this->nl;
				$count_num = 0;                     //increase number.
				for ($tr=0; $tr<$row; $tr++)          //$tr for write <tr>.  
				{
					$output .= '<tr>'.$this->nl;
					for ($td=0; $td<$column; $td++)      //$td for write <td>.
					{
						$links = str_pad($count_num, 2, '0', STR_PAD_LEFT); //add 0 if $count_num<10.
						if (in_array($links, $ary)) //$count_num is integer and $links is string.
						{
							$output .= ($links == $this->hour) ? '<td class="choosed">' : '<td class="calendar">';
							$output .= $this->set_url($links, $this->year, $this->mon, $this->day, $links); //$links is hour.
							$output .= '</td>'.$this->nl;
						}
						else
						{
							$output .= '<td class="calendar">'.$links.'</td>'.$this->nl; //$links is string of $count_num
						}
						$count_num++;
					}
					$output .= '</tr>'.$this->nl;
				}
				$output .= '</table>'.$this->nl;
				$this->strHour .= $output;
			}
			public function setMinute($ary=null, $row=5, $column=12)
			{
				if (!is_array($ary) or !isset($ary) or (null === $ary)) {
					$ary = array();
				}
				$output = '<table border="1">'.$this->nl;
				$count_num = 0;                     //increase number.
				for ($tr=0; $tr<$row; $tr++)          //$tr for write <tr>.  
				{
					$output .= '<tr>'.$this->nl;
					for ($td=0; $td<$column; $td++)      //$td for write <td>.
					{
						$links = str_pad($count_num, 2, '0', STR_PAD_LEFT);  //add 0 if $count_num<10.
						if (in_array($links, $ary)) //$count_num is integer and $links is string.
						{
							$output .= ($links == $this->min) ? ( '<td class="choosed">' ) : (  '<td class="calendar">' );
							$output .= $this->set_url($links, $this->year, $this->mon, $this->day, $this->hour, $links); //$links is minute.
							$output .= '</td>'.$this->nl;
						}
						else
						{
							$output .= '<td class="calendar">'.$links.'</td>'.$this->nl; //$links is string of $count_num
						}
						$count_num++;
					}
					$output .= '</tr>'.$this->nl;
				}
				$output .= '</table>'.$this->nl;
				$this->strMinute .= $output;
			}
		// add title for hour and minit.
		public function setTitle($str = '') {
			$this->strHtml .= $str.$this->nl;
		}
		
		public function getCalendar() {
			return $this->strCalendar.$this->nl;
		}
		
		public function getHour() {
			return $this->strHour.$this->nl;
		}
		
		public function getMinute() {
			return $this->strMinute.$this->nl;
		}
		
			//follow pear HTML rule.
		public function toHtml() {
			$this->strHtml .= $this->strCalendar.$this->strHour.$this->strMinute;
			return $this->strHtml.$this->nl;
		}
		
		/**
		* ts2date($ts)
		* timestamp to date array 
		* @param $ts string
		* @return $ary arraay
		*/
		
		public function ts2date($ts) {
			$year	= date("Y",$ts);
			$mon	= date("m",$ts);
			$day	= date("d",$ts);
			$hour	= date("H",$ts);
			$min	= date("i",$ts);
			$sec	= date("s",$ts);
			return array('year'=>$year,'mon'=>$mon,'day'=>$day,'hour'=>$hour,'min'=>$min,'sec'=>$sec);
		}
		
		/**
		* date2ts($year=1970, $mon=1, $day=1, $hour=0, $min=0, $sec=0)
		* date variable to timestamp
		* @param $year int
		* @param $mon int
		* @param $day int
		* @param $hour int
		* @param $min int
		* @param $sec int
		* @return $str string
		*/
		
		public function date2ts($year=1970, $mon=1, $day=1, $hour=0, $min=0, $sec=0) {
			return mktime((int)$hour, (int)$min, (int)$sec, (int)$mon, (int)$day, (int)$year);
		}
	}
?>
