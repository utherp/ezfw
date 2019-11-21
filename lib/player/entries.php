<?php

    /***************************
        new entry functions
    */

    function date_records_query ($vals, $y = false, $m = false, $d = false, $offset = false, $limit = false) {
        if (!is_numeric($y)) list ($y, $m, $d) = explode('-', date('Y-m-d'));
        $y = intval($y);
        $query = "select $vals from date_records where year='$y'";
        if (is_numeric($m)) $query .= " AND month='" . intval($m) . "'";
        if (is_numeric($d)) $query .= " AND day='" . intval($d) . "'";
        if (is_numeric($offset) || is_numeric($limit)) {
            if (!$limit) $limit = 999999;   /* this is stupid, but its how mysql manual says to do it (http://dev.mysql.com/doc/refman/4.1/en/select.html) */
            $query .= ' limit ' . $offset . ', ' . $limit;
        }
        return $query;
    }

    function time_string ($s) {
        $m = '00';
        if ($s > 59) {
            $m = intval($s/60);
            $s = $s%60;
            if ($m < 10) $m = '0'.$m;
        }
        if ($s < 10) $s = '0'.$s;
        return "$m:$s";
    }

    function date_pad ($y, $m = false, $d = false) {
        $q = str_pad($y,2,'0',STR_PAD_RIGHT) . '-';
        if ($m)
            $q .= str_pad($m,2,'0',STR_PAD_RIGHT) . '-';

        if ($d)
            $q .= str_pad($d,2,'0',STR_PAD_RIGHT);

        return $q;
    }

    function &load_videos ($y, $m, $d = false, $offset = NULL, $limit = NULL) {
        if ($videos = check_cache($y, $m, $d))
            return $videos;

        $vidData = get_db_connection()->fetchAll("select v.* from videos v, date_records r where r.video = v.id and r.year = '$y' AND r.month = '$m' AND r.day = '$d' ORDER BY v.start asc");
        if (!count($vidData)) return array();
        $videos = array();


        foreach ($vidData as $v) {
            $vid = new video($v, true);
            $videos[$vid->id] = $vid;
        }

        cache_videos($videos, $y, $m, $d);
        return $videos;
    }

    function count_items ($t, $y = false, $m = false, $d = false) {
        return get_db_connection()->fetchOne(date_records_query("count($t)",$y,$m,$d));
    }
        
    function day_has_video ($y, $m, $d)  { return count_items('video', $y, $m, $d); }
    function day_has_events ($y, $m, $d) { return count_items('event', $y, $m, $d); }
    function day_has_states ($y, $m, $d) { return count_items('state', $y, $m, $d); }

    function check_cache ($y, $m = false, $d = false) {
        global $cache;
        if (!$m) return isset($cache[$y])?$cache[$y]:false;
        if (!$d) return isset($cache[$y][$m])?$cache[$y][$m]:false;
        return isset($cache[$y][$m][$d])?$cache[$y][$m][$d]:false;
    }

    function cache_videos(&$videos, $y, $m, $d = false) {
        global $cache;
        if (!is_array($cache[$y])) $cache[$y] = array();

        if ($d) {
            if (!is_array($cache[$y][$m]))
                $cache[$y][$m] = array();
            $cache[$y][$m][$d] =& $videos;
            return;
        }

        $v = array();
        foreach ($videos as $v) {
            $d = date('d', $v->start);
            if (!is_array($cache[$y][$m][$d]))
                $cache[$y][$m][$d] = array();
            $cache[$y][$m][$d][] =& $v;
        }
        $videos =& $v;
        return;
    }

    function get_entry_filename($entry_name) {
        $i = 0;

        list ($type, $number, $loc) = explode('_', $entry_name);

        logger("type '$type', location '$loc', # '$number'");

        $path = abs_path(
                    VIDEO_PATH, 
                    constant($type . '_PATH'),
                    explode('-', $loc)
                );
        logger("Path = '$path'");

        if (!is_dir($path)) return false;
        $d = getcwd();
        chdir ($path);

        $entries = glob('*-*.mpg');

        if (isset($entries[$number])) return $path . '/' . $entries[$number];
        return false;
    }

    /******************************************************************************/
    function read_entry () {
        global $entry_cache;
        $args = func_get_args();
        $repo_name = array_shift($args);

        $args = array_map(
                    create_function('$a', 'return str_pad($a, 2, "0", STR_PAD_LEFT);'),
                    $args
                );
        $entry_index = array_pop($args);

        if (defined($repo_name . '_PATH')) $repo = abs_path(constant($repo_name . '_PATH'));
        else if (is_dir(abs_path($repo_name))) $repo = abs_path($repo_name);
        else if (is_dir($repo_name)) $repo = $repo_name;

        if (!is_dir($repo)) return false;
        if (!is_array($entry_cache[$repo_name])) $entry_cache[$repo_name] = array('path' => $repo);


        $found = false;
        if (is_array($entry_cache[$repo_name])) {
            $ref = &$entry_cache[$repo_name];
            foreach ($args as $a) {
                if (!is_array($ref[$a])) {
                    $found = false;
                    break;
                } else {
                    $ref =& $ref[$a];
                    $found = true;
                }
            }
            if ($found && is_array($ref[$entry_index]) && isset($ref[$entry_index]['filename'])) {
                return $ref[$entry_index];
            }
        }

        $ref = &$entry_cache[$repo_name];
        $cur_path = $repo;

        foreach ($args as $dir) {
            if (!is_dir($cur_path . '/' . $dir)) return false;
            $cur_path .= '/' . $dir;
            if (!is_array($ref[$dir])) $ref[$dir] = array('path' => $cur_path);
            $ref = &$ref[$dir];
        }

        if (is_dir($cur_path . '/' . $entry_index)) {
            $cur_path .= '/' . $entry_index;
            if (!is_array($ref[$entry_index])) $ref[$entry_index] = array('path' => $cur_path);
            $ref = &$ref[$entry_index];
            $entry_index = '*';
        }


        if (!is_array($ref['list'])) {
            $lwd = getcwd();
            chdir($cur_path);
            $ref['list'] = glob('*-*.mpg');
            chdir($lwd);
        }
        if ($entry_index != '*') {
            $entry_index = intval($entry_index);
            if (!isset($ref['list'][$entry_index])) return false;
            $entry = parse_entry($cur_path, $repo_name . '_' . $entry_index, $ref['list'][$entry_index]);
            $ref[$entry_index] = $entry;
            return $entry;
        } else {
            foreach ($ref['list'] as $index => $filename) {
                $ref[$index] = parse_entry($cur_path, $repo_name . '_' . $index, $filename);
            }
            return $ref;
        }
    }
    function parse_entry($path, $index, $filename) {
        return array_merge(
                array_combine(
                    array('start', 'end'),
                    array_map('intval', explode('-', substr($filename, 0, -4)))
                ),
                array(
                    'filename'  =>  $path . '/' . $filename,
                    'index'     =>  $index,
                )
            );
    }
    /******************************************************************************/

    function dir_entries () {
        if (func_num_args() == 0) return false;
        $args = func_get_args();
        global $entry_caches;

        $cachename = implode('_', $args);
        if (isset($entry_caches[$cachename])) {
            return $entry_caches[$cachename];
        }
        for ($i = 1; $i < count($args); $i++) 
            $args[$i] = add_zero($args[$i], 2);

        if (!is_dir(abs_path($args))) return false;

        $orig_dir = getcwd();
        if (chdir(abs_path($args)) === false) return false;
        $files = glob('*-*.mpg');
        chdir($orig_dir);

        switch ($args[0]) {
            case(BUFFER_PATH):
                $prefix = 'BUFFER_';
                break;
            case(ARCHIVE_PATH):
                $prefix = 'ARCHIVE_';
                break;
            default:
                $prefix = 'U_';
                break;
        }


        $entry_caches[$cachename] = array();
        for ($i = 0; $i < count($files); $i++) {
            list ($start, $end) = explode('-', preg_replace('/\.mpg$/', '', $files[$i]));
            $start = intval($start);
            $end = intval($end);
            array_push (
                $entry_caches[$cachename],
                array(
                    'filename'  =>  abs_path($args, $files[$i]),
                    'start'     =>  $start,
                    'end'       =>  $end,
                    'number'    =>  $prefix . $i,
                )
            );
        }
        return $entry_caches[$cachename];
    }

    /******************************************************************************/

    function archive_entries($year, $month, $day) {
        return read_entry('ARCHIVE', $year, $month, $day);
    }
    function buffer_entries($year, $month, $day) {
        return read_entry('BUFFER', $year, $month, $day);
    }
    function day_has_archives($timestamp) {
        if (!is_object($timestamp)) $timestamp = new DateTime("@$timestamp");

        list($year, $month, $day) = explode('_', $timestamp->format('Y_m_d'));
        $entries = read_entry('ARCHIVE', $year, $month, $day);
        if ($entries === false) return false;
        return (count($entries) > 0)?true:false;
    }
    function day_has_buffer($timestamp) {
        if (!is_object($timestamp)) $timestamp = new DateTime("@$timestamp");
        list($year, $month, $day) = explode('_', $timestamp->format('Y_m_d'));

        $entries = read_entry('BUFFER', $year, $month, $day);
        if ($entries === false) return false;
        return (count($entries) > 2)?true:false;
    }
    /******************************************************************************/

