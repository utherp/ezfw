<?php
    require_once('uther.ezfw.php');
    load_definitions('cache');
    load_definitions('flags');
    define('MPLAYER_BIN', '/usr/local/bin/mplayer');
    define('FFMPEG_BIN', '/usr/local/bin/ffmpeg');
    define('DISABLED_THUMBNAIL_NAME', 'disabled.jpg');

    $USE_FFMPEG = false;

    function current_frame($root, &$index) {
        $c = new Memcache();
        $c->connect('localhost');
        $index = $c->get($root . '/index');
        $img = $c->get($root . '/frame');
        return $img;

    }

    function frame_at_offset ($video, $offset) {
        if (is_numeric($video)) {
            $video = video::fetch($video);
        }
        if ($video instanceOf video) {
            $video = $video->fullpath;
        }
        if (!file_exists($video)) {
            logger('Warning: could not aquire frame_at_offset: video not found "'.$video.'"');
            return false;
        }

        $tmpdir = '/tmp/thumb_' . getmypid();
        if (!@mkdir($tmpdir, 0777, true)) {
            logger('Warning: could not aquire frame_at_offset: failed to make tmpdir "'.$tmpdir.'": ' . last_error_message());
            return false;
        }

        $fn = $tmpdir . '/00000001.jpg';

        if ($USE_FFMPEG) {
            exec(FFMPEG_BIN . ' -ss ' . $offset . ' -vframes 1 -i "' . $video . '" -r 1 -y -f image2 -sameq "' . $fn . '"', $out, $ret);
        } else {
            exec(MPLAYER_BIN . ' -nosound -vo jpeg:outdir="' . $tmpdir . '" -ss 8 -frames 1 "' . $video . '" >/dev/null', $out, $ret);
        }

        if ($ret) {
          logger('Warning: when aquiring frame_at_offset: ' . ($USE_FFMPEG?'ffmpeg':'mplayer') . ' returned error code ' . $ret);
        }
        if (file_exists($fn)) {
            $thumb = file_get_contents($fn);
            unlink($fn);
        } else {
            logger('Warning: could not aquire frame_at_offset: ' . ($USE_FFMPEG?'ffmpeg':'mplayer') . ' failed to generate thumbnail');
            $thumb = false;
        }

        foreach (glob($tmpdir . '/*') as $f) if (is_file($f)) unlink($f);
        if (!@rmdir($tmpdir)) {
            logger('Warning: failed to remove tmpdir in frame_at_offset (' . $tmpdir . '): ' . last_error_message());
        }

        return $thumb;
    }

    class thumbnails {
        
        private $type = 'video';
        private $id = -1;

        public $path = array(
            'full' => '/thumbnails/full/',
            'mini' => '/thumbnails/mini/',
            'list' => '/thumbnails/list/',
            'icon' => '/thumbnails/icon/',
        );
        public $size = array(
            'full' => array('w'=>352, 'h'=>288),
            'mini' => array('w'=>172, 'h'=>144),
            'list' => array('w'=>100, 'h'=>60),
            'icon' => array('w'=>88, 'h'=>72)
        );

        public $index = array();
        public $imgs = array();

        /* the rest are generated via convert */
        public $root = array(
            'full' => 'video/352x288',
            'mini' => 'video/160x120'
        );

        function __construct ($obj, $current = false) {
            if (is_string($obj) && is_scalar($current)) {
                $type = $obj;
                $id = $current;
                $current = false;
                $obj = eval("return $type::fetch('$id');");
            }

            $this->obj = $obj;
            $this->type = get_class($obj);
            $this->id = $obj->id;

            $this->validate_enabled($current);
            $this->generate_paths();

            /* if we're creating thumbnails for a new event/state/video, grab the frame from the stream */
            if ($current) $this->init_current();

            return;
        }

        private function validate_enabled ($current) {
            if ($current) {
                $v = video::current();
                if (!$v) {
                    /* no current video... we'll check flag instead */
                    $this->disabled = flag_raised(RECORDING_DISABLED);
                    return;
                }
            } else do {
                $v = $this->obj;
                if ($v instanceOf video) break;

                $v = $this->obj->video;
                if ($v instanceOf video) break;

                $ts = $this->obj->timestamp;
                if (!$ts) $ts = $this->obj->start;
                if ($ts) $v = video::from($ts);

                if ($v instanceOf video) break;

                /* no idea what this is.... we'll assume enabled for now */
                $this->disabled = false;
                return;
            } while (0);

            /* always try to get the disbled state from the video object first */
            $this->disabled = !!$v->disabled;

            return;
        }

        private function init_current () {
            /***********************************
             * we still generate overlays even 
             * if validation of recording failed
             * for use over live feeds. 
             * (see bug #865)
             *   -- Stephen
             */
            if (!$this->disabled)
                $this->get_current();


            if (!is_object($this->obj)) return false;

            /* check if an overlay should be created for this state... */
            $func = $this->type . '_overlay';
            if (($this->obj->service instanceOf service) && method_exists($this->obj->service, $func)) {
                /* create the state overlay */
                $this->obj->service->$func($this->obj);
            }

            return true;
        }

        public function generate_paths () {
            $fn = self::basename($this->type, $this->id);
            if ($this->disabled) $fn = DISABLED_THUMBNAIL_NAME;

            foreach (array_keys($this->path) as $n)
                $this->path[$n] = CV_CACHE_PATH . $this->path[$n] . $fn;

            return;
        }

        public function remove ($n = false) {
            if ($n === false) {
                $c = 0;
                foreach (array_keys($this->path) as $n)
                    if ($this->remove($n)) $c++;

                /* remove overlay */
                $overlay = streamOverlay::overlay_filename($this->type, $this->id);
                if (file_exists($overlay)) unlink($overlay);

                return $c;
            }

            if (file_exists($this->path[$n])) 
                unlink($this->path[$n]);
            unset($this->imgs[$n]);

            return true;
        }

        public function get_current ($n = false) {
            if ($n === false) {
                $c = 0;
                foreach (array_keys($this->root) as $n)
                    if ($this->get_current($n)) $c++;

                return $c;
            }

            $this->imgs[$n] = current_frame($this->root[$n], $this->index[$n]);

            if ($this->imgs[$n]) return true;
            return false;
        }

        public function get_historic ($n = false) {
            if ($n === false) return $this->get_historic('full');
            if (!($this->obj instanceOf ezObj)) return false;

            if ($this->obj instanceOf video) {
                $vid = &$this->obj;
                $ts = 0;
            } else {
                if ($this->obj instanceOf event)
                    $ts = $this->obj->time;
                else 
                    $ts = $this->obj->start;

                $vid = video::fetch('start >= ? AND end <=?', array($ts, $ts));
                if (!$vid) return false;
            }

            $off = $ts - $vid->start;
            if ($off < 0) $off = 0;
            $this->imgs[$n] = frame_at_offset($vid, $off);
            if (!$this->imgs[$n]) return false;

            file_put_contents($this->path[$n], $this->imgs[$n]);

            return true;
        }

        public function get ($n) {
            if ($this->imgs[$n] || $this->load($n)) 
                return $this->imgs[$n];

            reset($this->path);
            $key = key($this->path);
            if ($key == $n) {
                logger("Warning: failed to get '$n' frame, and there are no larger candidates for scaling.");
                return false;
            }

            if (!$this->get($key)) {
                logger("Warning: failed to get thumbnail '$n': could not load frame, nor could I load the full frame to scale.");
                return false;
            }

            /* could not load $n, but loaded first candidate, lets scale */
            if (!$this->scale($key, $n)) {
                logger("Warning: failed to scale '$key' frame to '$n'");
                return false;
            }

            return $this->imgs[$n];
        }

        public function load ($n = false) {
            if ($n === false) {
                $c = 0;
                foreach (array_keys($this->path) as $n) 
                    if ($this->load($n)) $c++;

                return $c;
            }

            $fn = $this->path[$n];
            if (file_exists($fn)) {
                $this->imgs[$n] = file_get_contents($fn);
                return true;
            }

            reset($this->path);
            $k = key($this->path);

            if ($n == $k) return $this->get_historic($n);

            return false;
        }

        public function save ($n = false) {
            if ($n === false) {
                $c = 0;
                foreach (array_keys($this->imgs) as $n)
                    if ($this->save($n)) $c++;
                
                return $c;
            }

            if ($this->imgs[$n] && self::write_thumbnail($this->path[$n], $this->imgs[$n]))
                return true;

            return false;
        }

        public function scale ($sname, $dnames) {
            if (!is_array($dnames)) $dnames = array($dnames);

            if (!$this->imgs[$sname] && !$this->load($sname)) {
                logger("Warning: Failed to load source frame for scaling: src: $sname ({$this->path[$sname]})");
                return false;
            }

            $src = imagecreatefromstring($this->imgs[$sname]);
            if (!$src) {
                logger("Warning: when attempting to scale frame image, failed to create gd image from source data.");
                return false;
            }

            $sw = imagesx($src);
            $sh = imagesy($src);

            $c = 0;
            foreach ($dnames as $n) {
                if (!is_array($this->size[$n]) || !isset($this->path[$n])) continue;
                $w = $this->size[$n]['w'];
                $h = $this->size[$n]['h'];
                $dst = imagecreatetruecolor($w, $h);
                if (!imagecopyresized($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh)) {
                    logger("Warning: Failed to scale frame to $w x $h: " . last_error_message());

                } else if (!imagejpeg($dst, $this->path[$n])) {
                    logger("Warning: Failed to write jpeg frame to '{$this->path[$n]}': " . last_error_message());

                } else {
                    $this->load($n);
                    $c++;
                }

                imagedestroy($dst);
            }

            imagedestroy($src);
            return $c;
        }

        static function basename ($type, $id) {
            $fn = ($type == 'video')?'':($type . '_');
            return $fn . $id . '.jpg';
        }

        static function write_thumbnail ($fn, $data) {
            if (!@file_put_contents($fn, $data)) {
                logger("Warning: failed to write thumbnail '$fn': " . last_error_message());
                return false;
            }
            @chown($fn, 'cam');
            @chgrp($fn, 'www-data');
            @chmod($fn, 0777);
            return;
        }

    }

