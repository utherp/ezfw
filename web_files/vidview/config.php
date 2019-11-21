<?php
    require_once('uther.ezfw.php');
//    $dbhost  = 'localhost';
    $dbhost  = 'gateway.cv-internal.com';
    $dbname  = 'SecureView';
    $dblogin = 'SecureView';
    $dbhaslo = 'n3wfl4shpl4y3r';

    $tries = 3;
    do {
        $connect = @mysql_connect($dbhost, $dblogin, $dbhaslo); 
        if ($connect) {
            @mysql_select_db($dbname, $connect);
            break;
        }
        sleep(1);
        $tries--;
    } while ($tries);

    $prefix = '';
    $serial = '486be1c6935f573';
    
    $language = 'en';
    
    if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $domain = $_SERVER['HTTP_X_FORWARDED_HOST'];
        $player_folder = 'nodes/' . load_object('node')->get_id() . '/vidview';
    } else {
        $domain = $_SERVER['SERVER_NAME'];
        $player_folder = 'ezfw/vidview'; 
    }
    
    $admin_login = 'admin'; 
    $admin_pass = 'n3wfl4shpl4y3r';
?>
