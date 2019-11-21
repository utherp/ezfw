<?php

    function __session_initialize($save_path, $session_name) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] . ' via ' . $_SERVER['HTTP_X_FORWARDED_HOST'];
        else
            $ip = $_SERVER['REMOTE_ADDR'];
        $GLOBALS['__session__'] = array(
                                    'path' => $save_path,
                                    'name' => $session_name,
                                    'ip'   => $ip,
                                );
        return true;
    }
    function __session_close() {
        return true;
    }
    function __session_read($id) {
        $GLOBALS['__session__']['id'] = $id;
        return __session_load();
    }
    function __session_filename() {
        if (!is_array($GLOBALS['__session__'])) return false;
        if (!isset($GLOBALS['__session__']['filename'])) {
            $GLOBALS['__session__']['filename'] = $GLOBALS['__session__']['path'] . '/' .
                                                    $GLOBALS['__session__']['name'] . '_' .
                                                    $GLOBALS['__session__']['id'] . '_' .
                                                    $GLOBALS['__session__']['ip'] . '.sess';
        }
        return $GLOBALS['__session__']['filename'];
    }
    function __session_load() {
        if (file_exists(__session_filename())) {
            touch(__session_filename());
            if (!isset($GLOBALS['__NO_SET_COOKIE__'])) setcookie(
                $GLOBALS['__session__']['name'],
                $GLOBALS['__session__']['id'],
                time() + 7200,
                '/',
                '.cv-internal.com'
            );

            return file_get_contents(__session_filename());
        } else {
            return '';
        }
    }

    /******************************************************/
    function __session_write($id, $sess_data) {
        if ($GLOBALS['__session__']['id'] != $id) {
            $GLOBALS['__session__']['id'] = $id;
            unset($GLOBALS['__session__']['filename']);
        }
        return file_put_contents(__session_filename(), $sess_data);
    }
    /******************************************************/
    
    /******************************************************/
    function __session_destroy($id) {
        if ($GLOBALS['__session__']['id'] != $id) {
            $GLOBALS['__session__']['id'] = $id;
            unset($GLOBALS['__session__']['filename']);
        }
        return unlink(__session_filename());
    }
    /******************************************************/
    function __session_gc($maxlifetime) {
        if (!is_dir($GLOBALS['__session__']['path'])) return false;
        $lwd = getcwd();
        chdir($GLOBALS['__session__']['path']);
        foreach (glob('*.sess') as $f) {
            if (filectime($f) < (gettimeofday(true)-$maxlifetime)) unlink($f);
        }
        return true;
    }
    /******************************************************/

    session_set_save_handler(
        "__session_initialize",
        "__session_close",
        "__session_read",
        "__session_write",
        "__session_destroy",
        "__session_gc"
    );

?>
