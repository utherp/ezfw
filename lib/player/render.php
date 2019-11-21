<?php
	/******************************************************************************/
	function draw_calendar($timestamp) {
		if (!is_object($timestamp)) $timestamp = new DateTime("@$timestamp");

		$month_name = $timestamp->format('F');
		$year = $timestamp->format('Y');
		$day = $timestamp->format('d');
		$timestamp->modify('-' . (intval($timestamp->format('j'))-1) . ' day');


		$timestamp->modify("-1 month");
		$last_link = browser_uri($timestamp->format('U'));
		$timestamp->modify("+2 month");
		$next_link = browser_uri($timestamp->format('U'));
		$timestamp->modify("-1 month");

?>		<div class="calyear">
			<a href='<?=$last_link?>'>&lt;&lt;</a> &nbsp;&nbsp;
			<b><?=$month_name?>, <?=$year?></b> &nbsp;&nbsp;
			<a href='<?=$next_link?>'>&gt;&gt;</a>
		</div>	

		<table>
			<tr>
<?				foreach (array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat') as $day_name) {
?>					<th class='calHeader'>
						<?=$day_name?>
					</th>
<?				}

?>			</tr>
			<tr>
<?				$d = array_flip(array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'));
				$cur = $d[$timestamp->format('D')];
				$col = 0;
				for ($i = 0; $i < $cur; $i++) {
	?>				<td bgcolor='#CCFFCC'>&nbsp;</td>
<?					$col++;
				}
/*				$timestamp->modify('+1 month -1 day');
				$days_month = intval($timestamp->format('j'));
				$timestamp->modify('+1 day -1 month');
				//$days_month = days_in_month($year, $month);
				$this_day = 1;
*/

				$this_day = $timestamp;
				$m = intval($timestamp->format('m'));
				while (intval($this_day->format('m')) == $m) {
					if ($col == 7) {
						$col = 0;
						print "\t\t\t</tr><tr>\n";
					}
					$archived = day_has_archives($this_day);
//					$buffered = day_has_buffer($this_day);
//					if ($buffered && $archived) $color = BOTH_COLOR;
//					else if ($buffered) $color = BUFFER_COLOR;
					if ($archived) $color = ARCHIVE_COLOR;
					else $color = NO_VIDEO_COLOR;
					if ($day == $this_day->format('d')) $color = SELECTED_COLOR;

	?>				<td class=calendar style='background-color: <?=$color?>;'>
<?

						$text = $this_day->format('d');;
						if ($day == $text) {
							$text = '<big><b>' . $text . '</b></big>';
						}
						

//						$text = '<font color="' . $color . '">' . $text . '</font>';
						if ($archived)
							$text = '<a href="' . browser_uri($this_day) . '">' . $text . '</a>';
?>						<?=$text?>
					</td>
<?
					$col++;
					$this_day->modify('+1 day');
				}

				for ($i = $col; $i < 7; $i++ ) {
	?>				<td bgcolor='#CCFFCC'>&nbsp;</td>
<?				}
?>			</tr>
		</table>
<?	}
	/******************************************************************************/
	function printable_time($timestamp) {
		return date('H:i:s', $timestamp);
	}
	/******************************************************************************/
	function mod_date($t, $a) {
		if (!is_array($a)) $a = array(DAYS => $a);

		foreach ($a as $k => $v) {
			if (is_int(intval($v))) $t[$k] += intval($v);
		}
		fix_date($t);
		return $t;
	}
	/******************************************************************************/
	function draw_entry($entry, $uri, $entry_string, $color, $selected) {
		global $dl_archive;
		$movie_name=$entry['start']."-".$entry['end'].".mpg";
		$tmp = stat($entry['filename']);
		$mode = $tmp[2] & 0777;
		$selected = ($mode != 0644);
//		$selected = is_executable($entry['filename']);
//						?=($selected)?'<b>':''?
//						?=($selected)?'</b>':''?
?>		<tr<?=$selected?' class=selected':''?> id='<?=$entry['start']?>'>
			<td align=left>
				<a href='player.php?id=<?=$entry['start']?>' target='player' >
					<?=printable_time($entry['start'])?>
				</a>
			</td>
			<td><?=($selected)?'<b>**</b>':'--'?></td>
			<td align=right>
				<font color='<?=$color?>'>
					<a href='player.php?id=<?=$entry['start']?>' target='player'>
						<?=printable_time($entry['end'])?>
					</a>

				</font>
			</td>
<?	}

	/******************************************************************************/
	function draw_archive_entries($timestamp, $selected = -1) {
		if (!is_object($timestamp)) $timestamp = new DateTime("@$timestamp");
		list($year, $month, $day) = explode('_', $timestamp->format('Y_m_d'));
		$entries = archive_entries($year, $month, $day);
		return draw_entries($timestamp, $entries, 'Archive', $selected);
	}

	/******************************************************************************/
	function draw_buffer_entries($timestamp, $selected = -1) {
		if (!is_object($timestamp)) $timestamp = new DateTime("@$timestamp");
		list($year, $month, $day) = explode('_', $timestamp->format('Y_m_d'));

		return draw_entries($timestamp, buffer_entries($year, $month, $day), 'Buffer', $selected);
	}

	/******************************************************************************/
	function draw_entries($timestamp, $entries, $header, $selected = -1) {
?>
		<table width=100%>
			<tr>
				<td style='text-align: left;'><b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Start</b></td>
				<td></td>
				<td style='text-align: left;'><b>End&nbsp;&nbsp;</b></td>
			</tr>
		</table>
		<div style='height: 215px; overflow: auto;'>
			<table width=100%>

<?			$this_entry = false;
			if (isset($entries) && is_array($entries)) 
				foreach ($entries as $i => $a) {
					if (!is_numeric($i)) continue;
					draw_entry(
						$a,
						create_uri(
							$timestamp,
							$a['index']
						) . '&player=load',
						$a['number'],
						constant(strtoupper($header) . '_COLOR'),
						($selected == $a['number'])?true:false
					);
					if ($selected == $a['number']) $this_entry = $a;
				}
?>			</table>
		</div>

<?		return $this_entry;
	}
	/******************************************************************************/
	function draw_content($timestamp, $entry) {
		if (!is_object($timestamp)) $timestamp = new DateTime("@$timestamp");
		list($year, $month, $day) = explode('_', $timestamp->format('Y_m_d'));

		if (is_array($entry)) {
			$start_time = date('H:i:s', $entry['start']);
			print js_exec("\nvar tz_offset = (new Date()).getTimezoneOffset();\n var start_time = {$entry['start']} * 1000 - (new Date()).getTimezoneOffset() * 60 * 1000;\n");
		} else {
			$start_time = Date('H:i:s');
			print js_exec('var start_time = (new Date()).getTime()');
		}
?>		<table width=400 height=404>
			<tr>
				<td width=400 colspan=3 align=center>
					<div id="datestamp" style="background-color: Gray; color: Lime;">
						<?=date('F d, Y', mktime(0,0,0,$month, $day, $year))?>
					</div>
				</td>
			</tr>
			<tr>
				<td width=24 id='zones' align=center><? draw_zones();?></td>
				<td width=352 height=288 id='videoContainer' align=center></td>
				<td width=24 id='right_col' align=center></td>
			</tr>
			<tr>
				<td width=400 colspan=3 align=center>
					<div id="timestamp" style='background-color: Gray; color: Lime;'>
						<input type=text id='stamp' style='width: 300px; background-color: Gray; color: Lime; border: none; text-align : center;' value="<?=$start_time?>" />
					</div>
				</td>
			</tr>
<?		global $video_opened;
		if ($video_opened) {
?>			<tr>
				<td width=400 colspan=3 align=center>
					<script type='text/javascript'>
						var my_slider = new slider(A_INIT, A_TPL);
					</script>
					<div id='controls' style='background-color: Grey;'>
						<br />
<?						draw_controls($timestamp, $entry);
?>				</td>
			</tr>
<?		}
?>		</table>
<?	}
	/******************************************************************************/
	function draw_zones() {
		return;
?>		Z<br />
		O<br />
		N<br />
		E<br />
		S<br />

<?	}
	/******************************************************************************/
	function draw_controls($timestamp, $entry) {
		global $video_opened;
		if (!is_object($timestamp)) $timestamp = new DateTime("@$timestamp");
//		if (file_exists($entry['filename'])) $video_loaded = true;
		if ($video_opened) {
?>
			<img src='img/control/inactive/back.png'
				 id=control_back onClick='button_set(this, "back");' />
			&nbsp;&nbsp;
			<img src='img/control/inactive/play.png'
				 id=control_play onClick='button_set(this, "play");' />
			&nbsp;&nbsp;
			<img src='img/control/inactive/pause.png'
				 id=control_pause onClick='button_set(this, "pause");' />
			&nbsp;&nbsp;
			<img src='img/control/active/stop.png'
				 id=control_stop onClick='button_set(this, "stop");' />
			&nbsp;&nbsp;
			<img src='img/control/inactive/forward.png'
				 id=control_forward onClick='button_set(this, "forward");' />
			<br />
			<div id='control_display'></div>
<?			$loc = $timestamp->format('Y-m-d');
?>			<div style='width: 400; text-align: right;'>
				<a href='../streams/viewer.php?ENTRY=<?=$entry['index'] . '_' . $loc?>&download=1'>Download</a>
			</div>
<?		}
	}

