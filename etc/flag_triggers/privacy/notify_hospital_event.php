#!/usr/bin/php
<?php
    /*
    ** Notify the Server that an hospital_events event has occurred
    */

    require_once('uther.ezfw.php');

    // Validate command line args
    if ($argc < 3) {
        print "USAGE: {$argv[0]} action flag_name\n";
        exit(1);
    }
    $action = strtolower($argv[1]);
    $flag = $argv[2];

    // Defining this prevents what we do here from firing another flag trigger
    $__NO_FLAG_ACTIONS__ = true;

    // Translate the flag action to an hospital_event service arguments
    $event = '';
    $expires = '';
    $expires_at = intval(read_flag($flag));
    if ($action == 'raise') {
        $event = 'patient_privacy_on';
        $expires = '&expires=' . $expires_at;
    } elseif ($action == 'lower') {
        // We don't really know what caused the patient privacy flag to be lowered,
        // but we will infer that if the expiry time has elapsed that this is an expiry
        // event instead of the patient pressing the privacy button to turn it off.
        // Note: If the expiry is unset, we have to assume it was a button press.
        if ($expires_at != -1 && $expires_at < time()) {
            $event = 'patient_privacy_expired';
        } else {
            $event = 'patient_privacy_off';
        }
    } else {
        // Ignore other actions
        exit(0);
    }

    // Identify ourselves to Server by our IP
    $ip = get_ip();

    // Nothing to do on failure, so ignore the return code
    @file_get_contents('http://server.cv-internal.com/ezfw/service/hospital_events.php?' .
        'category=video&event=' . $event . '&ip=' . $ip . $expires);

    exit(0);
