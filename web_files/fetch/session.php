<?php
    require_once('uther.ezfw.php');
    ini_set('session.save_path', abs_path('session_cache'));

    function param($name, $required = false, $default = false) {
        if (isset($_POST[$name])) return $_POST[$name];
        if (isset($_GET[$name])) return $_GET[$name];
        if ($required) {
            print base64_encode(serialize(array('ERROR'=>"$name parameter not sent!")));
            exit;
        }
        return $default;
    }

    function __remote_session_filename($id, $name, $ip) {
        return preg_replace(
                        '/\\\/',
                        '',
                        ini_get('session.save_path') .
                            "/$name\_$id\_$ip.sess"
                );
    }

    function __remote_session_expiry() {
        return gettimeofday(true) - ini_get('session.gc_maxlifetime');
    }

    function __remote_session_load($id, $name, $ip) {
        if (!file_exists(__remote_session_filename($id, $name, $ip))) return array('ERROR'=>'Not Logged In');
        if (filemtime(__remote_session_filename($id, $name, $ip)) < __remote_session_expiry()) {
            unlink(__remote_session_filename($id, $name, $ip));
            return array('ERROR'=>'Session Expired');
        }
        return array('session'=>file_get_contents(__remote_session_filename($id, $name, $ip)));
    }

    function __remote_session_save($id, $name, $ip, $data) {
        if (!file_put_contents(__remote_session_filename($id, $name, $ip), $data)) {
            return array('ERROR'=>'Could not write the session file!');
        } else {
            return array('response'=>'true');
        }
    }

    function conv ($data, $slice = false) {
        $vars = preg_split('/([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff^|]*)\|/',
                $data, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
            );

        if ($slice) {
            for($i=0; $vars[$i]; $i++)
                if ($vars[$i] == $slice) return $vars[$i+1];
        }
 
        $result = array();
        
        for($i=0; $vars[$i]; $i++)
            $result[$vars[$i++]]=unserialize($vars[$i]);

        return serialize($result);
    }

    function __remote_session_update($id, $name, $ip) {
        if (file_exists(__remote_session_filename($id, $name, $ip))) {
            touch(__remote_session_filename($id, $name, $ip));
        }
        return array('response'=>true);
    }

    function __remote_session_destroy($id, $name, $ip) {
        if (file_exists(__remote_session_filename($id, $name, $ip))) unlink(__remote_session_filename());
        return array('response'=>true);
    }



    $action = param('action', true);
    $id = param('id', true);
    $name = param('name', true);
    $ip = param('ip', true);
    $via = param('via', false, false);
    $slice = param('slice', false, false);
    $enc = param('enc', false, 'b64');

    if ($via) $ip .= ' via ' . $via;

    if ($action == 'save') $data = base64_decode(param('data', true));

    $response = array('ERROR'=>'No Action');

    switch ($action) {
        case 'load':
            $response = __remote_session_load($id, $name, $ip);
            if ($slice) {
                if (isset($response['ERROR'])) break;
                $response = conv($response['session'], $slice);
            }
            __remote_session_update($id, $name, $ip);
            break;
        case 'save':
            $response = __remote_session_save($id, $name, $ip, $data);
            break;
        case 'destroy':
            $response = __remote_session_destroy($id, $name, $ip);
            break;
        case 'update':
            $response = __remote_session_update($id, $name, $ip);
            break;
    }

    if (!is_string($response)) $response = serialize($response);

    if ($enc == 'b64') $response = base64_encode($response);
    else if ($enc != 'none') {
        // warning, unknown encoding method
    }
    print $response;

?>
