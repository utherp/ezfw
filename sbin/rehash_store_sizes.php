#!/usr/bin/php
<?php
    require_once('uther.ezfw.php');

    define('LOG_FILE', 'rehash_store_sizes.log');
    define('DEBUG', 5);

    function get_stores () { return store::fetch_all(); }

    $stores = get_stores();
    $vids = new ezIterator('video', '', NULL, 0, 'id');

    foreach ($stores as $s)
        $s->size = 0;

    while (is_a($v = $vids->next(), 'video')) {
        if (!file_exists($v->fullpath)) {
            print "Warning: video {$v->id} does not exist at '{$v->fullpath}'\n";
            //$v->_set('store_tag', NULL);
            //$v->delete();
            continue;
        }
        $fs = $v->current_size;
        print "video {$v->fullpath} size is {$v->size}\n";
        $v->size = $fs;
        $v->save();
        if ($fs != $v->size) {
            print "--> filesystem says $fs\n";
        }
        $v->store->size += $v->size;
    }

    print "new sizes:\n";
    foreach ($stores as $s) {
        print $s->name . ': ' . $s->size . "\n";
        $s->save();
        print $s->name . ': ' . $s->size . "\n";
    }


