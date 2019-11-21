<?php

	function request_year() {
		return isset($_GET['YEAR'])?intval($_GET['YEAR']):date('Y');
	}
	function request_month() {
		return isset($_GET['MONTH'])?intval($_GET['MONTH']):date('m');
	}
	function request_day() {
		return isset($_GET['DAY'])?intval($_GET['DAY']):date('d');
	}
	function request_entry() {
		return isset($_GET['ENTRY'])?$_GET['ENTRY']:false;
	}
	function request_hour() {
		return isset($_GET['HOUR'])?intval($_GET['HOUR']):-1;
	}


