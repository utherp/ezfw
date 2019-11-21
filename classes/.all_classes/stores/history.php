<?php
    require_once('uther.ezfw.php');

    define('HISTORY_HOURS', 12000); // 500 days (as much as possible)

    class historyStore extends store {

        /*****************************************
            Force always recalculate all videos
            for the history store, as the weight is
            based on the age, the weight is always
            needing recalculating.
        */

        public function recalculate_weights () {
            return parent::recalculate_weights(true);
        }

        public function weigh ($video) {
            /*********************************************
                The weight of a video in the history
                store is always based on the video's age.
                the weight returned is <= 1.
            */
    
            $hours_old = ($video->age / 60 / 60);
            $weight = 10 - (($hours_old / HISTORY_HOURS) * 10);

            if ($weight < -1) $weight = -1;
            
            return $weight;
        }

        public function score ($video) {
            return $this->weigh($video);
            /********************************************
                For the history store, the weigh method
                is the default for all videos.  The score
                method, being called for videos NOT in 
                the history store, adjusts the weight based
                on the weight of the last video exported to
                prevent reimported videos which were exported
                due to lack of sufficient disk space to 
                account for the total number of hours configured
                for the history store...(*phew*)
            */
            $weight = $this->weigh($video);

            if ($weight > 0 && $this->lowest_import_weight > 0) {
                debugger('History: (video ' . $video->id . ')  Applying lowest import weight "curve" of ' . $this->lowest_import_weight . ' to ' . $weight, 5);
                $weight -= $this->lowest_import_weight;
                $this->lowest_import_weight -= ($this->lowest_import_weight * 0.01);
                if ($this->lowest_import_weight < 0.03) $this->lowest_import_weight = 0;
                $this->save();
                 
                if ($weight < 0)
                    logger('History: NOTE: denied import of a video within the configured time due to lowest import weight curve of ' . $this->lowest_import_weight . '!', true);
            }

            if ($weight < -1) $weight = -1;
            return $weight;
        }

        static function fetch ($id) { return new historyStore(); }

    }

