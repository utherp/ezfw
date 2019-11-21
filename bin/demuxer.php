#!/usr/bin/php
<?php
    define('CV_CACHE_OBJECTS',false);
    require_once('uther.ezfw.php');

    load_definitions('DEMUXER');
    load_definitions('FLAGS');

    load_libs('player');
    load_libs('video/convert');
    load_libs('children');

    /******************************************************/
    function check_file_type($filename, $checkonly = false) {
        $type = exec('ls -lA "' . $filename . '"');
        if (strpos($type, 'prw') !== 0) {
            debugger('Import file is not a fifo pipe (' . $type . ')', 5);
            if ($checkonly) return false;
            return fix_file($filename);
        } else {
            return true;
        }
    }

    function fix_file($filename) {
        debugger('Fixing pipe', 5);
        if (file_exists($filename)) unlink($filename);
        posix_mkfifo($filename, octdec(PIPE_PERMISSION));
        chown($filename, PIPE_USER);
        chgrp($filename, PIPE_GROUP);
        chmod($filename, octdec(PIPE_PERMISSION));
        if (check_file_type($filename, true)) {
            debugger('Fixed', 5);
            return true;
        } else {
            debugger('Error! unable to repair file node to pipe', 5);
            return false;
        }
    }

    function stop_demuxing() {
        global $parent;
        if ($parent) {
            global $children;
            if (count($children)) terminate_children($children, SIG_TERM);
            if (count($children)) terminate_children($children, SIG_KILL);
            lower_flag(DEMUXER_FLAG);
        } else {
            global $demuxer_pid;
            if (is_resource($GLOBALS['_FFMPEG_'])) {
                @fwrite($GLOBALS['_PIPES_'][0], 'qqqqqq');
                @fclose($GLOBALS['_PIPES_'][0]);
                @fclose($GLOBALS['_PIPES_'][1]);
                @proc_close($GLOBALS['_FFMPEG_']);
            }
            //if (is_numeric($demuxer_pid)) exec('kill -9 ' . $demuxer_pid);
            exit;
        }
    }

    $children = array();
    $parent = true;

    register_shutdown_function('stop_demuxing');
    if (flag_raised(DEMUXER_FLAG)) lower_flag(DEMUXER_FLAG);
    raise_flag(DEMUXER_FLAG, getmypid());

    while (true) {
        stat_children($children);

        check_file_type(abs_path(DEMUXER_PIPE));
        $id = file_get_contents(abs_path(DEMUXER_PIPE));
        $id = rtrim($id);

        if (isset($children[$id])) {
            stat_children($children);
            if (isset($children[$id])) {
                debugger("Demuxer already running for video id #$id'", 3);
                continue;
            }
        }

        $v = video::fetch($id);
        if (!$v) {
            logger("Error: could not load video $id", true);
            continue;
        }

        $child = pcntl_fork();
        if ($child === 0) {
            $parent = false;
            if ($v->store_tag == 'buffer') {
                // must move it out of buffer store first
                $v->store = array(store::fetch('history'));
                $v->save();
                $v->reload();
            }
            decimate($v);
            exit(0);
        } else if ($child === false) {
            logger("ERROR:  Could not fork!");
        } else {
            $children[$id] = $child;
        }
    }
