<?php
    require_once('uther.ezfw.php');
    ini_set('memory_limit', '32M');

    // the number of videos to load from dbObj by the iterator (next_video()) when the buffer is empty
    define('REFILL_COUNT', 10);

    abstract class store extends ezObj {
    
        static $storeCache = array();
        private $last_size_update = 0;

    /*    Database Definitions */
        static $_db_settings = array(
            // Table name
            'table'                =>    'stores',
            // Primary key field name
            'identifier_field'    =>    'tag',
            // Table fields
            'fields'            =>    array (
                'tag', 'name', 'class', 'path', 'description',
                'export_ratio', 'safe_ratio', 'lowest_import_weight',
                'max_usage_bytes', 'size', 'flags', 'manager_pid'
            )
        );

    /*    CareView Object Settings */
        static $_ez_settings = array(
            /*  Property name translations */
            'property_translations'    =>    array(
                'max_bytes'        =>    'max_usage_bytes',
                'full_ratio'    =>    'export_ratio',
                'lowest_import'    =>    'lowest_import_weight',
                'last_export_weight'=> 'lowest_import_weight'
            ),
            'object_translations'    =>    array(
            ),
            'auto_commit'        =>    5,    // seconds after change before auto commit, or TRUE to commit every change immediatly
            'auto_refresh'        =>  3,    // max seconds since last refresh before refresh on property read, or TRUE to refresh every read
            'commit_on_destruct'=>    true, // good idea to set this true if you have auto_commit set to a number, otherwize it may be
                                        // destructed before the auto_commit... which is ok if your program is still running, but if you
                                        // exited, your changes will not be saved.
        );

        protected function get__ez_settings() { return self::$_ez_settings; }
        protected function get__db_settings() { return self::$_db_settings; }

        static $all_flags = array ('recalc', 'managed', 'locked', 'moving', 'local');

        /***************************************************\
        \***************************************************/

        // return bytes free from max_bytes
        public function get_bytes_free () {    return $this->max_bytes - $this->size; }
        // return usage ratio (ratio of bytes used to max_bytes)
        public function get_usage () { return $this->size / $this->max_bytes; }
        // return free ratio (ratio of bytes free to max_bytes)
        public function get_free_ratio () { return 1 - $this->usage; } 
        // return export bytes (free bytes remaining before exporting)
        public function get_export_at_bytes () { return $this->max_bytes * $this->export_ratio; }
        // return safe bytes (free bytes remaining before exporting stops)
        public function get_safe_bytes () { return $this->max_bytes * $this->safe_ratio; }
        // return true if store free space ratio is below export level
        public function get_at_export_level () { return ($this->free_ratio < $this->export_ratio); }
        // return true if store free space ratio is at or above safe level
        public function get_at_safe_level () { return ($this->free_ratio >= $this->safe_ratio); }

        /***************************************************\
        \***************************************************/

        // Called on a video being exported by *this* store
        abstract public function weigh($video);

        // Called when a video is being exported by another store
        abstract public function score($video);

        /*****************************************\
        \*****************************************/

        public function manage () { 
            if (!$this->flags->managed) {
                /* store unmanaged... manage store */
                $this->manager_pid = getmypid();
                $this->flags->managed = true;
                $this->save();
                return true;
            }

            /*************************************\
            \**** store is flagged as managed ****/

            /* is this process already managing this store? */
            if ($this->manager_pid == getmypid()) return true;

            /* is the current manager still active? */
            if ($this->verify_manager()) {
                logger('Warning: Store ' . $this->tag . ' is already managed by pid ' . $this->manager_pid);
                return false;
            }

            /**********************************\
             * store was not cleanly shutdown *
            \* ...attempting recovery         */

            $this->recover();
            return true;
        }

        /*****************************************/

        public function verify_manager() {
            if (!$this->manager_pid) return false;
            if (!$this->flags->managed) return false;

            if (!posix_kill($this->manager_pid, 0)) {
                /* failed sending null signal to manager, pid is not running */
                logger('Warning: Previous manager is not running');
                return false;
            }

            if (!file_exists('/proc/' . $this->manager_pid . '/cmdline')) {
                /* no entry in proc */
                logger('Warning: No proc/#/cmdline for previous manager');
                return false;
            }
            
            $cmdargs = explode("\0", file_get_contents('/proc/' . $this->manager_pid . '/cmdline'));
            if (!is_array($cmdargs) || !count($cmdargs)) {
                /* something went wrong... no args? */
                logger('Warning: Failed to parse proc/#/cmdline for previous manager');
                return false;
            }

            if ($cmdargs[1] != "/usr/local/ezfw/bin/store_manager.php") {
                /* process is not a store manager process */
                logger('Warning: Previous manager pid is not a store manager ("' . $cmdargs[1] . '")');
                return false;
            }

            if ($cmdargs[7] != $this->tag) {
                /* process is managing some other store */
                logger('Warning: Previous manager pid is managing other store ("' . $cmdargs[7] . '")');
                return false;
            }

            /* the previous store manager is still valid */
            return true;
        }

        /*****************************************/

        private function recover () {
            /* immediatly update all videos locked by the previous manager to
             * be locked by THIS manager's pid.. this way, even if we bail out
             * right after this, the next recovery will be recovering what we
             * were unable to do this time through.  This is a rare case where
             * I'm not using the abstrations because I want to ensure this takes
             * place, i.e. won't get interrupted by a potential bug in the
             * abstration or classes' code.
             */

            $last_mgr = $this->manager_pid;
            $pid = getmypid();

            /* perform the update to mover_pid field before changing the manager_pid,
             * this way any incompleted recoveries from this process may still be
             * recovered by the next process which attempts to manage this store
             */

            dbObj::_exec('update', 
                video::$_db_settings['table'], 
                array('mover_pid'=>$pid), 
                dbObj::_exec('quoteInto',
                    'mover_pid = ?',
                    $last_mgr
                )
            );

            /* manage store */
            $this->manager_pid = $pid;
            $this->flags->managed = true;
            $this->save();

            /* recover videos from interrupted moves */
            $iter = new ezIterator('video', 'mover_pid = ?', array($this->manager_pid));
            while (($v = $iter->next()))
                $v->recover();

            /* recovered from improper shutdown... */

            return;
        }

        /*****************************************/

        public function unmanage ($force = false) {
            if (!$this->flags->managed) return true;
            if ($this->manager_pid != getmypid() && !$force)
                return false;
            $this->manager_pid = 0;
            $this->flags->managed = false;
            return true;
        }

        /*****************************************\
        \*****************************************/

        protected function pack_data (&$data) {
            $new_flags = $this->flagsObj->get_packed();
            if ($new_flags != $this->_get('flags', true))
                $data['flags'] = $new_flags;

            if ($data['size'] === true)
                $data['size'] = $this->get_current_size(true);

            return;
        }

        /*****************************************\
        \*****************************************/

        /***************************************
            override _get_changed so as not to
            clear size if set to true (realtime)
        */
        protected function _get_changed ($clear = false) {
            if ($clear && $this->_get('size') === true) {
                $ret = parent::_get_changed($clear);
                $this->_set('size', true);
                return $ret;
            }
            return parent::_get_changed($clear);
        }

        /****************************************/

        public function set_size ($size = true) {
            if ($size === true) {
                $this->_set('size', true);
                return $this->size;
            }
            if (!is_numeric($size))
                return $this->size;
            return $this->_set('size', $size);
        }

        /****************************************/

        public function get_size () {
            if (($size = $this->_get('size')) === true)
                return $this->current_size;
            return $size;
        }

        /****************************************/

        public function get_current_size ($force = false) {
            if ($force || ($this->last_size_update + 5) < time()) {
                $db = get_db_connection();
                $size = $db->fetchOne('select sum(size) from videos where store_tag = ?', array($this->tag));
                $old = $this->_get('size');

                if (!is_numeric($size)) return $old;
                if ($old != $size) {
                   // size discrepancy, set to refresh on save 
                    $this->size = true;
                }
                $this->last_size_update = time();
            }
            return $size;
        }

        /****************************************/

        public function abs_path () {
            /* check if path is empty first, otherwize,
             * for a store with a blank path, this
             * call would return the abs path of the
             * current working directory
             */
            return $this->path?realpath($this->path):'';
        }

        /****************************************/

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

        /***************************************************\
        \***************************************************/

        public function import (&$video) {
            if (!is_object($this)) {
                logger('ERROR: Cannot call store import in static context!', true);
                return false;
            }

            /****************************************
                if we were not called from video's
                set_store method, then call it now.
            */
            if ($video->moving_to != $this->tag) 
                return $video->set_store($this);
            /****************************************/


            logger("Importing video {$video->id} into store {$this->name}", true);

            // Get Filename and source & destination paths
            $source_dir = $video->store->path;
            $dest_dir = $this->path;
            $filename = $video->filename;

            debugger("--> Importing ($filename) from ($source_dir) to ($dest_dir)", 2);

            // source and destination filenames
            $src = $source_dir . '/' . $filename;
            $dest = $dest_dir . '/' . $filename;

            // check if source and destinations are the same
            if ($source_dir == $dest_dir) {
                logger("--> NOTE: Source and destination of video {$video->id} are the same!");
                $this->size = $this->get_current_size(true); //$video->size;
                $this->save();
                return true;
            }


            // Call to (potentially) abstract cache method for store to import video file...
            // if the store has no cache method, the builtin below is used instead...
            if (!$this->cache($src, $dest)) {
                logger("--> Warning: '{$this->tag}' Store failed to cache video file!");
                return false;
            }

            logger("--> Successfully stored video {$video->id} to '{$this->tag}' Store at location '$dest'");

            if ($this->size !== true)
                $this->size = $this->get_current_size(true); //$video->size;
            $this->save();

            // call to (potentially) abstract remove method for previous store to remove video file...
            // if the store has no cache method, the builtin below is used instead.
            $video->store->remove($src);

            return true;

        }

        /****************************************************************************************************/

        public function cache ($src, $dest) {
            // This is the builtin cache method for importing video files into local store...

            debugger("Attempting to cache (builtin) into '{$this->tag}' Store", 2);
            debugger("--> (src:$src => dest:$dest)", 3);

            /*    lets attempt a hard link (will only succeed if src
                and dest are local AND on the same file system)... */
            if ($this->try_link($src, $dest))
                return true;

            /*    linking failed, lets try moving it (will only succeed if src
                and dest are local OR both use the same stream wrapper)... */
            if ($this->try_move($src, $dest))
                return true;

            /* moving failed, lets try copying.... (will only succeed if src and
                destination are local OR both use the same stream wrapper)... */
            if ($this->try_copy($src, $dest))
                return true;
                    
            // Add more builtin file copy methods here...

            // store caching failed...
            logger("--> Warning: Failed to find a viable method for storing video into '{$this->tag}' Store (src:$src => dest:$dest)");
            return false;
        }

        /****************************************************************************************************/

        public function remove ($src) {
            // This is the builtin remove method for deleting exported video file from local store...

            debugger("Attempting to remove (builtin) from '{$this->tag}' Store", 2);
            debugger("--> (filename: $src)", 3);

            // check if file exists (file must be local OR using a supported stream wrapper)
            if (!file_exists($src)) {
                logger("--> Warning: When attempting to remove video from '{$this->tag}' Store: the file was not found (filename: $src)");
                return true;
            }

            // unlink (delete) the file (file must be local OR using a supported stream wrapper)
            if (!unlink($src)) {
                logger("--> Error: When attempting to remove video from '{$this->tag}' Store: unlink failed: " . last_error_message());
                return false;
            }

            // Video file successfully removed

            debugger("--> Video file successfully removed from '{$this->tag}' Store (filename: $src)", 2);
            return true;
        }

        /****************************************************************************************************/

        public function recalculate_weights ($force_all = false) {
            return 0;
            debugger("Updating weights for all videos in '{$this->tag}' Store " . ($force_all?'(Forced)':'where last calculated was more than 4 hours ago'), 2);

            $clause = 'store_tag = ? AND NOT find_in_set(?, flags)';
            $vals = array($this->tag, 'locked');

            if (!$force_all) {
                $clause .= " AND UNIX_TIMESTAMP(calculated) < ?";
                $vals[] = (time() - 60 * 60 * 4);
            }

            $iterator = new ezIterator('video', $clause, $vals);

            while (is_a($v = $iterator->next(), 'video')) {
                $weight = $this->weigh($v);
                debugger("--> '{$this->tag}' Store weighed video {$v->id} as $score", 4);
                $v->weight = $weight;
                $v->save();
                $c++;
            }

            debugger("--> '{$this->tag}' Store reweighed $c videos", 3);
            return $c;
        }

        public function recalculate_scores ($force_all = false) {
            return 0;
            debugger("Updating scores for all videos in '{$this->tag}' Store " . ($force_all?'(Forced)':'where recalc is requested'), 2);

            $clause = 'store_tag = ? AND NOT find_in_set(?, flags)';
            $vals = array($this->tag, 'locked');

            if (!$force_all) {
                $clause .= " AND find_in_set(?, flags)";
                $vals[] = 'recalc';
            }

            $iterator = new ezIterator('video', $clause, $vals);

            $c = 0;

            while (is_a($v = $iterator->next(), 'video')) {
                $score  = $this->score($v, false);
                debugger("--> '{$this->tag}' Store scored video {$v->id} as $score", 4);

                $v->score = $weight;
                $v->save();
                $c++;
            }

            debugger("--> '{$this->tag}' Store rescored $c videos", 3);
            return $c;
        }

        /****************************************************************************************************/

        // Start an iterator over all videos in this store ordered by weight
        public function weight_iterator ($high_low = false) {
            // if $high_low, then order highest => lowest
            // default is lowest => highest
            $order = ($high_low)?'desc':'asc';

            // calling to recalculate videos owned by this store which need updating

            /* only iterating by age now... */
//            $this->recalculate_weights();

            $iterator = new ezIterator('video', 'store_tag = ? AND NOT find_in_set(?, flags)', array($this->tag, 'locked'), 0, 'start ' . $order);

            return $iterator;
        }

        /****************************************************************************************************/

        public function clean () {
            $this->size = true;
            $this->save();
            $this->reload();
            if (!$this->at_export_level) return 0;

            $bytes_needed = $this->safe_bytes - $this->bytes_free;

            logger("Store '{$this->tag}' needs to clean " . number_format($bytes_needed), true);

            // load weight iterator, lowest to highest
            $iterator = $this->weight_iterator(false);

            /*********************************************
                we want to get the lowest weight we're
                about to export to set the "bell curve";
                the weight all scoring is reduced by to
                prevent importing video we're just going
                to export right away (or reimporting
                video back from the purge store
            */
            $vid = $iterator->current();
            $this->lowest_import_weight = (is_a($vid, 'video'))?$this->weigh($vid, false):0.0;
                /* the last param to weigh says not to apply a previous curve */

            $freed = $this->export_bytes($bytes_needed, NULL, $iterator);

            debugger("Store '{$this->tag}' exported " . number_format($freed) . ' bytes', 3);
            $this->size = $this->current_size;
            $this->save();
            debugger("--> new size is {$this->size}", 3);

            if ($this->at_safe_level)
                debugger("Store '{$this->tag}' has successfully exported " . number_format($freed) . " bytes to a safe level (".number_format($this->bytes_free).' free', 2);

            else if (!$this->at_export_level)
                logger("WARNING: Store '{$this->tag}' could not export below its safe level (" . number_format($bytes_needed - $freed) . " bytes above safe level)", true);

            else {
                $still_needed = $this->export_at_bytes - $this->bytes_free;
                logger("CRITIAL ERROR: Store '{$this->tag}' could not export below its critical level (" . number_format($still_needed) . " bytes above export level)!", true);
            }

            $this->save();

            return $freed;
        }

        /****************************************************************************************************/

        public function export_bytes ($free_bytes, $destStore = false, &$iterator = false) {
            logger("Exporting $free_bytes bytes from '{$this->tag}' Store...", true);

            if (!is_numeric($free_bytes)) {
                logger("Warning: '{$this->tag}' Store asked to export bytes, but no byte count given! (bytes:$free_bytes)");
                return 0;
            }

            // check stores passed... if none, then load all stores as potential candidates
            if (!$destStore) {
                debugger('--> No destination stores given, loading all local stores as potential candidates.', 3);
                $destStore = store::fetch_local();
            } else if (!is_array($destStore))
                $destStore = array($destStore);

            // check each destination store to verify that it is a store
            // or at least a store tag (it will load the store from it)
            foreach (array_keys($destStore) as $k) {
                if ($destStore[$k] && is_string($name = $destStore[$k]))
                    $destStore[$k] = store::fetch($name);
                if (!is_a($destStore[$k], 'store'))
                    logger("--> Warning: an element passed to export_bytes is not a store or a store tag! ('$name'), (element '$k')");
                else if ($destStore[$k]->tag == $this->tag)
                    debugger("--> Note: Removing Own store ({$this->tag}) from destination candidates.", 1);
                else
                    continue;
                unset($destStore[$k]);
            }

            /**********************************************
                if we weren't passed a video iterator,
                load one now, ordered by weight low->high
            */
            if (!$iterator)
                $iterator = $this->weight_iterator(false);

            $c = 0;
            $bytes = 0;
            while (($v = $iterator->next()) instanceOf video) {
                $size = $v->size;
                $id = $v->id;
                $weight = $v->_get('weight');

                debugger("--> Calling to export video {$id} (weight: {$weight}) (size: $size) (us: {$this->tag}, video: {$v->store_tag})...", 2);

                if (!$this->export($v, $destStore)) {
                    logger("--> Warning: Failed to export video {$v->id} from '{$this->tag}' Store!");
                    continue;
                }

                debugger("<---- Video $id has been exported", 3);

                $c++;
                $bytes += $size;

                debugger("--> exported $bytes bytes from $c videos so far...", 3);

                if ($weight < 0) $weight = 0;
                if ($weight > $this->lowest_import_weight) {
                    $this->lowest_import_weight = $weight;
                    $this->save();
                } else if ($this->lowest_import_weight < 0) {
                    $this->lowest_import_weight = 0;
                    $this->save();
                }

                if ($bytes >= $free_bytes) break;
            }

            debugger("--> Freed $bytes bytes from $c videos", 2);

            if ($bytes < $free_bytes)
                logger("--> NOTICE: Only freed $bytes of $free_bytes bytes requested!");

            return $bytes;
        }

        /****************************************************************************************************/

        public function export_each (&$list, $destStore = false) {
            $cb = array(
                is_object($this)?$this:self,
                'export'
            );

            // if we're not passed a list, call to export just the one
            if (!is_array($list))
                return call_user_func($cb, $list, $destStore);

            $c = 0;
            $total = count($list);
            debugger("Exporting a list of videos (count: $total)", 2);

            foreach ($list as $k => $v) {
                $ret = call_user_func($cb, $v, $destStore);
                if ($ret) {
                    debugger("--> Video {$v->id} exported successfully (index:$k)", 2);
                    $c++;
                    unset($list[$k]);
                } else
                    debugger("--> Warning: Video {$v->id} failed to export (index: $k)", 1);
            }

            debugger("--> Exported $c / $total videos", 2);
            return $c;
        }

        /****************************************************************************************************/

        public function export (&$video, $destStore = false) {
            /***************************************************
                If we were passed an array of videos to export
                than we loop over each and call to export for
                each of them individually
            */
            if (is_array($video))
                return is_object($this)?$this->export_each($video, $destStore):self::export_each($video, $destStore);
            /***********************************************/

            $ourtag = is_object($this)?$this->tag:'Unknown';
            logger("Exporting video {$video->id} from '$ourtag' store (filename:{$video->filename})...", true);

            $targets = self::order_candidates($video, $destStore);

            if (!count($targets)) {
                logger('--> Cannot export video, no candidates');
                return false;
            }

            // Attempt to export video to a target store in order of weight
            $i = 0;
            foreach ($targets as $tag => $weight) {
                $i++;
                debugger("--> Attempting to export video {$video->id} to '$tag' store (candidate #$i)", 2);
                
                $store = store::fetch($tag);
                if (!is_a($store, 'store')) {
                    logger("WARNING: Could not load store with tag '$tag'");
                    continue;
                }

                $bytes = $video->size;
                try {
                    if (!$video->set_store($store)) {
                        debugger("--> '$tag' Store failed to import!", 1);
                        continue;
                    }
                    $video->save();
                } catch (Exception $e) {
                    logger("ERROR: Could not move video {$video->id} to '$tag' Store: " . $e->getMessage(), true);
                    continue;
                }

                logger("Video {$video->id} successfully imported by '$tag' Store");
                if ($this->size !== true)
                    $this->size = $this->get_current_size(true); //$bytes;
                $this->save();

                return true;
            }

            logger("Warning: All target candidates failed to import video {$video->id}!");
            return false;
        }

        /****************************************************************************************************/

        public function order_candidates ($video, $destStore) {
            $targets = array();

            // if we're only passed one store, then check it and return
            if (!is_array($destStore)) {
                if (is_string($destStore))
                    $destStore = store::fetch($destStore);

                if (is_a($destStore, 'store')) {
                    $targets[$destStore->tag] = $destStore->weigh($video); 
                    /*
                    array(
                        'tag'=>$destStore->tag,
                        'weight'=>$destStore->weigh($video),
                        'store'=>$destStore
                    );
                    */
                }
                return $targets;
            }

            $targets = array();

            // weigh video for each destination candidate
            foreach ($destStore as $store) {
                // check if candidate is a store, or at least a store tag
                if (is_string($name = $store))
                    $store = store::fetch($name, true);

                if (!is_a($store, 'store')) {
                    logger("--> Warning: an element passed to export is not a store or a store tag! ('$name')");
                     continue;
                }

                $tag = $store->tag;

                // check that candidate is not the same as the source
                if ($video->store_tag == $tag) {
                    debugger("--> NOTE: an export store candidate is the same as the source store ($tag)", 1);
                    continue;
                }

                $score = $store->score($video);
                $targets[$tag] = $score;
//                $stores[$tag] = $store;
//                $scores[$tag] = $store->weigh($video);

                debugger("--> '$tag' store scored video {$video->id} as {$score}", 3);
            }

            // no destination candidates passed
            if (!count($targets)) {
                logger("--> Warning: Cannot export video {$video->id} from '{$video->store_tag}' store:  No destination candidates!");
                return array();
            }

            // reverse sort the weights (highest to lowest)
            arsort($targets, SORT_NUMERIC);

            // reset array pointer
            reset($targets);

            $name = key($targets);
            $score = current($targets);

            debugger("--> Store '$name' claimed highest score for video {$video->id} with $score", 2);

            return $targets;

            // order stores into $targets by weight
            /*
            while ($cur = each($weights))
                $targets[] = array(
                    'tag'=>$cur['key'],
                    'weight'=>$cur['value'],
                    'store'=>$stores[$cur['key']]
                );

            debugger("--> Store '{$targets[0]['tag']}' claimed highest weight for video {$video->id} with {$targets[0]['weight']}", 2);

            // return ordered list of target stores
            return $targets;
            */
        }

        /****************************************************************************************************/

        private function try_link ($src, $target) { //, $target) {
            debugger('Attemping to import via link...', 3);
            if (!@link($src, $target)) {
                logger('--> Warning: link import failed : ' . last_error_message(), true);
                return false;
            }

            debugger('--> link import successful', 3);
            return true;
        }

        /****************************************************************************************************/

        private function try_move ($src, $target) {
            // lets move the file to our location
            debugger('Attempting to import via move...', 3);
            if (!@rename($src, $target)) {
                logger('--> Warning: move import failed: ' . last_error_message(), true);
                return false;
            }

            debugger('--> move import successful', 3);
            return true;
        }

        /****************************************************************************************************/

        private function try_copy ($src, $target) {
            // lets copy the file to the target
            debugger('Attempting to import via copy...', 3);
            if (!@copy($src, $target)) {
                logger('--> Warning: copy import failed: ' . last_error_message(), true);
                return false;
            }

            debugger('--> copy import successful', 3);
            return true;
        }

        /****************************************************************************************************/

        static function &fetch ($tag, $refresh = false) { 
            if ($refresh || !isset(self::$storeCache[$tag])) {
                $cls = $tag . 'Store';
                if (!class_exists($cls, false)) {
                    require_or_throw("stores/$tag.php", "Failed loading class $cls source from 'stores/$tag.php'", 1);
                }                   
                self::$storeCache[$tag] = new $cls($tag);
            }
            return self::$storeCache[$tag];
        }

        /****************************************************************************************************/

        static function fetch_local ($clause = '', $values = NULL, $limit = NULL, $order = '', $refresh = false) {
            $clause .= ($clause?' AND ':' ') . 'find_in_set(?, flags)';
            if (!is_array($values))
                $values = isset($values)?array($values):array();
            $values[] = 'local';
            return self::fetch_all($clause, $values, $limit, $order, $refresh);
        }

        static function fetch_all ($clause = '', $values = NULL, $limit = NULL, $order = '', $refresh = false) {
            $stores = array();
            require_once('stores/null.php');
            foreach (parent::fetch_all('nullStore', $clause, $values, $limit, $order) as $tag => $store) {
                $stores[$tag] =& self::fetch($tag, $refresh);
            }

            return $stores;
        }

        public function video_count() {
            return dbObj::fetch_count('video', 'store_tag = ?', array($this->tag));
        }

    }


    $lwd = getcwd();
    if (@chdir(abs_path('classes', 'stores'))) {
        foreach (glob('*.php') as $fn)
            require_once($fn);
        chdir($lwd);
    }


