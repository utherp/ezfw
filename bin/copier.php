#!/usr/bin/php
<?php
    require_once('uther.ezfw.php');

    $quiet = false;

    foreach ($GLOBALS['argv'] as $k => $v) {
        if (!$k) continue;
        if ($v == '--whiptail' || $v == '-w') {
            $whip = true;
            ob_end_clean();
            $wt = do_whiptail('--gauge',  'Initializing...', 10, 60, 0);
        } else if ($v == '--quiet' || $v == '-q')
            $quiet = true;
    }

    function &do_whiptail () {
        $params = func_get_args();
        $cmd = 'whiptail ';
        $cmd .= '"' . implode('" "', $params) . '"';
        $wt = proc_open(
            $cmd,
            array(
                array('pipe', 'r'),
                array('pipe', 'w'),
                array('file', '/usr/local/ezfw/logs/whiptail.err', 'a')
            ),
            $pipes,
            NULL,
            $_ENV,
            array('binary_pipes')
        );

        $pipes['_res'] = $wt;

        stream_set_blocking($pipes[1], 0);

        return $pipes;
    }

    /**********************************************************************************************\
    \**********************************************************************************************/

    function flush_whiptail ($wt) {
        declare(ticks = 0);
        $buf = '';
        $tmp = '';
        while ($tmp = @fread($wt[1], 8192)) {
            $buf .= $tmp;
            usleep(1000);
        }

        if (strlen($buf)) {
            file_put_contents("tmp.log", "flushing " . strlen($buf) . " bytes from whiptail\n", FILE_APPEND);
            fwrite(STDOUT, $buf);
            ob_end_flush();
            flush();
        }
        return;
    }

    function whip_msg ($msg) {
        global $whip, $wt, $quiet;
        if ($quiet) return true;
        if (!$whip)
            return print($msg."\n");
        file_put_contents("tmp.log", "adding msg '$msg'\n", FILE_APPEND);
        $msg = "XXX\n\n$msg\nXXX\n";
        fwrite($wt[0], $msg);
        usleep(10000);
        flush_whiptail($wt);
        return;
    }

    function whip_percent ($per) {
        global $whip, $wt;
        if (!$whip) return;
        $per = intval($per);
        file_put_contents("tmp.log", "setting percent to '$per'\n", FILE_APPEND);
        fwrite($wt[0], "$per\n");
        usleep(10000);
        flush_whiptail($wt);
        return;
    }

    function close_whip () {
        global $whip, $wt;
        if (!$whip) return;
        flush_whiptail($wt);
        fclose($wt[0]);
        fclose($wt[1]);
        proc_close($wt['_res']);
        return;
    }

if ($whip) 
        register_tick_function('flush_whiptail', $wt);



/**********************************************************************/

    load_definitions('COPIER');
    load_definitions('VIDEO');
    load_definitions('FLAGS');

    load_libs('children.php');
    load_libs('drive.php');
    load_libs('copier.php');

/**********************************************************************/
/**********************************************************************/

    declare(ticks = 1);

    pcntl_signal(SIGTERM, "stop_processes");
    pcntl_signal(SIGHUP, "stop_processes");
    pcntl_signal(SIGUSR1, "stop_processes");
    pcntl_signal(SIGINT, 'stop_processes');
    register_shutdown_function("stop_processes", -1);

    /* default cycle mode, markers and chunk sizes */
    $cycle_mode = 'marker';
    $cycle_markers = array(); for ($i=0;$i<60;$markers[$i++]=false);
    if (!defined('CYCLE_MARKERS')) define('CYCLE_MARKERS', "0 15 30 45");
    if (!defined('CYCLE_MARKER_MIN_DURATION')) define('CYCLE_MARKER_MIN_DURATION', 60);

    /* initialize chunk mode variables */
    $chunk_size = intval(CHUNK_SIZE);
    $max_chunk_size = intval(MAX_CHUNK_SIZE);
    $safe_min = 1024 * 1024 * 50;
    if ($chunk_size < $safe_min) {
        logger("Warning: Chunk size set too low ($chunk_size): using safe value of $safe_min");
        $chunk_size = $safe_min;
    }

    /* initialize marker mode variables */
    $marks = explode(' ', preg_replace('/\s+/', ' ', CYCLE_MARKERS));
    foreach ($marks as $m) {
        if (!is_numeric($m) || $m > 59 || $m < 0) {
            logger("Warning: Markers must be numeric between 0 and 59: '$m' is not numeric");
            continue;
        }
        if ($cycle_markers[$m]) continue;
        debugger("Adding cycle cycle_marker '$m'", 5);
        $cycle_markers[$m] = true;
    }

    /* set and verify cycle mode */
    $mode = strtolower(CYCLE_MODE);
    if ($mode == 'chunk' || $mode == 'marker') {
        $cycle_mode = $mode;
    } else {
        logger("WARNING: Ignoring unknown cycle mode '$mode' specified in ini file.", true);
    }
    if ($cycle_mode == 'chunk') {
        logger("Using cycle mode '$cycle_mode' with chunk size '$chunk_size'.", true);
    } else {
        logger("Using cycle mode '$cycle_mode' with markers '" . implode(', ', array_keys($cycle_markers)) .
            "' and min duration '" . CYCLE_MARKER_MIN_DURATION . "' seconds.", true);
    }

/**********************************************************************/
/**********************************************************************/
    /* this function updates the video_disabled and recording_disabled
       flags from the server's data */
    function update_flags () {
        system('/usr/local/ezfw/sbin/check_video_status.php');
    }


/**********************************************************************/
/**********************************************************************/

    function repair_input ($input, $reason) {
        logger("Repairing input '$input'", true);
        switch ($reason) {
            case (OPEN_ERR):
                logger('--> Reason: Open Error');
                break;
            case (IO_ERR):
                logger('--> Reason: I/O Error');
                break;
            case (READ_ERR):
                logger('--> Reason: Read Error');
                break;
            default:
                logger('--> Reason: Unknown reason code: ' . $reason);
                break;
        }

        load_libs('ivtv');

        if (verify_source($input)) {
            logger('--> Source verified and seems to be working...');
            return true;
        }

        load_libs('daemons');
        reload_ivtv('--keep copier --keep stream_test');

        return true;
    }

    function repair_output ($path, $reason) {
        logger("Repairing ouput '$path'", true);
        switch ($reason) {
            case (OPEN_ERR):
                logger('--> Reason: Open Error');
                break;
            case (IO_ERR):
                logger('--> Reason: I/O Error');
                break;
            case (READ_ERR):
                logger('--> Reason: Read Error');
                // shouldn't have got here for output device...
                break;
            default:
                logger('--> Reason: Unknown reason code: ' . $reason);
                break;
        }
        raise_flag(BUFFER_REBUILD_FLAG);
        sleep(600);
        #load_libs('flash_ctrl');
        #fix_buffer_device($path);

        return true;
    }

/**********************************************************************/
/**********************************************************************/

    function stop_processes($sig) {
        global $copier, $video, $exit_code;
        if ($copier) {
            whip_msg("killed with signal $sig\n");
            debugger("stopping copier, received signal ($sig)", 3);
            stop_copier($copier, $ret);
            finalize_video($video);
        }

        if ($sig == SIGTERM || $sig == SIGINT) exit(0);
        if ($sig == -1 && $exit_code) exit((int)$exit_code);
        return 0;
    }

/**********************************************************************/
/**********************************************************************/

    $check_flags_ticks = 20;
    function recording_disabled ($can_update = true) {
        global $check_flags_ticks;
        /* check if video flags have changed at startup and after every 10 videos */
        if ($can_update && !--$check_flags_ticks) {
            $check_flags_ticks = 20;
            update_flags();
        }
        return (flag_raised(RECORDING_DISABLED));
    }

/**********************************************************************/
/**********************************************************************/

    function &init_video () {
        whip_msg('Creating new video...');
        debugger('Initializing new video', 1, true);

        $video = new video();
        if (recording_disabled(false)) {
            /* recording is disabled... we still make a video object
             * so states and events will have something to attach to,
             * but there will be no video file
             */
            $video->_set('store_tag', 'history');
            $video->flags->disabled = true;
            $video->extension = 'disabled';
        } else {
            $video->_set('store_tag', 'buffer');
            $video->flags->disabled = false;
            $video->extension = 'mpg';
        }

        $video->flags->current = true;

        if ($video->flags->hidden)
            $video->prefix = '.';

        $video->flags->locked = true;
        $video->flags->moving = true;
        $video->mover_pid = getmypid();

        // set start and end to current timestamp
        $video->end = $video->start = true;

        return $video;
    }

    /***************************************************/

    function finalize_video (&$video) {
        if (!$video) {
            debugger('Warning: attempted to finalize video, but none given', 1);
            return;
        }
        whip_msg('Finalizing video...');
        debugger('Finalizing video', 1, true);
        $video->mover_pid = 0;
        $video->flags->locked = false;
        $video->flags->moving = false;
        $video->flags->current = false;
        update_video($video);
        $video = NULL;
        return;
    }

    /***************************************************/

    $show_size_interval = 15;
    function update_video (&$video) {
        global $show_size_interval;
        debugger('Updating video ' . $video->id, 3);

        $disabled = $video->flags->disabled;
        if (!is_a($video, 'video')) {
            debugger('Warning, attempted to update video, but passed no video', 1);
            return;
        }

        if ($disabled)
            $video->extension = 'disabled';
        // update video ending time to now
        $video->end = true;
        // update size to current filesize
        $video->size = true;
        // save video changes (will also rename file)
        $video->save();

        if ($disabled) {
            whip_msg('Video ' . $video->id . ' (Disabled)');
            if (!--$show_size_interval) {
                debugger("--> Video {$video->id} DISABLED", 2);
                $show_size_interval = 15;
            }
        } else {
            $sz = $video->size;
            whip_msg('Copying Video ' . $video->id . ' (' . number_format($sz/1000, 0) . 'kB)');
    
            if (!--$show_size_interval) {
                debugger("--> Video {$video->id} size is {$video->size}", 2);
                $show_size_interval = 15;
            }
        }

        // update current video flag with filename
        raise_flag(CURRENT_VIDEO_FILE, $video->filename);

        debugger('--> Video saved ('.$video->id.')', 4);

        if ($disabled)
            $video->flags->disabled = true;

        return;
    }

/**********************************************************************/
/**********************************************************************/

    function verify_copy ($video)  {
        clear_stat_cache(true, $video->fullpath);
        return file_exists($video->fullpath);
    }

    /**************************************/

    function buffer_chunk_full($video) {
        global $cycle_mode, $chunk_size, $whip;

        // return false if not in 'chunk' cycle mode
        if ($cycle_mode != 'chunk') return false;

        if (!$whip)
            return ($video->size > $chunk_size);
        $sz = $video->size;
        $per = intval($video->size / $chunk_size * 100);
        whip_percent($per);
        return ($sz > $chunk_size);
    }

    /**************************************/

    $start_minute = false;
    function cycle_marker_reached($video) {
        global $cycle_mode, $cycle_markers, $start_minute;

        $min = intval(date('i'));

        // Prevent cycling if the video started this minute
        if ($start_minute !== false) {
            if ($start_minute == $min) return false;
            $start_minute = false;
        }

        if ($cycle_markers[$min]) {
            // If we would cycle and the video is less than the minimum duration, skip cycling
            $now = time();
            // intval > 1 is a cheap way to ensure it's a real time and not null or a bool
            if (intval($video->start) > 1 && $now - $video->start < CYCLE_MARKER_MIN_DURATION) {
                debugger("Not cycling video at marker '$min' because duration is below " .
                    CYCLE_MARKER_MIN_DURATION . " (started {$video->start}, now $now)", 3);
                return false;
            }
            logger("NOTE: Cycle marker '$min' reached", true);
            return true;
        } else {
            return false;
        }
    }

    /**************************************/

    $warn_interval = 5;
    function check_copy_progress ($video, $old_size) {
        global $warn_interval;
        $new_size = $video->size;

        debugger("Checking copy progress of video {$video->id}", 3);

        if ($new_size === false) {
            logger("An unknown error occurred checking the filesize of '$filename': " . last_error_message(), true);
            return false;
        }

        if ($new_size == $old_size) {
            if (!--$warn_interval) {
                logger("Warning: Buffering video file size has not changed from '$new_size'", true);
                $warn_interval = 5;
            }
            debugger("--> Video {$video->id} size unchanged ($new_size)", 3);
            return false;
        } else
            $warn_interval = 5;

        if ($new_size <= intval(WARN_MIN_CHUNK_SIZE)) {
            logger("Warning: Buffering video file size is low: '$new_size'", true);
            return false;
        }

        debugger("Video {$video->id} copier check succeeded", 4);

        return true;
    }

/**********************************************************************/
/**********************************************************************/

    function handle_copier_exit_code ($copier_ret, &$video) {
        if (!$copier_ret) return;
        $reason = $copier_ret & REASON_MASK;
        $cause = $copier_ret & CAUSE_MASK;
        if ($cause == INPUT_ERR)
            repair_input(INPUT_FILE, $reason);
        else if ($cause == OUTPUT_ERR)
            repair_output($video->store->path, $reason);
        else
            logger("Warning: An unknown copier error has occurred (reason: $reason) (cause: $cause)", true);

        return;
    }

/**********************************************************************/
/**********************************************************************/


    /************************************************************
        First check if the buffer rebuild flag is raised
    */
    $store = store::fetch('buffer');
    if (flag_raised(BUFFER_REBUILD_FLAG)) {
        $store->flags->locked = true;
        $store->save();
        whip_msg('Repairing Buffer Device..');
        repair_output($store->path, IO_ERR);
        lower_flag(BUFFER_REBUILD_FLAG);
    }

    $copy_err = -30;
    $start_err = 0;
    $copier = 0;

    $recheck_flags = 1;

    /************************************************************
        Lower restart, recycle and buffer rebuild flags
    */
    lower_flag(FORCED_VIDEO_RESTART);
    lower_flag(RECYCLE_FLAG);

    logger("Buffer copier started", true);


    /************************************************************************
        Main video copier loop:
            initializes the video object
            starts the copier
            verifies the copier
            enters check loop
    */

    stream_set_blocking(STDIN, 0);

    /* update video status flags once upon startup, and every 20 times the flag is checked after that */
    update_flags();

    while (true) {
        debugger('Entered video loop', 2);

        /****************************************************
            Copy CURRENT_VIDEO_FILE contents to LAST_VIDEO_FILE
        */
        if (flag_raised(CURRENT_VIDEO_FILE)) {
            raise_flag(LAST_VIDEO_FILE, read_flag(CURRENT_VIDEO_FILE));
            lower_flag(CURRENT_VIDEO_FILE);
        }

        /****************************************************
            Initialize the video object and creates the
            Store's path if it doesn't already exist
        */


        $video = init_video();

        $store = $video->store;


        if ((!is_dir($store->path) && !mkdir($store->path)) || !chdir($store->path)) {
            /************************************************
                Could not make or change to the Store's root
                path... Maybe a filesystem problem, we'll 
                break of both loops to the catastrophy section
                of this script...
            */
            logger("*** CRITICAL FAILURE ***: Could not make buffer store path ({$store->path}): " . last_error_message(), true);
            exit(2);
        }
   

        /* if video recording is disabled, we do not start the copier */
        if ($video->flags->disabled) {
            touch($video->fullpath); /* create empty file with extension '.disabled' */
            debugger('Not starting copier: recording disabled!', 2);

        } else {
    
            /****************************************************
                Start the copier process, wait a second and
                verify that the copier started properly
            */
            $c = 50;
            $copier_ret = false;
    
            whip_msg('Starting copier...');
            $copier = start_copier(INPUT_FILE, $video->fullpath); //$store->path . '/' . $video->filename);
            sleep(1);
    
            whip_msg('Copier started');
            debugger("Started copier (pid: $copier)", 2);
            debugger('Verifying copier startup', 1);
    
            while (!verify_copy($video)) {
                /***********************************************
                    loop until we verify the copier started
                */
                usleep(100000);
                if ($start_err++ > MAX_START_FAIL) {
                    /*******************************************
                        Max copier check failures, check if
                        copier is even running
                    */
                    if (copier_running($copier,$copier_ret) === true) {
                        whip_msg('Copier did not start!');
                        logger("Warning: Copier did not start correctly!", true);
                        stop_copier($copier, $copier_ret);
                    }
    
                    debugger("NOTE: The copier ($copier) has stopped while verifying copy. (return code: $copier_ret)", 1);
                    handle_copier_exit_code($copier_ret, $video);
    
                    $copier_ret = 0;
                    $copier = 0;
    
                    // jump back to begining of main loop
                    continue 2;
                }
            }
    
            debugger('Copier startup verified', 2);
        }

        // Save video object
        $video->save();
    

        /**************************************************
            Video check loop...
                monitors copier
                checks conditions
                updates video data
        */

        debugger('Entering check loop for video ' . $video->id, 2);

        // start minute... for cycle marker checking
        $start_minute = intval(date('i'));
        while (true) {
            /*******************************************************************************/

            // sleep for the interval between checks and updates.
            if (!is_numeric($old_size)) $old_size = 0;
            sleep(INTERVAL_TIME);

            // update video data
            update_video($video);

            /*********************************************************
                Check if the Recycle flag is raised,
                signaling a copier restart
            */
            if (flag_raised(RECYCLE_FLAG)) {
                lower_flag(RECYCLE_FLAG);
                debugger("Recycle flag raised, restarting video", 1);
                break;
            }

            /*********************************************************
                Check if the "recording disabled" state has changed
                We want to break the videos apart at this change
            */
            if ($video->flags->disabled != recording_disabled()) {
                logger("NOTE: Video permission has changed from " . ($video->flags->disabled?'Disabled':'Enabled') .' to '. ($video->flags->disabled?'Enabled':'Disabled') . '!', true);
                break;
            }

            /* check for cycle marker */
            if (($cycle_mode == 'marker') && cycle_marker_reached($video)) {
                break;
            }

            if (!$video->flags->disabled) {
                /*************************************
                 * only perform the following checks 
                 * if recording is not disabled
                 */

                /********************************************************
                    Verify the copier is still running
                */
                if (copier_running($copier,$copier_ret) !== true) {
                    debugger("NOTE: The copier ($copier) has stopped within the check loop. (return code: $copier_ret)", 1);
                    handle_copier_exit_code($copier_ret, $video);
                    break;
                }
    
                /********************************************************
                    Check if the chunk size has been reached
                */
                if ($cycle_mode == 'chunk' && buffer_chunk_full($video)) {
                    debugger("NOTE: Chunk size reached", 1);
                    /* here is where to add checks if a video recycle is ok */
                    break;
                }
    
                /********************************************************
                    if buffer rebuild is not signaled, and progress
                    check fails too many times, log it, and break to
                    the catastrophy section at the end of the script
                */
                if (!flag_raised(BUFFER_REBUILD_FLAG) && !check_copy_progress($video, $old_size) && (++$copy_err > MAX_CHECK_ERR)) {
                    whip_msg('max copier failures!');
                    logger("WARNING: Max copier progress check failures reached (".MAX_CHECK_ERR.")!, Requesting buffer rebuild from stream_test", true);
                    $rebuild = true;
                    break 2;
                }

                $old_size = $video->size;
            }
    

            $tmp = fread(STDIN, 8192);
            if (strpos($tmp, 'q') !== false) {
                whip_msg('closing...');
                $quit = true;
                break;
            }

            /*****************************************
                End of check loop
            */
        }


        debugger('Out of check loop, stopping copier and finalizing video ' . $video->id, 1);
        /********************************************
            Stop copier, finalize video and 
            restart to the begining of the Main loop
        */
        if (copier_running($copier,$copier_ret) === true) {
            debugger("NOTE: Stopping the copier",1);
            stop_copier($copier, $copier_ret);
        }

        debugger("NOTE: The copier ($copier) has stopped to finalize video. (return code: $copier_ret)", 1);
        handle_copier_exit_code($copier_ret, $video);

        finalize_video($video);

        raise_flag(LAST_VIDEO_FILE, read_flag(CURRENT_VIDEO_FILE));
        lower_flag(CURRENT_VIDEO_FILE);

        if ($quit)
            exit;

        usleep(500000);

        /******************************************
            End of main loop
        */

        debugger('End of video loop', 3);
    }

    /*********************************************+
        Catastrophy Section!
    */

    debugger('Reached catastrophy section', 1);

    // If we got here its because something catastrophic occurred
    // such as the buffer device failing..
    stop_copier($copier, $copier_ret);
    finalize_video($video);

    /*********************
     * its possible it was the ivtv driver hung up
     * so, we'll verify the source before rebuilding the buffer...
     */
    load_libs('ivtv.php');
    if (!verify_source(INPUT_FILE)) {
        load_libs('daemons.php');
        logger('Ivtv input verification failed!', true);

        reload_ivtv('--keep copier --keep stream_test');
        sleep(5);

        // reverify
        if (verify_source(INPUT_FILE)) {
            logger('...Restarting ivtv seems to have solved the problem...', true);
            sleep(10);
            exit;
        }
        logger('Restarting ivtv did not work!', true);
        sleep(60);
        exit;
    }

    if ($rebuild) {
        /*****************
         * A rebuild was requested
         */
        raise_flag(BUFFER_REBUILD_FLAG);
        sleep(300);
    }

    exit;

?>
