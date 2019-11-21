<?php
    require_once('uther.ezfw.php');
    load_definitions('ACCESS');

    function start_registration($error = false) {
        system('/usr/local/sbin/toggle_tv_modulator.sh on');
        if ($error !== false) {
            file_put_contents('/tmp/registration.error', $error);
        } else if (is_file('/tmp/registration.error')) {
            unlink('/tmp/registration.error');
        }
        exec('/usr/bin/firefox "http://localhost/' . WEB_ROOT . '/setup/choose_room.php' . '" -geometry=640x480+0+0');
    }

    function start_frontend() {
        exec('/usr/local/ezfw/cvg/cvgui.pl');
    }

    function get_empty_locations($type, $site = false) {
        $url = 'setup/empty_location_list.php?type=' . $type;

        if ( $site !== false ) {
            $url .= '&site=' . $site;
        }

        return fetch_array($url);
    }

    function is_node_registered($mac) {
        $resp = fetch_array("setup/is_node_registered.php?mac=$mac");
        if (!is_array($resp)) return false;
        if (isset($resp['error'])) $last_response = $resp['error'];
        if (!isset($resp['registered'])) return false;
        if ($resp['registered']) return true;
        return false;
    }

    function register_node($mac, $cmmac) {
        # this file lives in ezfw-node, it's safe to assume we're
        # registering an rcp
        $url = 'setup/register_node.php?type=room&mac=' . $mac . '&cmmac=' . $cmmac;

        if ( has_wdt() ) {
            $url .= '&wdt_mac=' . get_wdt_mac();
        }
        if ( has_tvc() ) {
            $url .= '&tvc_mac=' . get_tvc_mac();
        }

        $response = fetch_array($url);
        if (!is_array($response)) return false;
        if (isset($response['error'])) {
            return false;
        }
        if (!isset($response['registered']))
            return false;
        return $response['registered'];
    }
    
    function fetch_array($url, $data = false) {
        global $last_response;
        $url = 'http://' . SERVER_HOST.'.'.DOMAIN_NAME . SERVER_WEB_ROOT . '/' . $url;
        if ($data === false) {
            $last_response = file_get_contents($url);
            return unserialize($last_response);
        }

        $params = array(
                    'http' => array(
                        'method' => 'POST',
                        'content' => http_build_query($data)
                    )
                );
        $ctx = stream_context_create($params);
        $fp = @fopen($url, 'rb', false, $ctx);
        if (!$fp) {
            return false;
        }
        $last_response = @stream_get_contents($fp);
        return unserialize($last_response);
    }

    function assign_node($id, $data = false, $mac = false, $type = 'room') {
        $info = array();
        if ($data) $info['data'] = serialize($data);

        if (!$mac)
            $info['mac'] = get_mac();
        else
            $info['mac'] = $mac;

        $info['id']  = $id;
        $info['type'] = $type;
        $res = send_data_to_server('setup/assign_node.php', $info);
        if (isset($res['error'])) {
            $last_response = $res['error'];
            return false;
        }
        if (!isset($res['assigned'])) return false;
        if (isset($res[LOCATION_TYPE])) {
            write_local_room($res[LOCATION_TYPE]);
        }
        return $res['assigned'];
    }

    function send_data_to_server($url, $data) {
        if (!isset($data['mac']))
            $data['mac'] = get_mac();
        if (!isset($data['key']))
            $data['key'] = crypt($data['mac'] . AUTH_KEY);
        return fetch_array($url, $data);
    }
