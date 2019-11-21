<?php
    require_once('uther.ezfw.php');

    function last_error_message () {
        $err = error_get_last();
        return isset($err['message'])?$err['message']:'An Unknown Error Occurred!';
    }

    function service_logger($msg, $username) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'localhost';
        $room = load_object('room'); 
        $room_name = is_object($room)?$room->get_name():'unreg_node';
        $data = array(
                    'msg'       => $room_name . ': ' . $msg,
                    'username'  => $username,
                    'logip'     => $ip,
                );
        $params = array(
                    'http' => array(
                        'method' => 'POST',
                        'content' => http_build_query($data)
                    )
                );
/*      if ($optional_headers !== null) {
            $params['http']['header'] = $optional_headers;
        }
*/
        $ctx = stream_context_create($params);
        $fp = @fopen('http://server.cv-internal.com/ezfw/service/logger.php', 'rb', false, $ctx);
        if (!$fp) {
            return false;
        }
        $response = @stream_get_contents($fp);
        if ($response == 'LOGGED') return true;
        return false;
    }

    function clear_log() {
        if (defined('LOG_FILE'))
            return unlink(log_path(LOG_FILE));
        return true;
    }

    /*************************************************/

    function logger($msg, $timestamp = true, $newline = true) {
        if ($timestamp) {
            $date = rtrim(`date`) . ': ';
        } else { 
            $date = '';
        }
        if (defined('LOG_FILE')) $logfile = log_path(LOG_FILE);
        else $logfile = log_path(preg_replace('/(.*)\..*$/', '$1', basename($_SERVER['SCRIPT_FILENAME'])) . '.log');
        if ($newline) $msg .= "\n"; else $msg .= "\r";
        file_put_contents($logfile, $date . '(' . getmypid().') ' . $msg, FILE_APPEND);
    }

    function debugger($msg, $level = 1, $timestamp = false, $newline = true) {
        if (!defined('DEBUG')) return;
        if (intval(DEBUG) < $level) return;

        $stack_str = get_trace_string(1, $timestamp);
        logger("$stack_str: DEBUG($level): $msg", $timestamp, $newline);

        return;
    }

    function get_trace_string ($stack_number = 0, $include_arg_types = false) {
        /* this function gets the stack trace and returns
         * a string in the form of "filename[lineno] funcname()"
         * where the filename, lineno and funcname are the
         * source filename, line number and function name
         * in the stack at $stack_number+1 (+1 because we don't
         * count the call to get_trace_string)
         */
        
        $stack_number++;
        $stack = debug_backtrace();
        // return Unknown if stack number not in stack trace
        if (!isset($stack[$stack_number])) return "Unknown[?]";

        $slice = $stack[$stack_number];

        $str = $slice['file'] . '[' . $slice['line'] . '] ' . $slice['function'] . '(';
        if ($include_arg_types) {
           for ($i = 0; $i < count($slice['args']); $i++) {
               $str .= gettype($slice['args'][$i]) . ', ';
           }
           $str = rtrim($str, ', ');
        }

        $str .= ')';
        return  $str;
    }

