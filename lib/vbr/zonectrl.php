<?php
    require_once('uther.ezfw.php');

    /***************************************************************\
    \***************************************************************/

    function create_zone_event ($type, $name, $state, $meta = NULL) {
        global $request_ts;
        $ev = new event();
        $ev->service_tag = 'vbr';
        $ev->video_id = true;
        $ev->type = $type;
        $ev->name = $name;
        $ev->state = $state;
        $ev->time = $request_ts;
        if (is_array($meta)) {
            foreach ($meta as $n => $v)
                $ev->$n = $v;
        }

        $ev->save();
    }

    /***************************************************************\
    \***************************************************************/

    function make_zone ($name, $points, $delta, $rate, $clr = false) {
        if (!$clr) {
            global $colors;
            $clr = $colors['default'];
        }

        $overlay = new streamOverlay(FRAME_WIDTH, FRAME_HEIGHT);
        $overlay->type = 'zone';
        $overlay->id = $name;

        $clr = $overlay->create_color($clr);
        $overlay->draw_zone($points, $clr);
        $l =& $points['left'];
        $r =& $points['right'];
        $bounds = array(
            'left' => $l['top']['x'],
            'right'=> $r['top']['x'],
            'top'  => $l['top']['y'],
            'bottom'=>$l['bottom']['y']
        );
        if ($l['bottom']['x'] < $bounds['left'])   $bounds['left']   = $l['bottom']['x'];
        if ($r['bottom']['x'] > $bounds['right'])  $bounds['right']  = $r['bottom']['x'];
        if ($r['top']['y']    < $bounds['top'])    $bounds['top']    = $r['top']['y'];
        if ($r['bottom']['y'] > $bounds['bottom']) $bounds['bottom'] = $r['bottom']['y'];

        $strides = calculate_zone_strides($overlay, $clr, $bounds);

        if (defined('SAVE_ZONE_IMAGE') && SAVE_ZONE_IMAGE)
            $overlay->save();

        return write_zone($name, $delta, $rate, $strides);
    }

    /***************************************************************\
    \***************************************************************/

    function write_zone ($name, $delta, $rate, $strides) {
        $data = pack_zone($delta, $rate, $strides);
        return file_put_contents(ZONE_PATH . '/' . $name, $data);
    }

    /***************************************************************\
    \***************************************************************/

    function pack_zone ($delta, $rate, $strides) {
        $data = $strides;
        array_unshift($data, ZONE_FORMAT, $delta, $rate);
        $packed = @call_user_func_array(pack, $data);
        return $packed;
    }

    /***************************************************************\
    \***************************************************************/

    function calculate_zone_strides ($overlay, $clr, $bounds) {
        $strides = array();

        $i = 0;
        $skipping = true;

        $rskip = FRAME_WIDTH - $bounds['right'] - 1;

        $i = FRAME_WIDTH * $bounds['top'];
        for ($y = $bounds['top']; $y <= $bounds['bottom']; $y++) {

            $i += $bounds['left'];
            for ($x = $bounds['left']; $x < FRAME_WIDTH; $x++) {
                $c = $overlay->colorat($x, $y);
                if ($c == $clr) {
                    if ($skipping) {
                        array_push($strides, $i);
                        $skipping = false;
                        $i = 1;
                    } else
                        $i++;
                } else {
                    if ($skipping) {
                        $i++;
                        if ($x > $bounds['right']) break;
                    } else {
                        array_push($strides, $i);
                        $skipping = true;
                        $i = 1;
                    }
                }
            }
            $i += FRAME_WIDTH - $x - 1;
        }

        if ($c == $clr)
            array_push($strides, $i);

        return $strides;
    }


    /***************************************************************\
    \***************************************************************/

    function save_zone ($name, $type, $r, $pack = true) {
        global $request_ts, $colors, $flags, $delta;
        if (file_exists(ZONE_PATH . "/.$name.$type")) {
            $z = unserialize(file_get_contents(ZONE_PATH . "/.$name.$type"));
            if (!is_array($z)) $z = array();
        }

        $z = order_points($r, $saved);

        if (is_array($flags)) {
            if (!is_array($saved['flags'])) $saved['flags'] = array();
            foreach ($flags as $n => $v) {
                $saved['flags'][$n] = $v;
            }
        }

        if ($type == 'vbr') {
            global $vbr_rail_sizes;
            $saved['rail_sizes'] = $vbr_rail_sizes;
        }

        write_zone_file($name, $type, $saved);

        if (!$pack) return true;

        if ($type != 'vbr' )
            make_zone($name . '.' . $type, $z, $delta, $rate, $colors['default']);
            //write_zone($name . '.' . $type, $z, $delta);
        else {
            save_bed_rails($name, $z, $delta);
            create_zone_event($type, $name, 'draw');
            
            ob_end_flush();
            $st = state::start('vbr', 'rails', $name, $request_ts++);

            $st->points = $z;
            $st->delta = $delta;
            $st->rate = $rate;
            $st->annotation_level = 10;     // we don't want to see this state in secureview

            $st->save();
            log_hospital_event('drawn');
        }

        return true;
    }

    /***************************************************************\
    \***************************************************************/

    function write_zone_file ($name, $type, $zone) {
        return file_put_contents(ZONE_PATH . '/.' . $name . '.' . $type, serialize($zone));
    }

    /***************************************************************\
    \***************************************************************/

    function load_zone ($name, $type, $scale = true) {
        if (!file_exists(ZONE_PATH . "/.$name.$type")) {
            return;
        }

        $jslist = '';
        $flags = '';
        $z = unserialize(file_get_contents(ZONE_PATH . "/.$name.$type"));

        if (!is_array($z) || !is_array($z['points']) || !count($z['points'])) {
            unlink(ZONE_PATH . '/.' . $name . '.' . $type);
            return false;
        }


        if ($scale)
            foreach (array_keys($z['points']) as $i) {
                $z['points'][$i]['x'] = round(($z['points'][$i]['x'] * DISPLAY_WIDTH) / FRAME_WIDTH);
                $z['points'][$i]['y'] = round(($z['points'][$i]['y'] * DISPLAY_HEIGHT) / FRAME_HEIGHT);
            }

        if (!is_array($z)) {
            return false;
        }

        return $z;
    }

    /***************************************************************\
    \***************************************************************/

    function remove_zone ($name, $type) {
        $lwd = getcwd();
        if (!@chdir(ZONE_PATH)) return false;

        if (file_exists(".$name.$type"))
            unlink(".$name.$type");

        foreach (glob("$name.*.$type") as $fn)
            unlink($fn);

        chdir($lwd);

        return true;

    }

