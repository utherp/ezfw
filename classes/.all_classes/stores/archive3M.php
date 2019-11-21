<?php
    require_once('uther.ezfw.php');
    require_once('archive.php');

    define('EVENTS_PER_QUERY', 50);

    /****************************************************
     * The archive's 3 month store takes the pure score
     * and adjusts based on age to make video's within
     * 3 months old more important. it also adjusts for
     * space so it will hold videos if it has the space
     */

    class archive3MStore extends archiveStore {

        public function store_name () { return 'archive3M'; }
    
        /*****************************************************************/

        public function adjust_for_age ($hours, $score) {
            $adj = 1 - ($hours / (24 * 91));
            if ($adj < 0) $adj = 0;

            $score += $adj;
            return $score;
        }

        /*****************************************************************/

        static function fetch ($id) { return new archive3MStore($id); }

    }

