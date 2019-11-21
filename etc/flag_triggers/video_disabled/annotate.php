#!/usr/bin/php
<?php
    require_once('uther.ezfw.php');

    /* annotations are in a tree of the following format:
     *
     * [flag_filename] => (
     *      [actionA] => [service, type, name, state]
     *      [actionB] => [service, type, name, state]
     *      ...
     * )
     * [another_flag] => (
     *      [actionA] => [service, type, name, state]
     *      ...
     * )
     * ...
     */

    $annotations = array(
        'privacy' => array(
            'raise' => array('secure', 'privacy', 'patient', 'enabled'),
            'lower' => array('secure', 'privacy', 'patient', 'disabled')
        ),
        'nurse_privacy' => array(
            'raise' => array('secure', 'privacy', 'nurse', 'enabled'),
            'lower' => array('secure', 'privacy', 'nurse', 'disabled')
        ),
        'video_disabled' => array(
            'raise' => array('secure', 'view', 'video', 'disabled'),
            'lower' => array('secure', 'view', 'video', 'enabled')
        ),
        'recording_disabled' => array(
            'raise' => array('secure', 'view', 'recording', 'disabled'),
            'lower' => array('secure', 'view', 'recording', 'enabled')
        ),
        'simulate_vbr_alarm' => array(
            'raise' => array('vbr', 'vbr', 'bed_area', 'simulated')
        )
    );

    $eventOnlyFlags = array(
        'simulate_vbr_alarm'
    );

    if ($argc < 3) {
        print "USAGE: {$argv[0]} action flag_name\n";
        exit(1);
    }

    $action = strtolower($argv[1]);
    $flag = $argv[2];

    // defining this prevents what we do here from firing another flag trigger.
    $__NO_FLAG_ACTIONS__ = true;

    if (!is_array($annotations[$flag])) exit(0);
    if (!is_array($annotations[$flag][$action])) exit(0);

    $ant =& $annotations[$flag][$action];

    $ev = new event();
    $ev->service_tag = $ant[0];
    $ev->type = $ant[1];
    $ev->name = $ant[2];
    $ev->state = $ant[3];
    $ev->time = true;
    $ev->video_id = true;

    if (filesize(flag_path($flag))) 
        $ev->flag_data = read_flag($flag, false); /* false parameter overrides flag actions, as we do not want to run a read trigger */

    $ev->save();

    # these flags need events, but not states
    if ( array_search($flag,$eventOnlyFlags) !== false ) {
        exit(0);
    }

    if ($action == 'raise') {
        $st = new state();
        $st->video_id = true;
        $st->service_tag = $ant[0];
        $st->type = $ant[1];
        $st->name = $ant[2];
        $st->start = true;
        $st->mode = $ant[3];
        $st->save();
    } else if ($action == 'lower') {
        $iter = new ezIterator('state', 'service_tag = ? AND type = ? AND name = ? AND end IS NULL', array($ant[0], $ant[1], $ant[2]), 0, 'start desc', 1);
        $st = $iter->next();
        if ($st) {
            $st->end = true;
            $st->duration = $st->end - $st->start;
            $st->save();
        }
    }


