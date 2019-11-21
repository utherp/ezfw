<?php
    require_once('uther.ezfw.php');

    load_definitions('VIEWER');
    load_definitions('VIDEO');
    load_definitions('FLAGS');

    if (!flag_raised(STANDALONE_FLAG)) {
        new access_client();
        if (! (access_client::logged_in() && access_client::is_allowed('secureView'))) {
            header("Content-Type: image/jpeg");
            print file_get_contents('../img/no_access.jpg');
            exit;
        }
        $dl_archive = access_client::is_allowed('dl_archives');
    } else {
        $dl_archive = true;
    }

 /********************************************\
|               Constants                      |
 \********************************************/
    declare(ticks = 1);
    $DEBUG = 1;
    $skip_limit = 0;

 /********************************************\
|               Functions                      |
 \********************************************/
    function signal_handler() {
        global $socket;
        logger("Got Signal...", true);
        close_process($socket);
        exit;
    }
    register_shutdown_function('signal_handler');
    /***************************************/
    function construct_header() {
        header("Server: Careview_Video_Feed/0.1.0");
        header("Connection: close");
        header("Max-Age: 0");
        header("Expires: 0");

    }
    /***************************************/
    function construct_frame($image, $stamp) {
        $frame =  "--BoundaryString\x0d\n";
        $frame .= "Content-type: image/jpeg\x0d\n";
        $frame .= "Content-Length: " . strlen($image) . "\x0d\n";
        $frame .= "Set-Cookie: timestamp=" . date('H:i:s', $stamp) . "\x0d\n";
        $frame .= "\x0d\n";
        $frame .= $image;
        $frame .= "\x0d\n";
        return $frame;
    }
    /***************************************/
    function get_image($socket) {
        $pre = stream_get_line($socket, 30000, "\xff\xd8\xff\xfe\x00\x0e");
        $jpg = "\xff\xd8\xff\xfe\x00\x0e";
        $jpg .= stream_get_line($socket, 30000, "--ffserver");
        if ($pre===false || $pre == '') {
            global $running;
            $running = false;
            logger("proc ended!");
            return false;
        }
        return $jpg;
    }
    /***************************************/
    function get_framerate () {
        if (!isset($_GET['rate'])) return 4;
        else return $_GET['rate'];
    }
    /***************************************/
    function open_process($filename, $seek, $framerate, &$pipes) {
        $descriptorspec = array(
                0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
                1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
                2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
            );
        
        
//      $cmd = "ffmpeg -an -ss $seek -i $filename -f mpjpeg -sameq -s 320x240 -r $framerate -";
        $cmd = "ffmpeg -an -ss $seek -i $filename -f mpjpeg -qmax 14 -qmin 9 -s 320x240 -r $framerate -";
        logger("running '$cmd'");
        $process = proc_open($cmd, $descriptorspec, $pipes);
        
        return $process;
        
        #popen($cmd, 'r');
    }
    /***************************************/
    function close_process() {
        global $socket, $pipes;
        foreach ($pipes as $p) fclose($p);
        proc_terminate($socket, 9);
        $i = proc_close($socket);
        logger("closing socket: $i");
    }
    /***************************************/
    function do_delay($start_time) {
        global $sleep_time;
        $delay = $sleep_time - (gettimeofday(true) - $start_time);
        if ($delay > 0) {
            usleep($delay);
            return true;
        }
        logger("too long");
        return false;
    }
    /***************************************/
    function request_seek() {
        return isset($_GET['SEEK'])?$_GET['SEEK']:0;
    }
    /***************************************/
    function request_entry() {
        return isset($_GET['ENTRY'])?$_GET['ENTRY']:false;
    }
    /***************************************/
    function request_download() {
        return isset($_GET['download'])?true:false;
    }
    /***************************************/
    function get_entry($entry_name) {
        $i = 0;

        list ($type, $number, $loc) = explode('_', $entry_name);

        logger("type '$type', location '$loc', # '$number'");

        $path = abs_path(
                    constant($type . '_PATH'),
                    explode('-', $loc)
                );
        logger("Path = '$path'");

        if (!is_dir($path)) return false;
        $d = getcwd();
        chdir ($path);

        $entries = glob('*-*.mpg');

        if (isset($entries[$number])) return $path . '/' . $entries[$number];
        return false;
    }
    /***************************************/
    function download_movie($movie) {
        global $dl_archive;
        if ($dl_archive) {
            header('Content-type: video/mpe');
            $times = explode(':', preg_replace('/^.*?(\d+)-(\d+)\.mpg$/', '$1:$2', $movie));
            for ($i=0; $i<count($times); $i++) $times[$i] = date('m-d-Y H:i', $times[$i]);
            header('Content-Disposition: filename="('.$times[0] . ')--(' . $times[1] . ').mpg"');
            passthru("/bin/cat $movie");
        } else {
            header('Content-type: text/html');
            ?><html>
                <head><title>Denied!</title></head>
                <body>
                    <br /><br />
                    <big><big>You do not have access to download video files!</big></big>
                </body>
            </html>
<?      }
        exit;
    }
/********************************************\
     \********************************************/

    $pipes = array();
    $running = true;
    
    $entry = request_entry();
    $filename = get_entry($entry);

    logger("Entry = '$entry', filename = '$filename'");

//  if (request_download()) {
        download_movie($filename);
        exit;
//  }


    $seek = request_seek();

    list ($start, $end) =  explode('-', preg_replace('/^.*\/(.*)\.mpg$/', '$1', $filename));

    $current_time = $start + $seek;

    if (!$entry || !$filename) {
        logger("Failed To Retrieve Date and Time!");
        print "Failed To Retrieve Date and Time";
        exit;
    }
    
    
    $framerate = get_framerate();
    $sleep_time = 1000000 / $framerate;
    $socket = open_process($filename, $seek, $framerate, $pipes);
    
    
    construct_header();
    ob_flush();

    /************************************************/
    $i = 1;
    while ($running) {
        $start_time = gettimeofday(true);
        $image = get_image($pipes[1]);
        if ($image === false) continue;

        if (!do_delay($start_time)) continue;

        if ($i == $framerate) {
            $current_time++;
            $i = 1;
        } else {
            $i++;
        }
        print construct_frame($image, $current_time);
    }

    /************************************************/
    close_process($socket);

?>
