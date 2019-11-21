<?php
    define('LOG_FILE', 'demux_status.log');
    define('DEBUG', 2);
    require_once('uther.ezfw.php');

    load_definitions('PLAYER');
    load_definitions('VIDEO');
    load_definitions('FLAGS');
    load_definitions('DEMUXER');
    load_libs('player');

    // reading timestamp
        $timestamp = isset($_GET['ts'])?$_GET['ts']:time();
        $timestamp = new DateTime("@$timestamp");
        list( $year, $month, $day ) = explode('_', $timestamp->format('Y_m_d'));

    // reading entry
        $entry_string = request_entry();
        if ($entry_string) {
            list($repo, $index) = explode('_', $entry_string);
            $entry = read_entry($repo, $year, $month, $day, $index);
        }
    
    $action = null;
    if (isset($_GET['action']) && isset($entry['filename'])) {
        if (!file_exists($entry['filename'])) {
            $error = 'Video Entry Not Found';
            $action = false;

        } else switch(strtolower($_GET['action'])) {
            case('play'):
                file_put_contents('/tmp/demuxing.log', getmypid() . "- playing...\n", FILE_APPEND);
                $videoid = video_demux_id($entry['filename']);
                $path = abs_path('web_files', 'vidview', 'playing', $videoid);
                file_put_contents('/tmp/demuxing.log', getmypid() . "- path = $path\n", FILE_APPEND);
                $action = check_video_status($path, $error);
                file_put_contents('/tmp/demuxing.log', getmypid() . "- action = $action\n", FILE_APPEND);
                break;
            case('download'):
                $action = 'download';
                break;
            default:
                $action = false;
                $error = 'Unknown Action Specified (' . $_GET['action'] . ')';
                break;
        }
    } else {
        $action = false;
        switch (false) {
            case(isset($_GET['action'])):
                $error = 'No Action Specified';
                break;
            case($entry_string):
                $error = 'No Entry Specified';
                break;
            case(isset($entry)):
                $error = 'Entry Not Found!';
                break;
        }
    }

    switch ($action) {

        // Alert Error Message
        case(false):
            header('Content-type: text/javascript');
            print "alert('$error');\n";
            exit;
            break;

        // Download Video File
        case('download'):
            download_video($entry['filename']);
            exit;
            break;

        // Video already demuxed, return path, rate and number of frames
        case('play'):
            send_parameters($path);
            exit;
            break;

        // Demux video and display loading status
        case('demux'):
            if (start_demuxer($path, $entry['filename'])) {
                $action = 'status';
                $c = 0;
                while (!file_exists($path . '/total_frames') && $c++ < 10) usleep(100000);
                send_parameters($path);
            }
            exit;
            break;

        // Show loading status of another demuxer process
        case('status'):
            //monitor_status($path);
            send_parameters($path);
            exit;
            break;

        default:
            header('Content-type: text/javascript');
            print "alert('An unknown error has occurred!');\n";
            exit;
            break;
    }

