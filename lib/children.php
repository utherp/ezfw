<?php
    require_once('uther.ezfw.php');

    /******************************************************/

    function kill_children($children) {
        if (!is_array($children)) $children = array($children);
        $killed = array();
        foreach (array(SIGTERM, SIGKILL) as $sig) {
            foreach (array_keys($children) as $k)
                if ($children[$k] && !process_running($children[$k]))
                    posix_kill($children[$k], $sig);

            usleep(50000);

            foreach (array_keys($children) as $k)
                if (!$children[$k] || stat_child($children[$k], $ret))
                    array_push($killed, $k);

            while (count($killed))
                unset($children[array_pop($killed)]);

            usleep(100000);
        }

        return $killed;
    }

    /******************************************************/

    function stat_child($pid, &$retval) {
        $status = 0;
        $returnedPid = pcntl_waitpid($pid, $status, WNOHANG);
        if ($returnedPid == -1) {
            logger("Warning: pcntl_waitpid failed for pid '$pid':  Either no child with that pid exists OR an unblocked signal was received (see ERRORS section of man page for 'waitpid')", true);
            $returnedPid = 0;
        }
        if (!$returnedPid) return $returnedPid;

        if( pcntl_wifexited($status) ){
            $retval = pcntl_wexitstatus($status);
        }else{
            $retval = 0;
        }
        return $returnedPid;
    }

    /***************************************/

  if (!function_exists('stat_children')) {
    function stat_children(&$children) {
        if (is_array($children)) {
            $dead = 0;
            $status = array();
            for ($i = 0; $i < count($children); $i++) {
                $s = '';
                $p = pcntl_waitpid($children[$i], &$s, WNOHANG);
                if ($p > 0) {
                    logger("Child of pid $p died with signal $s");
                    $tmp = $children[$i];
                    $children[$i] = $children[$dead];
                    $children[$dead] = $tmp;
                    $dead++;
                    array_push($status, array('pid' => $p, 'status' => $s));
                }
            }
            for ($i = 0; $i < $dead; $i++) {
                array_shift($children);
            }
            return $status;
        } else {
            $status = '';
            pcntl_waitpid($c, &$status, WNOHANG);
            return $status;
        }
    }
  }

    /***************************************/
  if (!function_exists('terminate_children')) {
    function terminate_children (&$children, $signal = SIGTERM) {
        if (is_array($children)) {
            foreach ($children as $c) {
                posix_kill($c, $signal);
            }
        } else {
            posix_kill($children, $signal);
        }
    }
  }
    /******************************************************/

    function kill_process($pid, $sig = 15) {
        posix_kill($pid, $sig);
    }

    /******************************************************/

    function process_running ($pid) {
        return posix_kill($pid, 0);
    }

    /******************************************************/

    function process_stopped($pid) {
        return !process_running($pid);
    }


