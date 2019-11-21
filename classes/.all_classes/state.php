<?php
    require_once('uther.ezfw.php');

    class state extends ezObj {

        /*****************************************\
        \*****************************************/

    /*  Database Definitions */
        static $_db_settings = array(
            // Table name
            'table'             =>  'states',
            // Primary key field name
            'identifier_field'  =>  'id',
            // Informal data field
            'informal_field'    =>  'meta',
            // Table fields
            'fields'            =>  array (
                'id', 'video_id',
                'service_tag', 'type', 'name',
                'start', 'end', 'duration',
                'weight', 'recalc', 'annotation_level',
                'meta' 
            )
        );

    /*  CareView Object Settings */
        static $_ez_settings = array(
            /*  Property name translations */
            'property_translations' =>  array(
                'weigh'     => 'weight'
            ),
            'object_translations'   =>  array(
                'video'     =>  array(
                    'cache' =>  true,
                    'field' =>  'video_id',
                    'class' =>  'video'
                ),
/*
    disabling service object translation, I'll wrap it to catch
    thrown exceptions if the service cannot be loaded.  See bug #463
    --Stephen

                'service'   =>  array(
                    'cache' =>  true,
                    'field' =>  'service_tag',
                    'class' =>  'service'
                ),
*/            
            )
        );

        protected function get__ez_settings() { return self::$_ez_settings; }
        protected function get__db_settings() { return self::$_db_settings; }

/*
        protected function unpack_data (&$data) {
            foreach (array('start', 'end') as $tm) {
                if (isset($data[$tm]))
                    $data[$tm] = strtotime($data[$tm]);
            }
        }
*/
        protected function pack_data (&$data) {
            $times=array();
            foreach (array('start', 'end') as $n) {
                do {
                    if (!array_key_exists($n, $data)) {
                        $times[$n] = $this->_get($n);
                        break;
                    }

                    if ($data[$n] === NULL) break;
                    if (is_bool($data[$n])) {
                        if (!$data[$n]) break;
                        $data[$n] = time();
                    }

                    if (is_numeric($data[$n])) {
                        $times[$n] = $data[$n];
                        $data[$n] = date('Y-m-d H:i:s', $times[$n]);
                    } else if (is_string($data[$n]))
                        $times[$n] = strtotime($data[$n]);

                } while(0);

                if (!$times[$n] || !is_numeric($times[$n])) 
                    unset($times[$n]);
            }

            if ($times['start'] && $times['end'])
                $data['duration'] = $times['end'] - $times['start'];
            else
                $data['duration'] = 0;

            if ($data['video_id'] === true)
                $data['video_id'] = video::latest()->id;

            return;
        }

        /***************************************************\
        \***************************************************/

        public function set_start ($ts = true) { return $this->_set_time('start', $ts); }
        public function set_end   ($ts = true) { return $this->_set_time('end',   $ts); }

        /************************************************************/

        public function _set_time ($name, $ts) {
            if (is_bool($ts)) {
                if (!$ts) {
                    unset($this->$name);
                    return null;
                }
                $ts = time();
            }
            if (!is_numeric($ts))
                $ts = strtotime($ts);
            $this->_set($name, $ts);
            return $ts;
        }

        /************************************************************/

        public function get_service () {
            $tag = $this->service_tag;
            if (!$tag) return NULL;

            $svc = $this->_cache('service');
            if (!($svc instanceOf service)) {
                try { 
                    $svc = service::fetch($tag);
                } catch (Exception $e) {
                    logger("Warning: Failed to load service '$tag' for event {$this->id}: " . $e->getMessage(), true);
                    return NULL;
                }
                $this->_cache('service', $svc);
            }

            return $svc;
        }

        /************************************************************/

        public function get_duration () {
            if ($this->start && $this->end)
                return $this->end - $this->start;
            return -1;
        }

        /************************************************************/

        public function get_weight ($last = false, $count = 1) {
            if (!is_a($this->service, 'service'))
                return 0;
            return $this->service->weigh_state($this, $last, $count);
        }

        /************************************************************/

        public function end ($timestamp = true, $incomplete = false) {
            if (!$this->start) {
                /* no start time, how could we end this? */
                return false;
            }

            /* backwards compatability */
            if (!$timestamp) $timestamp = $incomplete = true;

            if ($incomplete) {
                /* this means it was not ended property   
                 * ...the state is being ended by an 
                 * attempt to start a new state of the 
                 * same tag/type/name
                 */

                 /* NOTE: would set incomplete here, but 
                  * must fix bug where setting meta loses
                  * previous meta
                  */
                 // $this->incomplete = true;
            }

            $this->end = $timestamp;
            return $this->save();
        }

        /************************************************************/
        /************************************************************/
        /************************************************************/

        public function delete () {
            $id = $this->id;
            if (!($ret = parent::delete())) return $ret;

            load_libs('stream');
            $thumbs = new thumbnails($this);
            $thumbs->remove();
            return;
        }

        public function save () {
            $isnew = !$this->id;
            if (!($ret = parent::save())) {
                // commented this out as this is most likely a case where
                // the state simply hasn't changed
//                logger("Warning: failed to save state '{$this->id}'");
                return $ret;
            }

            /* check if an overlay should be created for this state... */
            if (!($this->service instanceOf service)) {
                logger("warning, state has no service '{$this->service_tag}'");
                return $ret;
            }

            if ($isnew) {
                load_libs('stream');
                $thumbs = new thumbnails($this, true);
                $thumbs->save();
            }

            return $ret;
        }

        static function &fetch ($id) { return parent::fetch('state', $id); }

        /************************************************************/

        static function &latest ($tag, $type, $name) {
            $st = parent::fetch_all(
                    'state',
                    'service_tag = ? AND type = ? AND name = ?',
                    array($tag, $type, $name),
                    1,
                    'start desc'
                );
           if (!count($st)) return false;
           return reset($st);
        }

        static function &open ($tag, $type, $name) {
            /* attempt to resume a state */
            if (($st = self::resume($tag, $type, $name))) {
                /* state already running meeting these values */
                return $st;
            }

            /* no state running, start a new one */
            return self::start($tag, $type, $name);
        }

        /************************************************************/

        function &resume ($tag, $type, $name) {
            if ($tag instanceOf service) $tag = $tag->tag;
            $st = self::fetch(array(
                      'service_tag'=>$tag,
                      'type'=>$type, 
                      'name'=>$name,
                      'end'=>'NULL'
                    )
                  );

            if (!$st->is_loaded()) return false;
            return $st;
        }

        /************************************************************/

        static function &start ($tag, $type, $name, $ts = false) {
            /* check if one is already started */
            if (!$ts) $ts = time();
            if ($st = self::resume($tag, $type, $name)) {
                /* a start assumes you want a new one,
                 * we'll assume now that the previous
                 * one is no longer valid */
                $st->end($ts - 1, true);
            }

            $st = new state();
            if ($tag instanceOf service) $tag = $tag->tag;
            $st->service_tag = $tag;
            $st->type = $type;
            $st->name = $name;

            $st->start = $ts;
            $st->video_id = video::latest()->id;
            return $st;
        }
    }

