<?php
    require_once('uther.ezfw.php');

    class nullStore extends store {
        public function weigh ($v) { return 0; }
        public function score ($v) { return 0; }
    }
