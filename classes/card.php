<?php
require_once('uther.ezfw.php');
ini_set('memory_limit', '32M');

class card extends ezObj {

/*    Database Definitions */
    static $_db_settings = [
        // Table name
        'table' => 'cards',
        // Primary key field name
        'identifier_field' => 'id',
        // Table columns
        'fields' => [ 'id', 'decks', 'name' ]
    ];

/*    ezObj Settings */
    static $_ez_settings = [
        /*  Property default values */
        'property_defaults' => [
            'name' => 'unknown card'
        ],
        'defaults_on_create'   =>true, // are the defaults use when a new object is created
        'defaults_on_missing'  =>true, // are the defaults used when properties are not set
        'read_only_properties' => [ ], // list of props which cannot be changed
        /*  Property name translations */
        'property_translations' => [
            // this is where you might put what someone might call another property
            'title' => 'name' // the "title" of a card might be equivilant to its name
        ],
        'object_translations' => [
            // object translations return objects when properties are accessed 
        ],
        'auto_commit'        =>   false, // seconds after change before auto commit, or TRUE to commit every change immediatly
        'auto_refresh'       =>   false, // max seconds since last refresh before refresh on property read, or TRUE to refresh every read
        'commit_on_destruct' =>   false, // good idea to set this true if you have auto_commit set to a number, otherwize it may be
                                         // destructed before the auto_commit... which is ok if your program is still running, but if you
                                         // exited, your changes will not be saved.
    ];

    /***************************************************\
    \***************************************************/

    private function get_packer() {
        static $packer = false;
        if (!$packer) { 
            $packer = new packedList(';:', ':', ';');
        }
        return $packer;
    }

    protected function unpack_data (&$data) {
        // use this method to manipulate any
        // property data after it's loaded from
        // the database, but before it's set on
        // the object
        $list = self::get_packer()->unpack($data['decks']);
        $val = false;
        $this->_cache('decks', $val);
        if (!$list) $list = [];
        $this->_cache('decks', $list);
        return;
    }

    protected function pack_data (&$data) {
        // use this method to manipulate any
        // property data before it gets written
        // to the database

        $deckList = $this->_cache('decksList');
        if ($deckList) {
            $data['decks'] = self::get_packer()->pack($deckList->keys());
        }
        return;
    }

    /*****************************************\
    \*****************************************/

    protected function get_decks() {
        $list = $this->_cache('decksList');
        //print"list is " . var_export($list, true) ."\n";
//        print "{$this->identifier} getting list...\n";
        if (!$list) {
//            print "no list\n";
            $decks = $this->_cache('decks');
            if (!$decks) $decks = [];
            //print"decks is " . print_r($decks, true);
            $list = new objectList('decksList', 'deck');
            $list->_set_list($decks);
            //print"setting decksList to " . print_r($list, true);
            $val = false;
            $this->_cache('decksList', $val);
            $this->_cache('decksList', $list);
//            print_r($list);
        }
        return $list;
    }
}

