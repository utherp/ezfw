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

    $ev_opts = $_POST['event'];
    if (!$ev_opts || !is_array($ev_opts)) {
        logger('Warning: failed to get event data!');
        print base64_encode(serialize(array('ERROR' => 'Data Corrupted')));
        exit;
    }

    $ev = new event();
    
    foreach ($ev_opts as $n => $v) {
        if ($v == 'true') $v = true;
        else if ($v == 'false') $v = false;
        else if ($v == 'NULL' || $v = 'null') $v = NULL;
        $ev->$n = $v;
    }

    $ev->save();

    print base64_encode(serialize(array('ACK'=>'SUCCESS', 'MSG'=>'Event ' . $ev->id . ' created')));

