<?php
    require_once('uther.ezfw.php');
    require_once('archive9M.php');

    define('EVENTS_PER_QUERY', 50);

    /*********************************************
     * The archive's ! year store does not adjust
     * for the video's age, only the lowest import
     * weight, which is the score of the lowest
     * video exported.  Weighing and scoring use
     * the same method.
     */

    class archivePermStore extends archive9MStore { 

        public function store_name () { return 'archivePerm'; }
    
        /*****************************************************************/

        public function adjust_for_age ($hours, $score) {
            // overlap the 9 month store slightly
            if ($hours < (24 * 254)) return $score;

            $hours -= (24 * 92);
            return parent::adjust_for_age($hours, $score);
        }

        /*****************************************************************/

        static function fetch ($id) { return new archivePermStore($id); }

    }

