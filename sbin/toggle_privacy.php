#!/usr/bin/php
<?php
    require_once('uther.ezfw.php');
    load_definitions('flags');

    /**
     * enable patient privacy flag, includes conditional logic handling
     * @return void
     */
    function privacy_enabled() {
        // mapped to hospital.conf: cv_patientPrivacyDuration
        $duration = intval(CV_PATIENTPRIVACYDURATION);

        $flag_value = null;
        if ($duration == -1) {
            $flag_value = -1;
        } else if ($duration > 0) {
            $flag_value = time() + $duration;
        } else {
            $flag_value = time() + 1200;
        }
        print("Raising privacy flag\n");
        raise_flag(PRIVACY_FLAG, $flag_value);
    }

    /**
     * disable patient privacy flag
     * @return void
     */
    function privacy_disabled() {
        print("Lowering privacy flag\n");
        lower_flag(PRIVACY_FLAG);
    }


    // if specifically called "on" or "off"
    if (isset($argv[1])) {

        if ($argv[1] == 'on')
            privacy_enabled();
        else if ($argv[1] == 'off')
            privacy_disabled();
        else print("Invalid parameter, must be 'on' or 'off'\n");

    } else { // else toggle based on if the flag is set or not

        if (flag_raised(PRIVACY_FLAG))
            privacy_disabled();
        else
            privacy_enabled();

    }
