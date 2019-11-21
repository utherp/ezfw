#!/usr/bin/php
<?php
    require_once('uther.ezfw.php');

    load_definitions('STREAM_TEST');
    load_definitions('CONVERTER');
    load_definitions('FLAGS');
    load_definitions('VIDEO');
    load_definitions('BUFFER');

    load_libs('children.php');

    $cache_name = 'video/' . THUMB_SIZE;
    $last_restart = file_exists(flag_path('last_restart.flag'))?intval(read_flag('last_restart.flag')):0;
    $last_reboot = file_exists(flag_path('last_reboot.flag'))?intval(read_flag('last_reboot.flag')):0;

    if ($last_restart) 
        debugger($cache_name . ': last restart "' . date('Y/m/d H:i:s', $last_restart) . '"', 3);
    else
        debugger($cache_name . ': no last restart...', 3);

 /********************************************\
|               Constants                      |
 \********************************************/
    $sleep_time = 1000000;
    $request_exit = false;

    /***************************************/
    function init($level, $exit = true) {
        global $cache_name;
        $level = intval($level);
        if (!$level) logger('Error: not level "' . $level .'"');
        logger($cache_name . ': Changing to init level "' . $level .'"');
        system('/sbin/init ' . $level);
        if ($exit === true) exit;
    }

    function check_camera_input () {
        $input = intval(exec("v4l2-ctl -I | sed '".'s,^.* : \([0-9]*\).*$,\1,'."'"));
        return ($input == intval(CAMERA_INPUT_NUMBER));
    }

    function stop_camera(){
        logger('--> stopping the camera (copier and yuv_to_jpeg)');
        exec(abs_path('sbin', 'camera-ctl.sh stop --keep stream_test'));
        sleep(5);
    }

    function reset_camera(){
        logger('--> reloading video module');
        exec(abs_path('sbin', 'video-ctl.sh stop --keep stream_test'), $output, $ret);
        if ($ret)
            logger('Warning: failed to unload module: "' . implode("\n", $output));
        sleep(2);
        exec(abs_path('sbin', 'video-ctl.sh start --keep stream_test'), $output, $ret);
        if ($ret)
            logger('ERROR: failed to load module: "' . implode("\n", $output));
        sleep(2);

    }

    function start_camera(){
        logger('--> starting the camera (copier and yuv_to_jpeg)');
        exec(abs_path('sbin', 'camera-ctl.sh start --keep stream_test'));
        sleep(5);
    }

    function reboot(){
            // don't do this during an FAI update (see bug 337)
            if ( flag_raised('fai_updating.flag') ) {
                if ( filemtime(flag_path('fai_updating.flag')) + 600 < time() ) {
                    // ... unless the flag is old enough to be erroneous
                    logger('FAI flag from ' . filemtime(flag_path('fai_updating.flag')) . ' too old (currently ' . time() . '), lowering...');
                    lower_flag('fai_updating.flag');
                } else {
                    logger('FAI update flag is raised, canceling reboot!');
                    return false;
                }
            }

            raise_flag('last_reboot.flag', time());
            init(REBOOT_INIT_LEVEL, false);
            sleep(300);
            exit;
    }

    function restart_software($last_restart, $reboot = false, $insist = false) {
        global $cache_name;
        global $last_reboot;

        raise_flag('last_restart.flag', time());

        while ((($last_restart + 600)  >= time()) || $reboot) {
            if ($reboot) logger($cache_name . ": Reboot forced!");
            else {
                logger('Last restart too recent, rebooting! (last: ' . date('Y/m/d H:i:s', $last_restart) . ')');
                if (($last_reboot + 1200) >= time()) {
                    logger('--> Last reboot was too recent, resuming software restart instead...');
                    break;
                }
            }
            // if we're not allowed to reboot, quit trying
            if ( ! reboot() ) { break; }
        }

        logger('Restarting software...', true);
        stop_camera();
        reset_camera();
        start_camera();
        logger('Restarting stream test...', true);
        exit;
    }   

    function wait_for_converter() {
        global $cache, $cache_name, $last_index, $last_restart;
        debugger($cache_name . ": Waiting for Converter...", 2);
        $readerr = 0;
        do {
            $readerr = 0;
            while (($last_index = $cache->get($cache_name . '/index')) === false) {
                usleep(500000);
                if (++$readerr == 20) {
                    logger($cache_name . ": Converter never started!");
                    restart_software($last_restart);
                    $readerr = 0;
                }
            }
        } while ($readerr == -1);
        debugger($cache_name . ": Converter has started...", 2);
    }

 /********************************************\
 \********************************************/

    logger($cache_name . ': ***** Stream Test Started *****');

    $cache = new Memcache();
    $readerr = 0;
    while ($cache->connect('localhost') === false) {
        logger($cache_name . ": ERROR: could not connect to memcached!");
        if ($readerr++ > 9) {
            logger($cache_name . ': ERROR: failed connecting for 10 seconds, giving up...');
            exit;
        }
    }

    /************************************************/

    wait_for_converter();
    debugger("Converter started", 3);
    $readerr = 0;

    $privacy_end = false;
    $recheck_input = 1;

    $priv_warn = 1;
    while (true) {

        // Loop Delay
        sleep(1);

        if (flag_raised(BUFFER_REBUILD_FLAG)) {
            logger("Buffer device rebuild requested", true);
            lower_flag(BUFFER_REBUILD_FLAG);

            #Stop copier so we're not sitting in the buffer device with an open file
            stop_camera();

            #Rebuild the flash device
            load_libs("flash_ctrl");
            fix_buffer_device(abs_path(BUFFER_PATH));

            #Start the copier back up
            start_camera();

            sleep(5);
        }

        // Check for Restart request
        if (flag_raised(RESTART_REQUEST_FLAG)) {
            logger("Restart Request Flag Raised!  Lowering flag and restarting...\n");

            // Lower restart request flag
            lower_flag(RESTART_REQUEST_FLAG);

            // Reboot machine, switch to REBOOT_INIT_LEVEL (ezfw.ini: section [STREAM_TEST]
            reboot();
        }

        // Check for Video Restart request
        if (flag_raised(FORCED_VIDEO_RESTART)) {
            logger("Restart Video Card Request Flag Raised!  Lowering flag and restarting...\n");

            // Lower restart request flag
            lower_flag(FORCED_VIDEO_RESTART);

            //Reset the video card
            stop_camera();
            reset_camera();
            start_camera();
        }


        // Get the current frame index
        $index = $cache->get($cache_name . '/index');

        // Does the index not exist?
        if ($index === false) {

            // Log only every 3 read errors
            debugger("read error ($readerr)", 3);
            if (!((++$readerr) % 3)) logger($cache_name . ': Converter has stopped! (err: ' . $readerr . ')');

            // Are the read errors greater than MAX_READERR (ezfw.ini: section [STREAM_TEST]
            if ($readerr > 9) {
                logger($cache_name . ': Too many read errors, restarting software');

                // Restart software
                restart_software($last_restart);

                // wait on converter
                wait_for_converter();

                // Reset readerr counter
                $readerr = 0;
            }

            // The remainder of the loop tests the index
            continue;
        }
        
        // Reset readerr counter
        $readerr = 0;

        // privacy mode, don't restart
        if ($index == 'PRIVACY') {
            if (!$privacy_end)
                $privacy_end = intval(read_flag(PRIVACY_FLAG));
            else if (($privacy_end < time()) && ($privacy_end != -1)) {
                lower_flag(PRIVACY_FLAG);
                $privacy_end = false;
            }
            if (!--$priv_warn) {
                debugger("Privacy mode set", 3);
                $priv_warn = 30;
            }
            continue;
        }

        if ($index == 'DISABLED' || $index == 'NPRIVACY') {
            if (!--$priv_warn) {
                debugger($index . ' mode set', 3);
                $priv_warn = 30;
            }
            continue;
        }

        // has index not changed since last check?
        if ($index == $last_index) {
            debugger("Index unchanged '$last_index' == '$index'", 3);
            $no_image++;
            if ($no_image >= MAX_HICCUP) {
                logger($cache_name . ': Exceeded ' . $no_image . ' seconds of no image update');
                restart_software($last_restart);
                wait_for_converter();
            }
            continue;
        }

        // Did the image not change for longer than MIN_WARN (ezfw.ini: section [STREAM_TEST]
        if ($no_image >= MIN_WARN)
            logger($cache_name . ': Image was silent for ' . $no_image . ' seconds before changing');

        if (--$recheck_input) {
            $recheck_input = intval(INPUT_CHECK_DELAY);
            if (!check_camera_input()) {
                logger("Camera input is not correct, restarting module and software...", true);
                restart_software($last_restart);
                wait_for_converter();
            }
        }
        // Reset no_image counter
        $no_image = 0;

        // Set last index
        $last_index = $index;

        continue;
    }
    /************************************************/

?>
