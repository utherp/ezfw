<?php
    require_once('uther.ezfw.php');
    require_once('archive6M.php');

    define('EVENTS_PER_QUERY', 50);

    /*********************************************
     * The archive's ! year store does not adjust
     * for the video's age, only the lowest import
     * weight, which is the score of the lowest
     * video exported.  Weighing and scoring use
     * the same method.
     */

    class archive9MStore extends archive6MStore { 

        public function store_name () { return 'archive9M'; }
    
        /*****************************************************************/

        public function adjust_for_age ($hours, $score) {
            // overlap the 6 month store slightly
            if ($hours < (24 * 160)) return $score;

            $hours -= (24 * 91);
            return parent::adjust_for_age($hours, $score);
        }

        /*****************************************************************/

        static function fetch ($id) { return new archive9MStore($id); }

    }

