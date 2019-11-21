<?php
    require_once('uther.ezfw.php');

    class node {

        /*****************************************\
        \*****************************************/

        static $table = 'nodes';
        static $identifier = 'id';
        static $fields = array (
                        'id'            => 'id',
                        'type'          => 'type',
                        'location_id'   => 'location_id',
                        'info'          => 'info',
                        'system_mac'    => 'system_mac',
                        'system_ip'     => 'system_ip',
                        'last_heartbeat'=> 'last_heartbeat',
                        'last_failure'  => 'last_failure',
                        'last_recovery' => 'last_recovery',
                        'last_reported' => 'last_reported',
                        'authorized'    => 'authorized'
                    );

        /*****************************************\
        \*****************************************/

        protected $id;
        protected $data = array();

        protected $loaded = false;
        protected $changed = false;

        /*****************************************\
        \*****************************************/

        public function toArray() {
            $tmp = array();
            foreach ($this as $key => $value)
                $tmp[$key] = $value;
            return $tmp;
        }

        /*****************************************\
        \*****************************************/

        function __construct($id = false) {
            $GLOBALS['__db__'] = get_db_connection();

            if (!$id === false) {
                if (is_array($id)) {
                    $this->load($id);
                } else {
                    $this->load(array('id' => $id));
                }
            }
            $type = $this->get_data('type');
            if ($type) $this->location = new $type();
        }


        function __sleep() {
            return array('id', 'data', 'loaded', 'changed');
        }
        function __wakeup() {
            $GLOBALS['__db__'] = get_db_connection();
            $type = $this->get_data('type');
            $this->location = new $type();
        }

        /*****************************************\
        \*****************************************/

        public function get_id()        { return $this->id; }
        public function get_location_id(){ return ($this->get_data('location_id') > 0)?$this->get_data('location_id'):false; }
        public function get_type()      { return $this->get_data('type'); }
        public function get_info()      { return $this->get_data('info'); }
        public function get_ip()        { return $this->get_data('system_ip'); }
        public function get_mac()       { return $this->get_data('system_mac'); }
        public function get_heartbeat() { return $this->get_data('last_heartbeat'); }

        public function get_data($name = false) {
            if ($name === false) return $this->data;
            if (!isset($this->data[$name])) return NULL;
            return $this->data[$name];
        }

        public function set_data($data_name, $value = false) {
            if (!is_array($data_name)) {
                if (isset($this->data[$data_name]) && $this->data[$data_name] == $value) return;
                $this->data[$data_name] = $value;
                $this->changed = true;
            } else {
                foreach ($data_name as $name => $value) {
                    if ($value === NULL && isset($this->data[$name])) {
                        unset($this->data[$name]);
                        $this->changed = true;
                    } else {
                        if ($this->data[$name] != $value) {
                            $this->data[$name] = $value;
                            $this->changed = true;
                        }
                    }
                }
            }
        }

        public function is_loaded()     { return $this->loaded; }
        public function is_authorized() { return ($this->get_data('authorized')==1)?true:false; }
        public function last_heartbeat(){
            $hb = $GLOBALS['__db__']->fetchRow('select ' . 
                                        self::$fields['last_heartbeat'] . ', ' . 
                                        self::$fields['last_failure'] . ', ' .
                                        self::$fields['last_recovery'] .
                                    ' from ' . self::$table .
                                    ' where ' .
                                        self::$fields['id'] . ' = ' . $this->get_id()
                                    );
            if ($hb === false || !is_array($hb)) return false;

            $this->set_data($hb);
            return $this->get_heartbeat();
        }

        private function set_id($id) {
            $this->id = $id;
            $this->load();
        }
        private function set_system_mac($id) {
            if ($this->get_data('system_mac') != $id){
                $this->set_data('system_mac', $id);
                $this->changed = true;
            }
        }

        public function save() {
            $db = get_db_connection();
            if (!$this->changed) return false;

            if (!$this->is_loaded()) {
                if ($this->get_id())
                    $this->load();
                else if ($this->get_mac())
                    $this->load(array('system_mac' => $this->get_data('system_mac')));
            }

            if ($this->is_loaded())
                return $db->update(self::$table, $this->get_data(), $db->quoteInto('id = ?', $this->get_id()));
            else {
                if (!$db->insert(self::$table, $this->get_data())) return false;
                $this->set_id($db->lastInsertId());
                return true;
            }
        }


        /*****************************************\
        \*****************************************/

        public function view_button() {
            return ($this->get_data('type') == 'room')?"
                <img
                    onClick='view_video({$this->get_id()}, \"Node {$this->get_id()}\", \"nodes\", \"{$this->get_id()}\");'
                    src='../img/small-camera.gif' height=15
                />
            ":'';
        }           

        /***************************************************\
        \***************************************************/

        public function send_to_node($objects = false) {
            if ($objects === false)
                $objects = array('node' => $this);
            if (is_object($objects))
                $objects = array(get_class($objects) => $objects);


            $content = array('key' => crypt($this->get_mac() . ACCESS_KEY), 'objects' => array());
            foreach ($objects as $n => $v)
                if ($v !== NULL)
                    $content['objects'][$n] = base64_encode(serialize($v));
                else
                    $content['objects'][$n] = 'NULL';

            $params = array(
                'http' => array(
                    'method' => 'POST',
                    'content' => http_build_query($content)
                )
            );

            $ctx = stream_context_create($params);
            $url = 'http://' . $this->get_id() . '.nodes.cv-internal.com/ezfw/node_comm/put_objects.php';
            $fp = @fopen($url, 'rb', false, $ctx);
            if (!$fp) 
                logger('Could not connect to node ' . $this->get_id());
            else {
                $response = @stream_get_contents($fp);
                if ($response === false) 
                    logger('Could not send to node ' . $this->get_id());
            
            }
            return $response;
        }
        /***************************************************\
        \***************************************************/

        public function record_heartbeat() {
            $this->set_data('last_heartbeat', time());
            if (intval($this->get_data('last_recovery')) < intval($this->get_data('last_failure')))
                $this->set_data('last_recovery', time());
            return;
        }

        public function is_active($update = true) {
            if ($update) $this->last_heartbeat();
            if (intval($this->get_data('last_heartbeat')) < (time() - 60*15)) {
                if (intval($this->get_data('last_failure')) < intval($this->get_data('last_heartbeat')))
                    $this->set_data('last_failure', time());
                return false;
            }
            return true;
        }

        public function get_report() {
            $tmp = array();
            foreach (array('last_heartbeat', 'last_failure', 'last_recovery', 'last_reported') as $n)
                $tmp[$n] = $this->get_data($n);
            $tmp['status'] = $this->is_active(false);
            return $tmp;
        }

        public function was_reported() {
            if (!$this->get_data('last_reported')) return false;
            $state_change = $this->is_active(false)?$this->get_data('last_recovery'):$this->get_data('last_failure');

            if (intval($this->get_data('last_reported')) < intval($state_change))
                return false;

            if (!$this->is_active(false))
                if (intval($this->get_data('last_reported')) < (time() - 60*60*24))
                    return false;

            return true;
        }

        public function reported() {
            if ($this->was_reported()) return;
            $this->set_data('last_reported', time());
            return;
        }
/*
        public function update_times() {
            $db = get_db_connection();
            $tmp = $db->fetchRow('select ' .
                                    self::$fields['last_heartbeat'] . ', ' . self::$fields['last_failure']

*/
        public function set_location_id($id) { 
            if ($this->get_data('location_id') !== $id) {
                $this->set_data('location_id', $id);
                $this->changed = true;
                if ($this->location->is_loaded()) {
                    $this->location = NULL;
                    $this->get_location();
                }
            }
        }

        public function set_type($type) {
            if ($this->get_data('type') != $type) {
                $this->set_data('type', $type);
                $this->changed = true;
                if ($this->location->is_loaded()) {
                    $this->location = NULL;
                    $this->get_location();
                }
            }
        }

        public function set_info($info) {
            if ($this->get_data('info') != $info) {
                $this->set_data('info', $info);
                $this->changed = true;
            }
        }

        public function set_ip($ip)     {
            if ($this->get_data('system_ip') != $ip) {
                $this->set_data('system_ip', $ip);
                $this->changed = true;
            }
        }

        public function set_mac($mac) {
            if ($this->get_data('system_mac') != $mac) {
                $this->set_data('system_mac', $mac);
                $this->changed = true;
            }
        }

        /*****************************************\
        \*****************************************/

        public function is_changed()    {
            switch (false) {
                case($this->changed):
                    return false;
                break;
                case($this->is_loaded()):
                    return false;
                break;
                default:
                    return true;
                break;
            }
        }

        /*****************************************\
        \*****************************************/

        private function send_authorized() {
            $data = array(
                        'authorized' => $this->is_authorized(),
                        'key' => crypt($this->get_mac() . ACCESS_KEY),
                    );
            if ($this->is_authorized())
                $data['hostname'] = 'node' . $this->get_id();

            $params = array(
                'http' => array(
                    'method' => 'POST',
                    'content' => http_build_query($data)
                )
            );


            $ctx = stream_context_create($params);
            $url = 'http://' . $this->get_id() . '.nodes.' . DOMAIN_NAME . '/ezfw/node_comm/set_authorized.php';
            $fp = @fopen($url, 'rb', false, $ctx);
            if (!$fp) 
                logger("Could not connect to node " . $this->get_id());
            else {
                $response = @stream_get_contents($fp);
                if ($response === false) 
                    logger("Could not send to node " . $this->get_id());
            }
        }


        /*****************************************\
        \*****************************************/

        public function get_location() {
            if (!is_object($this->location)) {
                $type = $this->get_data('type');
                $this->location = new $type();
            }

            if (get_class($this->location) !== $this->get_data('type')) {
                $type = $this->get_data('type');
                $this->location = new $type();
            }

            if (!$this->location->is_loaded()) {
                if ($this->get_data('location_id')) {
                    if ($this->location->load(array('id' => $this->get_data('location_id')))) {
                        $this->loaded = true;
                    }
                }
            }
            return $this->location;
        }

        /*****************************************\
        \*****************************************/

        public function load_from_ip($ip) {
                if ($ip == '') return false;
                $mac = exec('cat /var/lib/misc/dnsmasq.leases | grep "' . $ip . '" | cut -d" " -f2');
                return $this->load(array('system_mac' => $mac));
        }

        /*****************************************\
        \*****************************************/

        public function node_comm($cmd, $data) {
//          if (!$this->get_data('system_ip')) return false;
            if (!is_array($data)) $data = array('data' => $data);
            $data['key'] = crypt($this->get_mac() . ACCESS_KEY);

            $params = array(
                        'http'  =>  array(
                            'method'    =>  'POST',
                            'content'   =>  http_build_query($data),
                        )
                    );
            $ctx = stream_context_create($params);
//          $url = 'http://' . $this->system_ip . '/ezfw/node_comm/' . $cmd . '.php';
            $url = 'http://' . $this->get_id() . '.nodes.' . DOMAIN_NAME . '/ezfw/node_comm/' . $cmd . '.php';
            $fp = @fopen($url, 'rb', false, $ctx);
            if (!$fp) return false;
            $response = @stream_get_contents($fp);
            if ($response === false) return false;
            return unserialize(base64_decode($response));
        }

        /*****************************************\
        \*****************************************/

        public function load ($vals = false) {
            if ($vals === false) {
                if ($this->get_id() === false) {
                    return false;
                } else {
                    $vals = array('id' => $this->get_id());
                }
            }

            $clause = '1=1';

            foreach ($vals as $key => $value) {
                if (isset($value)) {
                    $clause .= ' AND ' . $GLOBALS['__db__']->quoteInto($key . ' = ?', $value);
                }
            }

            $node_info = $GLOBALS['__db__']->fetchAll('select ' . implode(', ', self::$fields) .
                                            ' from ' . self::$table . 
                                            ' where ' . $clause);
            if (count($node_info) > 0) {
                foreach (self::$fields as $key => $value) {
                    if (isset($node_info[0][$value])) {
                        if (property_exists($this, $key)) {
                            $this->$key = $node_info[0][$value];
                        } else {
                            $this->data[$key] = $node_info[0][$value];
                        }
                    }
                }
                $this->loaded = true;
                return true;
            } else {
                return false;
            }
        }

        /*****************************************\
        \*****************************************/

        public function link_to_location($id, $type) {
            return $GLOBALS['__db__']->update(
                self::$table,
                array(
                    'location_id' => $id,
                    'type' => $type,
                ),
                $GLOBALS['__db__']->quoteInto(self::$fields['id'] . ' = ?', $this->get_id())
            );
        }   
        public function unassign() {
            return $GLOBALS['__db__']->update(
                        self::$table,
                        array('location_id' => '-1'),
                        $GLOBALS['__db__']->quoteInto(
                            self::$fields['id'] . ' = ?',
                            $this->get_id()
                        )
                    );
        }
        /*****************************************\
        \*****************************************/

        public function has_location() {
            $location = $GLOBALS['__db__']->fetchAll(
                            'select ' . self::$fields['location_id'] . 
                            ' from ' . self::$table . 
                            ' where ' . $GLOBALS['__db__']->quoteInto(self::$fields['system_mac'] . ' = ?', $this->get_mac())
                        );

            if (count($location) == 0) return false;
            if ($location[0]['location_id'] == '-1') return false;
            return true;
        }

        /*****************************************\
        \*****************************************/

        public function is_assigned() {
            if ($this->get_location_id() === false) return false;
            return true;
        }

        static function is_registered($mac) {
            get_db_connection();
            return (
                    count(
                        $GLOBALS['__db__']->fetchAll(
                            'select ' . self::$fields['id'] . 
                            ' from ' . self::$table . 
                            ' where ' . $GLOBALS['__db__']->quoteInto(
                                self::$fields['system_mac'] . ' = ?', $mac
                            )
                        )
                    ) ==0
                )?false:true;
        }

        /*****************************************\
        \*****************************************/

        public function status_bulb() {
            if (!isset($site)) $site = '';

            $tag = "onClick='document.location = \"rcp_status.php?node={$this->get_id()}&site=$site\";'";
            $path = '../img/status_bulbs/';
            $alt = false;
            if (!$this->is_authorized()) {
                $path .= 'unauthorized/';
                $alt = 'Unauthorized';
            } else if (!$this->is_assigned()) {
                $path .= 'unassigned/';
                $alt .= $alt?' / Unassigned':'Unassigned';
            } else {
                $path .= 'assigned/';
                $alt .= $alt?' / Assigned':'Assigned';
            }

            if ($this->is_active()) {
                $path .= 'active.gif';
                $alt .= $alt?' | Active':'Active';
            } else {
                $path .= 'inactive.gif';
                $alt .= $alt?' | Inactive':'Inactive';
            }

            return "<img $tag src='$path' alt='$alt' onMouseOver='hover_tip(event, this)' />";

        }
        /*****************************************\
        \*****************************************/

        public function unauthorize() {
            if (!$this->is_loaded() || !$this->get_id()) return false;
            return $this->auth(false);
        }
        public function authorize() {
            if (!$this->is_loaded() || !$this->get_id()) return false;
            return $this->auth(true);
        }
        private function auth($i = false) {
            $i = $i?1:0;
            $this->set_data('authorized', $i);
            $this->save();
            $this->send_authorized();
            if ($i) return $this->add_to_ethers();
            else return $this->remove_from_ethers();
        }

        private function add_to_cnames($restart = true) {
            if (!is_dir('/etc/cnames/nodes')) return false;
            $this->remove_from_cnames(false);
            if ( $this->get_type() == 'station' ) {
                file_put_contents('/etc/cnames/nodes/' . $this->get_id(), $this->get_ip());
            } else {
                file_put_contents('/etc/cnames/nodes/' . $this->get_id(), $this->get_mac());
            }
            //if ($restart) exec('/usr/local/ezfw/sbin/restart_dnsmasq', $o, $ret);
        }
        private function remove_from_cnames($restart = true) {
            if (!is_dir('/etc/cnames/nodes')) return false;
            if (file_exists('/etc/cnames/nodes/' . $this->get_id()) 
                || is_link('/etc/cnames/nodes/' . $this->get_id()))
                    unlink('/etc/cnames/nodes/' . $this->get_id());

            //if ($restart) exec('/usr/local/ezfw/sbin/restart_dnsmasq', $o, $ret);
        }
        private function remove_from_ethers($restart = true) {
            if (is_dir('/etc/cnames/nodes')) return $this->remove_from_cnames($restart);

            if (!is_file('/etc/ethers')) return false;
            if (!is_writable('/etc/ethers')) return false;
            $out = '';
            foreach (file('/etc/ethers') as $l) {
                if (strpos($l, $this->get_data('system_mac')) !== 0) // != $this->get_data('system_mac'))
                    $out .= $l;
            }
            file_put_contents('/etc/ethers', $out);
            //if ($restart) exec('/usr/local/ezfw/sbin/restart_dnsmasq', $o, $ret);
            return true;
        }
        private function add_to_ethers($restart = true) {
            if (is_dir('/etc/cnames/nodes')) return $this->add_to_cnames($restart);

            if (!is_file('/etc/ethers')) return false;
            if (!is_writable('/etc/ethers')) return false;
            $this->remove_from_ethers(false);
            $this->add_to_cnames(false);

            $ethers = fopen('/etc/ethers', 'a');
            fwrite($ethers, $this->get_data('system_mac') . "\tnode" . $this->get_id() . "\n");
            fclose($ethers);
            //if ($restart) exec('/usr/local/ezfw/sbin/restart_dnsmasq', $o, $ret);
            return true;
        }
/*      private function add_to_cnames($restart = true) {
            if (!is_file('/etc/cnames/nodes')) return false;
            if (!is_writable('/etc/cnames/nodes')) return false;
            $this->remove_from_cnames(false);

            $cnames = fopen('/etc/cnames/nodes', 'a');
            fwrite($cnames, 'node' . $this->get_id() . "\t" . $this->get_id() . "\n");
            fclose($cnames);
            //if ($restart) exec('/usr/local/ezfw/sbin/restart_dnsmasq', $o, $ret);
            return true;
        }
        private function remove_from_cnames($restart = true) {
            if (!is_file('/etc/cnames/nodes')) return false;
            if (!is_writable('/etc/cnames/nodes')) return false;
            $out = '';
            foreach (file('/etc/cnames/nodes') as $l) {
                if (!preg_match('/^node'.$this->get_id().'[ \t]+/', $l)) // != $this->get_data('system_mac'))
                    $out .= $l;
            }
            file_put_contents('/etc/cnames/nodes', $out);
            //if ($restart) exec('/usr/local/ezfw/sbin/restart_dnsmasq', $o, $ret);
            return true;
        }
*/
        /*****************************************\
        \*****************************************/

        public function assign($location) {
            if (
                !is_object($location)
            ||  !method_exists($location, 'get_id')
            ) return false;

            $this->set_data('location_id', $location->get_id());
            $this->set_data('type', get_class($location));
            $this->save();
        }

        static function register_node($mac) {
            $node = new node();
            $node->load(array('system_mac' => $mac));
            if (!$node->is_loaded()) {
                $node->set_data('system_mac', $mac);
                $node->save();
                return $node;
            }
            return false;
        }

        /*****************************************\
        \*****************************************/

        static function unregister_node($mac) {
            $node = new node();
            $node->load(array('system_mac' => $mac));
            if (!$node->is_loaded()) return false;
            $node->remove_from_ethers();
            $node->unassign();
            $node->delete();
        }
                
        public function delete() {
            if ($this->is_loaded()) return false;
            $db = get_db_connection();
            return $db->delete(
                self::$table,
                $db->quoteInto(self::$fields['id'] . ' = ?', $this->get_id())
            );
        }           

        /*****************************************\
        \*****************************************/

        static function get_all_nodes() {
            $db = get_db_connection();
            $nodes = array();

            $ids = $db->fetchAll('select ' . self::$fields['id'] . ' from ' . self::$table);

            foreach ($ids as $id)
                $nodes[$id[self::$fields['id']]] = new node($id[self::$fields['id']]);

            return $nodes;
        }

        /*****************************************\
        \*****************************************/


        static function do_reports() {
            $nodes = self::get_all_nodes();
            $status = array();

            define('LOG_FILE', 'reports.log');

            $report = array();
            foreach ($nodes as $n) {
                if (!$n->is_active()) {
                    if (!$n->was_reported())
                        $report[$n->get_id()] = $n->get_report();
                } else {
                    if (!$n->was_reported()) 
                        $report[$n->get_id()] = $n->get_report();
                }
                $n->save();
            }
            
/*
        $nodes[$id] is the node: $n
        $n->has_location(); // is assigned
        $n->get_location()->get_name(); // location name
        $n->get_type(); // location type, room / station
*/      

            if (!count($report)) return;
            $tmp = CV_FULLNAME;
            $message = <<<EOF
<html>
    <head>
        <title>$tmp Node Status</title>
        <link rel='stylesheet' type='text/css' href='css/browser.css' />
    </head>
    <body style='background-image: url(img/blubk.jpg)'>
        <div style='width: 80%; margin-left: auto; margin-right: auto'>
            <h2>Node Failures and Recoveries</h2>
            <table border='2'>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Type</th>
                    <th>Heartbeat</th>
                    <th>Recovery</th>
                    <th>Reported</th>
                </tr>
EOF;
            foreach ($report as $id => $reported) {
                //$nodes[$id]->get_room();

                $location = $nodes[$id]->has_location() ? $nodes[$id]->get_location()->get_name() : 'Un-Assigned';
                $stat = $reported['status'] ? true : false;
                $ntype = $nodes[$id]->get_type();  // room or station

                $times = array();
                foreach (array('heartbeat', 'recovery', 'reported') as $n)
                    $times['last_'.$n] = $reported['last_'.$n]?date(DATE_FORMAT, $reported['last_'.$n]):'Never';
/*
                $message .= "
                <tr>
                    <td>&nbsp;$id&nbsp;</td>
                    <td>&nbsp;<img src='img/status_bulbs/assigned/";
                    $message .= $stat?'active.gif':'inactive.gif';
                    $message .= "' /></td>
                    <td>&nbsp;$ntype ($location)&nbsp;</td>
                    <td>&nbsp;{$times['last_heartbeat']}&nbsp;</td>
                    <td>&nbsp;{$times['last_recovery']}&nbsp;</td>
                    <td>&nbsp;{$times['last_reported']}&nbsp;</td>
                </tr>";
*/
                $message .= "
                <tr>
                    <td>&nbsp;$id&nbsp;</td>
                    <td style='text-align:center;color:";
                    $message .= $stat?'green':'red';
                    $message .= "'>";
                    $message .= $stat?'Up':'Down';
                    $message .= "</td>
                    <td>&nbsp;$ntype ($location)&nbsp;</td>
                    <td>&nbsp;{$times['last_heartbeat']}&nbsp;</td>
                    <td>&nbsp;{$times['last_recovery']}&nbsp;</td>
                    <td>&nbsp;{$times['last_reported']}&nbsp;</td>
                </tr>";
/*
"Node: $id
    Type: $ntype ($location)
    Status: $stat
    Last Heartbeat: {$times['last_heartbeat']}
    Last Recovery:  {$times['last_recovery']}
    Last Reported:  {$times['last_reported']}
-----------------------------\n";
*/              
                $nodes[$id]->reported();
                $nodes[$id]->save();
//                  ' DownTime: '.$report['last_failure'].' Recovery: '. $report['last_recovery'].'\n';
                /* do email report on:
                    $report == array(
                                'status'    //  (true == up, false == down)
                                'last_heartbeat'
                                'last_failure'
                                'last_recovery'
                            )
                */
            }
            // Email report
            $message .= "
            </table>
        </div>
    </body>
</html>
";

            $message = preg_replace('/\n/', '', $message);
            $message = preg_replace('/>[ \t\n]*?</', '><', $message);
            $params = array(
                'to'    => array('rkuether@care-view.com', 'uther@care-view.com'),
                'from'  =>  CV_CODE.'@care-view.com',
                'name'  =>  CV_FULLNAME . ' Monitoring', //<'.CV_CODE.'@care-view.com>',
                'subject' => CV_FULLNAME . ' Node Monitoring (' . count($report) . ' messages)'
            );
        /*  
            $to = 'rkuether@care-view.com, uther@care-view.com';
            $from = CV_FULLNAME.' <'.CV_CODE.'@care-view.com>';
            $subject = CV_FULLNAME.' RCP Status (' . count($report) . ' messages)';
            
            print "To: $to\nFrom: $from\nSubject: $subject\nMessage:\n$message\n";
*/
            require_once('mail.php');
            chdir(abs_path('web_files'));
            return html_email($params, $message);

            if (!mail($to, $subject, $message, $from)) {    // no mail sent
                logger('ERROR: Failed E-mailing Node reports!');
            }
        }

        /*****************************************\
        \*****************************************/

        static function status_legend() {
            return "
                <div style='width: 140px; height: 120px; background-color: lightblue; color: white; font-weight: bolder;'>
                    <table style='width: 95%; height: 95%;' cellspacing=0 cellpadding=0>
                        <tr style=' font-size: 16px; color: darkgreen; font-weight: 900;'>
                            <td colspan=2 style='text-align: center;'>
                                Legend
                                <hr style='height: 2px; color: black;' />
                            </td>
                        </tr>
                        <tr style='height: 13px;' >
                            <td style='width: 13px;'><img src='../img/grayball.gif' /></td>
                            <td style='text-align: left;' >Unauthorized</td>
                        </tr>
                        <tr style='height: 13px;' >
                            <td style='width: 13px;'><img src='../img/yellowball.gif' /></td>
                            <td style='text-align: left;' >Unassigned</td>
                        </tr>
                        <tr style='height: 13px;' >
                            <td style='width: 13px;'><img src='../img/greenball.gif' /></td>
                            <td style='text-align: left;' >Active</td>
                        </tr>
                        <tr style='height: 13px;' >
                            <td style='width: 13px;'><img src='../img/redball.gif' /></td>
                            <td style='text-align: left;' >Inactive</td>
                        </tr>
                    </table>
                </div>
                <script type='text/javascript'>
                    var tips = new Array();
                    function hover_tip(event, obj) {
                        var id = obj.getAttribute('tip_id');
                        if (undefined == id || undefined == tips[id]) {
                            var tip = obj.getAttribute('alt');
                            if (tip == undefined) return false;
                            var div = document.createElement('div');
                            div.style['border'] = 'thin black solid';
                            div.style['backgroundColor'] = 'lightblue';
                            div.style['padding'] = '2 5 2 5';
                            div.style['visibility'] = 'hidden';
                            div.style['position'] = 'absolute';
                            div.style['left'] = (event.clientX + 10) + 'px';
                            div.style['top'] = (event.clientY + 10) + 'px';
                            id = tips.length;
                            obj.setAttribute('tip_id', id);
                            tips[tips.length] = div;
                            document.body.appendChild(div);
                            div.appendChild(document.createTextNode(tip));
                            obj.onmouseout = function () { hover_tip_off(obj); }
                        }
                        tips[id].style['visibility'] = 'visible';
                        return true;
                    }
                    function hover_tip_off(obj) {
                        var id = obj.getAttribute('tip_id');
                        if (undefined == tips[id]) return false;
                        tips[id].style['visibility'] = 'hidden';
                        return true;
                    }
                </script>
            ";
        }
    }

?>
