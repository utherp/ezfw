<?php
    require_once('uther.ezfw.php');

    function verify_source ($input, $bs = 4096, $count = 128) {
        load_libs('children.php');
        // lets expect at least 60% of what we try to copy out
        // ... this is to compensate for short reads
        $min = ($bs * $count) * .6;

        logger('Verifying video source.  Performing dd on "'.$input.'"..', true);
        $child = pcntl_fork();
        if (!$child) {
            pcntl_exec(
                '/bin/dd',
                array(
                    'if=' . $input,
                    'of=/tmp/dump.tmp.' . getmypid(),
                    'bs=' . $bs,
                    'count=' . $count
                )
            );
            exit;
        }

        sleep(5);
        if (!process_stopped($child)) kill_children($child);
        stat_child($child, $ret);

        clearstatcache();

        if (!file_exists('/tmp/dump.tmp.' . $child)) {
            logger('--> Source check failed! dump file does not exist!', true);
            return false;
        }
        if (($fz = filesize('/tmp/dump.tmp.' . $child)) < $min) {
            logger('--> Source check failed! dump file is only '.$fz.' bytes!', true);
            unlink('/tmp/dump.tmp.' . $child);
            return false;
        }

        unlink('/tmp/dump.tmp.' . $child);
        return true;
    }

    function stop_ivtv_services() {
        return stop_services('copier', 'yuv_to_jpeg');
    }

    function start_ivtv_services() {
        return start_services('copier', 'yuv_to_jpeg');
    }

    function reload_ivtv ($keep = '') {
        logger('--> reloading video module', true);
        exec(abs_path('sbin','video-ctl.sh') . ' restart ' . $keep, $output, $ret);
        if ($ret)
            logger('failed to restart video module: "' . implode("\n", $output));

        sleep(2);

        return;
    }



