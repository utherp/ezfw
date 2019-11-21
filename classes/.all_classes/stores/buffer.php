<?php
    require_once('store.php');

    class bufferStore extends store {

        public function store_name () { return 'buffer'; }
        protected function store_path () { return abs_path(BUFFER_PATH); }

        public function weigh ($video) {
            // score 1 for current video only, all others are available to export
            if ($video->filename == read_flag(CURRENT_VIDEO_FILE))
                return 1;

            // if not current, score negative based on age to ensure older
            // videos get moved out first.
            return 0 - ($video->age / 60);
        }

        public function score ($video) {
            // Buffer store never imports videos, only copies from input device
            return -1;
        }

    }
