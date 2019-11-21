<?php
    require_once('uther.ezfw.php');
    require_once('archive3M.php');

    define('EVENTS_PER_QUERY', 50);

    /****************************************************
     * The archive's 6 month store takes the pure score
     * and adjusts based on age to make video's within
     * 6 months old more important. it also adjusts for
     * space so it will hold videos if it has the space
     */

    class archive6MStore extends archive3MStore {

        /*****************************************************************/

        public function store_name () { return 'archive6M'; }
    
        /*****************************************************************/

        public function adjust_for_age ($hours, $score) {
            // overlap the 3 month store slightly
            if ($hours < (24 * 80)) return $score;
            $hours -= (24 * 80);

            return parent::adjust_for_age($hours, $score);
        }

        /*****************************************************************/

        static function fetch ($id) { return new archive6MStore($id); }

        /*****************************************************************/

    }

