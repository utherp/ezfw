<?php
    require_once('uther.ezfw.php');

/*
    Layout of videos table:
    -----------------------
    | id        | int(11)                          | NO   | PRI | NULL    | auto_increment |
    | store_id  | int(11)                          | NO   | MUL | buffer  |                |
    | moving_to | int(11)                          | YES  |     | NULL    |                |
    | mover_pid | int(11)                          | YES  |     | NULL    |                |
    | start     | datetime                         | YES  | MUL | NULL    |                |
    | end       | datetime                         | YES  | MUL | NULL    |                |
    | size      | bigint(20)                       | NO   |     | 0       |                |
    | duration  | int(11)                          | NO   |     | 0       |                |
    | flags     | set(recalc,hidden,locked,        | NO   |     | recalc  |                |
    |           |     moving,current)              |      |     |         |                |
    | weight    | double                           | NO   |     | 0       |                |
    | score     | double                           | NO   |     | 0       |                |
    | calculated| datetime                         | YES  |     | NULL    |                |
    | filename  | text                             | NO   |     |         |                |
    | meta      | text                             | YES  |     | NULL    |                |
*/

    class video extends ezObj {

    /*****************************************\
    \*****************************************/

    /*  Database Definitions */
        static $_db_settings = array(
            // Table name
            'table'             =>  'videos',
            // Primary key field name
            'identifier_field'  =>  'id',
            // Informal data field
            'informal_field'    =>  'meta',
            // Table fields
            'fields'            =>  array (
                'id', 'store_tag', 'moving_to', 'mover_pid',
                'start', 'end', 'size', 'duration',
                'weight', 'score', 'calculated',
                'flags', 'filename', 'vcodec', 'acodec',
                'meta'
            ),
            'read_only_properties' => array ('id'),
        );

    /*  CareView Object Settings */
        static $_ez_settings = array(
            /*  Property name translations */
            'property_defaults'=>array(
                'flags'     => 'recalc',
                'weight'    => 0,
                'skew'      => 0,
            ),
            'defaults_on_create'=>true,
            'defaults_on_missing'=>true,
            'read_only_properties'=>array('filename'),
            'property_translations' =>  array(
                'length' => 'duration',
                'full_path'=>'fullpath'
            ),
            'object_translations'   =>  array(
                'store' =>  array(
                    'cache' => true,
                    'field' => 'store_tag',
                    'class' => 'store'
                )
            ),
        );

        protected function get__ez_settings() { return self::$_ez_settings; }
        protected function get__db_settings() { return self::$_db_settings; }

        static $all_flags = array ('recalc', 'hidden', 'locked', 'moving', 'current', 'disabled');

        /*****************************************\
        \*****************************************/

        public function save () {
            $isnew = !$this->id;
            if (!($ret = parent::save())) return $ret;

            if ($isnew) {
                load_libs('stream');
                $thumbs = new thumbnails($this, true);
                $thumbs->save();
            }
            return $ret;
        }

        public function delete ($path = false) {
            // do any final saves...
            $id = $this->id;
            $this->save();
            logger("Deleting video $id...", true);
            $this->_cache('store', false);

            // we only want to allow delete when no store is set!
            // if a store is set, try to export it instead.
            if (is_a($this->store, 'store')) {
                debugger("Warning: Store still set as {$this->store->tag}...", 2);
                return $this->store->export($this);
            }

            $this->remove_images($id);

            if (!$path)
                return parent::delete();

            $fullpath = $path . '/' . $this->filename;

            if (!file_exists($fullpath))
                logger("--> Warning: video does not exist ({$fullpath})!");
            else if (!unlink($fullpath))
                logger("--> Warning: Unable to delete video file: " . last_error_message());
            else {
                debugger("--> Video file removed", 3);
                return parent::delete();
            }

            return false;
        }

        public function remove_images ($id) {
            load_libs('stream');
            $thumbs = new thumbnails('video', $id);
            $thumbs->remove();
            return;
        }

        /*****************************************\
        \*****************************************/

        protected function pack_data (&$data) {

            /**********************************************************
                First check if we're being moved into another store.
                Retrieve from cache and clear (with false as second param).
                When moving to another store, this hook's return will
                never cause a save directly, as the move_to_store method
                saves before and after the move to lock the video and
                set moving_to and mover_pid.  All other changes will
                still be saved during the first save hook.
            */

            $stores = $this->_cache('destination_stores', false);
            if ($stores) {
                debugger('destination stores was cached', 2);
                if (!$this->move_to_store($stores)) {
                    /************************************************
                        there is no graceful way out if moving fails,
                        so we'll throw an exception.  If we simply
                        fail, the object would not reflect what is 
                        current because the the move_to_store must
                        do another save to commit the moving_to, 
                        mover_pid and appropreate flags (moving,locked)
                    */
                    if (is_array($stores)) {
                        $msg = 'to any of ' . count($stores) . ' destination stores! ( ';
                        foreach ($stores as $s)
                            $msg .= is_a($s, 'store')?$s->tag:(is_string($s)?$s:'Unknown ');
                        $msg .= ')';
                    } else 
                        $msg = 'to the destination store (' . is_a($s, 'store')?$s->tag:(is_string($s)?$s:'Unknown') . ')';

                    throw new Exception("Unable to move video {$this->id} from '{$data['store_tag']}' $msg");
                }

                /******************************************
                    On success, everything would have been
                    saved already by the commitals in
                    move_to_store with saving the flags and
                    moving values... so we'll clear out all
                    data values and return, causing dbObj
                    to abandon the save.
                */
                foreach (array_keys($data) as $k)
                    unset($data[$k]);

                return;
            }
            /**********************************************************/


            /*****************************************
                If we got to this point, we were not
                requested a move to another store, or
                we've already started the move by a
                save, then a call into move_to_store
                from above
            */

            /********************************************
                repack flags
            */
            $new_flags = $this->flagsObj->get_packed();
            if ($new_flags != $this->_get('flags', true)) {
                $data['flags'] = $new_flags;
                if ($this->flags->current) {
                    $qs = "update videos set flags = REPLACE(REPLACE(flags, 'current', ''), 'locked', '') where find_in_set('current', flags)";
                    if ($this->id)
                        $qs .= ' AND id != ' . $this->id;
                    dbObj::_exec('query', $qs);
                }
            }


            /*************************************************
                Rename file if filename has changed
            */
            $old_filename = $this->_get('filename', true);

            $tmp = array();
            foreach (array('start','end') as $ts) {
                if (!isset($data[$ts])) continue;
                if (is_numeric($data[$ts])) continue;
                $tmp[$ts] = $data[$ts];
                $data[$ts] = strtotime($data[$ts]);
                if (!is_numeric($data[$ts])) unset($data[$ts]);
            }
            $new_filename = $this->calculated_filename($data);
            $data = array_merge($data, $tmp);

            if ($old_filename[0] == '.' && $new_filename[0] != '.')
                $new_filename = '.' . $new_filename;

            if (!isset($old_filename)) {
                /* if no old filename we'll just set it */
                $data['filename'] = $new_filename;
            } else if ($old_filename != $new_filename) {
                /* filename has changed */
                if (!is_file($this->store->path . '/' . $old_filename)) {
                    /* old filename not found... does the new one exist? */
                    if (!is_file($this->store->path . '/' . $new_filename)) {
                        /* new filename does not exist either! */
                        logger("ERROR: Video {$v->id} file '$new_filename' not found at store path '{$this->store->path}'", true);
                    }
                } else if (!rename($this->store->path . '/' . $old_filename, $this->store->path . '/' . $new_filename)) {
                    /*******************************************
                        if we're here, rename failed in moving
                        the old filename to the new filename...
                        log it and keep the old filename
                    */
                    logger("ERROR: Unable to rename from old filename ({$this->store->path}/$old_filename) to new filename ({$this->store->path}/$new_filename): " . last_error_message(), true);
                    logger("--> Keeping old filename!");
                    $new_filename = $old_filename;
                } else {
                    /* rename succeeded, update the filename */
                    $data['filename'] = $new_filename;
                    debugger("NOTE: Renamed video {$this->id} file from '$old_filename' to '$new_filename'", 3);
                }
            }
            /*************************************************/

            $old_size = $this->_get('size', true);

            if ($data['size'] === true) {
                clear_stat_cache(true);
                $data['size'] = filesize($new_filename);
                if ($data['size'] === false) {
                    logger('ERROR: Failed to get filesize of video ' . $this->id . ': ' . last_error_message());
                    unset($data['size']);
                }
            }

            if (isset($data['size']) && is_numeric($old_size) && is_a($this->store, 'store')) {
                $this->store->size += ($data['size'] - $old_size);
                $this->store->save();
            }

            return;
        }

        /*****************************************\
        \*****************************************/

        protected function get_flagsObj () {
            if (!($flagsObj = $this->_cache('flagsObj'))) {
                $flagsObj = new flagsList('flags', self::$all_flags);
                $this->_cache('flagsObj', $flagsObj);
            }
            return $flagsObj;
        }

        /*****************************************/

        public function get_flags () {
            return $this->flagsObj->get_listObj();
        }

        /*****************************************/
        
        public function set_flags ($value) {
            if ($this->loaded == -1)
                $this->_set('flags', $value, true);

            return $this->flagsObj->set_flags($value);
        }

        /*****************************************\
        \*****************************************/

        public function set_disabled ($v) {
            return $this->flags->disabled = $v;
        }
        public function get_disabled () {
            return $this->flags->disabled;
        }
        public function get_extension () {
            if ($this->flags->disabled) return 'disabled';
            return $this->_get('extension');
        }

        public function calculated_filename($prefix, $start=false, $end=false, $suffix=false, $extension=false) {
            if (is_array($prefix)) {
                $data = $prefix;
                if (!isset($data['prefix'])) $data['prefix'] = $this->prefix;
                if (!isset($data['start'])) $data['start'] = $this->start;
                if (!isset($data['end'])) $data['end'] = $this->end;
                if (!isset($data['suffix'])) $data['suffix'] = $this->suffix;
                if (!isset($data['extension'])) $data['extension'] = $this->extension;
                return $this->calculated_filename($data['prefix'], $data['start'], $data['end'], $data['suffix'], $data['extension']);
            }

            return $prefix . $start . '-' . $end . $suffix . '.' . ($extension?$extension:'mpg');
        }

        public function get_filename() {
            if ($this->flags->hidden) $this->prefix = '.';
            return $this->calculated_filename($this->prefix, $this->start, $this->end, $this->suffix, $this->extension);
        }

        public function set_filename ($fn) {
            if ($this->loaded === -1)
                return $this->_set('filename', $fn);
            return false;
        }

        /*****************************************\
        \*****************************************/

        public function get_fullpath() {
            if ($this->store)
                return $this->store->path . '/' . $this->filename;
            return $this->filename;
        }

        /*****************************************\
        \*****************************************/

        public function get_start ()    { return $this->get_time('start'); }
        public function set_start ($ts = true) { return $this->set_time('start', $ts); }
        public function get_end   ()    { return $this->get_time('end'); }
        public function set_end   ($ts = true) { return $this->set_time('end', $ts); }
        public function get_calculated ()    { return $this->get_time('calculated'); }
        public function set_calculated ($ts = true) { return $this->set_time('calculated', $ts); }

        /*****************************************/

        function get_time ($name) {
            if ($c = $this->_cache($name))
                return $c;

            $c = $this->_get($name);

            if (!is_numeric($c))
                $c = strtotime($c);

            $this->_cache($name, $c);

            return $c;
        }

        /*****************************************/

        function set_time ($name, $ts = true) {
            // if no timestamp passed, use current time
            if ($ts === true)
                $ts = time();

            // if false is passed, revert the value
            if ($ts === false) {
                $this->_cache($name, false);
                $this->_revert($name);
            }
                
            // if its not a number and we cannot make a timestamp
            // out of it, then fail.
            if (!is_numeric($ts) && !is_numeric($ts = strtotime($ts)))
                return false;

            $this->_cache($name, $ts);
            $this->_set($name, date('Y-m-d H:i:s', $ts));

            return;
        }

        /*****************************************\
        \*****************************************/

        public function get_score ($force = false) {
            if (!$this->store) return 0;
            if ($force || $this->flags->recalc) {
                $this->score = $this->store->score($this);
                $this->flags->recalc = false;
            }
            return $this->_get('score');
        }

        public function get_weight ($force = false) {
            if (!$this->store) return 0;

            //  forced OR last calculated was more than 4 hours ago
            if ($force || ($this->calculated < (time() - 60 * 60 * 4))) {
                $this->weight = $this->store->weigh($this);
                $this->calculated = time();
            }

            return $this->_get('weight');
        }

        /*****************************************/

        public function set_score ($score) {
            $this->_set('score', $score);
            $this->flags->recalc = false;
            return $score;
        }

        public function set_weight ($weight) {
            $this->_set('weight', $weight);
            $this->calculated = time();
            return $weight;
        }

        /*****************************************\
        \*****************************************/

        public function get_current_size () {
            return $this->get_size(true);
        }

        public function get_size ($current = false) {
            if ($current || ($sz = $this->_get('size')) === true) {
                clear_stat_cache(true, $this->fullpath);
                $new_size = @filesize($this->fullpath);
                if ($new_size !== false)
                    return $new_size;
                logger("Warning: failed to get current size of video {$this->id}: " . last_error_message());
            }
            return $this->_get('size');
        }

        /*****************************************\
        \*****************************************/

        public function set_size ($size = true) {
            if (is_bool($size)) {
                if ($size === false) {
                    unset($this->size);
                    return false;
                } else {
                    return $this->_set('size', true);
                }
            }

            if (!is_numeric($size) && !($size = intval($size))) {
                debugger('Could not set size, size it not numeric "'.$size.'"', 1);
                return false;
            }

            return $this->_set('size', $size);
        }

        public function update () {

        }

        /*****************************************\
        \*****************************************/

        public function get_age () {
            return time() - $this->start;
        }

        /*****************************************\
        \*****************************************/

        public function get_store () {
            if (!($store = $this->_cache('store'))) {
                // not cached

                if (!($name = $this->store_tag))
                    //not set, no store tag
                    return false;

                // load store
                $store = store::fetch($name);
                // cache store
                $this->_cache('store', $store);
            }

            return $store;
        }

        /*****************************************\
        \*****************************************/

        public function set_store_tag ($tag = NULL) {
            /**********************************************
                Attempting to set the store tag, we'll
                load the store and set the store, which
                is cached to 'destination_stores', then
                committed when the video is saved
            */

            // if its null, unset and pass to set_store to clear cache
            if ($tag === NULL) {
                logger('Note: Unset store for video ' . $this->id);
                unset($this->store_tag);
                unset($this->store);
//              $this->_set('store_tag', NULL, true);
//              $this->_cache('store', false);
                return;
            }

            // if we're still loading, bypass hook and set value
            if ($this->loaded == -1)
                return $this->_set('store_tag', $tag);

            if (!is_a($tag, 'store')) $tag = store::fetch($tag);
            if (!is_a($tag, 'store') || !$tag->is_loaded())
                return false;

            $this->store = $tag;
            return true;
        }

        /*****************************************\
        \*****************************************/

        public function set_store ($store = NULL) {
            /***************************************
                if passed NULL or no params
                then clear cached store
            */
            if (!$store) {
                $this->_cache('store', false);
                return NULL;
            }
            /**************************************/

            /**************************************
                if we're in the middle of a move,
                we'll want to call straight to 
                move_to_store, as we got here by a
                call from store during export.
            */
            if ($this->moving)
                return $this->move_to_store($store);


            /**************************************/
            $this->_cache('destination_stores', $store);
            return true;
        }

        /*****************************************\
        \*****************************************/

        private $moving = false;

        private function set_moving ($store) {
            /********************************************
                set moving_to (store tag), mover pid
                (our pid), the moving and locked flags
                to signify the video is being moved; then
                set the private property $moving so we
                don't loop when saving the moving info.
            */
            $this->moving = true;
            $this->moving_to = $store->tag;
            $this->mover_pid = getmypid();
            $this->flags->moving = true;
            $this->flags->locked = true;
            return;
        }

        /*****************************************\
        \*****************************************/

        private function unset_moving () {
            /********************************************
                Unset the values which signify the video
                is being moved to another store (whether
                the move was successful is unspecified
                here.
            */
            $this->moving_to = '';
            $this->mover_pid = 0;
            $this->flags->moving = false;
            $this->flags->locked = false;
            return;
        }

        /*****************************************\
        \*****************************************/

        private function move_to_store ($store) {
            
            /**************************************************
                Were we passed an array of stores? if so,
                lets allow our store's export method to
                decide which get it based on weight
            */
            if (is_array($store)) {
                /*  if we belong to a store, use it's export otherwize, export it statically.
                    the reason we even care (and not call the static always) is to allow that
                    our store may have abstracted the export method.

                    We don't have to do anything else here because the store's export method will
                    call us again with one target store to attempt the export, if it fails, it
                    will call us again with the next candidate...
                */

                debugger("Calling to export video {$this->id} to one of " . count($store) . " stores...", 2);
                if (is_a($this->store, 'store'))
                    return $this->store->export($this, $store);

                // no current, store, call statically.
                return store::export($this, $store);
            }
            /***********************************************/


            /***************************************
                Validate the destination store
            */
            // if its a string, load store from tag
                if (is_string($store))
                    $store = store::fetch($store);

            // if not a store, cancel
                if (!is_a($store, 'store'))
                    return false;

            // is it the same store we're already in?
                if ($this->store_tag == $store->tag)
                    return true;
            /*************************************/


            /********************************************
                Here we have one validated target store.
                We stage the video for export, then call
                the target's import.  if the import is
                successful, we set the store as the new
                owner of the video.
            */

            $this->set_moving($store);
            $this->save();

            // call to target's import
            if (!$store->import($this)) {
                // import failed!
                $this->unset_moving();
                $this->save();
                return false;
            }

            /*******************************************
                Import succeeded, unset moving, set
                recalc flag, update store_tag and
                cache the new store object
            */
            $this->unset_moving();
            $this->flags->recalc = true;
            // set store_tag with _set, bypassing set_store_tag hook
            $this->_set('store_tag', $store->tag);
            $this->_cache('store', $store);

            // set score for video in store after moving
            $this->score = $store->score($this);
            $this->save();

            return true;
        }

        /*****************************************\
        \*****************************************/

        public function get_states ($limit = NULL, $annotation_level = 1) {
            if (!is_numeric($limit)) $limit = NULL;
            if (!is_numeric($annotation_level)) $annotation_level = 1;
            //$states = parent::fetch_all('state', 'video_id = ?', array($this->id), $limit);
            $states = parent::fetch_all('state', 
                                    'annotation_level <= ? AND UNIX_TIMESTAMP(start) < ? AND (end is NULL OR UNIX_TIMESTAMP(end) > ?) order by start',
                                    array($annotation_level, $this->end, $this->start), 
                                    $limit
                             );
            return $states;
        }

        public function get_events ($limit = NULL, $annotation_level = 1) {
            if (!is_numeric($limit)) $limit = NULL;
            if (!is_numeric($annotation_level)) $annotation_level = 1;
            $events = parent::fetch_all('event', 'video_id = ? AND annotation_level <= ? order by time', array($this->id, $annotation_level), $limit);
            return $events;
        }

        public function iterate_states ($annotation_level = 1) {
            if (!is_numeric($annotation_level)) $annotation_level = 1;
            return new ezIterator('state', 
                                    'annotation_level <= ? AND UNIX_TIMESTAMP(start) < ? AND (end IS NULL OR UNIX_TIMESTAMP(end) > ?)',
                                    array($annotation_level, $this->end, $this->start)); 
        }

        public function iterate_events ($annotation_level = 1) {
            if (!is_numeric($annotation_level)) $annotation_level = 1;
            $evtmp = $this->get_events(NULL, $annotation_level);
            $tmp = array();
            foreach ($evtmp as $k => $e)
                $tmp[$k] = $e->time;
            asort($tmp);
            $events = array();
            foreach ($tmp as $k => $e)
                $events[$k] = $evtmp[$k];
            
            return new ezIterator('event', $events);
        }

        /*****************************************\
        \*****************************************/

        public function recover() {
            do {
                if (!$this->moving_to) break; /* video is not moving... */

                $tag = $this->store_tag;
                $dtag = $this->moving_to;

                if ($tag == $dtag) break; /* video was successfully moved */

                $orig = store::fetch($tag);
                $dest = store::fetch($dtag);

                if (!$orig) {
                    /* the originating store doesn't exist... set the target */
                    $this->store_tag = $dtag;
                    $this->moving_to = '';
                    break;
                }

                if (!$dest) {
                    /* the target store does not exist... clear the moving_to field */
                    $this->moving_to = '';
                    break;
                }

                $spath = $orig->path . '/' . $this->filename;
                $dpath = $dest->path . '/' . $this->filename;

                if (file_exists($spath)) {
                    /* source file exists... still needs to be moved */
                    if (file_exists($dpath)) {
                        /* target file exists... */
                        if (filesize($dpath) == filesize($spath)) {
                            /* it appears they are the same... */
                            $this->moving_to = '';
                            $this->store_tag = $dtag;
                            unlink($spath);
                            break;
                        }

                        /* the src and target files are different */
                        unlink($dpath);
                    }

                    /* no target file, or target was different..., copy has not occurred yet */
                    $this->moving_to = '';
                    $this->store = $dest;
                    break;
                }

                if (file_exists($dpath)) {
                    /* destination file exist, but source does not... video has been moved */
                    $this->moving_to = '';
                    $this->store_tag = $dtag;
                    break;
                }

                /* neither source NOT target file exist... something has gone wrong here... */
                logger("WARNING: Video {$this->id} has disappeared while trying to recover a move from store '$tag' to store '$dtag'", true);
                return false;

            } while (0);

            $this->mover_pid = 0;
            $this->flags->locked = false;
            $this->save();

            return true;
        }

        /*****************************************\
        \*****************************************/

        static function from ($timestamp) {
//            $ds = date('Y-m-d H:i:s', intval($timestamp));
            $objs = self::fetch_range($timestamp, $timestamp);
           // parent::fetch_all('video', 'start <= ? AND end >= ?', array($ds, $ds), 1);
            return array_pop($objs);
        }

        static function fetch_id_range ($start, $end) {
            if (!is_numeric($start) || !is_numeric($end)) {
                // one of range is not a number
                return false;
            }
            $vids = parent::fetch_all('video', '(id >= ? && id <= ?)', array($start, $end));
            return $vids;
        }

        static function fetch_time_range($start, $end) {
            if (!is_numeric($start)) $start = strtotime($start);
            if (!is_numeric($end)) {
                if ($end[0] == '+') $end = strtotime($end, $start);
                else $end = strtotime($end);
            }

            $vids = parent::fetch_all('video', '(UNIX_TIMESTAMP(start) <= ? && UNIX_TIMESTAMP(end) >= ?)', array($end, $start));
            return $vids;
        }

        /*****************************************\
        \*****************************************/

        /* returns the current video (or null if none currently recording) */
        static function current() {
            $objs = parent::fetch_all('video', 'find_in_set(?, flags)', array('current'), 1, 'start desc');
            return array_pop($objs);
        }

        /*****************************************\
        \*****************************************/

        /* returns the previous video (last completed, not current) */
        static function previous() {
            $objs = parent::fetch_all('video', 'not find_in_set(?, flags)', array('current'), 1, 'start desc');
            return array_pop($objs);
        }

        /*****************************************\
        \*****************************************/

        /* returns the latest video (current video if one is recording) */
        static function latest() {
            $objs = parent::fetch_all('video', NULL, NULL, 1, 'start desc');
            return array_pop($objs);
        }

        /*****************************************\
        \*****************************************/

        static function fetch ($id) { return parent::fetch('video', $id); }
        static function fetch_all ($clause = '', $values = NULL, $limit = NULL, $order = '') { return parent::fetch_all('video', $clause, $values, $limit, $order); }

    }


