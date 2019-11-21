<?php
    require_once('uther.ezfw.php');
    require_once('config.php');
    load_definitions('PLAYER');
    load_definitions('VIDEO');
    load_definitions('FLAGS');

    load_libs('player');

    $buffer = '';
    $total_frames = false;
    $loaded_frames = 0;

    $id = isset($_GET['id'])?$_GET['id']:false;

    $annotation_level = 1;

    if ($id)
        $video = video::fetch($id);

    # getUserDesc, getUserDesc and stateText:
    #   Get a text description that means something to the user given an event
    #   or state object.

    function getUserDesc ( $thing ) {
        # call the appropriate function for this object
        if ( $thing instanceof state ) {
            return stateText($thing);
        } elseif ( $thing instanceof event ) {
            return eventText($thing);
        }

        return false;
    }

    function stateText( $state ) {
        switch ( strtolower($state->type) ) {
            case 'detection':
                $xl = 'Motion';
                break;
            case 'rails':
                $xl = 'Virtual Bed Rails Drawn';
                break;
            case 'armed':
                $xl = 'Virtual Bed Rails Armed';
                break;
            case 'privacy':
                $xl = ucwords($state->name . ' ' . $state->type);
                break;
            case 'view':
                if (strtolower($state->name) == 'video') {
                    $xl = 'Live Video Disabled';
                } else {
                    $xl = 'Recorded Video Disabled';
                }
                break;
            default:
                $xl = $state->type;
                break;
        }

        return $xl;
    }

    function eventText( $event ) {
        switch ( strtolower($event->type) ) {
            case 'vbr':
                switch( strtolower($event->state) ){
                    case 'trigger':
                        $xl = 'Virtual Bed Rails Alarm Triggered';
                        break;
                    case 'resolved':
                        $xl = 'Virtual Bed Rails Alarm Resolved';
                        break;
                    case 'rails':
                    case 'draw':
                        $xl = 'Virtual Bed Rails Drawn';
                        break;
                    case 'remove':
                        $xl = 'Virtual Bed Rails Erased';
                        break;
                    case 'arm':
                    case 'armed':
                        $xl = 'Virtual Bed Rails Armed';
                        break;
                    case 'disarmed':
                        $xl = 'Virtual Bed Rails Disarmed';
                        break;
                    case 'simulated':
                        $xl = 'Virtual Bed Rails Alarm Simulated';
                        break;
                    case 'alarm':
                        $xl = 'Virtual Bed Rails Alarm';
                        break;
                }
                break;
            case 'access':
                if( strtolower($event->state) == "play" )
                    $xl = 'Video Accessed';
                else if( strtolower($event->state) == "download" )
                    $xl = 'Video Downloaded';
                break;
            case 'view':
                if( strtolower($event->name) == "video" ){
                    $service = "Live Video";                    
                }else{
                    $service = "Recorded Video";
                }
                if( strtolower($event->state) == "disabled" ){
                    $state = "Set To Disabled";
                }else{
                    $state = "Set To Enabled";
                }
                $xl = "$service $state";
                break;
            case 'privacy':
                if( strtolower($event->name) == "patient" ){
                    $service = "Patient Privacy";                    
                }else{
                    $service = "Nurse Privacy";
                }
                if( strtolower($event->state) == "disabled" ){
                    $state = "Set To Disabled";
                }else{
                    $state = "Set To Enabled";
                }
                $xl = "$service $state";
                break;
            case 'auth':
                if( strtolower($event->state) == "authorized" ){
                    $xl = "PatientView Visitor Authorized";
                }else{
                    $xl = "PatientView Visitor Unauthorized";
                }
                break;
            default:
                $xl = $event->type;
                break;
        }

        return $xl;
    }

?><html>
    <head>
        <link rel='stylesheet' type='text/css' href='player.css' />
        <link rel='stylesheet' type='text/css' href='tabs.css' />
        <style>
            <? if ($video->disabled) {
            ?>img.thumbnail {
                margin: 0 0 0 0;
                width: 0;
                height: 0;
                padding: 0 0 0 0;
            }
            <? } else { ?>
            td.thumbnail_cell {
                position: relative;
                display: block;
                height: 50px;
            }
            img.thumbnail {
                position: absolute;
                top: 5px;
                left: 10px;
                margin-top: 0px; 
                margin-bottom: 0px;
                margin-right: 30px;
                margin-left: 10px;
                width: 60px;
                height: 40px; 
                z-index: 1;
            }
            img.thumbnail.overlay {
                z-index: 2;
                visibility: hidden;
            }
            <? } ?>
            img.selected.overlay {
                z-index: 6;
            }
            img.selected {
                width: 352px;
                height: 288px;
                z-index: 5;
                position: absolute;
            }
            table#overview_events_table {
                position: relative;
                width: 100%;
            }
        </style>
        <script type='text/javascript' src='tabs.js'></script>
        <script type='text/javascript' src='/ezfw/common/mouse_controls.js'></script>
        <script type='text/javascript'>
            var video_disabled = <?=$video->flags->disabled?'true':'false'?>;
            var selected_thumbnail = null;
            var selected_img = null;
            function remove_overlays () {
                var tmp = this.nextSibling;
                while ((tmp instanceof Object) && tmp.getAttribute('name') == 'overlay') {
                    var ovr = tmp;
                    tmp = tmp.nextSibling;
                    ovr.parentNode.removeChild(ovr);
                }
                if (this.parentNode) this.parentNode.removeChild(this);
                return true;
            }

            function set_selected_img (img, pos) {

                // Reusing the same img tag renders the previous src's contents
                // while loading the new contents which is _very_ misleading
                if (selected_img && selected_img.parentNode) {
                    selected_img.parentNode.removeChild(selected_img);
                }

                selected_img = document.createElement('img');
                selected_img.src = 'evthumb.php?type=' + img.getAttribute('targetType') + '&id=' + img.getAttribute('targetId') + '&cat=mini';
                selected_img.className = 'selected';
                selected_img.onmouseout = remove_overlays;
                pos.y -= 290;
                pos.x -= 420;
                if (pos.y < 1) pos.y = 1;
                if (pos.x < 1) pos.x = 1;
                pos.y += 'px';
                pos.x += 'px';
                selected_img.style.top = pos.y;
                selected_img.style.left = pos.x;
                document.body.appendChild(selected_img);

                // The thumbnail image is being shown, so switch to the full image
                selected_img.src = 'evthumb.php?type=' + img.getAttribute('targetType') + '&id=' + img.getAttribute('targetId') + '&cat=full';

                var ovr = img.nextSibling;
                while ((ovr instanceof Object) && (ovr.getAttribute instanceof Function) && ovr.getAttribute('name') == 'overlay') {
                    if (ovr.style.visibility == 'visible') {
                        var newovr = document.createElement('img');
                        newovr.setAttribute('name', 'overlay');
                        newovr.style.top = pos.y;
                        newovr.style.left = pos.x;
                        newovr.className = 'selected overlay';
                        newovr.onload = function () { this.style.visibility = 'visible'; }
                        newovr.onmouseout = function () { return selected_img.onmouseout(); }
                        newovr.src = ovr.src;
                        document.body.appendChild(newovr);
                    }
                    ovr = ovr.nextSibling;
                }
                return true;
            }

            function select_thumbnail (ev) {
                ev = ev || window.event;
                return set_selected_img(this, mouseCoords(ev));
            }
            function deselect_thumbnail (ev) {
                if (selected_img && selected_img.parentNode) {
                    remove_overlays.call(selected_img);
//                    return selected_img.parentNode.removeChild(selected_img);
                }
                return true;
            }
            function call_browser_func (name, param) {//set_date (ts) {
                if (!top.frames['browser'] || typeof(top.frames.browser[name]) != 'function') {
                    var tmp = param;
                    var n = name;
                    setTimeout(function () { return call_browser_func(n, tmp); }, 500);
                    return;
                }
                top.frames['browser'][name](param);
            }

            function load_thumbs() {
                var imgs = document.getElementsByName('thumbimg');
                if (!imgs.length) return true;
                imgs[0].onerror = imgs[0].onload = function () {
                    this.setAttribute('name', 'thumbbed');
                    return load_thumbs();
                }
                imgs[0].src = 'evthumb.php?type=' + imgs[0].getAttribute('targetType') + '&id=' + imgs[0].getAttribute('targetId') + '&cat=mini';
                var thisimg = imgs[0];
                var ts = parseInt(imgs[0].getAttribute('targetTs'));
                if (ts) for (var i in overlays) {
                    if (ts >= overlays[i][0] && (!overlays[i][1] || ts <= overlays[i][1])) {
                        var ovr = document.createElement('img');
                        ovr.className = 'thumbnail overlay';
                        ovr.setAttribute('name', 'overlay');
                        ovr.src = 'evthumb.php?type=state&id=' + i + '&cat=overlay';
                        ovr.onload = function () { this.style.visibility = 'visible'; }
                        ovr.onerror = function () { this.parentNode.removeChild(this); }
                        ovr.onmouseover = function (ev) { 
                            ev = ev || window.event; 
                            return thisimg.onmouseover(ev); 
                        }
                        ovr.onmouseout = function (ev) {
                            ev = ev || window.event; 
                            return thisimg.onmouseout(ev);
                        }
                        imgs[0].parentNode.appendChild(ovr);
                    }
                }

                imgs[0].onmouseover = select_thumbnail;
                imgs[0].onmouseout = deselect_thumbnail;
            }

<?        if (isset($_GET['ts'])) {
?>            call_browser_func('set_date', <?=$_GET['ts']?>);
<?            if (!$id) $id = 'pl' . $_GET['ts'];
        }
?>
            var overlays = [];
<?
        if ($video instanceOf video) {
            $stIter = $video->iterate_states(100);
            while (($st = $stIter->next()) instanceOf state) {
?>              overlays[<?=$st->id?>] = [<?=$st->start?>, <?=($st->end?$st->end:0)?>];
<?          }
        }
?>
            var VideoID = <?=$id?"'$id'":'false'?>;
            var Width =  '100%';
            var Height = '100%';
            var Background = "#ffffff";
            var domain = '<?=$domain?>';
            var player_folder = '<?=$player_folder?>';
<?             if ($id) {
?>                call_browser_func('set_focused', '<?=$id?>');
<?            }
?>
//            var path2 = '<?=$player_folder?>';
        </script>
    </head>
    <body <?=$id?"onLoad='enableTab.apply(document.getElementById(\"info_tab\"));load_thumbs();'":''?>>
<?    if ($id) {
?>        <div class=tab_frame id='video_tabs'>
            <div class=tab_header>
                <div class='tab_label hand' id='info_tab' tab:body='info_body' onClick='enableTab.apply(this);'>Overview</div>
                <!-- <div class=tab_label id='event_tab' tab:body='event_body' onClick='enableTab.apply(this);'>Events</div> -->
                <div class='tab_label hand' id='state_tab' tab:body='state_body' onClick='enableTab.apply(this);'>Advanced</div>
<?            if (!$video->flags->disabled) {
?>              <div class='tab_label hand' id='player_tab' tab:body='player_body' onClick='enableTab.apply(this);'
                  <?=($video->store_tag == 'buffer')?"onmouseup='alert(\"Please Note: Starting playback may take a few minutes as we prepare this video (this delay only occurs when attempting to playback fairly new video).\");'":''?>
                >Play</div>
<?            }
?>            </div>


            <!-- INFO TAB BODY -->
            <div class='tab_body info_body' id='info_body'>
                <div class="title">Info</div>
                <table>
                    <tr class='odd_row'>
                        <td class='title'>Start:</td>
                        <td class='value'><?=date('m/d/Y H:i:s', $video->start)?></td>
                    </tr>
                    <tr class='even_row'>
                        <td class='title'>End:</td>
                        <td class='value'><?=date('m/d/Y H:i:s', $video->end)?></td>
                    </tr>
                    <tr class='odd_row'>
                        <td class='title'>Duration:</td>
                        <td class='value'>
                        <?
                            $m = floor($video->duration / 60);
                            $s = $video->duration % 60;
                            print "$m min $s sec";
                        ?>
                        </td>
                    </tr>
                </table>
                <br>
                <div class="title">Events</div>
                <table style='width: 100%' id='overview_events_table'>
                    <tr class='even_row'>
                        <!-- <th>Service</th> -->
                        <th>Time</th>
                        <th>Duration</th>
                        <th>Event</th>
                        <th>Thumbnail</th>
                    </tr>
<?                $r = true;
                /*  this probably looks incredibly confusing.
                    what I'm doing here is zippering together the lists of states and events- the user has no
                    concept of the difference, and they'll want to see both. we iterate over events, and check the
                    time of the current state and event. we barf out a table row for the one with the earliest time,
                    and advance the appropriate iterator. when both iterators are returning false, exit the loop.
                */
                $eventIterator = $video->iterate_events($annotation_level);
                $stateIterator = $video->iterate_states($annotation_level);
                $ev = $eventIterator->next();
                $st = $stateIterator->next();
                while ($ev || $st) {
                        $evtime = ($ev)?$ev->time:time();
                        $sttime = ($st)?$st->start:time();
                        if ( $evtime <= $sttime ) {                        
?>                    <tr class='<?=$r?'odd':'even'?>_row'>
                        <td><?=date('H:i:s', $ev->time)?><br>
                        <?
                            $offset = $ev->time - $video->start;
                            $m = floor($offset / 60);
                            $s = $offset % 60;
                            if ( $ev->type != 'access' ) {
                                print "($m min $s sec)";
                            }
                        ?>
                        </td>
                        <td> -- </td>
                        <td><? print getUserDesc($ev); ?></td>
                        <? if ($ev->type != 'access') { ?>
                        <td class='thumbnail_cell'>
                            <img class=thumbnail targetTs=<?=$ev->time?> targetId=<?=$ev->id?> targetType="event" id="e_<?=$ev->id?>" name="thumbimg" style='width: 60px; height: 40px;' src='images/loading.gif'/></td>
                        <? } else { ?>
                        <td></td>
                        <? } ?>
                    </tr>
<?
                        $ev = $eventIterator->next();
                        } else {
?>                    <tr class='<?=$r?'odd':'even'?>_row'>
                        <td><?=date('H:i:s', $st->start)?><br>
                        (<?
                            $offset = $st->start - $video->start;
                            if ( $offset >= 0 ) {
                                $m = floor($offset / 60);
                                $s = $offset % 60;
                                print "$m min $s sec";
                            } else {
                                print "Began in previous video";
                            }
                        ?>)
                        </td>
                        <td>
                            <? 
                                if ( $st->duration > 0 ) {
                                    print $st->duration . " sec";
                                } else {
                                    print "Ongoing";
                                }
                            ?>
                        </td>
                        <td><? print getUserDesc($st); ?></td>
                        <td class='thumbnail_cell'>
                            <img class=thumbnail targetTs=<?=$st->start?> targetId=<?=$st->id?> targetType="state" id="s_<?=$st->id?>" name="thumbimg" class='thumbnail' src='images/loading.gif'/></td>
                    </tr>
<?
                        $st = $stateIterator->next();
                        }
?>

<?            
                    $r = !$r;
                }
?>
                </table>
            </div>

            <!-- Advanced tab body -->
            <div class='tab_body state_body' id='state_body'>
                <div class="title">Advanced Info</div>
                <table>
                    <tr class='odd_row'>
                        <td class='title'>ID:</td>
                        <td class='value'><?=$video->id?></td>
                    </tr>
                    <tr class='even_row'>
                        <td class='title'>Store:</td>
                        <td class='value'><?=$video->store_tag?></td>
                    </tr>
                    <tr class='odd_row'>
                        <td class='title'>Filename:</td>
                        <td class='value'><?=$video->filename?></td>
                    </tr>
                       
                </table>
                <br>
                <div class="title">States</div>
                <table style='width: 100%'>
                    <tr class='even_row'>
                        <th>Service</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Duration</th>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Weight</th>
                    </tr>
<?                $r = true;

                // now we can print out lists of the states and events with all the relevant columns
                $stateIterator = $video->iterate_states($annotation_level+10);
                while ($ev = $stateIterator->next()) {

?>                    <tr class='<?=$r?'odd':'even'?>_row'>
                        <td><?=$ev->service_tag?></td>
                        <td><?=date('m/d H:i:s', $ev->start)?></td>
                        <td><?=($ev->end)?date('m/d H:i:s', $ev->end):'Not Ended'?></td>
                        <td><?=($ev->end)?($ev->duration.' sec'):''?></td>
                        <td><?=$ev->type?></td>
                        <td><?=$ev->name?></td>
                        <td><?=sprintf("%.4f", $ev->weight)?></td>
                    </tr>
<?            
                    $r = !$r;
                }
?>
                </table>
                <br>
                <div class="title">Events</div>
                <table style='width: 100%'>
                    <tr class='even_row'>
                        <th>Service</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Name</th>
                        <th>State</th>
                        <th>Weight</th>
                    </tr>
<?                $r = true;

                $eventIterator = $video->iterate_events($annotation_level+10);
                while ($ev = $eventIterator->next()) {

?>                    <tr class='<?=$r?'odd':'even'?>_row'>
                        <td><?=$ev->service_tag?></td>
                        <td><?=date('H:i:s', $ev->time)?></td>
                        <td><?=$ev->type?></td>
                        <td><?=$ev->name?></td>
                        <td><?=$ev->state?></td>
                        <td><?=$ev->weight?></td>
                    </tr>
<?            
                $r = !$r;
            }
?>
                </table>
            </div>

            <!-- Play tab body -->
            <div class='tab_body player' id='player_body'>
                <script src="player_box.js" type="text/javascript"></script>
            </div>


        </div>
<?    }
?>
    </body>
</html>

