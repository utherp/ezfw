<?php
    require_once('uther.ezfw.php');
    class fns_log {
    
        /**
         * log($op, $owner)
         * log who, when, what, to database table tblaudit. 
         * @param $op string
         * @param $owner array
         * @return  none
         */

        static public function log($op, $owner) {
            logger("LOG: user($owner), '$op'", true);
            return;
        }
    }
?>
