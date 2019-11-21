<?php
require_once('uther.ezfw.php');
ini_set('memory_limit', '32M');

class user extends ezObj {

/*    Database Definitions */
    static $_db_settings = [
        // Table name
        'table' => 'users',
        // Primary key field name
        'identifier_field' => 'id',
        // Table columns
        'fields' => [ 'id', 'name', 'faction_id', 'pass' ]
    ];

/*    ezObj Settings */
    static $_ez_settings = [
        /*  Property default values */
        'property_defaults' => [
        ],
        'defaults_on_create'   =>true, // are the defaults use when a new object is created
        'defaults_on_missing'  =>true, // are the defaults used when properties are not set
        'read_only_properties' => [ ], // list of props which cannot be changed
        /*  Property name translations */
        'property_translations' => [
            // this is where you might put what someone might call another property
        ],
        'object_translations' => [
            'faction' => [
                'cache' => true,
                'field' => 'faction_id',
                'class' => 'faction'
             ]
        ],
        'auto_commit'        =>    5,    // seconds after change before auto commit, or TRUE to commit every change immediatly
        'auto_refresh'        =>  3,     // max seconds since last refresh before refresh on property read, or TRUE to refresh every read
        'commit_on_destruct'=>    true,  // good idea to set this true if you have auto_commit set to a number, otherwize it may be
                                         // destructed before the auto_commit... which is ok if your program is still running, but if you
                                         // exited, your changes will not be saved.
    ];

    /***************************************************\
    \***************************************************/

    protected function unpack_data (&$data) {
        // use this method to manipulate any
        // property data after it's loaded from
        // the database, but before it's set on
        // the object
        return;
    }

    protected function pack_data (&$data) {
        // use this method to manipulate any
        // property data before it gets written
        // to the database
        return;
    }

    /*****************************************\
    \*****************************************/

    protected function set_pass($pass) {
        return password_hash($pass, PASSWORD_BCRYPT, [ 'salt' => '75303fda89ab681e3e05f2' ]);
    }

    protected function get_props() {
        // this creates an objectList of all the cards in the deck
        if (!($list = $this->_cache('propList'))) {
            $props = prop::fetch_all('user_id = ?', $this->identifier);
            $list = new objectList('props', 'prop');
            $list->_set_list($props);
            $this->_cache('propList', $list);
        }
        return $list;
    }
}

