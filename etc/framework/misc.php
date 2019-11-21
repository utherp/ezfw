<?php
    /********************************************************
     * These are misc functions for the CareView Node
     * framework.  This file should not be included directly
     * as it is included by ezfw.php
     *
     * -- Stephen  2010-06-28
     */

    require_once('uther.ezfw.php');

    /* Misc functions */

    /****************************************
     * this function records the trigger name, event name and current timestamp
     * it is most likely to be removed/replaced soon
     *      -- Stephen 2010-06-28
     */

    function record_trigger_time($trigger_name, $event_name = false) {
        if ($event_name !== false)
            file_put_contents(abs_path(EVENTS_META_FILE), $event_name . '('.time().")\n", FILE_APPEND);
        return file_put_contents(abs_path(TRIGGER_LOG_PATH, $trigger_name), time() . "\n", FILE_APPEND);
    }


    /***********************************************
     * Functions for getting the mac and ip addr
     ***********************************************/

    function get_mac() {
        return trim(exec(
                '/sbin/ifconfig | ' . 
                '/bin/grep '. NETWORK_INTERFACE . ' | ' . 
                "/bin/sed 's/.*HWaddr //g'"
            ));
    }

    /***********************************************/

    function get_ip() {
        return trim(exec(
            '/sbin/ifconfig ' . NETWORK_INTERFACE . ' | /bin/grep "inet addr" | ' .
            '/bin/sed \'s/^[^0-9]*\([0-9\.]*\).*/\1/\''
        ));
    }

    /***********************************************/

    function get_cmmac() {
        return trim(exec(
            'wget -O - http://192.168.100.1/RgAddress.asp 2>&1 | ' .
            'perl -ne \'print uc($1) if /HFC MAC.*?((?:[0-9a-f]{2}:){5}[0-9a-f]{2})/i\''
        ));
    }
    
    /***********************************************/

    function has_wdt() {
        if ( exec('/usr/local/sbin/wdt_com.pl test 2>&1') == '' ) {
            return true;
        }

        return false;
    }

    function has_tvc() {
        if ( exec('/usr/local/sbin/tvc_com.pl test 2>&1') == '' ) {
            return true;
        }

        return false;
    }
    
    function get_wdt_mac() {
        if ( has_wdt() ) {
            return exec('/usr/local/sbin/wdt_com.pl get mac');
        }

        return false;
    }

    function get_wdt_ip() {
        if ( has_wdt() ) {
            return exec('/usr/local/sbin/wdt_com.pl get ip');
        }

        return false;
    }

    function get_tvc_mac() {
        if ( has_tvc() ) {
            return exec('/usr/local/sbin/tvc_com.pl get mac');
        }

        return false;
    }

    function get_tvc_ip() {
        if ( has_tvc() ) {
            return exec('/usr/local/sbin/tvc_com.pl get ip');
        }

        return false;
    }

    /***********************************************/
    /***********************************************/

    function get_proto() {
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTOCOL'])) {
            if ($_SERVER['HTTP_X_FORWARDED_PROTOCOL'] == 'ON') {
                return 'https://';
            }
        }
        return 'http://';
    }
    /***********************************************/
    function get_host($hostname = false) {
        $room = load_this_room();
        if (!$hostname) {
            $bouncepath = '/rooms/' . $room->get_hostname();
            $hostname = $room->get_hostname() . '.' . DOMAIN_NAME . WEB_ROOT;
        } else if ($room->get_hostname() . '.' . DOMAIN_NAME == SERVER_HOST) {
            $bouncepath = SERVER_WEB_ROOT;
            $hostname = SERVER_HOST . '.' . DOMAIN_NAME;
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_HOST']) && $_SERVER['HTTP_X_FORWARDED_HOST'] != $_SERVER['HOST_NAME'])
            return $_SERVER['HTTP_X_FORWARDED_HOST'] . $bouncepath;

        return $hostname;
    }
    /***********************************************/

