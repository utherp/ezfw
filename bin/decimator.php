#!/usr/bin/php
<?php
    define('CV_CACHE_OBJECTS',false);
    require_once('uther.ezfw.php');
    load_libs('video/convert');

    $pid = getmypid();
    $pidfile = abs_path('pid', 'decimator.pid');
    $last_pid = file_exists($pidfile)?file_get_contents($pidfile):false;

    if ($last_pid) {
        /* unclean shutdown previously... */
        dbObj::_exec('update',
            video::$_db_settings['table'],
            array('mover_pid'=>$pid),
            dbObj::_exec('quoteInto', 'mover_pid = ?', $last_pid)
        );
    }

    // I want the db update of mover_pid field changed right away... before I overwrite the pid file
    file_put_contents($pidfile, $pid);

    // here is where we'll recover the videos
    if ($last_pid) {
        $iter = new ezIterator('video', 'mover_pid = ?', array($pid));

        while (($v = $iter->next())) {
            $tmpname = $v->fullpath;
            $tmpname = dirname($tmpname) . '/.decimate_' . basename($tmpname);
            if (file_exists($tmpname)) unlink($tmpname);
            $v->moving_to = '';
            $v->mover_pid = 0;
            $v->flags->moving = false;
            $v->flags->locked = false;
            $v->save();
        }

        /* recovered from unclean shutdown ... */
    }


    while (true) {
        // (store != 'buffer' && store != 'purge') && ((acodec != '' && acodec != 'unknown') || (vcodec != 'h264' && vcodec != 'unknown'))
        $vids = video::fetch_all(
            'store_tag not in (?, ?) ' .        // not in buffer or purge store
            'AND vcodec not in (?, ?) ' .       // vcodec is not 'h264' or 'unknown' (unknown means previous conversion attempt failed)
            'AND NOT find_in_set(?, flags) ' .  // not disabled
            'AND NOT find_in_set(?, flags)',    // and not locked
            array(
                'buffer', 'purge', 
                'h264', 'unknown',
                'disabled',
                'locked'
            ),
            5,        // fetch 5 records.
            'start desc'
        );

        if (!count($vids)) {
            sleep(60);
            continue;
        }
        foreach ($vids as $v) {
            /* perform a reload on the video, in case anything has changed */
            $v->reload();
            /* skip this video if something changed to a value we would have ignored in the first place */
            switch (true) {
                case ($v->store_tag == 'buffer'):  // must not be in buffer store
                case ($v->store_tag == 'purge'):   // must not be in purge store
                case ($v->vcodec == 'h264'):       // vcodec must not be h264 (already transcoded)
                case ($v->vcodec == 'unknown'):    // vcodec must not be unknown (trancoding failed)
                case (!!$v->flags->locked):        // must not be locked
                case (!!$v->flags->disabled):      // must not be disabled
                    continue;
                default: 
                    break;
            }

            print $v->id . ' (' . $v->fullpath . '): vcodec(' . $v->vcodec . ') acodec(' . $v->acodec . ")\n";
            decimate($v);
        }
    }

