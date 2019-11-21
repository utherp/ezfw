#!/usr/bin/php
<?php
    require_once('uther.ezfw.php');
    require_once(abs_path('methods', 'registration_controls.php'));
    load_definitions('TEST_NET');
    load_definitions('FLAGS');


    print "\n--------------------------------\n" . date('r') . "\n";

    $last_boot = intval(read_flag(LAST_BOOT_FLAG));
    $last_warm_restart = intval(read_flag(LAST_RESTART_FLAG));
    $last_cold_boot = intval(read_flag(LAST_COLD_BOOT_FLAG));

    function get_hostname() {
        return exec('hostname');
    }

    function check_network () {
        $OUR_IP = get_ip();
        if ($OUR_IP == '') {
            print "------> No IP!\n";
            return false;
        }
        print "our ip = '$OUR_IP'\n";

        $IP_NET = preg_replace('/\..*$/', '', $OUR_IP); //s/\..*$//'`
        if ($IP_NET == '10') {
            return true;
        }
        print "------> Wrong Net!\n";
        return false;
    }

    function do_net_check () {
        for ($pass = 1; $pass < 4; $pass++) {
            if (check_network()) {
                print "----> Network is correct.\n";
                return true;
            }
            print "Network test failed...\n";
            print "---->Restarting VT6102/3.\n";
            system("/usr/local/ezfw/sbin/vt6103_phyreset");
            print "----->Waiting 2 seconds...\n";
            sleep(2);
            print "---->Restarting network.\n";
            system("/sbin/ifdown -a");
            print "----->Waiting 3 seconds...\n";
            sleep(3);
            print "----->Trying network again\n";
            system("/sbin/ifup -a");
            print "\n";
            sleep(2);
        }
        return false;
    }

    function ping ($host) {
        for ($pass = 1; $pass < 4; $pass++) {
            exec("/bin/ping $host -c1 ", $out, $ret);
            if ($ret === 0) return true;
            unset($ret);
        }
        return false;
    }

    function remove_watchdog_script () {
        global $last_cold_boot;

/*      if (!file_exists(WATCHDOG_DESTINATION . '/' . WATCHDOG_FILENAME)) {
            print "---> NOTE: Watchdog timer already removed!\n\n";
            return false;
        }
*/
        if ($last_cold_boot > time() - 3600) {
            print "---> NOTE: Last system cold boot was too recent (" .
                    date('r', $last_cold_boot) .
                    ")! Not unlinking watchdog script.\n";
            return false;
        }
        return raise_flag(COLD_RESTART_REQUEST_FLAG);
//      return unlink(WATCHDOG_DESTINATION . '/' . WATCHDOG_FILENAME);
    }

    function add_watchdog_script () {
/*      if (file_exists(WATCHDOG_DESTINATION . '/' . WATCHDOG_FILENAME)) {
            print "---> NOTE: Watchdog timer already added!\n\n";
            return true;
        }
        return copy(
            WATCHDOG_SOURCE . '/' . WATCHDOG_FILENAME,
            WATCHDOG_DESTINATION . '/' . WATCHDOG_FILENAME
        );
*/
        return lower_flag(COLD_RESTART_REQUEST_FLAG);
    }

    print "--> Doing network check...\n";
    if (!do_net_check()) {
        print "----> Net failed to come live after 3 tries, unlinking watchdog script.\n";
        remove_watchdog_script();
        exit;
    }

    print "--> Doing registration check...\n";

    $REG = exec(abs_path('sbin', 'registered.php'));
    if ( $REG != "TRUE" ) {
        print "----> Node is not registered!  Not running host tests...\n\n";
        exit;
    }


    print "--> Doing hostname check...\n";

    $host = get_hostname();
    if ( $host == '' ) {
        print "----> No Hostname!  Restarting network...\n";
        system("/etc/init.d/networking restart");
        print "\n\n";
        exit;
    }
    $ip = get_ip();
    if (gethostbyaddr($ip) == $ip) {
        print "----> Cannot resolve ip to hostname!  Restarting network...\n";
        system("/etc/init.d/networking restart");
        print "\n\n";
        exit;
    }

    print "--> Doing server ping test...\n";

    if (!ping(PINGTEST_HOSTNAME)) {
        print "----> Host '" . PINGTEST_HOSTNAME . "' is not pingable! Restarting Network!\n";
        system(NETWORK_RESTART_CMD);
        print "\n\n";
        exit;
    }


    print "--> All tests successful, resetting watchdog script.\n";
    add_watchdog_script();
    print "\n\n";
?>
