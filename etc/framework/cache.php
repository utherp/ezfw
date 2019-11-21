<?php 
    require_once('uther.ezfw.php');
    load_definitions('CACHE');

    $GLOBALS['_cached_objects_'] = array();

    function &load_object($name, $max_age = DEFAULT_MAX_CACHE_AGE) {
        if (!is_bool($max_age) && !is_numeric($max_age)) {
            // a safe override
            $max_age = 1800;
        }
        /***********************************************
         * as of 3.8.1 r334:
         * max_age parameter now has dual function
         * if boolean true:  forces reload of the object from the server
         * if boolean false: reloads only if it doesn't exist
         * if numeric:  forces reload if the cached object's 
         * timestamp is older than the $max_age seconds
         * NOTE: passing 0 is equivilant to forcing reload
         *       passing -1 is the same as passing false
         *
         * default for max_age has been changed from false
         * to 1800 (30 minutes).  By default, now objects
         * are always refreshed if older than 30 minutes
         *
         *      --Stephen Ecker (2010-06-09)
         */

        $cache =& $GLOBALS['_cached_objects_'];

        $reload = false;
        if (is_bool($max_age)) {
            $reload = $max_age;
            $max_age = 86400; // oldest cache allowed is 1 day
        } else if ($max_age === 0) {
            $reload = true;
        } else if (!is_numeric($max_age)) {
            $max_age = 1800;
        }

        $cache_filename = abs_path('objects', $name . '.ser');
        if (!file_exists($cache_filename)) {
            $filetime = 0;
            $reload = true;
        } else {
            clear_stat_cache(true, $cache_filename);
            $filetime = filemtime($cache_filename);
        }

        $oldest = (time() - $max_age);

        // is file age within max_age
        if (!$reload && $filetime >= $oldest) {
            // if the object is loaded, but differs from the file's timestamp, clear it
            if (isset($cache[$name]) && $filetime != $cache[$name]['timestamp']) {
                unset($cache[$name]);
            }
    
            // at this point, the cached is valid, if its not loaded, load it, then return
            if (!isset($cache[$name])) {
                $cache[$name] = array(
                    'timestamp' => $filetime,
                    'data' => unserialize(file_get_contents($cache_filename))
                );
            }
            return $cache[$name]['data'];
        }

        // at this point, the file cache is invalid, reload from server...
        $obj = fetch_object($name);

        if (NULL === $obj) {
            // We failed to get a valid response from the server
            clear_stat_cache(true, $cache_filename);
            if (file_exists($cache_filename)) {
                // Use previously cached object and hope it is correct
                $cache[$name] = array(
                    'timestamp' => $filetime,
                    'data' => unserialize(file_get_contents($cache_filename))
                );
            } else {
                // If the file does not exist, we don't even want to use a loaded version
                // as the object has probably been invalidataed by the server
                unset($cache[$name]);
            }
        } else {
            // We got a valid response from the server
            if (false === $obj) {
                // The object response was "false" meaning this object does not exist
                // (e.g. there is no longer a patient assigned to this node's room).
                // Clear the cache and delete the cache file
                remove_object($name);
                unset($cache[$name]);
            } else {
                // The object from the server looks valid, so store it
                $cache[$name] = array(
                    'timestamp' => time(),
                    'data' => $obj
                );
                write_object($name, $cache[$name]['data']);
            }
        }

        if (!isset($cache[$name])) return false;

        return $cache[$name]['data'];
    }
        
    function load_this_room($force = false) {
        return load_object('room', $force);
    }

    function object_cache_filename ($name) {
        return abs_path(CACHE_PATH, $name . '.ser');
    }

    function call_cache_triggers ($type, $action, $old_filename = "", $new_filename = "") {
        $dir = abs_path(OBJECT_TRIGGERS_PATH, $type);
        if (!is_dir($dir)) return true;
        $lwd = getcwd();
        if (!@chdir($dir)) return false;
        foreach (glob('*') as $trigger) {
            if (is_executable($trigger)) {
                logger("calling trigger '$dir/$trigger'...", true);
                exec("./$trigger \"$type\" \"$action\" \"$old_filename\" \"$new_filename\"", $out, $ret);
                if ($ret) {
                    logger("Warning: trigger returned code $ret, output is:\n\t" . implode("\n\t", $out), true);
                }
            }
        }
        chdir($lwd);
        return true;
    }

    function write_object($name, $data) {
        if (is_object($data)) $data = serialize($data);
        if (!strlen($data)) return false;

        $filename = object_cache_filename($name);
        $old_filename = false;
        $old_data = false;
        if (file_exists($filename)) {
            // An object of this name already exists in cache
            // we'll do a compare and call change triggers if
            // nessessary
            $old_filename = $filename . '.last';
            $old_data = file_get_contents($filename);
            @rename($filename, $old_filename);
        }
        
        file_put_contents($filename, $data);
        @chmod($filename, 0666);

        if (!$old_filename || !$old_data) {
            // old object did not exist, calling 'set' triggers
            call_cache_triggers($name, 'set', "", $filename);
        } else if ($old_data && ($old_data != $data)) {
            // object data has changed
            call_cache_triggers($name, 'change', $old_filename, $filename);
        }
            
        if ($old_filename) @unlink($old_filename);

        return true;

    }

    function remove_object ($name) {
        $filename = object_cache_filename($name);
        if (!file_exists($filename)) return true;
        call_cache_triggers($name, 'remove', $filename, "");
        @unlink($filename);
        return true;
    }

    function write_local_room($room) {
        if (is_object($room)) $room = serialize($room);
        write_object('room', $room);
    }

    // Get an object from the server
    // Returns:
    //   null   - There was some failure communicating with the server
    //   false  - The server's response indicates the object does not exist
    //            (e.g. there is no patient assigned to this node's room)
    //   object - The requested object from the server
    function fetch_object($name) {
        $resp = @file_get_contents(
                        'http://' . SERVER_HOST . '.' . DOMAIN_NAME . SERVER_WEB_ROOT .
                        '/setup/get_object.php' .
                        '?name=' . $name .
                        '&mac=' . get_mac()
                    );
        if (!isset($resp) || (is_bool($resp) && !$resp)) {
            // We failed to get a valid response from the server
            return null;
        }
        $reg = unserialize($resp);
        if (!isset($reg['objects']) || !isset($reg['objects'][$name])) {
            // We got a response from server, but we didn't get the object
            // so there was some kind of error
            return null;
        }
        // We got a valid response from the server, so return it
        // Note that the server's response could be the object or the bool false
        return $reg['objects'][$name];
    }

    function fetch_local_room() {
        $reg = unserialize(
                    file_get_contents(
                        'http://' . SERVER_HOST .'.'.DOMAIN_NAME. SERVER_WEB_ROOT . 
                        '/setup/is_node_registered.php' .
                        '?mac=' . get_mac()
                    )
                );
        if (isset($reg['room'])) $room = $reg['room']; else $room = new room();
        return $room; 
    }

    function talking_to_server() {
        switch (false) {
            case(isset($_REQUEST['key'])):
            case(defined('AUTH_KEY')):
            case(crypt(get_mac() . AUTH_KEY, $_REQUEST['key']) == $_REQUEST['key']):
            case(isset($_SERVER)):
            case(isset($_SERVER['REMOTE_ADDR'])):
            case(defined('SERVER_HOST')):
            case(defined('DOMAIN_NAME')):
                return ($_SERVER['REMOTE_ADDR'] == gethostbyname(SERVER_HOST));
            default:
                return ($_SERVER['REMOTE_ADDR'] == gethostbyname(SERVER_HOST.'.'.DOMAIN_NAME));
        }
    }
?>
