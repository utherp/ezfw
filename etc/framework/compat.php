<?php 
    /* here is where we'll put all php compatability wrappers */

    if (version_compare(PHP_VERSION, '5.3.0', '<')) {
      function clear_stat_cache ($clear_realpath_cache = false, $filename = NULL) {
        return clearstatcache();
      }
    } else {
      function clear_stat_cache ($clear_realpath_cache = NULL, $filename = NULL) {
        return clearstatcache($clear_realpath_cache, $filename);
      }
    }


