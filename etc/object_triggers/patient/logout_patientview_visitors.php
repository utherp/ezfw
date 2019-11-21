#!/usr/bin/php
<?php
    
    if ($argc < 5) exit;

    list (
        $cmd,           // the script name
        $type,          // the object type (patient, room, node, ect...)
        $action,        // one of (set, remove, change)
        $old_filename,  // filename of old object (for action 'set', this will be an empty string)
        $new_filename   // filename of new object (for action 'remove', this will be an empty string)
    ) = array_slice($argv, 0, 5);
    // NOTE: use of array slice above is in case a later version passes 
    // more values, we'll still want this to work

    if ($type != 'patient') exit;

    // this script only acts when the patient changes or is removed
    if ($action != 'change' && $action != 'remove') exit;

    if ($action == 'change') {
        // verify whether the patient is different, or if just some property changed
        $old_obj = unserialize(file_get_contents($old_filename));
        $new_obj = unserialize(file_get_contents($new_filename));
        if ($old_obj->hospital_id == $new_obj->hospital_id) {
            // the object changed, but contains the same patient
            exit;
        }
    }

    // stop all video and logout all visitors from patientview
    require_once('uther.ezfw.php');

    session_chat::remove_all_users('Patient has been discharged from the room');

    exit;

