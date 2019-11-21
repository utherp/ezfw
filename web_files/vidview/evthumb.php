<?php

    define('LOG_FILE', 'evthumb.log');
    require_once('uther.ezfw.php');

    $type = $_REQUEST['type'];
    $id = $_REQUEST['id'];
    $cat = $_REQUEST['cat'];
    if (!$type) $type = 'video';
    if (!$cat) $cat = 'full';

    if (!$type || !$id) exit;

    switch ($type) {
        case ('state'):
        case ('event'):
        case ('video'):
            break;
        default: exit;
    }
    switch ($cat) {
        case ('full'):
        case ('mini'):
        case ('icon'):
        case ('overlay'):
            break;
        default: exit;
    }

    load_libs('stream');

    if ($cat == 'overlay') {
        $fn = streamOverlay::overlay_filename($type, $id);
        if (!file_exists($fn)) exit;
        header('Content-type: image/png');
        fpassthru(fopen($fn, 'r'));
        exit;
    }

    $thumbs = new thumbnails($type, $id);
    $img = $thumbs->get($cat);
    if ( $img === false ) {
        # if we can't get an image, return SOMETHING at least
        header('Content-type: image/gif');
        print file_get_contents('images/noimg.gif');
    } else {
        header('Content-type: image/jpg');
        print $img;
    }
    exit;

