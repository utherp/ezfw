<?php
    require_once('uther.ezfw.php');

    if (!class_exists('CVException', false)) {
        /* this is to add the 'previous' functionality of php 5.3 to earlier versions using this abstraction */
        if (!method_exists(Exception, 'getPrevious')) {
            class CVException extends Exception {
                private $previous = NULL;
                public function __construct($message, $code = 0, Exception $previous = null) {
                    parent::__construct($message, $code);
                    if (!is_null($previous)) {
                        $this->previous = $previous;
                    }
                }
                public function getPrevious() {
                    return $this->previous;
                }
            }
        } else {
            class CVException extends Exception { }
        }
    }

    function require_or_throw($filename, $msg = '', $code = 0, $previous = NULL) {
        $ret = @include $filename;
        if ($ret === false) {
            // The previous exception argument requires PHP 5.3
            /* 2010-11-29: I added CVException, which implements 'previous' if it
             * doesn't already exist... a bit excessive, maybe ...but I figure at
             * least its a good place to start developing new ezfw exceptions
             * if we wish to later.  --Stephen
             */
            throw new CVException($msg, $code, $previous);
        }
        return $ret;
    }

    function __autoload($classname) {
        $filename = $classname;
        $retry = false;
        try { 
            require_or_throw($filename . '.php', "Failed __autoload for class '$classname'");
        } catch (Exception $e) {
            $retry = true;
        }
        if ($retry) {
            $filename = str_replace('_', '/', $filename);
            require_or_throw($filename . '.php', "Failed __autoload for class '$classname'");
        }
        return;
    }                                                                                   

    function include_paths($paths) {
        $formatted_paths = '';
        foreach (explode(':', $paths) as $p) {
            if (strpos($p, '/') !== 0) $p = abs_path($p);
            $formatted_paths .= PATH_SEPARATOR . $p;
        }
        ini_set('include_path', ini_get('include_path') . $formatted_paths);
    }

    function load_definitions($context) {
        global $system_config;
        if (!isset($system_config[$context])) {
            if (!file_exists(abs_path(INI_PATH, strtolower($context) . '.ini'))) return false;
            $system_config[$context] = parse_ini_file(abs_path(INI_PATH, strtolower($context) . '.ini'));
        }

        if (!isset($system_config[$context]))
            return false;

        if (is_array($system_config[$context]))
            foreach ($system_config[$context] as $n => $v) @define(strtoupper($n), $v, true);
        else
            @define(strtoupper($context), $system_config[$context], true);

        return true;
    }

    function load_methods($context) {
        global $system_config;
        if (isset($system_config[$context]) &&
            isset($system_config[$context]['METHODS_FILE'])) {
            require_once($system_config[$context]['METHODS_FILE']);
            return true;
        }
        return false;
    }

    function load_libs($name = false) {
        $files_loaded = 0;
        $libdir = abs_path('lib');

        if (!is_dir($libdir)) {
            logger("ERROR: Unable to locate lib root! '$libdir'", true);
            return false;
        }

        // strip '.php' from the end of $name
        if (preg_match('/\.php$/i', $name)) {
            $name = substr($name, 0, -4);
        }

        // is there a directory under lib with this name?
        $loaddir = is_dir("$libdir/$name");

        // load the file named lib/$name.php if exists
        if (file_exists("$libdir/$name.php")) {
            require_once("$libdir/$name.php");
            $files_loaded++;
        }

        // load all php files under directory lib/$name/
        if ($loaddir) {
            $lwd = getcwd();
            chdir("$libdir/$name");
            foreach (glob('*.php') as $lib) {
                require_once($lib);
                $files_loaded++;
            }
            chdir($lwd);
        }

        // if no files were loaded, generate an error in the log
        if (!$files_loaded) {
            logger("ERROR: Failed loading lib '$name'", true);
        }

        return $files_loaded;
    }


