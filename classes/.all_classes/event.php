<?php
    require_once('uther.ezfw.php');

    class event extends ezObj {

        /*****************************************\
        \*****************************************/

    /*  Database Definitions */
        static $_db_settings = array(
            // Table name
            'table'             =>  'events',
            // Primary key field name
            'identifier_field'  =>  'id',
            // Informal data field
            'informal_field'    =>  'meta',
            // Table fields
            'fields'            =>  array (
                'id', 'video_id',
                'service_tag', 'type', 'name',
                'state', 'meta', 'time',
                'weight', 'recalc',
                'annotation_level'
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
                )
*/           
            )
        );

        protected function get__ez_settings() { return self::$_ez_settings; }
        protected function get__db_settings() { return self::$_db_settings; }

        /***************************************************\
        \***************************************************/

        protected function unpack_data (&$data) {
            if (isset($data['time']))
                $data['time'] = strtotime($data['time']);
            return;
        }

        protected function pack_data (&$data) {
            if (isset($data['time'])) {
                if ($data['time'] === true)
                    $data['time'] = time();
                if (is_numeric($data['time']))
                    $data['time'] = date('Y-m-d H:i:s', $data['time']);
            }
            if ($data['video_id'] === true)
                $data['video_id'] = video::latest()->id;
            return;
        }

        /***************************************************\
        \***************************************************/

        public function set_time ($ts) {
            if (is_bool($ts)) {
                if (!$ts) {
                    unset($this->time);
                    return null;
                }
                $ts = time();
            }
            if (!is_numeric($ts))
                $ts = strtotime($ts);
            $this->_set('time', $ts);
            return $ts;
        }

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

        public function get_video () {
            return video::fetch($this->video_id);
        }
        public function get_weight ($last = false, $count = 1) {
            if (!is_a($this->service, 'service'))
                return 0;
            return $this->service->weigh_event($this, $last, $count);
        }

        static function fetch ($id) { return parent::fetch('event', $id); }

        public function delete () {
            $id = $this->id;
            if (!($ret = parent::delete())) return $ret;

            load_libs('stream');

            $thumbs = new thumbnails('event', $id);
            $thumbs->remove();

            return;
        }

        public function save () {
            load_libs('stream');
            $isnew = !$this->id;
            if (!($ret = parent::save())) return $ret;

            if ($isnew) {
                $thumbs = new thumbnails($this, true);
                $thumbs->save();
            }
            return $ret;
        }


    }

