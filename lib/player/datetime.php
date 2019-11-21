<?php
	function days_in_month($year, $month) {
		switch ($month) {
			case (1): return 31; break;
			case (2):
				if (($year%4) === 0) return 29;
				return 28;
			break;
			case (3): return 31; break;
			case (4): return 30; break;
			case (5): return 31; break;
			case (6): return 30; break;
			case (7): return 31; break;
			case (8): return 31; break;
			case (9): return 30; break;
			case (10): return 31; break;
			case (11): return 30; break;
			case (12): return 31; break;
		}
		return false;
	}
	function fix_date(&$year, &$month, &$day) {
		$exec_time = getdate();
		if ($year > $exec_time['year']) $year = $exec_time['year'];
		else if ($year < 2006) $year = 2006;

		while ($month > 12) {
			$month = $month - 12;
			$year++;
		}
		if ($month < 1) {
			$year--;
			$month = 12;
		}
		
		$days_month = days_in_month($year, $month);
		while (intval($day) > $days_month) {
			$day = $day - $days_month;
			$month++;
			if ($month == 13) {
				$month = 1;
				$year++;
			}
			$days_month = days_in_month($year, $month);
		}
		if ($day < 1) {
			$month--;
			if ($month < 1) {
				$month = 12;
				$year--;
			}
			$day = days_in_month($month, $year);
		}
	}
	/******************************************************************************/

