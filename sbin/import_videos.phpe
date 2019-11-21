#!/usr/bin/php
<?php
    require_once("ezfw.php");
    define('CV_CACHE_OBJECTS',false);

    if ($argc < 3) {
        print "USAGE: {$argv[0]} store_tag filename [filename [...]]\n";
        print "  OR to read filenames from stdin:\n";
        print "       {$argv[0]} store_tag -\n";
        exit;
    }

    $next_vids = array();
    $buffer = '';

    function next_filename () {
        global $argv, $argc, $idx, $from_stdin, $buffer, $next_vids;
        
        if ($argc > $idx) return $argv[$idx++];
        if ($from_stdin) {
            if (!count($next_vids)) {
                $buffer .= fgets(STDIN);
                $tmp = explode("\n", $buffer);
                $buffer = array_pop($tmp);
                while (count($tmp)) array_push($next_vids, array_shift($tmp));
            }
            if (!count($next_vids)) return '';
            return array_shift($next_vids);
        }
        return '';
    }

    $from_stdin = ($argv[2] == '-');
    $idx = $from_stdin?3:2;

    $store = store::fetch($argv[1]);

    if (!$store->is_loaded()) {
        print "ERROR: Could not load store '{$argv[1]}'!\n";
        exit(1);
    }

    # ...added by Stephen: do not check for fai or wait for fai to
    # stop IF the environment variable "NO_WAIT_ON_FAI" is set...
    # this is to give us a way around this, perticularly if importing
    # is being done by way of a dpkg control script, which would be run
    # when installed or upgraded... *through* fai.
    if (!isset($_ENV['NO_WAIT_ON_FAI'])) {

        # is FAI is running, wait a minute and try again, to save processing power
        # after 15 minutes, start regardless
        $waiting = 0;
        while ( exec('pidof -x fai') != "" && $waiting < 15 ) {
            print "FAI is running, waiting...\n";
            $waiting++;
            sleep(60);
        }

    }

    while ($full = trim(next_filename())) {
//    for ($i = 2; $i < count($argv); $i++) {
//        $full = $argv[$i];
        if (!file_exists($full)) {
            print "WARNING: File '$full' not found!\n";
            continue;
        }


        if (preg_match('/^(.*)\/(.*)$/', $full, $matches)) {
            $filename = $matches[2];
            $path = $matches[1] . '/';
        } else {
            $filename = $full;
            $path = '';
        }

        if (!preg_match('/([0-9]*?)-([0-9]*?)\.(.*)/', $filename, $matches)) {
            print "WARNING: Could not parse file '$full'!\n";
            continue;
        }

        $v = new video();
        $v->start = intval($matches[1]);
        $v->end = intval($matches[2]);
        $v->extension = $matches[3];
        $v->duration = $v->end - $v->start;
        if ($filename[0] === '.')
            $v->prefix = '.';

        if ($v->extension == 'disabled') {
            $v->flags->disabled = true;
        } elseif ($v->extension == 'flv') {
            $v->vcodec = 'h264';
        }

        if (!$v->flags->disabled && !filesize($full)) {
            print "WARNING: File '$full' is zero length!\n";
            unlink($full);
            unset($v);
            continue;
        }

        $tmp = video::fetch_all("filename='$filename'");
        if( count($tmp) > 0 ){
            print "WARNING: File '$full' has already been imported!\n";
            continue;
        }

        $v->_set('store_tag', $store->tag);
        if (!rename($full, $v->fullpath)) {
            print "ERROR: Unable to move '$full' to '{$v->fullpath}'!\n";
            continue;
        }

        $v->size = true;
        $v->save();

        print "Saved video #{$v->id} from '$full' to '{$v->fullpath}'\n";
        unset($v);
        unset($full);
    }

