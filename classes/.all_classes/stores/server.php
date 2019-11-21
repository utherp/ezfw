<?php
    require_once('uther.ezfw.php');

    class serverStore extends store {
        public function weigh ($video) { return -10; }
        public function score ($video) { return -10; }
    }
