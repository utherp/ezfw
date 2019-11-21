<?php
    require_once('uther.ezfw.php');

    abstract class service extends ezObj {
    /*  Database Definitions */
        static $_db_settings = array(
            // Table name
            'table'             =>  'services',
            // Primary key field name
            'identifier_field'  =>  'tag',
            // Table fields
            'fields'            =>  array (
                'tag', 'name', 'description',
            )
        );

    /*  CareView Object Settings */
        static $_ez_settings = array(
            /*  Property name translations */
            'property_translations' =>  array(
            ),
            'object_translations'   =>  array(
            )
        );

        protected function get__ez_settings() { return self::$_ez_settings; }
        protected function get__db_settings() { return self::$_db_settings; }

        /***************************************************\
        \***************************************************/

        abstract public function weigh_event($event);
        abstract public function weigh_state($state);

        static $serviceCache = array();

        static function fetch ($tag) {
            if (!isset(self::$serviceCache[$tag])) {
                $cls = $tag .'Service';
                if (!class_exists($cls, false)) {
                    require_or_throw("services/$tag.php", "Failed loading class {$tag}Service source from 'services/$tag.php'", 1);
                }
                self::$serviceCache[$tag] = new $cls($tag);
            }
            return self::$serviceCache[$tag];
        }
    }

