#!/usr/bin/php
<?php
    require_once('uther.ezfw.php');
    require_once('registration_controls.php');
    load_definitions('NODE_WEB');
    load_definitions('FLAGS');

    if ($argc < 3) {
        print "USAGE: {$argv[0]} action flag_name\n";
        exit(1);
    }

    $action = strtolower($argv[1]);
    $flag = $argv[2];

    if ($flag != 'recording_disabled' && $flag != 'video_disabled') {
        print "only valid flags are 'video_disabled' and 'recording_disabled'!\n";
        exit(1);
    }

    $tmp = explode('_', $flag);
    $flag = $tmp[0];
    $action = ($action == 'raise')?0:1;

    $contents = file_get_contents(
        'http://' . SERVER_HOST .'.'.DOMAIN_NAME. SERVER_WEB_ROOT . 
        '/setup/video_status.php?set=1&mac=' . get_mac() .
        "&$flag=$action"
    );
