<?php
    require_once('uther.ezfw.php');

    load_definitions('cache');

    if (!defined('CV_VBR_ARMED_COLOR'))
        define('CV_VBR_ARMED_COLOR', '90 EE 90 60');

    if (!defined('CV_VBR_UNARMED_COLOR'))
        define('CV_VBR_UNARMED_COLOR', 'FF FF 00 60');

    if (!defined('Overlay_BGColor'))
        define('Overlay_BGColor', '00 00 00 127');

    class streamOverlay {

        private $colors = array();
        private $img = NULL;
        private $size = array('w'=>352, 'h'=>288);

        public $type = '';
        public $id = 0;

        static function parse_color ($c) {
            if (is_array($c)) return $c;
            $tmp = explode(' ', $c, 4);
            return array(
                'r' => hexdec($tmp[0]),
                'g' => hexdec($tmp[1]), 
                'b' => hexdec($tmp[2]),
                'a' => intval($tmp[3])
            );
        }

        function __construct ($w = NULL, $h = NULL, $bg = NULL) {
            if (is_int($w)) $this->size['w'] = $w;
            if (is_int($h)) $this->size['h'] = $h;

            $this->img = imagecreatetruecolor($this->size['w'], $this->size['h']);
            imagealphablending($this->img, false);
            imagesavealpha($this->img, true);

            if ($bg) $bg = $this->create_color($bg);
            if (!$bg) $bg = $this->create_color(Overlay_BGColor);

            imagefill($this->img, 0, 0, $bg);

            return;
        }

        function __destruct () {
            if ($this->img) imagedestroy($this->img);
            $this->img = NULL;
            return;
        }

        public function create_color ($c) {
            if (is_array($c) && isset($c['c'])) return $c['c'];
            if (is_int($c)) return $c;

            $i = count($this->color);
            $this->color[$i] = self::parse_color($c);
            if (!$this->color[$i]) return false;

            return $this->color[$i]['c'] = imagecolorallocatealpha(
                $this->img, 
                $this->color[$i]['r'],
                $this->color[$i]['g'],
                $this->color[$i]['b'],
                $this->color[$i]['a']
            );
        }

        public function draw_line ($x1, $y1, $x2, $y2, $w, $clr) {
            imagesetthickness($this->img, $w);
            return imageline($this->img, $x1, $y1, $x2, $y2, $clr);
        }

        public function draw_polygon ($points, $clr) {
            $fg = $this->create_color($clr);
            $verts = array();
            $n = count($points);
            foreach ($points as $p) {
                $verts[] = $p['x'];
                $verts[] = $p['y'];
            }

            return imagefilledpolygon(
                $this->img, 
                $verts, $n,
                $fg
            );
        }

        public function colorat ($x, $y) { return imagecolorat($this->img, $x, $y); }

        public function save() {
            $fn = self::overlay_filename($this->type, $this->id);
            self::debug("saving image, (type: {$this->type}, id: {$this->id}), filename is '$fn'");
            return imagepng($this->img, $fn);
        }


        /***************************************
         * The following functions are common
         * overlays, bedrails and detection zones.
         */


        static function debug ($msg) {
           file_put_contents("/tmp/overlay.log", $msg . "\n", FILE_APPEND);
           return;
        }

        public function draw_rails ($points, $c) {
            self::debug("Drawing rails...");
            $fg = $this->create_color($c);
            $this->draw_line(
                $points['left']['top']['x'], $points['left']['top']['y'],
                $points['left']['bottom']['x'], $points['left']['bottom']['y'],
                3,
                $fg
            );
            $this->draw_line(
                $points['right']['top']['x'], $points['right']['top']['y'],
                $points['right']['bottom']['x'], $points['right']['bottom']['y'],
                3,
                $fg
            );

            return true;
        }

        public function draw_zone ($pnts, $c) {
            return $this->draw_polygon(
                array(
                    $pnts['left']['top'],
                    $pnts['right']['top'],
                    $pnts['right']['bottom'],
                    $pnts['left']['bottom']
                ),
                $c
            );
        }

        static function overlay_filename($type, $id) {
            /* zone overlay file path */
            if ($type == 'zone') 
                return ZONE_IMAGE_PATH . "/$id.png";

            /* general, state/event overlay file path */
            $fn = ($type == 'video')?'':($type.'_');
            $fn .= $id . '.png';
            return CV_CACHE_PATH . '/overlay/' . $fn; 
        }

    }


