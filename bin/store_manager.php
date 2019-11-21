#!/usr/bin/php
<?php
    require_once('uther.ezfw.php');

    $logfile = 'store_manager.log';
    $dbg_level = 3;

    $argc = &$GLOBALS['argc'];
    $argv = &$GLOBALS['argv'];

    $store_tags = array();
    $force = false;
    $cmd = array_shift($argv);
    $local = false;

    $quiet = false;

    $details = NULL;
    $target = 'store_tags';
    $reset = -1;

    while ($arg = array_shift($argv)) switch ($arg) {
            case ('--whiptail'):
            $whip = true;
            break;
        case ('--list'):
            list_stores();
            exit(0);
        case ('--detail'):
        case ('--details'):
            if (!is_array($details))
                $details = array();
            $target = 'details';
            $reset = -1;
            break;
        case ('--local'):
            $local = true;
            break;
        case ('--force'):
            $force = true;
            break;
        case ('--manage'):
            $target = 'store_tags';
            $reset = -1;
            break;
        case ('--logfile'):
            $target = 'logfile';
            $reset = 1;
            break;
        case ('--quiet'):
            $quiet = true;
            break;
        case ('--debug'):
            $target = 'dbg_level';
            $reset = 1;
            break;
        default:
            if (is_array($$target))
                array_push($$target, $arg);
            else
                $$target = $arg;
            if (!--$reset)
                $target = 'store_tags';
    }

    define('LOG_FILE', $logfile);
    define('DEBUG', $dbg_level);
    
    if ($details !== NULL) {
        if (!count($details))
            $details = store::fetch_all();
        show_details($details);
        exit(0);
    }

    if (!count($store_tags)) usage("You must specify at least one store to manage!", 1);

    if ($whip) {
        ob_end_clean();
        $wt = do_whiptail('--gauge',  'Loading Stores...', 10, 60, 0);
//        declare(ticks = 1);
//        register_tick_function('flush_whiptail', $wt);
    }

    /**********************************************************************************************\
    \**********************************************************************************************/
    /**********************************************************************************************\
    \**********************************************************************************************/

    function usage ($msg, $ret = 1) {
        global $cmd;
        fwrite(STDERR, "\033[01;31m$msg\033[00m\n");
        fwrite(STDERR, 
            "\nUsage: \n" .
            "    $cmd tag [tag [tag ...]]\n" . 
            "    $cmd --list\n" .
            "    $cmd --details [tag [tag [tag...]]]\n\n" .
            "          tag:  The tag name of a store to manage\n" .
            "       --list:  List all available stores.\n" .
            "    --details:  Show details of store[s] (show all by default)\n\n"
        );
        exit($ret);
    }

    /**********************************************************************************************\
    \**********************************************************************************************/

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
        global $whip, $wt;
        if (!$whip)
            return print($msg."\n");
        file_put_contents("tmp.log", "adding msg '$msg'\n", FILE_APPEND);
        $msg = "\nXXX\n\n$msg\nXXX\n";
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
        fwrite($wt[0], "\n$per\n");
        usleep(10000);
        flush_whiptail($wt);
        return;
    }

    function close_whip () {
        global $whip, $wt;
        if (!$whip) return;
        fclose($wt[0]);
        flush_whiptail($wt);
        fclose($wt[1]);
        proc_close($wt['_res']);
        return;
    }

    /**********************************************************************************************\
    \**********************************************************************************************/

    function close_stores ($s = NULL) {
        if (is_numeric($s)) {
            whip_msg("Received signal $s...");
            $exit = true;
            $s = NULL;
        }

        if ($s === NULL) {
            global $stores;
            $s =& $stores;
        }

        if (!is_array($s))
            $s = array($s);

        $t = count($s);
        $c = 0;
        foreach (array_keys($s) as $name) {
            if (!is_a($s[$name], 'store'))
                continue;

            $store =& $s[$name];
            unset($s[$name]);
            whip_msg("Closing store '{$store->tag}'...");

            $store->flags->managed = false;
            $store->manager_pid = NULL;
            $store->save();

            logger("Unmanaged {$store->name}", true);

            $c++;
            whip_percent(intval($c / $t * 100));
        }

        if ($exit) {
            close_whip();
            exit();
        }
        return;
    }

    /**********************************************************************************************\
    \**********************************************************************************************/

    function list_stores () {
        print "Available Stores:\n---------------------------------\n";

        foreach (store::fetch_all() as $s)
            print "{$s->tag} ({$s->name})\n";

        print "\n\n";
        return;
    }

    /**********************************************************************************************\
    \**********************************************************************************************/

    function show_details ($stores) {
        global $whip, $local;
        if (!is_array($stores)) $stores = array($stores);

        $full_message = '';
        foreach ($stores as $s) {
            if (!is_a($s, 'store'))
                $s = store::fetch($name = $s);
            if (!$s->is_loaded()) {
                if (!$name)
                    print "\033[01;31mWarning: Store is not loaded, could not show details.\033[00m\n";
                else
                    print "\033[01;31mWarning: Could not load store '$name', could not show details.\033[00m\n";
                continue;
            }

            if ($local && !$s->flags->local)
                continue;

            if ($s->at_export_level)
                $color = $whip?'':"\033[01;31m";

            if ($s->at_safe_level)
                $color = $whip?'':"\033[01;32m";

            $reset = $whip?'':"\033[00m";

            $msg = 
                "------------------------------------------------\n".
                "         Tag: '{$s->tag}'\n" .
                "        Path: '{$s->path}'\n" .
                "       Flags: " . $s->_get('flags', true) . "\n" .
                "      Videos: " . $s->video_count() . "\n" . 
                ($s->flags->managed?" Manager PID: {$s->manager_pid}\n":'') .
                "   Max Bytes: " . number_format($s->max_bytes) . "\n" .
                "  Used Space: " . number_format($s->size) .  ' (' . number_format($s->usage * 100, 2) . "%) used\n" .
                "  Free Space: $color" . number_format($s->bytes_free) .  ' (' . number_format($s->free_ratio * 100, 2) . "%) free $reset\n" .
                "Export Level: < " . number_format($s->export_at_bytes) . ' (' . number_format($s->export_ratio * 100, 2) . "%) free space\n" .
                "  Save Level: " . number_format($s->safe_bytes) . ' (' . number_format($s->safe_ratio * 100, 2) . "%)\n" . 
                "Import Curve: " . $s->lowest_import_weight . "\n";


            if ($s->at_export_level)
                $msg .= ($whip?'':"\033[01;31m") . "*** Free space is below export level ***" . ($whip?"\n":"\033[00m\n");

            if ($s->at_safe_level)
                $msg .= ($whip?'':"\033[01;32m") . "*** Free space is above safe level ***" . ($whip?"\n":"\033[00m\n");

            $full_message .= $s->name . " Details:\n $msg\n";

        }
        
        if ($whip) {
            ob_end_clean();
            pcntl_exec(
                '/usr/bin/whiptail',
                array('--scrolltext', '--title', 'Store Details', '--msgbox', $full_message, 20, 60),
                $_ENV
            );
            exit;
        } else
            print $full_message;

        return;
    }

    /**********************************************************************************************\
    \**********************************************************************************************/

    stream_set_blocking(STDIN, 0);

    declare(ticks = 1);
    register_shutdown_function('close_stores', $stores);
    pcntl_signal(SIGTERM, 'close_stores');
    pcntl_signal(SIGINT, 'close_stores');
    pcntl_signal(SIGABRT, 'close_stores');

    if ($whip) 
        register_tick_function('flush_whiptail', $wt);

    $stores = array();
    // Load stores...
    foreach ($store_tags as $tag) {
        $store = store::fetch($tag);
        if (!$store->is_loaded()) {
            whip_msg("Error: Unable to load store '$tag'");
            sleep(1);
            continue;
        }

        if ($local && !$store->flags->local) {
            whip_msg("Error: Unable to manage {$store->tag}: Not local");
            sleep(2);
            continue;
        }

        if (!is_dir($store->abs_path())) {
            /* store path does not exist, attempt to create it */
            logger("Warning: '{$store->name}' store's path does not exist, attempting to create ({$store->abs_path()})", true);
            if (!mkdir($store->abs_path(), 0755, true)) {
                logger("ERROR: Failed to create store path '{$store->abs_path()}': " . last_error_message(), true);
                continue;
            }
        }

        if (!$store->manage()) {
            /* store is actively managed by another process */
            $msg = "already managed by pid {$store->manager_pid}";
            whip_msg("Error: Unable to manage store '$tag': $msg");
            continue;
        }

        logger("Managing '{$store->name}'", true);

        /*************************************************
            set size to true, signifying real-time, gets
            size as sum(size) of all videos in this store
        */
        $store->size = true;
        $store->save();

        $stores[$tag] =& $store;
        unset($store);
        
        sleep(1);
    }

    if (!count($stores)) {
        whip_msg("Error: No stores to manage!");
        close_whip();
        exit(1);
    }

    $update_details = 1;

    $wait = 30 / count($stores) / 2;
    if (!$wait) $wait = 1;

    $per = array();
    $sz = array();
    $mx = array();

    $check = true;

    while (true) {
        foreach ($stores as $store) {
            if ($check) {
                $tag = $store->tag;
                $sz[$tag] = number_format($store->current_size / 1000000000, 2) . 'G';
                $mx[$tag] = number_format($store->max_bytes /     1000000000, 2) . 'G';
                $per[$tag] = $store->usage * 100;
    
                if (!$whip && !$quiet && !--$update_details) {
                    print date("r\n");
                    show_details($store);
                    $update_details = 6;
                }
    
                if ($store->at_export_level) {
                    whip_msg("{$store->name} is at export level, cleaning...");
                    /* no need to save after clean... clean auto-saves if nessessary */
                    $freed = $store->clean();
                    whip_msg("{$store->name}: Freed $freed bytes...");
                }
            }
    
            if ($whip || !$quiet)
                whip_msg($store->name . " Usage: ({$sz[$tag]}/ {$mx[$tag]})");
            whip_percent($per[$tag]);

            $tmp = fread(STDIN, 8192);
            if (strpos($tmp, 'q') !== false) break 2;

            sleep($wait);
        }
        $check = !$check;
    }


