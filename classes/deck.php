<?php
require_once('uther.ezfw.php');
ini_set('memory_limit', '32M');

class deck extends ezObj {

/*    Database Definitions */
    static $_db_settings = [
        // Table name
        'table' => 'decks',
        // Primary key field name
        'identifier_field' => 'id',
        // Table columns
        'fields' => [ 'id', 'player_id', 'name' ]
    ];

/*    ezObj Settings */
    static $_ez_settings = [
        /*  Property default values */
        'property_defaults' => [
            'name' => 'my deck'
        ],
        'defaults_on_create'   =>true, // are the defaults use when a new object is created
        'defaults_on_missing'  =>true, // are the defaults used when properties are not set
        'read_only_properties' => [ ], // list of props which cannot be changed
        /*  Property name translations */
        'property_translations' => [
            // this is where you might put what someone might call another property
            'title' => 'name' // the "title" of a deck is equivilant to its name
        ],
        'object_translations' => [
            // object translations return objects when properties are accessed 
            'player' => [
                'cache' => true,        // cache the objects
                'field' => 'player_id', // the property used as the 'player' object's id
                'class' => 'player'     // the classname of the object
            ]
        ],
        'auto_commit'        => false,   // seconds after change before auto commit, or TRUE to commit every change immediatly
        'auto_refresh'       => false,   // max seconds since last refresh before refresh on property read, or TRUE to refresh every read
        'commit_on_destruct' => true,    // good idea to set this true if you have auto_commit set to a number, otherwize it may be
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

    protected function get_cards() {
        // this creates an objectList of all the cards in the deck
        $list = $this->_cache('cardsList');
        if (!$list) {
            $cards = card::fetch_all('decks like ?', [ "%:{$this->identifier};%" ]);
            $list = new objectList('cards', 'card');
            $list->_set_list($cards);
            $this->_cache('cardsList', $list);
        }
        return $list;
    }
}

