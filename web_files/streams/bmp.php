<?php
  require_once('uther.ezfw.php');
  load_definitions('FLAGS');

//  new session_login();
  define('MAX_FRAMES_PER_CONNECTION', 100);

  function do_delay() {
    global $skip_limit, $start_time, $sleep_time, $rate;
    $delay = $sleep_time - (gettimeofday(true) - $start_time);
    if ($delay > 0) {
      usleep($delay);
      $skip_limit--;
      if ($skip_limit < -4) {
        $skip_limit = 0;
        if ($rate < 10) {
          $rate++;
          $sleep_time = 1000000 / $rate;
          debugger('-->Increasing Framerate to ' . $rate, 3);
        }
      }
    } else {
      $skip_limit++;
      if ($skip_limit > 4) {
        $skip_limit = 0;
        if ($rate > 1) {
          $rate--;
          $sleep_time = 1000000 / $rate;
          debugger('-->Dropping Framerate to ' . $rate, 3);
        }
      }
    }
  }
  function get_size() {
    return get_param('size', '352x288');
  }
  function get_res() {
    return get_param('res', 'HIGH');
  }
  function get_rate() {
    return get_param('rate', 2);
  }
  function get_hash() {
    return get_param('hash', '');
  }
  function get_single() {
    return false;
    return get_param('single', false);
  }
  function get_param($name, $default = false) {
    if (isset($_POST[$name])) return $_POST[$name];
    if (isset($_GET[$name])) return $_GET[$name];
    return $default;
  }

/*  function get_VideoStream($size = false, $res = false, $rate = false) {
    $stream = false;
    if (isset($_SESSION['_VideoStream_'])) {
      $stream =& $_SESSION['_VideoStream_'];
      if ($size != $stream->get_size()) $stream->set_size($size);
      if ($res != $stream->get_res())  $stream->set_res($res);
      if ($rate != $stream->get_rate()) $stream->set_rate($rate);
    } else {
      $_SESSION['_VideoStream_'] = new VideoStream($size, $res, $rate);
      $stream =& $_SESSION['_VideoStream_'];
    }
    return $stream;
  }

  @session_name(get_size());
  @session_start();
*/

//  $cache = memcache_pconnect('localhost');
//  $cache_name = 'video/' . get_size() . '/frame';

//  $stream = get_VideoStream(get_size(), get_res(), get_rate());
  $single = get_single();
  $rate = get_rate();
  $sleep_time = 1000000 / $rate;

  if (flag_raised(PRIVACY_FLAG)) {
    print file_get_contents('../img/privacy.jpg');
    exit;
  }

  $frame = 0;
  if ($single) {
    $image = $cache->get($cache_name);
    if ($image === false) {
      print "failed!\n";
      exit;
    }
    header('Content-type: image/jpeg');
    header('Content-length: ' . strlen($image));
    print $image;
//    $stream->send_single_image();
    exit;
  } else {
    header("HTTP/1.1 200 OK");
    header("Server: mpjpeg.php/0.1.0");
    header("Connection: close");
    header("Max-Age: 0");
    header("Expires: 0");
    header("Cache-Control: no-cache, private");
    header("Pragma: no-cache");
    header("Content-Type: multipart/x-mixed-replace; boundary=--BoundaryString");
    while ($frame < MAX_FRAMES_PER_CONNECTION) {
//      $image = $cache->get($cache_name);
      $image = file_get_contents("/dev/shm/tmp_deltas.bmp");
      if ($image === false) exit;

      $start_time = gettimeofday(true);
      print "--BoundaryString\x0d\n";
      print "Content-type: image/bmp\x0d\n";
      print "Content-Length: " . strlen($image) . "\x0d\n";
      print "Set-Cookie: {$_SERVER['SERVER_NAME']}_index=$start_time; path=/; domain=.cv-internal.com\x0d\n";
      print "\x0d\n";
      print $image;
      ob_flush();
      flush();

      $frame++;
      usleep(250000);
    }
  }

?>
