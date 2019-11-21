<?
//$_GET['code'] <- id (u'll get it from player show)
    require_once('uther.ezfw.php');
    load_libs('player');

    if (!empty($_GET['code']))
        $id = $_GET['code'];

    if (strpos($id, 'pl') === 0) {
        $start = substr($id, 2);
        $GLOBALS['just_playlist'] = true;
    } else {
        $id = intval($id);
        setcookie('movie_id', $id);
        $video = video::fetch($id);
        $start = $video->start;
    }

    list ($year, $month, $day) = explode('/', date('Y/m/d', $start));
    $date_epoch = mktime(1, 1, 1, intval($month), intval($day), intval($year));

    setcookie('date_epoch', $date_epoch);
    setcookie('index', 0);

    if (isset($GLOBALS['just_playlist'])) {
        $video = 'no_video.flv';
        $thumb = '';
        $link = '/' . $player_folder . '/player.php?ts=' . $id;
        $video_name = 'Archives for ' . date('Y-m-d', $id);  //video name
        $videoDescription = $video_name;
    } else {
        $video = "get_movie.php?&type=flash&id=$id";
        $thumb = "get_movie.php?&type=thumb&id=$id";
        $link  = web_path('vidview', "get_movie.php?&type=movie&id=$id"); //$web_filename . '.mpg';
    
        $video_id = $id;
        $video_name = date('Y-m-d H:i:s', $video->start) . ' - ' . date('Y-m-d H:i:s', $video->end);  //video name
        $videoDescription = "Archived video from Stephen's Office";
        
    }
    $channels = array('x1z','x2','x3','x4','x5','x6');
?>
