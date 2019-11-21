<?php
    require_once('uther.ezfw.php');

    class patient {

        static $table = 'patients';
        static $fields = array(
                'id'         => 'id',
                'passwd'     => 'password',
                'hospital_id'=> 'hospital_id',
                'doctor_id'  => 'doctor_id',
                'room_id'    => 'room_id',
                'name'       => 'name',
                'admitted'   => 'admitted',
                'services'   => 'services',
            );

        /**********************************\
        \**********************************/

        protected $id = '';
        protected $passwd = '';
        protected $hospital_id = '';
        protected $doctors = array();
        protected $doctor_id = array();
        protected $room = '';
        protected $room_id = '';
        protected $loaded = false;
        protected $name;
        protected $changed = false;
        protected $services = array();
        protected $admitted;
        protected $video_enabled = true;

        /**********************************\
        \**********************************/

        function __sleep() {
            return array('id', 'admitted', 'passwd', 'hospital_id', 'doctors', 'room_id', 'loaded', 'name', 'changed', 'services', 'video_enabled');
        }
        function __wakeup() {
            get_db_connection(); 
            $this->doctors[0] = new user();
            $this->room = new room();
        }
        function __construct ($info = '') {
            get_db_connection();
            if ($info != '') {
                if (is_array($info)) {
                    $this->load($info);
                } else {
                    $this->load(array('id' => $info));
                }
/*              $this->doctors[0] = new user();
                if ($this->get_doctor_id(0)) {
                    $this->doctors[0]->load(array('uid' => $this->get_doctor_id(0)));
                }
*/          }
        }

        /**********************************\
        \**********************************/

        public function is_loaded()         { return $this->loaded; }
        public function get_id()            { return $this->id; }
        public function get_hospital_id()   { return $this->hospital_id; }
        public function get_room_id()       { return $this->room_id; }
        public function get_passwd()        { return $this->passwd; }
        public function number_of_doctors() { return count($this->doctor_id); }
        public function get_doctor_id($i = 0) { return $this->doctor_id[$i]; }
        public function get_name()          { return $this->name; }
        public function get_admission_time(){ return $this->admitted; }
        public function privacy_mode()      { return $this->get_room()->privacy_mode(); }
        public function check_privacy_mode(){ return $this->get_room()->check_privacy_mode(); }
        public function get_room($force = false)            {
            if (!is_object($this->room) || $force)
                $this->room = load_object('room',$force);
            if (!is_object($this->room)) {
                $this->room = new room();
            }
            return $this->room;
        }
        public function get_doctor($i = 0)      {
            if (!isset($this->doctor_id[$i])) return false;
            if (!isset($this->doctors[$i]) || !is_object($this->doctors[$i])) {
                $this->doctors[$i] = new user();
            }
            if (!$this->doctors[$i]->is_loaded()) {
                if ($this->get_doctor_id($i)) {
                    $this->doctors[$i]->load(array('uid' => $this->get_doctor_id($i)));
                }
            }
            return $this->doctors[$i];
        }

        /**********************************\
        \**********************************/

        public function set_id($id)         { $this->id = $id; }

        public function set_name($name)     {
            $this->name = $name;
            $this->changed = true;
        }
        public function set_hospital_id($id) {
            $this->hospital_id = $id;
            $this->changed = true;
        }
        public function set_room($room_id) {
            $this->room_id = $room_id;
            $this->changed = true;
            $this->room = new room();
        }
        public function set_doctor($did, $i = 0) {
            $this->doctor_id[$i] = $did;
            $this->doctors[$i] = new user();
            $this->changed = true;
        }
        public function set_passwd($passwd) {
            $this->passwd = $passwd;
            $this->changed = true;
        }


        /**********************************\
        \**********************************/

        static function all_fields() {
            $f = '';
            foreach (self::$fields as $d) {
                if ($f != '') $f .= ', ';
                $f .= $d;
            }
            return $f;
        }
        /**********************************\
        \**********************************/

        public function load ($info = '') {
            $query =    'select ' . self::all_fields() .
                        ' from '  . self::$table . ' where ';

            if (is_array($info)) {
                // Enumerate for all possible set field vars
                foreach (self::$fields as $field_name => $column) {
                    if (isset($info[$field_name])) {
                        $query .= $GLOBALS['__db__']->quoteInto($column . ' = ?', $info[$field_name]);
                    }
                }

            } else if (($info == '') && ($this->id != '')) {
                $query .= $GLOBALS['__db__']->quoteInto('id = ?', $this->id);
            } else {
                return false;
            }

            $patient = $GLOBALS['__db__']->fetchRow($query);

            if (count($patient)) {
                return $this->set_data($patient);
            } else {
                return false;
            }

            return false;
        }
        /**********************************\
        \**********************************/

        private function set_data($info) {
            foreach (self::$fields as $field_name => $column)
                if (isset($info[$column]) && property_exists($this, $field_name) && !is_array($this->$field_name)) $this->$field_name = $info[$column];

            if ($this->id != '') {
                $this->loaded = true;
            } else {
                $this->loaded = false;
            }
            $this->doctor_id = array();
            if (isset($info['doctor_id'])) $this->parse_doctors($info['doctor_id']);

            if (isset($info['services'])) $this->parse_services($info['services']);

            if (!is_array($this->doctor_id)) $this->doctor_id = array($this->doctor_id);
            return $this->loaded;

        }
        /**********************************\
        \**********************************/
        public function validate_service($name) {
            if (!isset($this->services[$name])) return false;
            if ($this->services[$name] == -1) return true;
            if ($this->services[$name] < time()) {
                $this->remove_service($name);
                $this->save();
                return false;
            }
            return true;
        }
        public function service_expires($name) {
            if (!$this->validate_service($name)) return false;
            return $this->services[$name];
        }
        public function add_service($name, $expiry) {
            $this->set_service($name, $expiry);
            $this->changed = true;
        }
        public function remove_service($name) {
            $this->set_service($name);
            $this->changed = true;
        }
        private function set_service($name, $expiry = false) {
            if ($expiry === false) unset($this->services[$name]);
            else $this->services[$name] = $expiry;
        }
        public function expire_services() {
            $now = time();
            foreach ($this->services as $n => $e) 
                if ($e < $now) $this->remove_service($n);

            $this->save();
        }
        private function parse_services($data) {
            foreach (explode('::', $data) as $s) {
                if ($s == '') break;
                list($name, $expiry) = explode('|', $s);
                $this->set_service($name, $expiry);
            }
            $this->expire_services();
        }
        private function pack_services() {
            $data = '';
            foreach ($this->services as $n => $e) {
                if ($data != '') $data .= '::';
                $data .= $n . '|' . $e;
            }
            return $data;
        }   
        /**********************************\
        \**********************************/
        private function parse_doctors($data) {
            foreach (explode('::', $data) as $d) 
                if ($d != '') array_push($this->doctor_id, $d);
        }
        private function pack_doctors() {
            return implode('::', $this->doctor_id);
        }
        
        /**********************************\
        \**********************************/

        private function clear_data() {
            foreach (array_keys(self::$fields) as $k) 
                $this->$k = '';
            $this->loaded = false;
        }
        /**********************************\
        \**********************************/

        public function load_with($info) {
            if ($this->loaded) $this->clear_data();
            if (is_array($info))
                return $this->load($info);
            else
                return $this->load(array('id' => $info));
        }
                
        /**********************************\
        \**********************************/
        public function visitor_login($passwd) { 
            @session_start();
            $this->load_with(array('passwd' => "password('$passwd')"));

            if ($this->loaded) {
                $_SESSION['patient_id'] = $this->id;
                $_SESSION['hospital_id'] = $this->hospital_id;
                $_SESSION['room_id'] = ($this->room->is_loaded())?$this->room->get_id():'';
                fns_log::log('Visitor log in', $this->id);
                return true;
            } 
            return false;
        }

        /**********************************\
        \**********************************/

        /**********************************\
        \**********************************/

        public function has_room() {
            if ($this->get_room()->is_loaded()) {
                return true;
            } else {
                return false;
            }
        }

        /**********************************\
        \**********************************/

        public function save() {
            if (defined('NODE_TYPE') && NODE_TYPE != 'server') return true;
            if (!$this->changed) return true;
            $update_fields = array( 'password'  => $this->get_passwd(),
                                    'doctor_id' => $this->pack_doctors(),
                                    'room_id'   => $this->get_room_id(),
                                    'name'      => $this->get_name(),
                                    'services'  => $this->pack_services()
                            );
            $rows = $GLOBALS['__db__']->update(self::$table, $update_fields, $GLOBALS['__db__']->quoteInto('id = ?', $this->get_id()));
            if ($rows) return true;
            return false;
        }

        /**********************************\
        \**********************************/

        static function check_visitor_permission() {
            @session_start();
            if (!isset($_SESSION['patient_id']))
                return false;
            else
                return true;
        }
        /**********************************\
        \**********************************/
        static function get_all_patients() {
            get_db_connection();
            $patients = array();
            foreach ($GLOBALS['__db__']->fetchAll('select ' . self::$fields['id'] . ' from ' . self::$table) as $p) {
                array_push($patients, new patient($p));
            }
            return $patients;
        }
        /**********************************\
        \**********************************/
        public function get_all_notes() {
            $notes = array();
            foreach ($GLOBALS['__db__']->fetchAll('select ' . note::$fields['id'] .
                                        ' from ' . note::$table .
                                        ' where ' .
                                            note::$fields['patient_id'] . ' = ' . $this->get_id() .
                                        ' order by ' . note::$fields['posted'] . ' desc') as $n) {

                array_push($notes, new note($n));
            }
            return $notes;
        }
        /**********************************\
        \**********************************/

    }

    /*************************************************\
    \*************************************************/

?>
