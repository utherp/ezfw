<?php
    require_once('uther.ezfw.php');

    /*******************************************************************
        The Purge Store

            This store weighs all video to the defined 'PURGE_STORE_WEIGHT'
        floating point value.  When no other stores weigh a video above    the
        PURGE_STORE_WEIGHT, it goes here.  The purge store has a small amount
        of storage space.  You can consider it like your desktop 'recycle bin'.

        The overrides are 'export', 'score' and 'weigh'. 
        (see below for more details)
    */


    /* The weight the purge store gives to all videos */
    if (!defined('PURGE_STORE_SCORE')) define('PURGE_STORE_SCORE', 0.000001);


    class purgeStore extends store {

        /**************************************
            Purge store always weighs at the
            PURGE_STORE_SCORE definition
        */
        public function score ($video) {
            return floatval(PURGE_STORE_SCORE);
        }

        public function weigh ($video) {
            // weigh negative based on age...
            return 0 - ($video->age / 60);
        }


        /******************************************************
            The export_bytes method override does essentially
            the same as store::export_bytes() with the following differences:

            * It may export to ANY store, not just stores
              with the 'local' flag set.
            * If no other store scores it higher than
              PURGE_STORE_WEIGHT, then the lowest weighed
              videos are removed completely.
        */

        public function export (&$video, $destStore = NULL) {

            if (is_array($video))
                return is_object($this)?$this->export_each($video, $destStore):self::export_each($video, $destStore);

            /************************************************************************************
             * Due to tuning purposes, I'm removing the code for attempting to rescore the
             * videos being exported from the purge store... this needs more work...
             *  -- Stephen 2010-08-05
             */

            if (is_array($destStore)) {
                /***********************************************************
                 * I'm still allowing moving from purge to somewhere else
                 * but a multi-element array suggests all were loaded 
                 */
                if (count($destStore) == 1) $destStore = array_pop($destStore);
                else $destStore = NULL;
            }

            if ($destStore) {
    
                $targets = self::order_candidates($video, $destStore);
                logger("Exporting video {$video->id} from the purge store...", true);
    
                $valid_targets = array();
    
                foreach ($targets as $tag => $weight) {
                    if ($weight <= 0) {
                        debugger("--> Store '$tag' weighted video < 0 ($weight)", 2);
                        continue;
                    }
                    $valid_targets[] = $tag;
                }
    
                if (count($valid_targets)) {
                    for ($i = 1; $i <= count($valid_targets); $i++) {
                        $tag = $valid_targets[$i-1];
                        $weight = $targets[$tag];
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
    
                        logger("Video {$video->id} was reimported by store '$tag'!");
                        return true;
                    }
                    logger("Video {$video->id} was not imported by any other store!");
                } else
                    logger("All export candidates scored video {$video->id} <= 0!");
            }


            logger("Deleting video {$video->id}!", true);
            $video->store_tag = NULL; //); //_set('store_tag', NULL);
//            unset($video->store); //', false);
            $video->delete($this->path);
            unset($video);

            return true;
        }

    }


