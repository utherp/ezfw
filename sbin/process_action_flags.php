#!/usr/bin/php
<?php
/*
** Process all flags that require a direct action.
*/
require_once('uther.ezfw.php');


// Helper functions
function msg($msg) {
    print date('Y-m-d H:i:s') . ' ' . rtrim($msg) . "\n";
}

function fail($msg, $rc = 1) {
    msg("ERROR: $msg");
    exit($rc);
}

function wdt($cmd) {
    system("/usr/local/sbin/wdt_com.pl $cmd >/dev/null 2>&1", $rc);
    if ($rc == 13) {
        fail('No permission to access WDT');
    }
    return $rc;
}

//
// Camera needs to be power cycled action
if (flag_raised('camera_restart')) {
    lower_flag('camera_restart');
    if (wdt('test') == 0) {
        if (wdt('set reset12v 1') == 0) {
            msg('Camera restarted');
            exit(0);
        } else {
            fail("Camera restart failed");
        }
    } else {
        fail('No WDT on this RCP; cannot restart camera');
    }
}
