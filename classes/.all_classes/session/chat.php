<?php
    require_once('uther.ezfw.php');
    class session_chat {

    /*******************************************\
    \*******************************************/

        static $tables = array (
                          'event'   => 'chat_events',
                        'visitor'   => 'chat_visitors',
                        'channel'   => 'chat_channels',
                    );

        static $fields = array (
                        'visitor'   =>  array(
                                           'id' => 'id',
                                         'name' => 'name',
                                     'channels' => 'channels',
                                   'authorized' => 'authorized',
                                'last_activity' => 'last_activity',
                            ),
                        'event'     =>  array(
                                           'id' => 'id',
                                    'source_id' => 'source_id',
                                  'source_name' => 'source_name',
                               'destination_id' => 'destination_id',
                             'destination_name' => 'destination_name',
                                       'action' => 'action',
                                      'message' => 'message',
                                       'posted' => 'posted',
                                   'patient_id' => 'patient_id',
                            ),
                        'channel'   =>  array(
                                           'id' => 'id',
                                         'name' => 'name',
                                   'creator_id' => 'creator_id',
                                 'creator_name' => 'creator_name',
                                'creation_time' => 'creation_time',
                                'never_expires' => 'never_expires',
                                'last_activity' => 'last_activity',
                            ),
                    );

    /*******************************************\
    \*******************************************/

        protected $id;
        protected $name;
        protected $authorized = 0;
        protected $local = false;
        protected $channels = array();
        protected $index = array(-1 => 0);
        protected $DEBUG = false;

    /********************************************\
    \********************************************/

        static function session() {
            return isset($_SESSION['_chat_'])?$_SESSION['_chat_']:false;
        }

    /********************************************\
    \********************************************/

        public function session_exists() {
            $sess = self::session();
            if ($sess === false)    return false;       // is there a session
            if (!is_object($sess))  return false;       // is it an object
            if (get_class($sess) !== get_class($this)) 
                                    return false;       //is it a chat object
            return true;
        }

    /********************************************\
    \********************************************/

        function __construct() {
            $this->debugger('constructing...');
            load_definitions('FLAGS');
            get_db_connection();
            self::expire_zombies();

            $this->debugger('checking session...');
            //Checking If Session Exists
            if ($this->session_exists()) {
                $session = self::session();
                if ($session->logged_in())
                    return; //session exists, return
            }

            load_object('patient', true);
            $this->debugger('checking local');
            //Checking if client is the patient (from localhost)
            if ($this->are_we_local()) {
                //Yes we're local:
                $this->debugger('local');
                if (!$this->patient_exists()) {
                    $this->debugger("no patient in room");
                    // We no longer allow patients to initiate PatientView to another patient
                    //self::show_login(
                    //        'Your account has not been created yet,' .
                    //        'but you can still login to talk with another patient if have given you their passcode.'.
                    //        'If you wish to chat with another patient, enter your name and their passcode above.'
                    //    );

                    /* there is no need to part and delete visitor 
                     * here as the user has not yet logged in
                     */
//                        $this->part(-1, 'discharge');
//                        $this->delete_visitor();
                    $this->destroy_session();
                    self::show_not_allowed();
                } elseif (!$this->verify_name()) {
                    // Name is bad -- errors handled by verify_name function
                } else {
                    $this->expire_old_patients();
                    if (!$this->register_me()) {
                        self::show_login('An unknown registration error has occurred, please try again.');
                    } elseif (!$this->join(-1)) {
                        self::show_login('Couldn\'t Join you to the main channel');
                    }
                }
                // The patient has successfully logged in, so track an event for it
                // We don't care about the return value or any failures (besides debugging)
                // Note: So long as this session is valid, we'll never reach here again because
                //       the constructor short-circuits above.
                $resp = @file_get_contents('http://server.cv-internal.com/ezfw/service/hospital_events.php' .
                    '?category=service&event=accessed&service=PatientView&patient=' . $this->get_patient()->get_id());
                $this->debugger("patient resp=$resp");
            } else {
                //Not local, logging in:
                $this->debugger('not local');
                switch (false) {
                    case($this->check_credentials()):
                        self::show_login('No name was supplied. Please login.');
                        break;
                    case($this->patient_exists()):
                        self::show_login('Sorry, the patient has not been checked in yet, please try again later');
                        break;
                    case($this->register_me()):
                        self::show_login('An unknown registration error has occurred, please try again.');
                        break;
                }
                // The visitor has successfully logged in, so track an event for it
                // We don't care about the return value or any failures (besides debugging)
                // Note: So long as this session is valid, we'll never reach here again because
                //       the constructor short-circuits above.
                $resp = @file_get_contents('http://server.cv-internal.com/ezfw/service/hospital_events.php' .
                    '?category=service&event=visitor_accessed&service=PatientView&patient=' . $this->get_patient()->get_id() .
                    '&pv_username=' . urlencode($this->get_name()));
                $this->debugger("visitor resp=$resp");
            }
            //Completed Login
            $this->debugger('done constructing');
        }
    
    /*******************************************\
    \*******************************************/

        public function check_credentials() {
            // Posted Name?
            if (!$this->name && isset($_GET['name'])) {
                $this->name = $_GET['name'];
                $this->name = strtr($_GET['name'], ' ', '_');
                return true;
            }
            return false;
        }

    /*******************************************\
    \*******************************************/

        public function are_we_local() {
            if (!isset($_SERVER['SERVER_ADDR'])) return false;
            if (!isset($_SERVER['REMOTE_ADDR'])) return false;
            $this->local = ($_SERVER['SERVER_ADDR'] == $_SERVER['REMOTE_ADDR'])?true:false;
            return $this->local;
        }

    /*******************************************\
    \*******************************************/

        public function get_patient()   {
            $this->get_room();
            if (!is_object($this->patient))
                $this->patient = load_object('patient', true);
            return $this->patient;
        }

    /*******************************************\
    \*******************************************/

        private function get_room($force = false) {
            if (!is_object($this->room) || $force)
                $this->room = load_object(LOCATION_TYPE, $force);
            return $this->room;
        }

    /*******************************************\
    \*******************************************/

        public function is_authorized()  { return (intval($this->authorized) == 2)?true:false; }

    /*******************************************\
    \*******************************************/

        public function get_name()      { return $this->name; }
        public function get_id()        { return $this->id; }
        public function get_hash()      { return $this->hash; }
        public function get_index($channel = -1)    {
            return (isset($this->index[$channel]))?$this->index[$channel]:0;
        }
        private function set_index($i, $channel = -1) {
            $this->index[$channel] = $i;
        }

    /*******************************************\
    \*******************************************/
    
        public function privacy_mode()  {
            return (flag_raised(PRIVACY_FLAG));
        }

    /*******************************************\
    \*******************************************/
        function __sleep() {
            return array('id', 'name', 'index', 'channels', 'authorized', 'local', 'privacy');
        }

        function __wakeup() {
            get_db_connection();
            $this->room = load_object(LOCATION_TYPE);
            // Since our last run in this session, the patient could have been removed from this room
            if ($this->patient_exists()) {
                if ($this->are_we_local()) {
                    $this->verify_name();
                }
            } else {
                // There is no longer a patient in this room
                // If the user has pressed the PatientView button, show the not allowed page.
                // If the patient's chat i/f is up, we can't tell it to shut down,
                // so showing the not allowed page has no effect.
                $this->debugger("Woke up to no patient in room");
                $this->part(-1, 'discharge');
                $this->delete_visitor();
                $this->destroy_session();
                self::show_not_allowed();
            }
        }

    /*******************************************\
    \*******************************************/

        public function verify_name() {
            if ($this->get_patient()->get_name()) $this->name = $this->get_patient()->get_name();
            if ($this->name) {
                $this->name = strtr($this->name, ' ', '_');
                return true;
            }
            if (!isset($_POST['patient_name'])) {
                $this->patient_name_form();
                exit;
            }

            $name = $_POST['patient_name'];

            $this->get_patient()->set_name($name);
            require_once('registration_controls.php');
            write_local_room($this->get_room());

            $this->name = $name;
            return true;
        }

    /*******************************************\
    \*******************************************/

        public function patient_exists() {
            // Does Patient Exist with given Hospital Id?
            //if (!$this->get_patient()->is_loaded()) {

            if (!is_object($this->get_patient())) return false;
            if ($this->get_patient()->is_loaded()) return true;
            $this->patient = $this->get_room(true)->get_patient();
            return $this->get_patient()->is_loaded();
            
            //} else {
            //  return true;
            //}
            //return $this->get_patient()->is_loaded();
        }

    /*******************************************\
    \*******************************************/

        public function debugger($msg) {
            if ($this->DEBUG) file_put_contents('/tmp/chat.log', $msg."\n", FILE_APPEND);
        }

    /*******************************************\
    \*******************************************/

        public function register_me() {

            $vars = array(  'name'       => $this->get_name(),
                            'authorized' => '0',
                        );
            $id = false;

            if ($this->local) {
                $vars[self::$fields['visitor']['authorized']] = '-1';
                $id = $GLOBALS['__db__']->fetchOne('select ' .
                                                self::$fields['visitor']['id'] .
                                            ' from ' .
                                                self::$tables['visitor'] .
                                            ' where ' .
                                                self::$fields['visitor']['authorized'] . ' = -1'
                                    );
            }
            if ($id) {
                $this->id = $id;
                $this->join(-1);
            } else {
                $request = $GLOBALS['__db__']->insert(self::$tables['visitor'], $vars);
                if (!$request) {
                    self::show_login('An Unknown Registration Error Occurred!');
                    exit;
                }
                $this->id = $GLOBALS['__db__']->fetchOne('select last_insert_id()');
                $this->join(-1);
            }

            $this->set_session();
            return true;
        }

    /*******************************************\
    \*******************************************/

        static function patient_name_form() {
            web_html::header('PatientView', '<link rel="stylesheet" href="'.web_path(STR_CSS).'" />', true);
            fns_output::show_login_header(CV_FULLNAME, 'PatientView');
?>          <center>
                <form name=chat_login action='<?=$_SERVER['REQUEST_URI']?>' method='POST'>
                    <table>
                        <tr>
                            <td>Enter Your Name:</td>
                            <td><input type=text name='patient_name' id='chat_name' /></td>
                        </tr><tr>
                            <td colspan=2><input type=submit value='Go' /></td>
                        </tr>
                    </table>
                </form>
            </center>
            <script type='text/javascript'>
                document.body.setAttribute('onLoad', 'document.getElementById("chat_name").focus();');
            </script>
            <div align="center">
                <h3 style="color: Blue;">
                    Please Enter A Name For Yourself...
                </h3>
            </div>
<?          web_html::footer();
            exit;
        }

    /*******************************************\
    \*******************************************/

        static function show_login($error_info) {
            /*
            $proto = 'http://';
            if (isset($_SERVER['HTTP_X_FORWARDED_PROTOCOL'])) {
                if ($_SERVER['HTTP_X_FORWARDED_PROTOCOL'] == 'ON') {
                    $proto = 'https://';
                }
            }
    
            if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
                $host = $proto . $_SERVER['HTTP_X_FORWARDED_HOST'] . '/ezfw/ptv/patientview.php';
            } else {
                $host = $proto . SERVER_HOST . '.' . DOMAIN_NAME . SERVER_WEB_ROOT . '/ptv/patientview.php';
            }
            */
            $host = server_web_path('ptv', 'patientview.php');
            $host .= '?msg='.$error_info;
            ?><html>
                <body onload='document.location = "<?=$host?>";'>
                <script type='text/javascript'>
                    window.status = 'EVENT::NoAcct';
                </script>
                </body>
            </html><?
            exit;
        }

    /*******************************************\
    \*******************************************/

        static function show_not_allowed() {
            switch ($GLOBALS['__CONTENT_TYPE__']) {
                case ('xml'):
                    xml::header();
                    xml::write(array('response'=>array('kicked'=>'discharge')));
                    break;
                case ('javascript'):
                    print "\ntop.location = 'patientview.php?" . time() . "';\n";
                    break;
                default:
?>
            <html>
            <head>
                <title>PatientView Chat Not Allowed</title>
                <script type='text/javascript'>
                    window.status = 'EVENT::NoAcct';
                </script>
                <style type="text/css">
                    html, body {
                        background-image: url(../img/blubk.jpg);
                        background-attachment: fixed;
                        text-align: center;
                        min-width: 500px;
                        font-family: Arial;
                        font-size: 11pt;
                    }
                    #content {
                        width: 90%;
                        margin-left: auto;
                        margin-right: auto;
                    }
                    h2 {
                        font-size: 12pt;
                        font-weight: bold;
                    }
                    .error {
                        color: red;
                        margin: 0.5em 0 0.5em 0;
                    }
                </style>
            </head>
            <body>
                <div id="context">
                    <h2 class="error">PatientView Not Allowed</h2>
                    <p>To use PatientView, a patient must be assigned to this room.</p>
                    <p>If you are the patient in this room, please wait while the
                        hospital processes your room assignment.</p>
                    <p>If this error persists, please ask the hospital staff for help.</p>
                    <p>You can press Esc to exit this screen.</p>
                    <p>Thank you for your patience and understanding.</p>
                </div>
            </body>
            </html>
<?
            }

            exit;
        }

    /*******************************************\
    \*******************************************/

        static function expire_zombies() {
            get_db_connection();

            // A zombie is a visitor that is inactive for 10+ minutes or a patient that is inactive for 30+ minutes
            $zombies = $GLOBALS['__db__']->fetchAll('select ' .
                                        self::$fields['visitor']['id'] . ', '.
                                        self::$fields['visitor']['name'] . ', '.
                                        self::$fields['visitor']['authorized'] .
                                    ' from ' . self::$tables['visitor'] .
                                    ' where ' .
                                        '(' . 
                                            '(' . self::$fields['visitor']['last_activity'] . ' < ( now() - INTERVAL 10 MINUTE )) ' .
                                            ' AND ('.self::$fields['visitor']['authorized'] . ' != "-1") ' .
                                        ') OR (' .
                                            '(' . self::$fields['visitor']['last_activity'] . ' < ( now() - INTERVAL 30 MINUTE )) ' .
                                            ' AND ('.self::$fields['visitor']['authorized'] . ' = "-1")' .
                                        ')'
                                );

            foreach($zombies as $z) {
                self::expire_zombie($z);
            }
        }

    /*******************************************\
    \*******************************************/

        // If there was a patient in this room using PatientView less than 30 minutes ago
        // (over 30 minutes ago and expire_zombies would have reaped it),
        // but now a new patient is assigned to this room, the old patient is a zombie.
        //
        // Note: This requires $this->name to be the current patients name
        //       (i.e. this must be called after $this->verify_name).
        protected function expire_old_patients() {
            get_db_connection();

            $zombies = $GLOBALS['__db__']->fetchAll('select ' .
                                        self::$fields['visitor']['id'] . ', '.
                                        self::$fields['visitor']['name'] . ', '.
                                        self::$fields['visitor']['authorized'] .
                                    ' from ' . self::$tables['visitor'] .
                                    ' where ' .
                                        '(' . 
                                            $GLOBALS['__db__']->quoteInto(
                                                self::$fields['visitor']['name'] . " != ?",
                                                $this->name
                                            ) .
                                        ') AND ('.self::$fields['visitor']['authorized'] . ' = "-1")'
                                );

            foreach($zombies as $z) {
                $this->debugger("Deleting zombie " . $z[self::$fields['visitor']['id']] . "=" . $z[self::$fields['visitor']['name']]);
                self::expire_zombie($z);
            }
        }

    /*******************************************\
    \*******************************************/

        static function expire_zombie($z) {
            get_db_connection();
            $event = array(
                        self::$fields['event']['source_id']     => $z[self::$fields['visitor']['id']],
                        self::$fields['event']['source_name']   => $z[self::$fields['visitor']['name']],
                        self::$fields['event']['action']        => 'TIMEOUT',
                    );
            $GLOBALS['__db__']->insert(self::$tables['event'], $event);
            $GLOBALS['__db__']->delete(
                                    self::$tables['visitor'],
                                    $GLOBALS['__db__']->quoteInto(
                                        self::$fields['visitor']['id'] . ' = ?',
                                        $z[self::$fields['visitor']['id']]
                                    )
                                );

            if ($z[self::$fields['visitor']['authorized']] == '-1') {
                self::unauthorize_all_for_patient();
            }
       }

    /*******************************************\
    \*******************************************/

        public function logged_in() {
            if (!$this->get_patient()->is_loaded()) return false;

            $q = 'select ' .
                    self::$fields['visitor']['id'] . ', '.
                    self::$fields['visitor']['name'] . ', '.
                    self::$fields['visitor']['authorized'] .
                ' from ' . self::$tables['visitor'] . 
                ' where ' .
                    $GLOBALS['__db__']->quoteInto(self::$fields['visitor']['id'] . ' = ?', $this->get_id());


            $session = $GLOBALS['__db__']->fetchRow($q);

            if (!isset($session[self::$fields['visitor']['name']])) return false;
            if ($this->name != $session[self::$fields['visitor']['name']]) return false;

//            $this->authorized = $this->is_authorized(); //$this->is_authorized(); 
            $this->update_activity();
            return true;

        }

    /*******************************************\
    \*******************************************/

        private function destroy_session() {
            $this->debugger("Session destroyed.");
            @session_destroy();
            unset($_SESSION['_chat_']);
        }

    /*******************************************\
    \*******************************************/

        private function set_session() {
            $this->debugger("Setting session.");
            $this->set_index($this->load_index(-1, 0), -1);
            $this->set_index($this->load_index($this->get_id(), 0), $this->get_id());
            $_SESSION['_chat_'] = $this;
            return true;
        }

    /*******************************************\
    \*******************************************/

        public function check_authorization() {

            $auth = $GLOBALS['__db__']->fetchOne('select ' .
                                                    self::$fields['visitor']['authorized'] . 
                                                ' from ' . self::$tables['visitor'] .
                                                ' where ' .
                                                    self::$fields['visitor']['id'] . ' = ' . $this->get_id()
                                            );

            $auth = intval($auth);
            if ($auth == 3) {
                $GLOBALS['__db__']->delete(self::$tables['visitor'], $GLOBALS['__db__']->quoteInto(self::$fields['visitor']['id'] . ' = ?', $this->get_id()));
                @session_destroy();
                return false;
            }

            $this->authorized = intval($auth);
            return $auth;
        }

    /*******************************************\
    \*******************************************/

        static function remove_all_users ($reason) {
            $db = get_db_connection();
            self::unauthorize_all_for_patient();
            $uids = $db->fetchAll('select id from chat_visitors');
            $rmids = array();
            foreach ($uids as $u) {
                self::kick_for_patient($u['id'], -5, $reason);
                array_push($rmids, $u['id']);
            }
//            return $db->delete(self::$tables['visitor'], self::$fields['visitor']['id'] . ' in (' . implode(', ', $rmids) . ')');
            return true;
        }

    /*******************************************\
    \*******************************************/

        public function request_authorization() {
            $ret = $this->set_authorized(1);
            if ($ret)
                write_flag('patientview_requests.flag', $this->get_id() . '::' . $this->get_name() . "\n", true);
            return $ret;
        }

    /*******************************************\
    \*******************************************/

        private function set_authorized($mode, $id = false) {
            if ($id === false) $id = $this->get_id();
            $mode = intval($mode);
            $f = array(self::$fields['visitor']['authorized'] => $mode);
            $auth = $GLOBALS['__db__']->update(self::$tables['visitor'], $f, $GLOBALS['__db__']->quoteInto(self::$fields['visitor']['id'] . ' = ?', $id));
                                                                                    
            if (!$auth) return false;
            $this->authorized = $mode;
            return true;
        }       

    /*******************************************\
    \*******************************************/

        public function get_requests() {
            $requests = $GLOBALS['__db__']->fetchAll('select ' . 
                                                self::$fields['visitor']['name'] . ', ' .
                                                self::$fields['visitor']['id'] .
                                            ' from ' . self::$tables['visitor'] . 
                                            ' where ' .
                                                self::$fields['visitor']['authorized'] . ' = 1'
                                        );

            $GLOBALS['__db__']->update(
                self::$tables['visitor'],
                array(self::$fields['visitor']['authorized'] => '0'),
                self::$fields['visitor']['authorized'] . ' = 1 '
            );

            return $requests;
        }

    /*******************************************\
    \*******************************************/

        static function annotate_authorization($id, $auth) {
            $db = get_db_connection();
            $name = $db->fetchOne('select ' . self::$fields['visitor']['name'] .
                ' from ' . self::$tables['visitor'] .
                ' where ' . self::$fields['visitor']['id'] . ' = ' . $id);

            if (strlen($name) > 0) {
                // This visitor exists, so record the (un)auth event
                $ev = new event();
                $ev->video_id = true;
                $ev->service_tag = 'patientview';
                $ev->type = 'auth';
                $ev->name = $name;
                $ev->state = ($auth?'authorized':'unauthorized');
                $ev->time = true;
                $ev->save();

                // Add a Server hospial event for this (un)authorization                                                  
                // We don't care about the return value or any failures (besides debugging)                               
                $resp = @file_get_contents('http://server.cv-internal.com/ezfw/service/hospital_events.php' .         
                    '?category=service&event=' . ($auth ? 'visitor_video_on' : 'visitor_video_off') .                     
                    '&service=PatientView&ip=' . get_ip() . '&pv_username=' . urlencode($name));                          
                #@file_put_contents('/tmp/chat.log', "auth for $id ($name) resp=$resp\n", FILE_APPEND);                   
            }

            return;
        }

    /*******************************************\
    \*******************************************/

        public function authorize($id = false, $pat = false) {
            if (!($pat instanceOf patient)) $pat = load_object('patient', 10);
            if (!$pat) return false;

            if ($id === false) $id = $this->get_id();
            if ($id == 'all') return $this->authorize_all($pat);

            return self::authorize_for_patient($id, $pat);
        }

    /*******************************************\
    \*******************************************/

        public function unauthorize($id, $pat = false) {
            if (!($pat instanceOf patient)) $pat = load_object('patient', 10);
            if (!$pat) return false;

            if ($id == 'all') return $this->unauthorize_all($pat);

            return self::unauthorize_for_patient($id, $pat);
        }

    /*******************************************\
    \*******************************************/

        public function authorize_all($pat = false) {
            if (!($pat instanceOf patient)) $pat = load_object('patient', 10);
            if (!$pat) return false;

            $db = get_db_connection();
            $users = $db->fetchAll(
                    'select ' . self::$fields['visitor']['id'] . ' as id ' .
                    ' from ' . self::$tables['visitor'] . 
                    ' where ' . self::$fields['visitor']['authorized'] . ' IN (0,1)'
                );

            foreach ($users as $u) $this->authorize($u['id'], $pat);

            return true;
        }

    /*******************************************\
    \*******************************************/

        public function unauthorize_all($pat = false) {
            if (!($pat instanceOf patient)) $pat = load_object('patient', 10);
            if (!$pat) return false;

            return self::unauthorize_all_for_patient($pat);
        }

    /*******************************************\
    \*******************************************/

        static function authorize_for_patient($id, $pat = false) {
            if (!($pat instanceOf patient)) $pat = load_object('patient', 10);
            // dont authorize anything if can't load patient
            if (!$pat) return false;

            $db = get_db_connection();
            $f = array(self::$fields['visitor']['authorized'] => '2');
            $auth = $db->update(self::$tables['visitor'], $f, $db->quoteInto(self::$fields['visitor']['id'] . ' = ?', $id));
            if (!$auth) return false;

            $tmp  = $db->fetchAll(
                        'SELECT ' . self::$fields['visitor']['id'] . ' as id, ' . self::$fields['visitor']['name'] . ' as name ' .
                        'FROM ' . self::$tables['visitor'] .
                        ' WHERE ' . $db->quoteInto(self::$fields['visitor']['id'] . ' = ?', $id) .
                        ' OR ' . self::$fields['visitor']['authorized'] . ' = -1'
                    );

            $pid = -1;
            $name = '';
            foreach ($tmp as $r) {
                if ($r['id'] == $id)
                    $name = $r['name'];
                else
                    $pid = $r['id'];
            }

            self::post_event_for($id, $name, 'AUTHORIZED', $pat->get_id(), $pid, 'User ' . $name . ' Authorized');

            self::annotate_authorization($id, true);
            return true;
        }

    /*******************************************\
    \*******************************************/

        static function unauthorize_for_patient($id, $pat = false) {
            if (!($pat instanceOf patient)) $pat = load_object('patient', 10);
            // dont authorize anything if can't load patient
            if (!$pat) return false;

            $db = get_db_connection();
            $f = array(self::$fields['visitor']['authorized'] => '0');
            $auth = $db->update(self::$tables['visitor'], $f, $db->quoteInto(self::$fields['visitor']['id'] . ' = ?', $id));
            if (!$auth) return false;

            $tmp  = $db->fetchAll(
                        'SELECT ' . self::$fields['visitor']['id'] . ' as id, ' . self::$fields['visitor']['name'] . ' as name ' .
                        'FROM ' . self::$tables['visitor'] .
                        ' WHERE ' . $db->quoteInto(self::$fields['visitor']['id'] . ' = ?', $id) .
                        ' OR ' . self::$fields['visitor']['authorized'] . ' = -1'
                    );

            $pid = -1;
            $name = '';
            foreach ($tmp as $r) {
                if ($r['id'] == $id)
                    $name = $r['name'];
                else
                    $pid = $r['id'];
            }

            self::post_event_for($id, $name, 'UNAUTHORIZED', $pat->get_id(), $pid, 'User ' . $name . ' Unauthorized');

            self::annotate_authorization($id, false);
            return true;
        }

    /*******************************************\
    \*******************************************/

        static function unauthorize_all_for_patient($pat = false) {
            if (!($pat instanceOf patient)) $pat = load_object('patient', 10);
            if (!$pat) return false;

            $db = get_db_connection();
            $users = $db->fetchAll(
                    'select ' . self::$fields['visitor']['id'] . ' as id ' .
                    ' from ' . self::$tables['visitor'] . 
                    ' where ' . self::$fields['visitor']['authorized'] . ' > 0'
                );

            foreach ($users as $u) self::unauthorize_for_patient($u['id'], $pat);
            return true;
        }

    /*******************************************\
    \*******************************************/

        static function kick_for_patient($id, $code = -2, $msg = '') {
            get_db_connection();
            $kicked = $GLOBALS['__db__']->update(
                            self::$tables['visitor'],
                            array(self::$fields['visitor']['authorized'] => "$code"),
                            $GLOBALS['__db__']->quoteInto(self::$fields['visitor']['id'] . ' = ?', $id)
                        );
            self::post_event_for(-1, 'CareView', 'PART', -1, -1, $msg);
            if (!$kicked) return false;
            return true;
        
        }

    /*******************************************\
    \*******************************************/

        public function kick($channel, $id) {
            if (!$this->are_we_local()) return false;
            $f = array('authorized' => '-2');
            $kicked = $GLOBALS['__db__']->update(self::$tables['visitor'], $f, $GLOBALS['__db__']->quoteInto(self::$fields['visitor']['id'] . ' = ?', $id));
//            $this->delete_visitor($id);
            if (!$kicked) return false;
            return true;
        }

    /*******************************************\
    \*******************************************/

        public function is_kicked($channel = -1) {
            $this->check_authorization();
            $auth = intval($this->authorized);
            if ($auth < -1) {
                switch ($auth) {
                    case (-2): $msg = 'kick'; break;
                    case (-5): $msg = 'discharge'; break;
                    default:   $msg = 'unknown'; break;
                }
                $this->part($channel, $msg);
                return $msg;
            }
            return false;
        }

    /*******************************************\
    \*******************************************/

        public function update_activity() {
            $r = $GLOBALS['__db__']->query('update ' .
                                    self::$tables['visitor'] . 
                                ' set ' .
                                    self::$fields['visitor']['last_activity'] . ' = now()' .
                                ' where ' . $GLOBALS['__db__']->quoteInto(self::$fields['visitor']['id'] . ' = ?', $this->get_id())
                            );
            return true;
        }   

    /*******************************************\
    \*******************************************/

        private function load_index($channel, $offset = 0) {
            $id = $GLOBALS['__db__']->fetchOne(
                                        'select ' . self::$fields['event']['id'] . 
                                        ' from ' . self::$tables['event'] .
                                        ' where ' . self::$fields['event']['destination_id'] . ' = ' . $channel .
                                        ' order by ' . self::$fields['event']['id'] .
                                    ' desc limit 1'
                                );
            if ($id === false) return 0;
            return $id + $offset;
        }

    /*******************************************\
    \*******************************************/

        public function get_private_messages() {
            return $this->get_events($this->get_id());
        }

    /*******************************************\
    \*******************************************/

        public function get_events($channel, $action = false, $many = false) {
            $db = get_db_connection();
            $last_id = $this->get_index($channel);

            $query = 'select ' .
                        self::$fields['event']['id'] . ', ' . 
                        self::$fields['event']['source_id'] . ', ' . 
                        self::$fields['event']['source_name']. ', ' .
                        self::$fields['event']['action'] . ', ' .
                        self::$fields['event']['message'] . ', ' .
                        self::$fields['event']['posted'] .
                    ' from ' . self::$tables['event'] . 
                    ' where ';
            $where = array();
            $subq = ' order by id';

            if ($many) {
                $subq .= ' desc limit ' . $many;
            } else {
                array_push($where, self::$fields['event']['id'] . ' > ' . $last_id);
                $subq .= ' asc';
            }

            if ($action) 
                array_push($where, $db->quoteInto(self::$fields['event']['action'] . ' = ?', $action));
            
            $patient = load_object('patient', true);
            if (method_exists($patient, 'get_id'))
                array_push($where, self::$fields['event']['patient_id'] . ' = ' . $patient->get_id());

            array_push($where, $db->quoteInto(self::$fields['event']['destination_id'] . ' = ?', $channel));

            $events = $db->fetchAll($query . implode(' AND ', $where) . $subq);
            if ($many) $events = array_reverse($events);

            $this->set_index($this->load_index($channel, 0), $channel);

            if (count($events))
                $this->set_index($events[count($events)-1]['id'], $channel);
            return $events;
        }

     /*******************************************\
    \*******************************************/

        private function delete_visitor() {
            $db = get_db_connection();
            if ($this->is_authorized()) {                                                                             
                self::annotate_authorization($this->get_id(), false);                                                 
            }                                                                                                         
            $db->delete(
                self::$tables['visitor'],
                $db->quoteInto(self::$fields['visitor']['id'] . ' = ?', $this->get_id())
            );
            @session_destroy();
        }
            
    /*******************************************\
    \*******************************************/

        public function timeout() {
            $this->part(-1, 'timeout');
        }

    /*******************************************\
    \*******************************************/

        public function get_users($channel = -1) {
//          if ($channel < 0) $channel *= -1;
            $q = 'select ' . 
                    self::$fields['visitor']['id'] . ', ' . 
                    self::$fields['visitor']['name'] . ', ' .
                    self::$fields['visitor']['authorized'] . ' as auth ' .
                ' from ' . self::$tables['visitor'] .
                ' where (' .
                    self::$fields['visitor']['name'] . ' is not null ' .
                ' OR ' . 
                    self::$fields['visitor']['name'] . ' != ""' .
                ') AND ' .
                    self::$fields['visitor']['channels'] . ' like "%:'.$channel.';%"';
            
            if ($this->are_we_local()) { $q .= ' AND ' . self::$fields['visitor']['authorized'] . ' != -1'; }
            $users = $GLOBALS['__db__']->fetchAll($q);
            return $users;
        }

    /*******************************************\
    \*******************************************/

        public function read_channel_membership() {
            $channel_list = $GLOBALS['__db__']->fetchAll('select c.' . self::$fields['channel']['id'] . ', ' .
                                                                'c.' . self::$fields['channel']['name'] . 
                                                            ' from ' . self::$tables['channel'] . ' c, ' .
                                                                       self::$tables['visitor'] . ' v' .
                                                            ' where ' .
                                                                'v.' . self::$fields['visitor']['id'] . ' = ' . $this->get_id() .
                                                            ' and ' .
                                                                'v.' . self::$fields['visitor']['channels'] . ' like concat("%:", concat(c.id, ";%"))'
                                                    );
            $this->channels = array();
            foreach ($channel_list as $c)
                $this->channels[$c['id']] = $c['name'];

            return;
        }

    /*******************************************\
    \*******************************************/

        public function get_channel_name($channel) {
            $name = $GLOBALS['__db__']->fetchOne('select ' . self::$fields['channel']['name'] .
                                                    ' from ' . self::$tables['channel'] .
                                                    ' where ' . self::$fields['channel']['name'] . ' = ' . $channel);
            return $name;
        }

    /*******************************************\
    \*******************************************/

        public function check_login() {
            $stat = $GLOBALS['__db__']->fetchOne('select ' . self::$fields['visitor']['id']  .
                                        ' from ' . self::$tables['visitor'] . 
                                        ' where ' . self::$fields['visitor']['id'] . ' = ' . $this->get_id()
                                    );
            if ($stat) return true;
            return false;
        }

    /*******************************************\
    \*******************************************/

        static function post_event_for ($id, $name, $action, $patient_id = -1, $channel = -1, $message = NULL) {
            $db = get_db_connection();
            if ($message) $message = preg_replace("/\\\\(['\"])/", '$1', $message);

            if (!$id) $id = -1;
            if (!$name) $name = '';
            $vars = array(
                        self::$fields['event']['source_id']     => $id,
                        self::$fields['event']['source_name']   => $name,
                        self::$fields['event']['destination_id']=> $channel,
                        self::$fields['event']['action']        => $action,
                        self::$fields['event']['message']       => $message,
                        self::$fields['event']['patient_id']    => $patient_id
                    );

            $ret = $db->insert(self::$tables['event'], $vars);
            return !!$ret;
        }
 

        public function post_event($action, $channel = -1, $message = NULL) {

            if ($message) $message = preg_replace("/\\\\(['\"])/", '$1', $message);
            $patient = load_object('patient', 10); // load patient with a cache timeout of 10 seconds
            $patient_id = ($patient instanceof patient)?$patient->get_id():-1;
            return self::post_event_for($this->get_id(), $this->get_name(), $action, $patient_id, $channel, $message);
        }

    /*******************************************\
    \*******************************************/

        public function part($channel = -1, $reason = 'leave', $msg = false) {
        
            if (!$msg) switch($reason) {
                case('leave'):
                    $msg = 'User has left the channel';
                    break;
                case('kick'):
                    $msg = 'User has been kicked from the channel';
                    break;
                case('timeout'):
                    $msg = 'User has timed out';
                    break;
                case('discharge'):
                    $msg = 'The patient has been discharged from the room';
                    break;
                default:
                    $msg = 'User has left the channel';
                    break;
            }
            $this->post_event('PART', -1, $msg);
            $this->delete_visitor();
            return true;
        }

    /*******************************************\
    \*******************************************/

        public function quit($msg = false) {
            foreach($this->channels as $id => $name)
                $this->part($id, 'quit', $msg);
            return $this->delete_visitor();
        }

    /*******************************************\
    \*******************************************/

        public function channel_ids() {
            $ids = implode(':;', array_keys($this->channels));
            if ($ids !== '') $ids = ':' . $ids . ';';
            return $ids;
        }

    /*******************************************\
    \*******************************************/

        public function join($channel = -1) {
            if (isset($this->channels[$channel])) return true;

            $GLOBALS['__db__']->update(
                                    self::$tables['visitor'],
                                    array(self::$fields['visitor']['channels'] => $this->channel_ids() . ':'.$channel.';'),
                                    self::$fields['visitor']['id'] . ' = ' . $this->get_id()
                                );
            $this->read_channel_membership();
            $this->channels[$channel] = $this->get_channel_name($channel);
            if (!$this->post_event('JOIN', $channel, NULL)) return false;
        }

    /*******************************************\
    \*******************************************/


}

if (isset($_SERVER['REMOTE_ADDR'])) {
    session_name('chatuser');
    session_start();
}
