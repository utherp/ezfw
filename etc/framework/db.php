<?php
    require_once('uther.ezfw.php');

    /***********************************************
     * Connect and return database connection
     ***********************************************/
    function get_db_connection($force=false) {
        if (!isset($GLOBALS['__db__']) || $force) 
            $GLOBALS['__db__'] = Zend_Db::factory('PDO_MYSQL', parse_ini_file(abs_path('etc/db.ini')));
        return $GLOBALS['__db__'];
    }
    /***********************************************/
    /***********************************************/

