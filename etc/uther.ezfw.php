<?php

ini_set('include_path', '.:/usr/share/php:/home/uther/ezfw/etc');
    // Load global context variables so they can be used in path.php and load.php
    global $system_config;
    $system_config = array();
    $system_config['GLOBAL'] = parse_ini_file('/home/uther/ezfw/etc/ezfw.ini');
    foreach ($system_config['GLOBAL'] as $n => $v) define(strtoupper($n), $v, true);

    // This includes all the framework pieces (now all loaded from all.php)
    require_once(dirname(__FILE__) . '/framework/all.php');

/***********************************************
 * Main initialization
 ***********************************************/

    // load system configuration
    $system_config = array();

    include_paths(INCLUDE_PATHS);

    /*
        // uncomment this section to use local 
        // sessions and/or be a session server

        ini_set('session.save_path', abs_path('session_cache'));
        require_once('session_server.php');
    */

    /*
        // uncomment this section to use a remote session server

        ini_set('session.save_path', SESSION_SERVER);
        require_once('remote_session.php');
    */

    load_definitions('flags');
    
?>
