<?php
    require_once('uther.ezfw.php');
    load_definitions('Sessions');

    class remote_session {

        /******************************************************/
        static $our_session = false;
//        static function sess_create($server_name, $session_name) {
        static function sess_create() {
            self::$our_session = new remote_session();
            return self::$our_session;
        }
        /******************************************************/

        /******************************************************/
        protected $server_name;
        protected $server_path;

        protected $cache_path;
        protected $cache_file;
        protected $caching = false;
        protected $cache_expiry = false;

        protected $session_name;
        protected $session_id;
        protected $session_data;
        protected $ip;
        /******************************************************/

        /******************************************************/
        public function initialize($server_name, $session_name, $ip = false) {
//            $this->set_session_handlers();

            $this->server_name = $server_name;
            $this->session_name = $session_name;

            $this->check_caching();

            $this->check_parameters();
            if (!$ip && $this->ip) return;

            if ($ip !== false)
                $this->ip = $ip;
            else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
                $this->ip = $_SERVER['HTTP_X_FORWARDED_FOR'] . ' via ' . $_SERVER['HTTP_X_FORWARDED_HOST'];
            else 
                $this->ip = $_SERVER['REMOTE_ADDR'];
 
        }
        /******************************************************/


        private function check_caching() {
            if (!defined('Cache_Path')) {
                //debugger('Cache_Path is not defined', 1);
                $this->caching = false;
            } else if (!is_dir(Cache_Path)) {
                //debugger('Cache_Path "'.Cache_Path.'" is not a directory', 1);
                $this->caching = false;
            } else {
                //debugger('Caching is on!', 2);
                $this->caching = true;
            }
        }

        /******************************************************/
        private function connect() {
        }
        /******************************************************/

        /******************************************************/
        private function check_parameters() {
            if ($this->server_name == '') {
                defined('Default_Server_Name')
                    or $this->report_error('No Session Server Name specifed (session.save_path in php.ini) ' .
                            'and no Default_Server_Name specified in Config!');
                $this->server_name = Default_Server_Name;
            }

            if (defined('Cache_Expiry') && is_int(Cache_Expiry)) {
                $this->cache_expiry = Cache_Expiry;
            } else {
                $this->cache_expiry = 60*10;
            }
            defined($this->server_name.'_Path')
                or $this->report_error('No Path specified for server "'.$this->server_name.'"!');
            $this->server_path = constant($this->server_name.'_Path');

        }
        /******************************************************/

        /******************************************************/
        public function close() {
            unset($this->session_id);
            unset($this->database_id);
            unset($this->session_data);
            //debugger('closed', 2);
            return true;
        }
        /******************************************************/
        
        /******************************************************/
        public function read($id) {
            $this->session_id = $id;
            if ($this->caching) {
                $filename = Cache_Path . '/' . $this->ip .
                                            '_' . $this->session_name .
                                            '_' . $id . '.sess';
                if (is_file($filename)) {
                    if (filectime($filename) > (gettimeofday(true)-(60*15))) {
                        //debugger('read from cache', 3);
                        $this->cache_file = $filename;
                        return $this->session_data = file_get_contents($filename);
                    } else {
                        unlink($filename);
                    }
                }
            }
            if ($this->load_session() && $this->caching) {
                //debugger('caching session', 3);
                file_put_contents(Cache_Path . '/' . $this->ip .
                                            '_' . $this->session_name .
                                            '_' . $id . '.sess', $this->session_data);
            }

            return $this->read_session();
        }
        /******************************************************/
        private function create_session() {
/*            $response = file_get_contents(
                            'http://'.$this->server_name.$this->server_path.'?'.
                            '&id=' . $this->session_id . 
                            '&ip=' . $this->ip .
                            '&name=' . $this->session_name .
                            '&new=true'
                        );
            if (strpos($response, 'true') === false) return false;
            return true;
*/
            return true;
        }
        /******************************************************/
        private function report_error($msg) {
            logger($msg);
            exit;
        }
        /******************************************************/
        private function load_session() {

            $res = unserialize(
                     base64_decode(
                       file_get_contents(
                         'http://'.$this->server_name . $this->server_path . '?' .
                         '&name=' . urlencode($this->session_name) . 
                         '&id=' . urlencode($this->session_id) .
                         '&action=load' . 
                         '&enc=b64' .
                         '&ip=' . urlencode($this->ip)
                       )
                     )
                   );

            if (!is_array($res)) {
                // the request either failed base64 decode and/or was not valid serialized data
                return false;
            }
            
            if (isset($res['ERROR'])) {
                // an error occurred, msg in $res['ERROR']
                return false;
            }
            
            if (!isset($res['session'])) {
                // the response had no error, but sent no session info
                return false;
            }

            $this->session_data = $res['session'];
   
            return true;
        }
        /******************************************************/
        private function save_session() {
            if (filectime($this->cache_file) > (gettimeofday(true)-$this->cache_expiry)) {
                file_put_contents($this->cache_file, $this->session_data);
                //debugger('wrote session back to cache');
            } else {
                $this->send_session('save');
            }
        }

        private function send_session($action) {
            $params = array(
                'http' => array(
                    'method' => 'POST',
                    'content' => http_build_query(
                                    array(
                                        'action'    =>    $action,
                                        'id'        =>    $this->session_id,
                                        'name'        =>    $this->session_name,
                                        'data'        =>    base64_encode($this->session_data)
                                    )
                                )
                            )
                        );
            $ctx = stream_context_create($params);
            $fp = @fopen($url, 'rb', false, $ctx);
            if (!$fp) {
                //throw new Exception("Problem with $url");
            }
            $response = @stream_get_contents($fp);
            if ($response === false) {
                //throw new Exception("Problem reading data from $url");
            }
            return $response;
        } 

        /******************************************************/
        private function read_session() {
            return $this->session_data;
        }
        /******************************************************/
        private function update_activity() {
            if (filectime($this->cache_file) < (gettimeofday(true)-60*5)) {
                //debugger('sending update for session');
                $ret = unserialize(
                         base64_decode(
                           file_get_contents(
                             'http://'.$this->server_name.$this->server_path.'?'.
                             '&id=' . $this->session_id .
                             '&name=' . $this->session_name .
                             '&action=update'
                           )
                         )
                       );
            }

            if (!is_array($ret) || isset($ret['ERROR'])) return false;
            return true;
        }
        /******************************************************/
        public function write($id, $sess_data) {
            $this->session_id = $id;
            if ($this->session_data == $sess_data) {
                //debugger('nothing to write', 4);
                return $this->update_activity();
            } else {
                //debugger('writting session', 4);
                $this->session_data = $sess_data;
                return $this->save_session();
            }
        }
        /******************************************************/
        
        /******************************************************/
        public function destroy($id) {
            $this->session_id = $id;
            if ($this->caching && file_exists($this->cache_file)) {
                //debugger('removed cache file');
                unlink($this->cache_file);
            }
            return $this->destroy_session();
        }
        /******************************************************/
        private function destroy_session() {
            $ret = unserialize(
                     base64_decode(
                       file_get_contents(
                         'http://'.$this->server_name . $this->server_path.'?'.
                                        '&id=' . $this->session_id .
                                        '&name=' . $this->session_name .
                                        '&ip=' . $this->ip .
                                        '&action=destroy'
                       )
                     )
                   );
            if (!is_array($ret) || isset($ret['ERROR'])) return false;
            return true;
        }
        /******************************************************/
        public function gc($maxlifetime) {
            if ($this->caching) {
                $this->clean_cache();
                //debugger('garbage collector was called, local cache was cleaned', 1);
            }
            return true;
        }
        private function clean_cache() {
            if (!$this->caching) return true;
            if (!is_dir(Cache_Path)) return true;
            $lwd = getcwd();
            chdir(Cache_Path);
            $exp = intval(gettimeofday(true)) - $this->cache_expiry;
            foreach (glob('*.sess') as $f)
                if (filectime($f) < $exp) unlink($f);
            chdir($lwd);
            return true;
        }
        /******************************************************/
    }

//$dummy = create_function('', '');


session_set_cookie_params(60*60*2, '/', '.'.DOMAIN_NAME);

$our_session = remote_session::sess_create();

session_set_save_handler(
    array($our_session, "initialize"),
    array($our_session, "close"),
    array($our_session, "read"),
    array($our_session, "write"),
    array($our_session, "destroy"),
    array($our_session, "gc")
);

?>
