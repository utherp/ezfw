<?php
    require_once("ezfw.php");

    if (!defined('CV_DECIMATE_CMD'))
        define('CV_DECIMATE_CMD', 'nice -n 15 ffmpeg -an -i ${SRC} -vcodec libx264 -r 12 -vpre ultrafast -b 65536 -f flv -an ${DEST} 2>&1');

    if (!defined('CV_DECIMATE_DIR'))
        define('CV_DECIMATE_DIR', '/usr/local/ezfw/video/decimating');

    function decimate_tmp_filename ($v) {
        if (!($v instanceOf video)) {
            $v = video::fetch($v);
            if (!($v instanceOf video)) {
                /* could not load video! */
                return false;
            }
        }

        $source = $v->fullpath;
        $tmpname = CV_DECIMATE_DIR . '/.decimate_' . basename($source);

        return $tmpname;
    }



    function decimate ($v) {
        $ret = true;
        $v->moving_to = 'transcoding';
        $v->mover_pid = getmypid();
        $v->flags->locked = true;
        $v->save();

        $source = $v->fullpath;
        $tmpname = decimate_tmp_filename($v);

        if (file_exists($tmpname)) unlink($tmpname);
        $cmd = preg_replace(
                 array('/\$\{SRC\}/', '/\$\{DEST\}/'),
                 array(escapeshellarg($source), escapeshellarg($tmpname)),
                 CV_DECIMATE_CMD
               );

        logger('[decimateStore]: About to decimate video ' . $video->id . ', cmd:  ' . $cmd, true);
        touch($tmpname . '.filepart');
        exec($cmd, $out, $ret);
        unlink($tmpname . '.filepart');
        if ($ret) {
            logger("[decimateStore]: Warning: Decimate command ended with return code $ret.  Command output will be stored in '" . log_path('decimation_error.log') . "'", true);
            @file_put_contents(
                log_path('decimation_error.log'),
                date('r') . ":  *** Decimation Error (" . get_class() . ") ***\n  CMD: $cmd\n  Return Code: $ret\n  Output:\n    " . implode("\n    ", $out) . "\n------------------------------------------------------\n",
                FILE_APPEND
            );

            // for whatever reason this failed, we don't want to try again so we'll set the codecs to unknown
            $v->acodec = 'unknown';
            $v->vcodec = 'unknown';
            $ret = false;

        } else {
    
            $backup = dirname($source) . '/_' . basename($source);
            rename($source, $backup);
    
            if (!rename($tmpname, $source)) {
                logger("Error: could not move decimated file '$tmpname' to target '$source': " . last_error_message(), true);
                rename($backup, $source);
                unlink($tmpname);
                $v->acodec = 'unknown';
                $ret = false;
            } else {
                $v->extension = 'flv';
                $v->acodec = '';
                $v->vcodec = 'h264';
            }

        }
    
        $v->size = filesize($source);
        unset($v->flags->locked);
        unset($v->moving_to);
        unset($v->mover_pid);
        $v->save();
        
        if (file_exists($backup)) unlink($backup);
        return $ret;
    }


