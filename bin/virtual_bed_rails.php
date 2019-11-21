#!/usr/bin/php
<?php
    require_once('uther.ezfw.php');
    require_once('memcache_alerts.php');

    load_definitions('VBR');
    load_definitions('FLAGS');
    load_libs('statectrl');

/****************************************************************************/
/********** Initialization **************************************************/

    $last_pack_time = 0;
    lower_flag(MOTION_FLAG);    // in case this was up and node lost power

    $MAKE_TIMER_STATES = (defined('CV_VBR_TIMER_STATES') ? CV_VBR_TIMER_STATES : false);
    $DEBUG = true;

    $sock = false;
    $index = rand(1, 99);

    $timers = array (
        'nowait' => 0,
        'wait' => 0,
        'listen' => 0
    );

    $sv_states = reopen_all_states('secure', 'detection');
    $vbr_states= reopen_all_states('secure', 'vbr');

    $check_stale_state_counter = 1;
    register_shutdown_function("close_vbr_states");

    udp_connect();

/****************************************************************************/
/****************************************************************************/

    function close_vbr_states () {
        global $sv_states, $vbr_states;
        close_all_states($sv_states);
        close_all_states($vbr_states);
    }

    /********************************************************************/

    function print_message($pack, $message) {
        global $DEBUG;
        if ($DEBUG)
            print '[' . getmypid() . '] ' . date("Y-m-d H:i:s") .  ": \033[01;31m{$pack['zone']}[{$pack['count']}]({$pack['delta']})\033[37m:\033[00m $message \033[00;37m\n";
    }

    function print_info($message) {
        global $DEBUG;
        if ($DEBUG)
            print '[' . getmypid() . '] ' . date("Y-m-d H:i:s") .  ": \033[00m $message \033[00;37m\n";
    }

    /********************************************************************/

    function duration ($s) {
        $m = $h = 0;
        if ($s > 59) {
            $m = intval($s/60);
            $s = $s%60;
            if ($m > 59) {
                $h = intval($m/60);
                $m = $m%60;
            }
        }
        if ($h) $str = (($h<10)?'0':'') . "$h:" . (($m<10)?'0':'') . $m . ':';
        else if ($m) $str = (($m<10)?'0':'') . "$m:";
        $str .= (($s<10)?'0':'') . $s;
        return $str;
    }

/****************************************************************************/
/****************************************************************************/

    function get_vbr_flags() {
        $tmp = unserialize(file_get_contents(abs_path('etc', 'zones', '.bed_area.vbr')));
        if (!is_array($tmp)) return array();
        if (!isset($tmp['flags'])) return array();
        return $tmp['flags'];
    }

    /********************************************************************/

    function vbr_armed() {
        $tmp = get_vbr_flags();
        if (!isset($tmp['armed'])) return false;
        if ($tmp['armed'] != 'true') return false;
        return true;
    }

/****************************************************************************/
/****************************************************************************/

    function udp_connect() {
        global $sock;

        $name = DETECTION_HOST;
        $port = DETECTION_PORT;

        $sock = socket_create(AF_INET, SOCK_DGRAM, getprotobyname('udp'));
        socket_bind($sock, $name, $port);
    }

    /********************************************************************/

    function udp_hear() {
        global $sock;
        while (true) {
            $buf = "";
            if (!is_resource($sock)) udp_connect();
            if (!socket_recvfrom($sock, $buf, 100, 0, $name, $port)) {
                sleep(5);
                $sock = false;
                continue;
            }
            $tmp = explode(' ', $buf);
            if (count($tmp) < 3) {
                print getmypid() . ": packet '$buf' has invalid parameter count\n";
                continue;
            }
            $pack = array();
            $pack['zone'] = preg_replace('/bed_area\.(.*?)\.vbr/', '$1', $tmp[0]);
            $pack['state'] = $tmp[1];
            $pack['time'] = floatval($tmp[2]);

            $pack['count'] = isset($tmp[3])?intval($tmp[3]):1;
            $pack['delta'] = isset($tmp[4])?floatval($tmp[4]):'?';
            return $pack;
        }
    }

/****************************************************************************/
/****************************************************************************/

    function trigger_virtual_bed_rails (&$pack, &$timers) {
        global $index;
        print_message($pack, "\033[01;33mTRIPPED VIRTUAL BEDRAILS!");
        unset_timer($pack, $timers, 'listen');
        unset_timer($pack, $timers, 'wait');

        if (!vbr_armed()) {
            print_message($pack, "--> Virtual Bed Rails is not armed, not signaling...");
            return;
        }

        get_memcache_connection()->set('video/active_zones', 'vbr:' . $index++ . '|', 0, CV_VBR_TRIGGER_TTL);

        $ev = new event();
        $ev->service_tag = 'secure';
        $ev->video_id = true;
        $ev->type = 'vbr';
        $ev->name = 'bed_area';
        $ev->state = 'trigger';
        $ev->time = true;
        $ev->save();

        // Log an hospital event on Server
        // We don't care about the response or any failures (besides for debugging)
        $resp = @file_get_contents('http://server.cv-internal.com/ezfw/service/hospital_events.php' .
            '?category=vbr&event=alarmed&ip=' . get_ip());
        #print_message($pack, 'log hospital_event response=' . $resp);

//      record_trigger_time('Virtual Bed Rails', 'vbr');
        set_timer($pack, $timers, 'trigger', CV_VBR_AFTER_TRIGGER_DELAY);
        sleep(CV_VBR_TRIGGER_TTL);

        get_memcache_connection()->delete('video/active_zones');
        return;
    }

/****************************************************************************/
/****************************************************************************/

    function validate_timer ($name) {
        if ($name != 'wait' && $name != 'nowait' && $name != 'listen' && $name != 'trigger') {
            print_message($pack, "WARNING: Attempted to set an unknown timer '$name'");
            return false;
        }
        return true;
    }

    /********************************************************************/

    function set_timer (&$pack, &$timers, $name, $dur) {
        if (!$dur) return unset_timer($pack, $timers, $name);

        if (!validate_timer($name)) return false;

        $msg = $name . ' timer for ' . duration($dur);
        if ($timers[$name] > $pack['time'])
            $msg = 'Resetting ' . $msg . ' (' . duration($pack['time'] - $timers[$name]) . ' was remaining)';
        else
            $msg = 'Setting ' . $msg;
        print_message($pack, $msg);

        global $MAKE_TIMER_STATES;
        if ($MAKE_TIMER_STATES) {
            global $vbr_states;
            /* if a state already exists for this timer, end it as incomplete */
            if ($vbr_states[$name]) {
                $vbr_states[$name]->end(false);
                unset($vbr_states[$name]);
            }
    
            /* start state for this timer */
            $state = state::start('vbr', 'timer', $name, intval($pack['time']));
            $state->activity = intval($pack['time']) + $dur;
            $state->annotation_level = 20;      /* fairly high as these are primarily for debugging */
            $state->save();
            $vbr_states[$name] =& $state;
        }

        return $timers[$name] = $pack['time'] + $dur;
    }

    /********************************************************************/

    function unset_timer (&$pack, &$timers, $name) {
        if (!validate_timer($name)) return false;

        if ($timers[$name] >= $pack['time'])
            print_message($pack, "Unsetting '$name' timer (" . duration($timers[$name] - $pack['time']) . ' was remaining)');
        
        global $MAKE_TIMER_STATES;
        if ($MAKE_TIMER_STATES) {
            /* end state for this timer */
            global $vbr_states;
            $state =& $vbr_states[$name];
            if ($state) {
                $state->end(intval($pack['time']));
                unset($vbr_states[$name]);
            }
        }

        $timers[$name] = 0;
        return true;
    }

    /********************************************************************/

    function set_listen (&$pack, &$timers, $listen) { return set_timer($pack, $timers, 'listen', $listen); }

    /********************************************************************/

    function set_wait (&$pack, &$timers, $wait) { return set_timer($pack, $timers, 'wait', $wait); }

    /********************************************************************/

    function set_nowait (&$pack, &$timers, $nowait) { return set_timer($pack, $timers, 'nowait', $nowait); }

/********************************************************************************************/
/********************************************************************************************/

/********************************************************************************************/
/********************************************************************************************/

    $hi_count = 0;
    function check_full_zone (&$pack, &$timers) {
        global $sv_states;
        if ($pack['state'] == 'ACTIVE') {
            /* temporary workaround for issue #3 of bug 919. --Stephen */
            if ((intval($pack['delta']) > 100) && ($hi_count++ > 9)) {
                /* restart cellnet... */
                print_message($pack, "WARNING: Outrageous full zone delta detected!  Restarting cellnet...");
                exec('/usr/bin/killall -9 cellnet');
            } else {
                /* the zone is active, set the state as active (state_active worries about whether one is already open) */
                state_active($sv_states, 'full', 'detection', intval($pack['time']));
                raise_flag(MOTION_FLAG);
                return true;
            }
        }

        $hi_count = 0;
        // the zone has become inactive, set the state as inactive ( state_inactive worries whether one
        // is open or not, and the conditions of it (being long enough, a high enough count, ect...)
        state_inactive($sv_states, 'full', intval($pack['time']));
        lower_flag(MOTION_FLAG);
        return true;
    }

    /********************************************************************/

    function check_upper_bed (&$pack, &$timers) {
        if ($timers['nowait'] > $pack['time']) return;
        if ($timers['listen'] > $pack['time']) return;
        if ($pack['state'] == 'ACTIVE')
            set_nowait($pack, $timers, CV_VBR_UPPER_ACTIVE_WAIT);
        else
            set_nowait($pack, $timers, CV_VBR_UPPER_INACTIVE_WAIT);
        return;
    }

    /********************************************************************/

    function check_lower_bed (&$pack, &$timers) {
        if ($pack['state'] != 'ACTIVE') {
            if ($timers['wait'] <= $pack['time']) // && $timers['listen'] <= $pack['time'])
                set_listen($pack, $timers, CV_VBR_LOWER_INACTIVE_LISTEN);
            return;
        }

        if ($timers['wait'] >= $pack['time'])
            print_message($pack, 'Bed trigger, ignoring from previous rail trigger for ' . duration($timers['wait'] - $pack['time']));
        else
            set_listen($pack, $timers, CV_VBR_LOWER_ACTIVE_LISTEN);

        return;
    }

    /********************************************************************/

    function check_rail (&$pack, &$timers) {
        if (!preg_match('/(upper|lower)-(left|right)-rail_(\d+?)/', $pack['zone'], &$matches)) {
            print_message($pack, 'Not an identified rail');
            return;
        }

        $quad = $matches[1];
        $side = $matches[2];
        $n = $matches[3];
        switch (true) {
            case ($pack['state'] != 'ACTIVE'): break;
            case ($timers['wait'] > $pack['time']): 
                if ($n < 2) break;
                print_message($pack, 'Resetting Inward Motion Wait');
                set_wait($pack, $timers, CV_VBR_INWARD_MOTION_WAIT);
                break;
            case ($timers['listen'] >= $pack['time']):
                if ($n < 3) {
                    if ($quad == 'upper' && $timers['nowait'] > $pack['time']) {
                        print_message($pack, 'Not tripping for upper rail while nowait is active!');
                        break;
                    } 
                    trigger_virtual_bed_rails($pack, $timers);
                    break;
                }
                print_message($pack, 'exterior rail trip, ignoring...');
                if ($timers['nowait'] > $pack['time']) break;

                print_message($pack, '--> Setting wait for inward motion during listen');
                set_wait($pack, $timers, CV_VBR_INWARD_MOTION_WHILE_LISTEN_WAIT);
                break;
            case ($timers['nowait'] <= $pack['time']):
                if ($n > 1) 
                    set_wait($pack, $timers, CV_VBR_INWARD_MOTION_WAIT);
                else
                    print_message($pack, 'Not setting inward wait from inner rail!');
                break;
            default:
                if ($n > 2) {
                    print_message($pack, 'Setting inward wait while not wait for exterior rail activity!');
                    set_wait($pack, $timers, CV_VBR_INWARD_MOTION_WAIT);
                    break;
                }
                print_message($pack, 'Not setting wait until ' . duration($timers['nowait'] - $pack['time']));
        }
        return;
    }

/****************************************************************************/
/*****  Main Loop ***********************************************************/
/****************************************************************************/

    print_info('VBR Started');
    print_info('CV_VBR_TRIGGER_TTL = ' . CV_VBR_TRIGGER_TTL . ', CV_VBR_AFTER_TRIGGER_DELAY = ' . CV_VBR_AFTER_TRIGGER_DELAY);
    print_info('CV_VBR_UPPER_ACTIVE_WAIT = ' . CV_VBR_UPPER_ACTIVE_WAIT . ', CV_VBR_UPPER_INACTIVE_WAIT = ' . CV_VBR_UPPER_INACTIVE_WAIT);
    print_info('CV_VBR_LOWER_ACTIVE_LISTEN = ' . CV_VBR_LOWER_ACTIVE_LISTEN . ', CV_VBR_LOWER_INACTIVE_LISTEN = ' . CV_VBR_LOWER_INACTIVE_LISTEN);
    print_info('CV_VBR_INWARD_MOTION_WAIT = ' . CV_VBR_INWARD_MOTION_WAIT . ', CV_VBR_INWARD_MOTION_WHILE_LISTEN_WAIT = ' . CV_VBR_INWARD_MOTION_WHILE_LISTEN_WAIT);
    print_info('MAKE_TIMER_STATES (CV_VBR_TIMER_STATES) = ' . ($MAKE_TIMER_STATES ? 'true' : 'false'));

    while ($pack = udp_hear()) {

        if (!$check_stale_state_counter--) {
            close_stale_states($sv_states);
            close_stale_states($vbr_states);
            $check_stale_state_counter = 10;
        }

        if ($pack['zone'] != 'full' && $timers['trigger']) {
            if ($timers['trigger'] < ($pack['time'])) {
                print_message($pack, 'Trigger delay has ended');
                unset_timer($pack, $timers, 'wait');
                unset_timer($pack, $timers, 'nowait');
                unset_timer($pack, $timers, 'trigger');
            } else {
                print_message($pack, 'Discarding... packet was before trigger delay...');
                continue;
            }
        }

        if ($last_pack_time < ($pack['time'] - 20)) {
            /* I have no idea what this is for... so I wont remove it
             * right away, but I am going to make it output a msg
             * so we can tell if its causing a problem...
             * --Stephen 2011-09-04
             */
            print_message($pack, 'NOTE: Last packet time was more than 20 seconds ago');
            set_wait($pack, $timers, false);
            set_nowait($pack, $timers, false);
        }

        if ($pack['state'] != 'ACTIVE') {
            print_message($pack, "[inactive]");
        } else if (($pack['count'] < 3) || ($pack['count'] % 10)) {
            print_message($pack, "[ACTIVE]");
        }

        switch (true) {
            case (strpos($pack['zone'], 'rail') !== false):
                check_rail($pack, $timers);
                break;
            case ($pack['zone'] == 'bed-lower'):
                check_lower_bed($pack, $timers);
                break;
            case ($pack['zone'] == 'bed-upper'):
                check_upper_bed($pack, $timers);
                break;
            case ($pack['zone'] == 'full'):
                check_full_zone($pack, $timers);
                break;
        }
        $last_pack_time = $pack['time'];
    }
