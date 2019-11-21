<?php
// fns/output.php
class fns_output {
	static function display_return_link() {
?>			<a href="./login.php?q=visitor" >
				<span class='lbl' style="width:200; display:inline;">
					Login as Visitor
				</span>
			</a>
			&nbsp;&nbsp;
			<a href="./login.php?q=doctor" >
				<span class='lbl' style="width:200; display:inline;">
					Login as Doctor
				</span>
			</a>
<?
	}

	static function display_visitor_login_form() { 
?>		<body onload="document.forms[0].elements[0].focus();">
			<form name="frm_login" method="post" action="../ptv/visitor_main.php">
				<div align="center">
					<table border="0" width="305" cellspacing="0" cellpadding="3" class="logfrm">
						<tr>
							<td colspan="2">&nbsp;</td>
						</tr><tr>
							<td>Your Name:</td>
							<td><input type='text' name='name' size='20' /></td>
						</tr><tr>
							<td>Patient ID:</td>
							<td><input type="password" name="passwd" size="20" /></td>
						</tr><tr>
							<td width="300" colspan="2" align="right">
								<input type="submit" value="Login" name="B1">
							</td>
						</tr>
					</table>
				</div>
			</form>
		</body>
<?	}

	static function redirect($url, $top = false) {
?>		<html>
			<body onLoad='<?=$top?'top':'document'?>.location="<?=$url?>";'>
				redirecting
			</body>
		</html>
<?	}

	static function restricted($title) {
?>      <html>
            <head>
                <title><?=$title?></title>
                <link rel="stylesheet" type="text/css" href="../css/browser.css" />
            </head><body>
                <h1>You do not have permission to access this resource!</h1>
            </body>
        </html>
<?	}


	static function display_doc_login_form() {
?>		<body onload="document.forms[0].elements[0].focus();">
			<form method="post" action="login.php">
				<div align="center">
					<table border="0" width="305" cellspacing="0" cellpadding="3" class="logfrm">
						<tr>
							<td colspan="2">&nbsp;</td>
						</tr><tr>
							<td>Login ID</td>
							<td>
								<input type="text" name="username" id="username" size="20">
							</td>
						</tr><tr>
							<td>Password</td>
							<td>
								<input type="password" name="passwd" id="passwd" size="20">
							</td>
						</tr><tr>
							<td width="300" colspan="2" align="right">
								<input type="submit" value="Login" name="B1">
							</td>
						</tr>
					</table>
				</div>
			</form>
		</body>
<?	}

	static function display_change_passwd_form() {
?>		<body onload="document.forms[0].elements[0].focus();">
			<form method="post" action="login.php" onSubmit="return verify_passwords()">
				<input type=hidden name='change_password' value='yes' />
				<div align="center">
					<table border="0" width="325" cellspacing="0" cellpadding="3" class="logfrm">
						<tr>
							<td colspan="2">&nbsp;</td>
						</tr><tr>
							<td>New Password</td>
							<td>
								<input type="password" name="newpswd" id="newpswd" size="20">
							</td>
						</tr><tr>
							<td>Confirm Password</td>
							<td>
								<input type="password" name="confpswd" id="confpswd" size="20">
							</td>
						</tr><tr>
							<td width="320" colspan="2" align="right">
								<input type="submit" value="Submit">
							</td>
						</tr>
					</table>
				</div>
			</form>
		</body>
<?	}

////////////////////////////////////////////////////////////////////////
/**	Function:	   show_login_header				 
*   Description:	display common parts doctor and  visitor login
*	Parameters:	 $hospital_name, $view_name like phisicalView and PatientView
*	Return value:	None.
*///////////////////////////////////////////////////////////////////////

	static function show_login_header($hospital_name, $view_name) {
?>		<br />
		<div  align="center">
			<table>
				<tr>
					<td colspan="2" align="center">
						<h1>Welcome to</h1>
					</td>
				</tr><tr>
					<td rowspan="2">
						<img name="" src="../img/logo3.png" width="271" height="102">
					</td><td>
						<br />
						<span class="tblhead" style="font-weight: bold; color:black;">
							<?=$hospital_name?>
						</span>
					</td>
				</tr><tr>
					<td>
						<br />
						<h1 style="font-size:38px; color:black;">
							<?=$view_name?>
						</h1>
					</td>
				</tr>
			</table>
			<br />
			<span class="tblhead">
				Connecting patients, families and healthcare providers
			</span>
		</div>
<?	}


////////////////////////////////////////////////////////////////////////
/**	Function:	   set_array_table				 
*   Description:	connect db run query then put table to 2D array table
*					out side is number array and inside is associated array. 
*	Parameters:	 SQL query string.
*	Return value:	array table.
*///////////////////////////////////////////////////////////////////////
/*
	use $ary_tbl = $GLOBALS['__db__']->fetchAll($query); now
	static function set_array_table($query) {
		$result=mysql_query($query);
		if($result) {
			$ary_tbl = array();
			while($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
				array_push($ary_tbl, $row);
			}
			@mysql_free_result( $result );
			return $ary_tbl;
		}
	}
*/

////////////////////////////////////////////////////////////////////////
/**	Function:	   js_mousover				 
*   Description:	used on table mouse over change color.
*	Parameters:	 none.
*	Return value:	none.
*///////////////////////////////////////////////////////////////////////

	static function js_mousover() {
?>		<script type="text/javascript">
			function setPointer(theRow, thePointerColor) {
				if (typeof(theRow.style) == 'undefined' || typeof(theRow.cells) == 'undefined') {
					return false;
				}
				var row_cells_cnt = theRow.cells.length;
				for (var c = 0; c < row_cells_cnt; c++) {
					theRow.cells[c].bgColor = thePointerColor;
					theRow.cells[c].borderColor = thePointerColor;
				}
				return true;
			}
		</script>
<?	}

////////////////////////////////////////////////////////////////////////
/**	Function:	   html_array_table				 
*   Description:	get 2D array table print html table with data. 
*					GET strings and start for call function pager()
*	Parameters:	 array table, page limit lines.
*					GET strings and start (current page).
*	Return value:	none.
*///////////////////////////////////////////////////////////////////////

	public function html_array_table($ary_tbl, $get_string='', $start=0, $limit=12) {
		$numrows = count($ary_tbl);
		if($numrows) {
			echo '<table border="0" cellspacing="4" cellpadding="2" frame="box" rules="all" align="center">'."\n";
			echo '<tr '; fns_common::set_css_class(1); echo '>'."\n";
			while($field = each($ary_tbl[0])) {
				echo '<th>'.$field['key'].'</th>'."\n";
			}
			echo '</tr>'."\n";
				// those variables for seperate page use.
			$start = (($start*$limit) < $numrows) ? $start : 0; // start number too big.
			$remainder = $numrows - ($start * $limit);
			$floor = floor($remainder / $limit);
			$mod_remainder = $remainder % $limit;
			$lowbound = $start*$limit;
			$upbound = (0 == $floor) ? ($start * $limit + $mod_remainder) : ($start * $limit + $limit);
				
			function print_td($value) { // ready for array_walk
				echo '<td>'.$value.'</td>'."\n";
			}
		
			for ($i=$lowbound; $i<$upbound; ++$i) {
				//echo $tr."\n";
				echo '<tr '; fns_common::set_css_class($i); echo '>';
				array_walk($ary_tbl[$i], 'print_td');
				echo '</tr>'."\n";
			}
			echo '</table><br />';
			$page_num = ceil($numrows / $limit);
			$this->pager($page_num, $start, $get_string);
		}
	}


	public function html_table($fields, $table, $clause, $order, $get_string='', $start=0, $limit=12) {
		get_db_connection();

		$start = $start * $limit;
		$numrows = intval($GLOBALS['__db__']->fetchOne('select count(logtime) ' .
								 ' from ' . $table . 
								 ' where ' . $clause));


		$ary_tbl = $GLOBALS['__db__']->fetchAll('select ' . $fields .
								 ' from ' . $table . 
								 ' where ' . $clause . 
								 ' order by ' . $order . 
								 ' limit ' . $start . ',' . $limit);
		if($numrows) {
			echo '<table border="0" cellspacing="4" cellpadding="2" frame="box" rules="all" align="center">'."\n";
			echo '<tr '; fns_common::set_css_class(1); echo '>'."\n";
			while($field = each($ary_tbl[0])) {
				echo '<th>'.$field['key'].'</th>'."\n";
			}
			echo '</tr>'."\n";
				// those variables for seperate page use.
			$start = (($start*$limit) < $numrows) ? $start : 0; // start number too big.
			$remainder = $numrows - ($start * $limit);
			$floor = floor($remainder / $limit);
			$mod_remainder = $remainder % $limit;
				
			function print_td($value) { // ready for array_walk
				echo '<td>'.$value.'</td>'."\n";
			}
		
			foreach($ary_tbl as $i) {
				echo '<tr '; fns_common::set_css_class($i); echo '>';
				array_walk($i, 'print_td');
				echo '</tr>'."\n";
			}
			echo '</table><br />';
			$page_num = ceil($numrows / $limit);
			$this->pager($page_num, $start, $get_string);
		}
	}


////////////////////////////////////////////////////////////////////////
/**	Function:	   pager			   
*   Description:	for more than one page and previous next page.
*	Parameters:	 total page numbers, start (current page) and GET strings.
*	Return value:	none.
*///////////////////////////////////////////////////////////////////////

	public function pager($page_num, $start=0, $get_string='') {
		if ($page_num > 1)	{ 	//one page do not need pager.
			$start = ($start > 0) ? $start : 0; //in case bad guy put $start = -5
			$href_str = '<a href="'.$_SERVER['PHP_SELF'].'?start=';
			if($start > 0) {
				$prev = $start - 1;
				echo $href_str.$prev.$get_string.'">&lt;&lt;&nbsp;Prev&nbsp;|</a>'."\n"; 
			}
			$i = 0;
			while ($i < $page_num) {
				echo $href_str.$i.$get_string.'">&nbsp;'.(++$i).'&nbsp;|</a>'."\n";
			}
			if($start < ($page_num-1)) {
				$next = $start + 1;
				echo $href_str.$next.$get_string.'">Next&nbsp;&gt;&gt;</a>'."\n";
			}
		}
	}
}
?>
