<?php
    require_once('uther.ezfw.php');

    function fix_buffer_device ($path) {
        load_libs('ivtv');
        load_libs('daemons');

        logger('--> copying out any remaining video from the buffer device before rebuilding...', true);
        $iter = new ezIterator('video', 'store_tag = ?', array('buffer'));

        // lets just shove them into history...
        $store = store::fetch('history');

        while ($v = $iter->next()) {
            $v->store = $store;
            try{
                $v->save();
            }catch(Exception $e){
                logger("ERROR in fix_buffer_device: ".$e->getMessage(), true);
            }
            unset($v);
        }

        $um = '';
        exec('/bin/umount ' . $path, &$um);
        logger("umount output: '".implode("\n", $um)."'", true);

        $mkfs = '';
        exec('/usr/local/ezfw/sbin/CV_dboot_creation.sh', &$mkfs);
        logger("mkfs output: '".implode("\n", $mkfs)."'", true);

        $mnt = '';
        exec('/bin/mount -L CV_flashide ' . $path, &$mnt);
        logger("mount output: '".implode("\n", $mnt)."'", true);

        $msg = "fixing buffer device:\n";
        $msg .= "umount:\n";
        $msg .= "------------------------\n";
        $msg .= implode("\n", $um);
        $msg .= "------------------------\n";
        $msg .= "\nmkfs.ext3:\n";
        $msg .= "------------------------\n";
        $msg .= implode("\n", $mkfs);
        $msg .= "------------------------\n";

        $n = load_object('node');
        $nid = 'unknown';
        if (is_object($n)) $nid = $n->get_id();

        file_put_contents("/tmp/fix_buffer_device.log", $msg, FILE_APPEND);
        file_get_contents("http://server.cv-internal.com/ezfw/service/fix_buffer_log.php?node=$nid");

        return;

    }


