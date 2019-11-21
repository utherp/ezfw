<?php
require_once('../config.php');

load_definitions('BROWSER');
load_definitions('VIDEO');
load_definitions('NODE_WEB');
load_definitions('FLAGS');
load_libs('player');

$index = isset($_COOKIE['index'])?intval($_COOKIE['index']):0;
$movie_id = isset($_COOKIE['movie_id'])?intval($_COOKIE['movie_id']):0;
$limit = isset($_GET['giveme'])?intval($_GET['giveme']):3;

$video = video::fetch($movie_id);

list ( $year, $month, $day ) = explode('/', date('Y/m/d', $video->start));

$epoch = mktime(1, 1, 1, intval($month), intval($day), intval($year));

function _tmp($msg) {
    file_put_contents('/tmp/blah.log', $msg . "\n", FILE_APPEND);
}
function print_entry($title, $info1, $info2, $thumb, $path) {
    print '<poz  thumb="' . $thumb . '" ' .
                'tilte="' . $title . '" ' .
                'info1="' . $info2 . '" ' . 
                'info2="' . date('l, F jS, Y', $info1) . '" ' .
                'nmovie="' . $path . '" />' . "\n";
    return;
}

//$list = archive_entries($year, $month, $day);

if ($index == 0)
    $index = -3;

$vindex = $index + 1;
if ($vindex < 1) {
    $limit += $vindex;
    $vindex = 1;
}

$videos = load_videos($year, $month, $day, $vindex, $limit);
$entries = count($videos);
//print "<!-- loading from video $movie_id:  '$year/$month/$day', loaded $entries videos -->\n";

$times = array(
    'last' => array(
        'year' => mktime(1, 1, 1, $month, $day, ($year - 1)),
        'month'=> mktime(1, 1, 1, ($month - 1), $day, $year),
        'day'  => mktime(1, 1, 1, $month, ($day - 1), $year)
    ), 'next'=> array(
        'year' => mktime(1, 1, 1, $month, $day, ($year + 1)),
        'month'=> mktime(1, 1, 1, ($month + 1), $day, $year),
        'day'  => mktime(1, 1, 1, $month, ($day + 1), $year)
    )
);
$times['last']['week'] = $times['last']['day'] - 518400;
$times['next']['week'] = $times['next']['day'] + 518400;

ob_start();

print '<?xml version="1.0" encoding="UTF-8"?><slideshow>';

// Previous Day
if ($index == -3) {
    print_entry(
        'Back One Day',
        $times['last']['day'],
        '# of archives in the previous day',
        '/'.$player_folder . '/images/b.jpg',
        '/'.$player_folder . '/player.php?ts=' . $times['last']['day']
    );
    $limit--;
    $index++;
}

// Back One Week
if ($limit && $index == -2) {
    print_entry(
        'Back One Week',
        $times['last']['week'],
        '# of archives in the previous week',
        '/'.$player_folder . '/images/bb.jpg',
        '/'.$player_folder . '/player.php?ts=' . $times['last']['week']
    );
    $limit--;
    $index++;
}

// Back One Month
if ($limit && $index == -1) {
    print_entry(
        'Back One Month',
        $times['last']['month'],
        '# of archives in the previous month',
        '/'.$player_folder . '/images/bbb.jpg',
        '/'.$player_folder . '/player.php?ts=' . $times['last']['month']
    );
    $limit--;
    $index++;
}

if (!$index) $index = 1;
if ($limit < 1) $limit = 0;

//$remain = $limit - ($entries - $index) - 3;

//if ($remain > 0) 
//    $index -= $remain;

//    print "<!-- remain($remain), limit($limit), index($index) -->\n";
    

foreach ($videos as $v) {
//while ($limit-- && ($index < $entries)) {
    if (!$v->start) break;
    $h = $m = $s = 0;
    $s = $v->end - $v->start;
    if ($s > 59) {
        $m = intval($s / 60);
        $s -= $m * 60;
    }
    if ($m > 59) {
        $h = intval($m / 60);
        $m -= $h * 60;
    }

    print_entry(
        date('H:i:s - ', $v->start) . date('H:i:s', $v->end),
        $v->id,
        "Duration: $h:$m:$s",
        '/' . $player_folder . '/get_movie.php?type=minithumb&id=' . $v->id,
        '/' . $player_folder . '/player.php?ts='.$epoch.'&id='.$v->id
    );
    $index++;
}


// Forward One Day
if ($limit-- >= 0) {
    print_entry(
        'Forward One Day',
        $times['next']['day'],
        '# of archives in the next day',
        '/'.$player_folder . '/images/f.jpg',
        '/'.$player_folder . '/player.php?ts=' . $times['next']['day']
    );
}

// Forward One Week
if ($limit-- >= 0) {
    print_entry(
        'Forward One Week',
        $times['next']['week'],
        '# of archives in the next week',
        '/'.$player_folder . '/images/ff.jpg',
        '/'.$player_folder . '/player.php?ts=' . $times['next']['week']
    );
}

// Forward One Month
if ($limit-- >= 0) {
    print_entry(
        'Forward One Month',
        $times['next']['month'],
        '# of archives in the next month',
        '/'.$player_folder . '/images/fff.jpg',
        '/'.$player_folder . '/player.php?ts=' . $times['next']['month']
    );
}

print '</slideshow>';

setcookie('index', $index);

ob_end_flush();

exit;

