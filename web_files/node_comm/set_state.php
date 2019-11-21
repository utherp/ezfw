<?php
    require_once('uther.ezfw.php');
    require_once('registration_controls.php');

    load_definitions('ACCESS');

    $response = array();
    if (!talking_to_server()) {
        logger('Warning:  ['.$_SERVER['REMOTE_ADDR'] . ']  Unauthorized Flag Set Attempted! ' . 
                    ' key(' . crypt(get_mac() . AUTH_KEY) . ') vs ('.$_REQUEST['key'] . ')');
        print base64_encode(serialize(array('ERROR' => 'Unauthorized')));
        exit;
    }

    if (!isset($_POST['flags'])) {
        logger('Warning:  ['.$_SERVER['REMOTE_ADDR'] . '] No Flags Sent With Post!');
        print base64_encode(serialize(array('ERROR' => 'No Flags Sent')));
        exit;
    }

    $st_opts = $_POST['state'];
    if (!$st_opts || !is_array($st_opts)) {
        logger('Warning: failed to get state data!');
        print base64_encode(serialize(array('ERROR' => 'Data Corrupted')));
        exit;
    }

    if (!$_POST['action']) {
        print base64_encode(serialize(array('ERROR' => 'No action specified')));
        exit;
    }

    if ($_POST['action'] == 'start') {
        $st = new state();
        $st->start = true;
    } else if ($_POST['action'] == 'end'){
        if (!$_POST['id']) {
            print base64_encode(serialize(array('ERROR' => 'No id specified')));
            exit;
        }
        $st = state::fetch($_POST['id']);
    }

    if (!$st) {
        print base64_encode(serialize(array('ERROR' => 'No State')));
        exit;
    }

    foreach ($st_opts as $n => $v) {
        if ($v == 'true') $v = true;
        else if ($v == 'false') $v = false;
        else if ($v == 'NULL' || $v = 'null') $v = NULL;
        $ev->$n = $v;
    }

    $st->save();

    print base64_encode(serialize(array('ACK'=>'SUCCESS', 'MSG'=>'State' . $st->id . ' ' . $_POST['action'] . 'ed')));

