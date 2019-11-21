<?php
    require_once('uther.ezfw.php');

    /***********************************************
     * Flag functions
     ***********************************************/

    function read_flag($flag) {
        if (!flag_raised($flag)) return false;
        flag_action('read', $flag);
        return file_get_contents(flag_path($flag));
    }

    /***********************************************/

    function flag_name($path) {
        return preg_replace('/\.flag$/', '', basename($path));
    }

    function flag_path($path) {
        return abs_path(FLAG_PATH, flag_name($path) . '.flag');
    }

    /***********************************************/

    function write_flag($flag, $str, $append) {
        if (!$append) {
            $data = read_flag($flag);
            /* no change, don't fire trigger */
            if ($data == $str) return true;
            $append = NULL;
        } else 
            $append = FILE_APPEND;

        file_put_contents(flag_path($flag), $str, $append);

        flag_action('write', $flag);
        return true;
    }

    /***********************************************/

    function raise_flag($flag, $str = false, $append = false) {
        $raised = flag_raised($flag);

        touch(flag_path($flag));

        if ($str !== false)
            write_flag($flag, $str, $append);

        if (!$raised)
            flag_action('raise', $flag);
        return true;
    }

    /***********************************************/

    function lower_flag($flag) {
        if (!flag_raised($flag)) return true;
        flag_action('lower', $flag);
        unlink(flag_path($flag));
        return true;
    }

    /***********************************************/

    function flag_raised($flag) {
        return file_exists(flag_path($flag));
    }

    /***********************************************/

    function flag_action ($action, $flag) {
        global $__NO_FLAG_ACTIONS__;
        if ($__NO_FLAG_ACTIONS__) return true;
        $flag = flag_name($flag);
        run_flag_trigger(abs_path('etc', 'flag_triggers', $flag), array($action, $flag));
        return true;
    }

    /***********************************************/

    function run_flag_trigger($path, $args) {
        if ($path[0] == '.') return;
        if (!is_executable($path)) return;

        if (is_dir($path)) {
            $lwd = getcwd();
            chdir($path);
            foreach (glob('*') as $fn)
                run_flag_trigger($fn, $args);
            chdir($lwd);
            return;
        }

        $dn = dirname($path);
        $fn = basename($path);
        $lwd = false;
        if ($dn && $dn != '.') {
            $lwd = getcwd();
            chdir($dn);
        }

        $argstr = '';
        foreach ($args as $v) $argstr .= escapeshellarg($v) . ' ';

        $execcmd = './' . $fn . ' ' . $argstr;
        debugger("Calling flag trigger '$fn' as '$execcmd'", 3);
        exec($execcmd, $out, $ret);
        if ($ret) logger("Warning: trigger command \"$execcmd\" failed with error code $ret", true);

        if ($lwd) chdir($lwd);

        return;
    }
    /***********************************************/


