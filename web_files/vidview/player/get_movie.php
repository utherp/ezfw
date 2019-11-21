<?php

    require_once('uther.ezfw.php');
    load_libs('video/convert');
    load_libs('stream');

    if (!isset($id)) $id = (int)$_GET['id'];
    if (!isset($type)) $type = $_GET['type'];
    if (!isset($etype)) $etype = $_GET['etype'];
    if (!isset($etype)) $etype = 'video';

    define('CV_CACHE_PATH', '/usr/local/ezfw/video/cache');

    function make_thumbnails($type, $id) {
        $thumbs = new thumbnails($type, $id);
        return $thumbs->save();

        $video = new $type($id);
        $offset = 15;
        $srvc = false;
        if (!($video instanceOf video)) {
            $obj = $video;
            $video = $video->video;
            switch (get_class($obj)) {
                case 'event':
                    $offset = $obj->time - $video->start;
                    $srvc = $obj->service;
                    break;
                case 'state':
                    $offset = $obj->start - $video->start;
                    $srvc = $obj->service;
                    break;
                default:
                    $offset = $video->start;
                    break;
            }
            if ($offset < 0) $offset = 15;
        }

        if ($video->flags->disabled) {
            /* video is disabled, there will be no thumbnails */
            return false;
        }

        if ($type == 'video') $type = '';
        else $type .= '_';
        @exec('/usr/local/ezfw/sbin/thumbnail.sh "'.$video->fullpath.'" '. $type . $id . ' ' . $offset, $output);

        if (($srvc instanceOf service) && method_exists($srvc, 'thumbnail_overlay')) {
            $srvc->thumbnail_overlay($obj, thumbnail_filename($type, $id));
        }

        return true;
    }

    function make_mini_thumbnail ($type, $id) {
        return make_thumbnails($type, $id);
        global $movie_file;
        if (!$movie_file) $movie_file = mpg_filename($id);

        $thumb = thumbnail_filename($type, $id);
        $mini_thumb = minithumb_filename($type, $id);
        if (!file_exists($thumb))
            if (!make_thumbnails ($type, $id)) return false;

        // commented because the thumbnailer now makes this for us
//        @exec('/usr/bin/convert ' . $thumb . ' -resize 100x60 ' . $mini_thumb);
        return true;
    }

/***************************************************************/

    function remove_arch_dir ($dir) {
        if (is_dir($dir)) exec('/bin/rm -Rf "' . $dir . '"');
        return;
    }

/***************************************************************/

    function download_movie ($file) {
        $r = load_object('room');
        $n = (is_object($r) && $r->is_loaded())?('Room ' . $r->get_name()):('Node ' . exec('/bin/hostname -s'));

        $tmp = explode('/', $file);
        $name = $tmp[count($tmp)-1];
        if (preg_match('/([0-9\.]+)-([0-9\.]+)(\.[^\.]*)$/', $name, &$matches))
            $name = $n . ' from ' . date('Y-m-d H.ia', intval($matches[1])) . '-' . date('H.ia', intval($matches[2])) . $matches[3];
        else
            $name = $n . ' - ' . $name;

        header('Content-type: ' . exec('/usr/bin/file -bi "'.$file.'"'));
        header('Content-disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($file));
        return send_file($file);
    }

/***************************************************************/

    function download_archives ($list) {
        $r = load_object('room');
        $n = (is_object($r) && $r->is_loaded())?('Room ' . $r->get_name()):('Node ' . exec('/bin/hostname -s'));

        if (is_executable('/usr/bin/zip')) {
            $arch = 'zip';
            $cmd = '/usr/bin/zip -3cqz - ';
        } else {
            $arch = 'tar';
            $cmd = '/bin/tar -hc *';
        }

        header('Content-type: application/x-' . $arch);
        header('Connection: close');
        header('Content-disposition: attachment; filename="'.$n.' Archives.' . $arch . '"');
        $dir = '/tmp/archtmp.'.getmypid();
        mkdir($dir);
        register_shutdown_function('remove_arch_dir', $dir);
        $comments = '';
        $filenames = '';

        foreach ($list as $target) {
            if (!preg_match('/([0-9\.]+)-([0-9\.]+)(\.[^\.]*)$/', $target, &$matches)) continue;
            $str = date('Y-m-d h.ia', intval($matches[1]));
            $end = date('h.ia', intval($matches[2]));
            $ext = $matches[3];
            $s = floatval($matches[2]) - floatval($matches[1]);
            
            $m = 0; $h = 0;
            if ($s>60) {
                $m = intval($s/60);
                $s = $s%60;
                if ($m>60) {
                    $h = intval($m/60);
                    $m = $m%60;
                }
            }
            if ($m < 10) $m = '0'.$m;
            if ($h < 10) $h = '0'.$h;
            if ($s < 10) $s = '0'.$s;

            $fn =  "$str-$end$ext";
            symlink($target, $dir . '/' . $fn);
            $filenames .= " '$fn'";
            $comments .= "Video from $str to $end (duration: $h:$m:$s)\n";
        }

        $comments .= "Video Archives for $n from " . cv_fullName . "\n\nNOTICE:\n"
                    ."This archive, all files and information within,\n"
                    ."is intended for the exclusive use of the person\n"
                    ."or entity to which it is addressed and may contain\n"
                    ."confidential health information that is privileged\n"
                    ."and legally protected from disclosure by federal\n"
                    ."law (HIPAA). If the reader of this message is not\n"
                    ."the intended recipient, you are hereby notified that\n"
                    ."disseminating, distributing, copying or otherwise\n"
                    ."using the information within is strictly prohibited.\n"
                    ."If you have received this file in error, please notify\n"
                    ."CareView Communications at kjohnson@care-view.com\n"
                    ."and delete it immediately.\n.\n";

        $lwd = getcwd();
        chdir($dir);
        passthru('/usr/bin/nice -19 ' . $cmd . ' '.$filenames.' <<<"'.$comments.'"'); //'/bin/tar -hc *.mpg');
        chdir($lwd);
        return;
    }



    function send_file ($filename, $flash = false, $id = false) {
        $file = fopen($filename, 'rb');
        if (!$file)
            return false;

        header('Connection: close');

        if (!$flash || !file_exists($filename . '.filepart')) {
            header('Content-Length: ' . filesize($filename));
            fpassthru($file);
            fclose($file);
            return true;
        }
            
        $nullcount = 0;
        $buffer = '';

        $filesize = file_exists($filename . '.filepart')?500000000:filesize($filename);
        header('Content-Length: ' . $filesize);
        
        $buffer = '';
        do {
            $buffer = fread($file, 8192);
        } while (strlen($buffer) < 180);

        $tmp = unpack('d',
                $buffer[60] . $buffer[59] . $buffer[58] . $buffer[57] .
                $buffer[56] . $buffer[55] . $buffer[54] . $buffer[53]);

        if ($tmp[1] == 0) {
            if ($id) {
                $v = video::fetch($id);
                $start = $v->start;
                $end = $v->end;
            } else {
                $size = preg_replace('/^.*\/([0-9\-]+)\.flv$/', '$1', $filename);
                list ($start, $end) = explode('-', $size);
                $start = intval($start);
                $end = intval($end);
            }
            $duration = doubleval($end - $start + .125);

            $i = 60;
            foreach (str_split(pack('d', $duration)) as $c)
                $buffer[$i--] = $c;

        }

        $tmp = unpack('d',
                $buffer[172] . $buffer[173] . $buffer[174] . $buffer[175] .
                $buffer[176] . $buffer[177] . $buffer[179] . $buffer[180]);
            
        if ($tmp[1] == 0) {
            $i = 180;
            foreach (str_split(pack('d', 500000000)) as $c)
                $buffer[$i--] = $c;
        }

        print $buffer;
        
        while (@ob_end_flush());

        $miss = 500;
        $check = 10;
        while ($check) {
            while ($miss) {
                if (!fpassthru($file)) {
                    $miss--;
                    usleep(1000);
                } else 
                    $miss = 500;
            }
            if (file_exists($filename . '.filepart')) {
                $miss = 500;
                $check--;
            } else break;
        }

        fclose($file);
        return $miss?true:false;

        $initial = true;
        do {
            $data = fread($file, 8192);
            if ($data) {
                $buffer .= $data;
                $nullcount = 0;
                ob_flush();
                flush();
            } else {
                $nullcount++;
                usleep(1000);
            }
            if ($initial)
                if (strlen($buffer) > 50000) {
                    $initial = false;
                    print $buffer;
                    $buffer = '';
                }
            else {
                print $buffer;
                $buffer = '';
            }
        } while ($nullcount < 2000);
        
        fclose($file);
        return ($nullcount < 2000);
    }

    function icon_filename ($type, $id) {
        if ($type == 'video') $type = '';
        else $type = $type . '_';
        return CV_CACHE_PATH . '/thumbnails/icon/' . $type . $id . '.jpg';
    }

    function minithumb_filename ($type, $id) {
        if ($type == 'video') $type = '';
        else $type = $type . '_';
        return CV_CACHE_PATH . '/thumbnails/mini/' . $type . $id . '.jpg';
    }

    function flash_filename ($type, $id) {
        $v = video::fetch($id);
        $tmpname = decimate_tmp_filename($v);
        return $tmpname;
    }

    function mpg_filename ($id) {
        $v = video::fetch($id);
        return $v->fullpath; //$v->store->path . '/' . $v->filename;
        $path = '/usr/local/ezfw/video/archive/' . date("Y/m/d", $id);

        $lwd = getcwd();
        if (!@chdir($path))
            return false;

        $tmp = glob($id . "-*.mpg");
        if (!count($tmp)) 
            return false;

        chdir($lwd);
        return $path . '/' . $tmp[0];
    }


    $movie_file = mpg_filename($id);

    switch ($type) {
        case ('list'):
            if (count($_GET['v']) == 1)
                download_movie(mpg_filename($_GET['v'][0]));
            else {
                $list = array();
                foreach ($_GET['v'] as $i)
                    array_push($list, mpg_filename($i));
                download_archives($list);
            }
        break;
    
        case ('minithumb'):
            if (!file_exists(minithumb_filename($etype, $id)))
                make_mini_thumbnail($etype, $id);
            if (file_exists(minithumb_filename($etype, $id))) {
                header('Content-type: image/jpeg');
                readfile(minithumb_filename($etype, $id));
            } else {
                header('Content-type: image/gif');
                readfile('images/noimg.gif');
            }
            exit;
        case ('thumb'):
            $thumbs = new thumbnails($etype, $id);
            $img = $thumbs->get('full');
            /*
            if (!file_exists(thumbnail_filename($etype, $id)))
                make_thumbnails($etype, $id);
            */

//            if (file_exists(thumbnail_filename($etype, $id))) {
            if ($img) {
                header('Content-type: image/jpeg');
                header('Content-length: ' . strlen($img));
                print $img;
            } else {
                header('Content-type: image/gif');
                readfile('images/noimg.gif');
            }
            exit;
        case ('icon'):
            if (!file_exists(icon_filename($etype, $id)))
                make_thumbnails($etype, $id);
            header('Content-type: image/jpeg');
            readfile(icon_filename($etype, $id));
            exit;
        case('flash'):
            $ev = new event();
            $ev->service_tag = 'secure';
            $ev->video_id = $id;
            $ev->type = 'access';
            $ev->name = 'view';
            $ev->state = 'play';
            $ev->time = true;
            $ev->save();

            header('Content-type: application/octet-stream');
            $v = video::fetch($id);
            if ($v->extension == 'flv') {
                /* file is already flash */
                send_file($v->fullpath, true);
                exit;
            }

            $filename = flash_filename($etype, $id);
            if (!file_exists($filename)) {
                require_once('uther.ezfw.php');
                load_libs('player');
                if (!request_demux_to_flash($id)) exit(0);
                $v = video::fetch($id);
                while ($v->store_tag == 'buffer') {
                    sleep(1);
                    unset(ezObj::$_cached_objects['video'][$id]);
                    unset($v);
                    $v = video::fetch($id);
                }
                $filename = flash_filename($etype, $id);
            }
            $nullcount = 0;

            /* wait for decimation to begin... */
            while (!file_exists($filename)) {
                $nullcount++;
                usleep(10000);
                if ($nullcount > 1000) exit;
            }
            sleep(1);
            send_file($filename, true, $id);
            exit;
        case('movie'):
            $ev = new event();
            $ev->service_tag = 'secure';
            $ev->video_id = $id;
            $ev->type = 'access';
            $ev->name = 'view';
            $ev->state = 'download';
            $ev->time = true;
            $ev->save();

            download_movie(mpg_filename($id));
        default:
            exit;
    }
?>
