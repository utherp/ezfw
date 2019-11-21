<?php
    require_once('uther.ezfw.php');
    abstract class dbObj  extends jsObj {

        /*****************************************\
        \*****************************************/

        protected $loaded = false;
        private $informal_properties = array();

//      abstract protected function _get_db_settings();

        protected function get__db_settings() { return static::$_db_settings; }

        /*****************************************\
        \*****************************************/

        public function get_identifier() {
            $settings = $this->_db_settings;
            return $this->data[$settings['identifier_field']];
        }

        public function identifier_field() {
            $settings = $this->_db_settings;
            return $settings['identifier_field'];
        }

        /*****************************************\
        \*****************************************/

        public function set_identifier($id, $loading = false) {
            $settings = $this->_db_settings;
            if ($loading) return $this->data[$settings['identifier_field']] = $id;
                
            $oldid = $this->get_identifier();
            if ($oldid && $oldid == $id) return $id;

            $this->data[$settings['identifier_field']] = $id;
            if (!$loading)
                $this->load();

            return $id;
        }

        /*****************************************\
        \*****************************************/

        public function is_loaded() { return $this->loaded; }
        public function is_loading(){ return ($this->loaded===-1); }

        /*****************************************\
        \*****************************************/

        static function _exec () {
            $args = func_get_args();
            $cmd = array_shift($args);
            $force = false;
            do {
                try {
                    $db = get_db_connection($force);
                    $ret = call_user_func_array(array($db, $cmd), $args);
                } catch (Exception $e) {
                
                    if ($e->getCode() == 'HY000') {
                        if ($force) {
                            logger("ERROR: Connection could not be reestablished!", true);
                            throw $e;
                        }
                        logger("WARNING: DB Connection dropped, reconnecting...", true);
                        $force = true;
                        continue;
                    }
                    logger("ERROR: Unknown error occurred when calling '$cmd' (".$e->getCode()."): " . $e->getMessage() . "\n", true);
                }
                break;
            } while (true);

            return $ret;
        }

        public function load ($vals = false) {
            $settings = $this->_db_settings;
            if (!is_array($vals)) {
                $this->data[$settings['identifier_field']] = $vals;
                $vals = false;
            }

            if ($vals === false) {
                if (!($vals = $this->get_identifier())) return false;
                $vals = array($settings['identifier_field'] => $vals);
            }

            $clause = '1=1';
            $qvals = array();

            foreach ($settings['fields'] as $f)
                if (array_key_exists($f, $vals)) {
                    $qvals[$f] = $vals[$f];
                    unset($vals[$f]);
                }

            foreach ($qvals as $key => $value) {
                if ($value === NULL || $value == 'NULL') {
                    $clause .= " AND $key IS NULL";
                } else {
                    $clause .= ' AND ' . self::_exec('quoteInto', $key . ' = ?', $value);
                }
            }

            $query = 'select ' . implode(', ', $settings['fields']) .
                                            ' from ' . $settings['table'] . 
                                            ' where ' . $clause;



            $result = self::_exec('fetchRow', $query);

            if (!$result) return false;

            $this->loaded = -1;

            $this->_unpack_values($result);

            foreach ($result as $key => $value) {
                if ($key === $settings['identifier_field']) 
                    $this->set_identifier($value, true);
                else if (isset($value))
                    $this->$key = $value;
            }

            return $this->loaded = true;
        }

        /*****************************************\
        \*****************************************/

        public function validate_lock() {
            if (!$this->is_loaded()) return true;
            $settings = $this->_db_settings;
            $uid = isset($_SESSION['_login_'])?$_SESSION['_login_']->get_user()->get_id():getmypid();

            self::_exec('delete', 'locks', 'locked < subtime(now(), "00:05:00")');

            $l = self::_exec('fetchRow', 'select i, class, id, uid, locked from locks where ' . 
                self::_exec('quoteInto', 'class = ?', $settings['table']) . ' AND ' .
                self::_exec('quoteInto', 'id = ?', $this->get_identifier()));

            if (!$l) return 0;
            if ($uid != $l['uid']) return false;

            self::_exec('update', 'locks', array('locked' => NULL), 'i = ' . $l['i']); 

            return true;
        }

        public function remove_lock() {
            if (!$this->is_loaded()) return true;
            $settings = $this->_db_settings;
            $uid = isset($_SESSION['_login_'])?$_SESSION['_login_']->get_user()->get_id():getmypid();

            self::_exec('delete', 'locks',
                self::_exec('quoteInto', 'class = ?', $settings['table']) . ' AND ' . 
                self::_exec('quoteInto', 'id = ?', $this->get_identifier()) . ' AND ' .
                self::_exec('quoteInto', 'uid = ?', $uid)
            );

            return true;
        }

        public function get_lock() {
            if (!$this->is_loaded()) return true;
            $settings = $this->_db_settings;

            $v = $this->validate_lock();

            if (is_bool($v)) return $v;

            $uid = isset($_SESSION['_login_'])?$_SESSION['_login_']->get_user()->get_id():getmypid();

            self::_exec('insert', 'locks',
                array(
                    'class'=>$settings['table'],
                    'id'=>$this->get_identifier(),
                    'uid'=>$uid
                )
            );

            return $this->validate_lock();
        }

        public function informal_property_names() { return $this->informal_properties; }

        /*****************************************\
        \*****************************************/

        protected function _unpack_values (&$data) {
            $settings = $this->_db_settings;

            if (isset($data[$settings['informal_field']])) {
                $informal = unserialize($data[$settings['informal_field']]);
                unset($data[$settings['informal_field']]);
                if (is_array($informal)) foreach ($informal as $k => $v) {
                    if (array_key_exists($k, $data)) {
                        $this->informal_properties[] = "$k.informal";
                        $data["$k.informal"] = $v;
                    } else {
                        $this->informal_properties[] = $k;
                        $data[$k] = $v;
                    }
                }
            } 

            if (method_exists($this, 'unpack_data'))
                $this->unpack_data($data);

            return $data;
        }

        /*****************************************\
        \*****************************************/

        private function _pack_values (&$data) {
            $settings = $this->_db_settings;

            if (is_array($settings['read_only_properties']))
                foreach($settings['read_only_properties'] as $p)
                    if (array_key_exists($p, $data)) unset($data[$p]);

            if (method_exists($this, 'pack_data'))
                $this->pack_data($data);
            
            $fields = array_flip($settings['fields']);
            $informal = array();
            foreach (array_keys($data) as $key)
                if (!isset($fields[$key])) {
                    if ($data[$key] !== NULL)
                        $informal[$key] = $data[$key];
                    unset($data[$key]);
                }

            if (isset($settings['informal_field']) && count($informal))
                $data[$settings['informal_field']] = serialize($informal);

            return;
        }

        /*****************************************\
        \*****************************************/

        public function save() {
            $settings = $this->_db_settings;

            if (!$this->loaded && !$this->load()) 
                return $this->create();

            $id = $this->get_identifier();

            $vals = $this->_get_changed(true);
            $this->_pack_values($vals);

            // nothing writable has changed
            if (!count($vals)) return true;

            $ret = self::_exec('update', 
                $settings['table'],
                $vals,
                self::_exec('quoteInto', $settings['identifier_field'] . ' = ?', $id)
            );

            if ($ret) {
                $this->audit_instance('Updated');
                foreach ($vals as $n => $v) {
                    $this->data[$n] = $v;
                    $this->_revert($n);
                }
            } else
                $this->audit_instance('Update Failed');

            return $ret;
        }

        /*****************************************\
        \*****************************************/

        private function create($settings = false) {
            if (!$settings)
                $settings = $this->_db_settings;

            $id = $this->get_identifier();

            $vals = $this->_get_changed();

            $this->_pack_values($vals);

            $ret = self::_exec('insert', $settings['table'], $vals);

            if (!$ret) {
                $this->audit_instance('Creation Failed');
                return $ret;
            }

            $this->loaded = true;

            $this->set_identifier(self::_exec('lastInsertId'), true);
            $this->audit_instance('Created');

            foreach (array_keys($vals) as $k) {
                $this->data[$k] = $vals[$k];
                $this->_revert($k);
            }

            return $ret;
        }
    
        /*****************************************\
        \*****************************************/

        public function delete () {
            $settings = $this->_db_settings;

            if (!$this->is_loaded()) return false;
            $id = $this->get_identifier();

            $ret = self::_exec('delete', 
                $settings['table'],
                self::_exec('quoteInto', $settings['identifier_field'] . ' = ?', $id)
            );

            $this->audit_instance(($ret?'Deletion Failed':'Deleted'));

            return $ret;
        }

        private function _build_query ($clause = '', $limit = NULL, $order = '', $settings = false) {
            if (!$settings) $settings = $this->_db_settings;
            
            $cls = get_class($this);

            $query = 'select ' . implode(', ', $settings['fields']) .
                    ' from ' . $settings['table'];

            if ($clause) {
                $p = strpos($clause, 'where');
                if ($p === false || $p > 1) $query .= ' where';
                $query .= ' ' .$clause;
            }

            if ($order) {
                $p = strpos($order, 'order by');
                if ($p === false || $p > 1) $query .= ' order by';
                $query .= ' ' . $order;
            } else if (isset($settings['order_field']))
                $query .= " order by {$settings['order_field']}";
            
            if (isset($limit)) {
                $p = strpos($limit, 'limit');
                if ($p === false || $p > 1) $query .= ' limit';
                $query .= ' ' . $limit;
            }

            return $query;
        }

        public function count_all ($clause = '', $values = NULL, $limit = NULL, $order = '') {
            $objs = array();
            $settings = $this->_db_settings;

            $query = $this->_build_query($clause, $limit, $order, $settings);

            $query = preg_replace('/^.*? from/', '', $query);
            $query = 'select count(' . $settings['identifier_field'] . ') as num from ' . $query;

            $data = get_db_connection()->fetchOne($query, $values);

            return $data;
        }
        static function fetch_count ($type, $clause = '', $values = NULL, $limit = NULL, $order = '') {
            $obj = new $type();
            return $obj->count_all($clause, $values, $limit, $order);
        }

        static function fetch_all ($type, $clause = '', $values = NULL, $limit = NULL, $order = '') {
            $obj = new $type();
            return $obj->load_all($clause, $values, $limit, $order);
        }

        public function load_all ($clause = '', $values = NULL, $limit = NULL, $order = '') {
            $objs = array();
            $settings = $this->_db_settings;

            $query = $this->_build_query($clause, $limit, $order, $settings);

            $data = get_db_connection()->fetchAll($query, $values);

            $cls = get_class($this);

            foreach ($data as $d)
                $objs[$d[$settings['identifier_field']]] = new $cls($d, true);
    
            return $objs;
        }

    }

