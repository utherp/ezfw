#!/usr/bin/php
<?php
    require_once("ezfw.php");

    chdir("/usr/local/ezfw/video/");

    foreach (glob("*-*.mpg") as $fn) {
        $v = video::fetch(array('filename'=>$fn));

        if (!$v->is_loaded()) {
            $v->filename = $fn;
            preg_match('/([0-9]*?)-([0-9]*?)\.mpg/', $fn, $matches);
            $v->start = intval($matches[1]);
            $v->end = intval($matches[2]);
            $v->_set('store_tag', 'purge');
            $v->size = true;
        //    $v->store_tag = 'buffer';
            $v->save();
        }
    }
