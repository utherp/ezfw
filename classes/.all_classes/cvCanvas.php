<?php

    class ezCanvas { 
        static $all;
        static $active;
        static $default = array(
            'w'  => 5,          // ellipse width and height
            'h'  => 5, 
            't'  => 2,          // thickness
            'c'  => 'ffffffdd', // color (RRGGBBAA) (RGB: 00-FF, A: 00-7F)
        );

        public $bg = '0000007f';
        public $w = 352;
        public $h = 288;
        private $objs;
        public $name; 
        private $img;
        private $rendered;

        /* validate vertex */
        static function parse_point ($p) {
            $tmp = explode('x', $p);
            if (count($tmp) < 2) return false;
            if (!is_numeric($tmp[0]) || !is_numeric($tmp[1])) return false;
             
            return array('x'=>$tmp[0], 'y'=>$tmp[1]);
        }

        /**************************\
         * parse and create color *
        \**************************/
        static function parse_color ($c, $def = false) {
            $tmp = str_split(preg_replace('/[^0-9A-Fa-f]/', '', $c), '2');
            if (count($tmp) < 4) {
                if (!$def) $def = self::$default['c'];
                $def = self::parse_color($def);
                if (count($tmp) < 3) {
                    if (count($tmp) < 2) {
                        if (!count($tmp))
                            $tmp[0] = $def['r'];
                        $tmp[1] = $def['g'];
                    }
                    $tmp[2] = $def['b'];
                }
                $tmp[3] = $def['a'];
            }
            if ($tmp[3] > 127) $tmp[3] = 127;

            return array(
                'r' => hexdec($tmp[0]),
                'g' => hexdec($tmp[1]),
                'b' => hexdec($tmp[2]),
                'a' => hexdec($tmp[3])
            );
        }

        public function create_color ($cstr, $def = false) {
            if (isset($this->colors[$cstr])) 
                return $this->colors[$cstr];
            $c = self::parse_color($cstr, $def);

#            print "creating color '$cstr':<br />\n";
#            print_r($c);

            return $this->colors[$cstr] = imagecolorallocatealpha($this->img, $c['r'], $c['g'], $c['b'], $c['a']);
        }
        /***********************************/

        /* start a new canvas */
        static function start ($name, $w = false, $h = false) {
            if (isset(self::$all[$name])) 
                unset(self::$all[$name]);
            
            $c = new ezCanvas($name, $w, $h);
            foreach (array('w', 'h', 'bg') as $n)
                if (($v = param($n, false)))
                    $c->$n = $v;

            self::$all[$name] = &$c;
            return $c;
        }

        /* construct canvas */
        function __construct ($name, $w = false, $h = false) {
            $this->name = $name;
            $this->colors = array();
            $this->objs = array();
            $this->img = 0;
            $this->rendered = false;
            if ($w) $this->w = $w;
            if ($h) $this->h = $h;
            $this->last_color = self::$default['c'];
            $this->last_thickness = self::$default['t'];
        }

        /* destroy canvas */
        function __destroy () {
            unset(self::$all[$this->name]);
            return $this->close();
        }

        function __wakeup () { $this->close(); }

        /* cleanup gd canvas image */
        public function close () {
            if ($this->img) 
                imagedestroy($this->img);
            $this->img = 0;
            $this->colors = array();
            $this->rendered = false;
            return;
        }

        public function set_color (&$obj) {
            if (!$obj['c'])
                return $obj['c'] = $this->last_color; 
            return $this->last_color = $obj['c'];
        }

        public function set_thickness (&$obj) {
            if (!$obj['t'])
                return $obj['t'] = $this->last_thickness;
            return $this->last_thickness = $obj['t'];
        }

        /* add a line to canvas */
        public function line ($id=false, $p1=false, $p2=false, $t=false, $c=false) {
            if (!is_array($id)) {
                return $this->line(array(
                    'id'=>$id,
                    'p1'=>$p1,
                    'p2'=>$p2,
                    't'=>$t,
                    'c'=>$c
                ));
            }

            if (!$id['id']) $id['id'] = count($this->objs);
            if (!is_array($id['p1']) && !($id['p1'] = self::parse_point($id['p1']))) return false;
            if (!is_array($id['p2']) && !($id['p2'] = self::parse_point($id['p2']))) return false;
            $this->set_thickness($id);
            $this->set_color($id);

            $id['type'] = 'line';
            $this->objs[$id['id']] = $id;
            $this->close();

            return true;
        }

        public function polygon ($id = false, $points = false, $t = false, $c = false, $f = false) {
            if (!is_array($id)) {
                $pnts = array();
                $i = 0;
                foreach ($points as $p) {
                    if (!is_array($p)) $p = self::parse_point($p);
                    array_push($pnts, $p['x'], $p['y']);
                    $i++;
                }
                return $this->polygon(array(
                    'id' => $id,
                    'points' => $pnts,
                    'filled' => ($f?true:false),
                    'c' => $c,
                    't' => $t
                ));
            }


            if (!$id['id']) $id['id'] = count($this->objs);
            $this->set_thickness($id);
            $this->set_color($id);
            $id['type'] = 'polygon';

            $this->objs[$id['id']] = $id;
            $this->close();
            return true;
        }

        public function ellipse ($id = false, $p = false, $w = false, $h = false, $c = false, $f = false) {
            if (!is_array($id)) {
                return $this->ellipse(array(
                    'id' => $id,
                    'p' => $p,
                    'w' => $w,
                    'h' => $h,
                    'c' => $c,
                    'filled' => ($f?true:false)
                ));
            }

            if (!$id['id']) $id['id'] = count($this->objs);
            if (!$id['w']) $id['w'] = self::$default['w'];
            if (!$id['h']) $id['h'] = self::$default['h'];
            if (!is_array($id['p']) && !($id['p'] = self::parse_point($id['p']))) return false;
            if (isset($id['f'])) {
                $id['filled'] = $id['f'];
                unset($id['f']);
            }
            $this->set_color($id);
            $this->set_thickness($id);
            $id['type'] = 'ellipse';

            $this->objs[$id['id']] = $id;
            $this->close();
            return true;
        }

        public function string ($args) {
            if (!$args['id']) $args['id'] = count($this->objs);
            if (!$args['s']) return false;
            if (!isset($args['p'])) return false;
            if (!is_array($args['p']) && !($args['p'] = self::parse_point($args['p']))) return false;
            $this->set_color($args);
            $this->set_thickness($args);
            $args['type'] = 'string';

            $this->objs[$args['id']] = $args;
            $this->close();
            return true;
        }

        public function box ($id = false, $p1=false, $p2=false, $t=false, $c=false, $f=false) {
            if (!is_array($id)) {
                return $this->box(array(
                    'id'=>$id,
                    'p1'=>$p1,
                    'p2'=>$p2,
                    't'=>$t,
                    'c'=>$c,
                    'filled'=>($f?true:false)
                ));
            }

            if (!$id['id']) $id['id'] = count($this->objs);
            if (!is_array($id['p1']) && !($id['p1'] = self::parse_point($id['p1']))) return false;
            if (!is_array($id['p2']) && !($id['p2'] = self::parse_point($id['p2']))) return false;
            $this->set_thickness($id);
            $this->set_color($id);

            $id['type'] = 'box';
            $this->objs[$id['id']] = $id;
            $this->close();

            return true;
        }

        /* erase an object from canvas */
        public function erase ($id = false) {
            if (is_array($id)) {
                $id = isset($id['id'])?$id['id']:'';
            }
            if (!$id && $id !== 0) $this->objs = array();
            else {
                if (!isset($this->objs[$id])) return;
                unset($this->objs[$id]);
            }
            $this->close();
            return;
        }

        private function render () {
            if ($this->rendered) return true;
            if ($this->img) $this->close();

            $this->img = imagecreatetruecolor($this->w, $this->h);
            imagealphablending($this->img, false);
            imagesavealpha($this->img, true);

            imagefill($this->img, 0, 0, $this->create_color($this->bg));

            foreach ($this->objs as $n => $o) {
                $c = $this->create_color($o['c']);
                imagesetthickness($this->img, $o['t']);
                $args = array($this->img);
                $func = '';

                switch ($o['type']) {
                    case ('line'): 
                        $func = 'line';
                        array_push($args,
                            $o['p1']['x'],
                            $o['p1']['y'],
                            $o['p2']['x'],
                            $o['p2']['y'],
                            $c
                        );
                        break;
                    case ('box'):
                        $func = 'rectangle';
                        array_push($args,
                            $o['p1']['x'],
                            $o['p1']['y'],
                            $o['p2']['x'],
                            $o['p2']['y'],
                            $c
                        );
                        break;
                    case ('polygon'):
                        $func = 'polygon';
                        $i = 0;
                        foreach ($o['points'] as $p) {
                            $i++;
                            array_push($pnts, $p['x'], $p['y']);
                        }

                        if ($i < 3) continue;
                        array_push($args, $pnts, $i, $c);
                        break;
                    case ('ellipse'):
                        $func = 'ellipse';
                        array_push($args, $o['p']['x'], $o['p']['y'], $o['w'], $o['h'], $c);
                        break;
                    case ('string'):
                        $func = 'string';
                        array_push($args, $o['f'], $o['p']['x'], $o['p']['y'], $o['s'], $c);
                        unset($o['f']);
                        if ($o['v']) $func .= 'up';
                        break;
                    default:
                        continue;
                };

                if ($o['filled']) $func = 'filled' . $func;
                $func = 'image' . $func;
                if (!function_exists($func)) continue;
                call_user_func_array($func, $args);
            }

            $this->rendered = true;

            return;
        }

        public function output ($fn = NULL) {
            if (!$this->rendered) $this->render();
            if (!$fn) {
                header('Content-Type: image/png');
                $fn = NULL;
            }
            return imagepng($this->img, $fn);
        }

    }

