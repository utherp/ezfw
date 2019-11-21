#!/usr/bin/php
<?php
    
    require_once("ezfw.php");
    $db = get_db_connection();

    $stores = array(
       'buffer' => 'buffer',
       'history' => 'history',
       'archive3M' => 'archive/3M',
       'archive6M' => 'archive/6M',
       'archive9M' => 'archive/9M',
       'archivePerm'=>'archive/Perm'
    );

    $totals = array();

    foreach ($stores as $tag => $path) {
        if (!chdir(abs_path('video', $path))) {
            print "Error: failed to chdir to " . abs_path('video', $path) . ": " . last_error_message() . "\n";
            continue;
        }

        $importing = array();
        foreach (glob("*.*") as $fn) chmod($fn, 0644);

        $vids = $db->fetchAll('select id, size, filename from videos where store_tag = ? ' .
                              'and not find_in_set("current", flags) ' .
                              'and not find_in_set("disabled", flags)', array($tag));
        foreach ($vids as $r) {
            $fn = $r['filename'];
            $id = $r['id'];
            $size = $r['size'];
            if (!file_exists($fn)) {
                print "WARNING: Video $id file '$fn' does not exist!\n";
                $db->delete('videos', 'id = ' . $id);
                continue;
            }
            $fs = filesize($fn);
            if (!$fs) {
                print "WARNING: File '$fullpath' is 0 length!\n";
                $db->delete('videos', 'id = ' . $id);
                unlink($fullpath);
                continue;
            }
    
            if ($fs != $size) {
                print "WARNING: Filesize for '$fullpath' is incorrect (claimed: {$size}, actual: $fs)\n";
                $db->update('videos', array('size'=>$fs), 'id = ' . $id);
            }

            chmod($fn, 0744);
        }

        foreach (glob("*.*") as $fn) {
            if (!is_executable($fn)) {
                print "WARNING: Video file '$fn' exists in '$tag' store's path, but not in the database.  Importing\n";
                $importing[] = $fn;
            }
            chmod($fn, 0644);
        }

        exec(abs_path('sbin', 'import_videos.php') . ' ' . $tag . ' ' . implode(' ', $importing), $out, $ret);
        if ($ret) {
            print "ERROR: import_videos.php failed with code $ret:  output is\n\t" . implode("\n\t", $out) . "\n\n";
        }

        $total = $db->fetchOne('select SUM(size) as total from videos where store_tag = ?', array($tag));
        $db->update('stores', array('size'=>$total), "tag='$tag'");
    }

/*    
    foreach ($stores as $store_tag => $path) {
        $idx = 0;
        $limit = 100;
        do {
            $vids = dbObj::_exec('fetchAll', 
                        'SELECT id, filename, size ' .
                        'FROM videos ' .
                        'WHERE ' . dbObj::_exec('quoteInto', 'store_tag = ? ', $store_tag) . 
                        "AND NOT find_in_set('current', flags) AND NOT find_in_set('disabled', flags) limit $idx,$limit"
                   );
    
            foreach ($vids as $vid) {
                $fullpath = abs_path('video', $path, $vid['filename']);
                if (!file_exists($fullpath)) {
                    print "WARNING: file '$fullpath' does not exist!\n";
                    $db->delete('videos', 'id = ' . $vid['id']);
                    continue;
                }
                $fs = filesize($fullpath);
                if (!$fs) {
                    print "WARNING: File '$fullpath' is 0 length!\n";
                    $db->delete('videos', 'id = ' . $vid['id']);
                    unlink($fullpath);
                    continue;
                }
    
                if ($fs != $vid['size']) {
                    print "WARINING: Filesize for '$fullpath' is incorrect (claimed: {$vid['size']}, actual: $fs)\n";
                    $db->update('videos', array('size'=>$fs), 'id = ' . $vid['id']);
                }
            }
    
            $idx += count($vids);
    
        } while (count($vids));

        $total = $db->fetchOne('select SUM(size) as total from videos where store_tag = "' . $store_tag . '"');
        $db->update('stores', array('size'=>$total), 'tag="'.$store_tag.'"');
    }
 */
