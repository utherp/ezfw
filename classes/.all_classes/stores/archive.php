<?php
    require_once('uther.ezfw.php');

    define('EVENTS_PER_QUERY', 50);

    class archiveStore extends store {

        public function store_name () { return 'archive'; }
        protected function store_path () { return abs_path(ARCHIVE_PATH); }
    
        /*****************************************************************/

        public function export (&$video, $dest = NULL) {
            sleep(5);
            return parent::export($video, $dest);
        }

        public function pure_score ($video) {
            /***************************************************
                this function is for getting the pure score for
                a video in this store.  it does no adjustments,
                it only returns the collective scoring of the
                events tied to a video.
            */

            $score = 0;
            // Sum scores of all events...
            $events = $video->iterate_events();
            while ($ev = $events->next())
                $score += $ev->weight;

            // Sum scores of all states...
            $states = $video->iterate_states();
            while ($st = $states->next())
                $score += $st->weight;

            return $score;
        }

        /*****************************************************************/

        public function score ($video) {
            return 0;
            // scoring just adjusts the weight by the lowest import weight
            $score = $this->weigh($video);
            $score = $this->adjust_for_space($score);
            $score = $this->adjust_for_import($video, $score);
            return $score;
        }

        /*****************************************************************/

        public function weigh ($video) {
            return 0;
            $score = $this->pure_score($video);
            $score = $this->adjust_for_age(($video->age / 60 / 60), $score);
            return $score;
        }

        /*****************************************************************/

        protected function adjust_for_import ($video, $score) {
            if ($this->lowest_import_weight > 0) {
                $score -= $this->lowest_import_weight;
                /* adding this so the lowest_import_weight will drop overtime as it
                 * adjusts each scoring by it */
                $this->lowest_import_weight -= ($this->lowest_import_weight * 0.01);
                if ($this->lowest_import_weight < 0.03) $this->lowest_import_weight = 0;
                $this->save();
            }
            return $score;
        }

        /*****************************************************************/

        protected function adjust_for_age ($hours, $score) { return $score; }

        /*****************************************************************/

        protected function adjust_for_space ($score) {
            // code to adjust score based on storage space available

            if ($this->at_safe_level)
                $score += (($this->free_ratio - $this->export_ratio) / 100);

            return $score;
        }

        /*****************************************************************/

        static function fetch ($id) { return new archiveStore($id); }

    }

