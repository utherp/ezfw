<?php
    require_once('uther.ezfw.php');
    load_definitions('flags');

    if (flag_raised(STANDALONE_FLAG)) {
        print "<font color=red size=4><b>Warning: Standalone flag raised, skipping authentication</b></font><br />\n";
        $dl_archive = true;
    } else {
        new session_login();
        if (!$_SESSION['_login_']->validate_permission('secureView')) {
?>          <html><body onload='top.location = "vidview.php"'></body></html>
<?          exit;            
        }
        $dl_archive = $_SESSION['_login_']->validate_permission('dl_archives', false);

        if (!is_array($_SESSION['_video_cache_'])) {
            $cache = array();
            $_SESSION['_video_cache_'] =& $cache;
        } else
            $cache =& $_SESSION['_video_cache_'];
    }

    /********************************************
        this turns off caching of loaded objects
        so we don't use too much memory during
        iteration
    */
    define('CV_CACHE_OBJECTS', false);

    load_libs('player');

    load_definitions('BROWSER');
    load_definitions('VIDEO');
    load_definitions('NODE_WEB');
    load_definitions('FLAGS');

    $db = get_db_connection();

    # where we'll store the states and events we're about to grab
    $statesSet = array();
    $eventsSet = array();

    /* need place to set this in interface (maybe just user/group setting?) */
    $annotation_level = 30;


    $script_name = 'browser.php';
    $room = load_object(LOCATION_TYPE);
    $row = true;

    $now = time();

    $current = date('Y/m/d', $now);

    // GET[ts] tells us what time range to get videos for
    $ts = isset($_GET['ts'])?intval($_GET['ts']):$now;
    if (!is_numeric($ts)) $ts = $now;

    list ($year, $month, $day) = explode('/', ($dstr = date('Y/m/d', $ts)));

    $today = false;
    if ( $current == $dstr) {
        $today = true;
    }

    $mstart = mktime(1, 1, 1, $month, 1, $year);
//    $mend = mktime(1, 1, 1, (($month==1)?12:$month-1), 1, (($month==1)?$year-1:$year));
    $week_offset = intval(date('w', $mstart));

    switch ($month) {
        case('09'):
        case('04'):
        case('06'):
        case('11'):
            $days = 30;
            break;
        case('02'):
            $days = ($year%4)?28:29;
            break;
        default:
            $days = 31;
    }

    /*
        video_event_types() and video_event_types():
        These functions will now go get all states and events for videos on
        the selected day when invoked with no arguments, or will return the
        same kind of counts that the old versions would return when given a
        video_id. You'll need to seed arrays before using the data.

        Note that they no longer take lists of types- these functions are 
        ONLY called on this page, in one place. They look for the same things
        every single time they're called.
    */
    function video_event_types ($video_id = null) {
        global $year, $month, $day;
        global $annotation_level, $eventsSet, $db;

        if ( $video_id == null ) {
            $query = "SELECT e.video_id, e.type, e.state, count(e.id) as c" .
                     "  FROM events e, date_records r" .
                     " WHERE r.year = $year" .
                     "   AND r.month = $month" .
                     "   AND r.day = $day" .
                     "   AND r.event = e.id" .
                     "   AND e.annotation_level <= $annotation_level " .
                     " GROUP BY e.video_id, e.type, e.state";
/*            
            $query = "SELECT e.video_id, e.type, e.state, count(e.id) as c 
                        FROM events e
                        JOIN videos v
                          ON e.video_id = v.id 
                       WHERE v.start >= '$year-$month-$day 00:00'
                         AND v.end <= '$year-$month-$day 23:59:59'
                         AND e.service_tag IN ('secure','vbr')
                         AND e.annotation_level >= $annotation_level
                    GROUP BY e.type,e.state,e.video_id";
*/            
            $rowset = $db->fetchAll($query);
            foreach ($rowset as $row) {
                $eventsSet[$row['video_id']][$row['type'] . '-' . $row['state']] = $row['c'];
            }

            return null;
        }

        return (isset($eventsSet[$video_id]) ? $eventsSet[$video_id] : array());
    }

    function video_state_types ($video_id = null) {
        global $year, $month, $day;
        global $annotation_level, $statesSet, $db;
        if ( $video_id == null ) {
            $query = "SELECT s.video_id, s.type, count(s.id) as c" .
                     "  FROM states s, date_records r" .
                     " WHERE r.year = $year" .
                     "   AND r.month = $month" .
                     "   AND r.day = $day" .
                     "   AND r.state = s.id" .
                     "   AND s.annotation_level <= $annotation_level " .
                     " GROUP BY s.video_id, s.type";
/*            
            # TODO: this won't pick up states that started in previous videos,
            # or ended in future ones.
            $query = "SELECT v.id as video_id, v.start, v.end, s.type, count(s.id) as c 
                        FROM videos v, states s 
                       WHERE v.start >= '$year-$month-$day 00:00'
                         AND v.end <= '$year-$month-$day 23:59:59'
                         AND s.start < v.end
                         AND s.end > v.start
                         AND s.type = 'detection'
                         AND s.annotation_level >= $annotation_level
                    GROUP BY video_id,s.type";
*/                    
            $rowset = $db->fetchAll($query);
            foreach ($rowset as $row) {
                $statesSet[$row['video_id']][$row['type']] = $row['c'];
            }

            return null;
        }

        return (isset($statesSet[$video_id]) ? $statesSet[$video_id] : array());
    }

    # fetch the states and events for this day's videos
    video_state_types();
    video_event_types();

// Page Head and CSS Link
?><html>
    <head>
        <link rel='stylesheet' type='text/css' href='browser.css' />
        <link rel='stylesheet' type='text/css' href='calendar.css' />
        <link rel='stylesheet' type='text/css' href='entrylist.css' />
        <script type='text/javascript' src='browser.js'></script>
        <script type="text/javascript">
            x = 20;
            y = 70;
            function setVisible(obj)
            {
                obj = document.getElementById(obj);
                obj.style.visibility = (obj.style.visibility == 'visible') ? 'hidden' : 'visible';
            }
            function placeIt(obj)
            {
                obj = document.getElementById(obj);
                if (document.documentElement)
                {
                    theLeft = document.documentElement.scrollLeft;
                    theTop = document.documentElement.scrollTop;
                }
                else if (document.body)
                {
                    theLeft = document.body.scrollLeft;
                    theTop = document.body.scrollTop;
                }
                theLeft += x;
                theTop += y;
                obj.style.left = theLeft + 'px' ;
                obj.style.top = theTop + 'px' ;
                setTimeout("placeIt('legend')",500);
            }
        </script>

        <script type=text/javascript>
            var ts=<?=$ts?>;
            var dateObj = new Date();
            dateObj.setTime(ts*1000);
            function fix_list() {
                window.onresize = fix_list;
                var list = document.getElementById('listBody');
                var disp = list.style.display;
                if (disp == 'none') disp = '';
                list.style.display = 'none';
                var head = document.getElementById('listHead');
                var h = list.parentNode.parentNode.clientHeight;
                h -= head.clientHeight;
                h -= 8;
                list.style.height = h+'px';
                list.style.display = disp;
            }

            function initialize() {
                document.getElementById('browserBar').style.visibility = 'visible';
                fix_list();
                findFocus();
                selectChecked();
            }

            /*  if we're pre-setting a row as focused (because it's the video being currently recorded)
                we need to set the focusedEntry ref to it, otherwise it won't get unset properly when
                the user selects a different row
            */
            function findFocus() {
                var tbl = document.getElementById('entries')
                for (var rowIndex in tbl.rows) {
                    var row = tbl.rows[rowIndex];
                    if ( row.className && row.className.indexOf('focusedEntry') > -1 )  {
                        focusedEntry = row;
                    }
                }
            }
        </script>
        <title>SecureView for Room '<?=$room->get_name()?>'</title>
    </head>
    <body onLoad='initialize()'>
      <table id=browserBar style='visibility: hidden' class='browserBar'><tr><td class='calendarCell'>
        <div class='calendar'>
            <table class="calHead"><tr>
                <td class='calNav hand' onClick='previousMonth();'>&lt;&lt;</td>
                <td><?=date('F, Y', $ts)?></td>
                <td class='calNav hand' onClick='nextMonth();'>&gt;&gt;</td>
            </tr></table>
            <table class='calBody'>
                <tr class='calDayNames'>
                    <th>Sun</th>
                    <th style='font-size: 16px; padding: 1px 0px 1px 0px;'>Mon</th>
                    <th>Tue</th>
                    <th style='font-size: 16px;'>Wed</th>
                    <th>Thu</th>
                    <th style='padding: 1px 2px 1px 2px;'>Fri</th>
                    <th style='padding: 1px 2px 1px 2px;'>Sat</th>
                </tr>
<?
        $d = 1 - $week_offset;
        while ($d <= $days) {
            # TODO: calling day_has_events() for every single day is really, really stupid
            # it doesn't cause the massive slowdown that video_state_types() does, but
            # we should still come back and fix this later
?>            <tr class='calDays'>
<?            for ($i=0; $i<7; $i++, $d++) {
                $cls = ''; $ds = $d;
                if ($ds < 10) $ds = '0'.$ds;
                $dts = ($d>0)?mktime(1, 1, 1, $month, $d, $year):0;

                if ($d < 1 || $d > $days) {
                    $ds = '&nbsp;';
                    $dts = 0;
                    $cls = 'noDay';
                } else {
                    $cls = 'hand';
                    if ($v = day_has_events($year, $month, $d)) $cls .= ' hasEvents';
                    if ($v = day_has_states($year, $month, $d)) $cls .= ' hasStates';
                    if ($v = day_has_video($year, $month, $d)) $cls .= ' hasVideo';
                }

                $eid = '';
                if ($day == $d) $eid = 'selectedDay';
                else if ($current == "$mstr/$ds") $eid = 'currentDay';
?>                <td <?=$eid?"id='$eid'":''?> class='<?=$cls?>' onClick='return <?=($dts)?"dayClick($d)":'false'?>;'><?=$ds?></td>
<?            }
?>            </tr>
<?        }
?>            </table>
        </div>

        </td></tr>
        
        <tr><td>
<? include "legend.php"; ?>
            <a href="#" onclick="setVisible('legend');return false" target="player">Legend</a>
        </td></tr>

        <tr><td class='listCell'>
<!-- Entry List -->

        <div class='entryList'>
            <table id='listHead' class=entryListHead><tr>
<?
                if( $dl_archive ){
?>
                    <th class='download'><img id='dlButton' src='images/dl.gif' class='downloadButton' onClick='downloadSelected()' /></th>
<?                }else{
?>
                    <th></th>
<?
                }
?>
                <th class='start'>Start</th>
                <th class='end'>End</th>
                <th class='duration'>Dur.</th>
                <th class='events'>Events</th>
                <th style='width: 16px;'></th>
            </tr></table>
            <div id='listBody' class='entryListBody' style='display: none'>
                <table id='entries' class='entryListTable'>
<?
//                $vidData = get_db_connection()->fetchAll("select v.* from videos v, date_records r where r.video = v.id and r.year = '$year' AND r.month = '$month' AND r.day = '$day' ORDER BY v.start asc");
                $vids = load_videos($year, $month, $day);
                foreach ($vids as $v) {
//                    $v = new video($data, true);
                    $rid = '';
                    // don't bother doing this unless it's today's list
                    if ($today && $ts >= $v->start && $ts <= $v->end)
                        $rid = 'focusedEntry';
?>                    <tr class='<?=($row=!$row)?'odd':'even'?>_row <?=$rid?$rid:''?>' id='<?=$v->id?>'>
<?
                    if( $dl_archive && !$v->flags->disabled) {
?>
                        <td class='download'><input type=checkbox onClick='return dlClick(this);' /></td>
<?                    }else if ($v->flags->disabled) {
?>                      <td>XX</td>
<?
                      } else {
?>
                        <td></td>
<?
                    }
?>
                        <td class='start hand' onClick='return entryClick(this);'><?=date('H:i', $v->start)?></td>
                        <td class="end hand" onClick='return entryClick(this);'><?=date('H:i', $v->end)?></td>
                        <td class='duration hand' onClick='return entryClick(this);'><?=time_string($v->duration)?></td>
                        <td class='events'
<?
                        $types = array_merge(video_event_types($v->id), video_state_types($v->id));
                        foreach ($types as $ev => $c) {
?>                            ><img eventName='<?=$ev?>' class='eventIcon' src='images/events/<?=$ev?>.gif'
                                 onClick='return eventClick(this);'
                                 onError='this.style.visibility="hidden"'
                                 title='<?=$c?> "<?=$ev?>" events'
<?                        }
?>                        ></td>
                    </tr>
<?                }
?>                </table>
            </div>
        </div>

        </td></tr></table>

<!--    </div>-->
    </body>
</html>

