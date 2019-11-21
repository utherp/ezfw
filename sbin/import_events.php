#!/usr/bin/php
<?php
    require_once("ezfw.php");

    if ($argc < 2) {
        print "USAGE: {$argv[0]} filename [filename [...]]\n";
        print "  OR to read filenames from stdin:\n";
        print "       {$argv[0]} - [filename [filename [...]]]\n";
        print "NOTE: to read from stdin, the '-' MUST be the first parameter, if you supply other filenames on the command line they must come after\n";
        exit;
    }

    function next_filename () {
        global $argv, $argc, $idx, $from_stdin;

        if ($argc > $idx) return $argv[$idx++];
        if ($from_stdin)
            return fgets(STDIN, 8192);

        return '';
    }

    $from_stdin = ($argv[1] == '-');
    $idx = $from_stdin?2:1;

    while ($full = trim(next_filename())) {
//    for ($i = 1; $i < count($argv); $i++) {
//        $full = $argv[$i];
        if (!file_exists($full)) {
            print "WARINING: File '$full' not found!\n";
            continue;
        }

        if (preg_match('/^(.*)\/(.*)$/', $full, $matches)) {
            $filename = $matches[2];
            $path = $matches[1] . '/';
        } else {
            $filename = $full;
            $path = '';
        }

        if (!preg_match('/([0-9]*?)-([0-9]*?)/', $filename, $matches)) {
            print "WARNING: Could not parse file '$full'!\n";
            continue;
        }

        $start = intval($matches[1]);
        $end = intval($matches[2]);

        $v = video::from(intval($start));

        $states = array();

        $fh = fopen($full, 'r');

        while ($line = fgets($fh)) {
            if (preg_match('/^([a-zA-Z0-9-]*?)_(START|END)\(([0-9\.]*)\)/', $line, $matches)) {
                $name = strtolower($matches[1]);
                if ($name == 'motion') $name = 'full';
                if ($matches[2] == 'START') {
                    $state = new state();
                    if ($v) $state->video_id = $v->id;
                    $state->_set('service_tag', 'secure');
                    $state->start = intval($matches[3]);
                    $state->type = 'detection';
                    $state->name = $name;
                    $states[$name] =& $state;
                } else if (isset($states[$name])) {
                    $states[$name]->end = intval($matches[3]);
                    if ($states[$name]->duration < 4) {
                        print "NOTE: '$name' state's duration too short ({$states[$name]->duration} sec)\n";
                    } else {
                        $states[$name]->save();
                        print "Saved state #{$states[$name]->id}: '$name'\n";
                    }
                    unset($states[$name]);
                }
            } else if (preg_match('/\([a-zA-Z0-9_-]*?)\(([0-9\.]*)\)/', $line, $matches)) {
                $event = new event();
                $event->_set('service_tag', 'secure');
                $event->type = 'detection';
                $event->name = $name;
                $event->state = '';
                $event->time = intval($matches[2]);
                $event->save();
                print "Saved event #{$event->id}: '$name'\n";
            } else {
                $line = rtrim($line);
                print "Warning: Unknown formatting '$line'...\n";
            }
        }

        fclose($fh);

        unlink($full);

    }
