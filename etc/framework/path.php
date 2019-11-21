<?php
    require_once('uther.ezfw.php');

    /*************************************************
     * Path expansion functions
    /*************************************************/

    function expand_path($pathlist) {
        if (!is_array($pathlist)) return $pathlist;
        $path = '';
        foreach ($pathlist as $p) {
            $path .= (is_array($p))?expand_path($p):'/'.$p;
        }
        return $path;
    }
    /***********************************************/
    function abs_path() {
        $p = EZFW_ROOT;
        if (func_num_args() === 0) return $p;
        $p .= '/' . expand_path(func_get_args());
        return $p;
    }
    function log_path() {
        if (func_num_args() === 0) return abs_path(LOG_PATH);
        $args = func_get_args();
        return abs_path(LOG_PATH, $args);
    }
    /***********************************************/
    function video_path() {
        if (func_num_args() === 0) return abs_path(VIDEO_PATH);
        $args = func_get_args();
        return abs_path(VIDEO_PATH, $args);
    }
    /***********************************************/
    function server_web_path() {
        return get_proto() . get_host(SERVER_HOST) . '/ezfw/' . expand_path(func_get_args());
    }
    /***********************************************/
    function web_path() {
        if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1')
            return WEB_ROOT . expand_path(func_get_args());
        return get_proto() . get_host() . '/' . expand_path(func_get_args());
    }

    /***********************************************/
    /***********************************************/


