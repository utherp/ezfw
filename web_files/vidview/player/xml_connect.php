<?php
require_once('uther.ezfw.php');
require_once('../config.php');

new session_login();
$dl_archive = $_SESSION['_login_']->validate_permission('dl_archives', false);

$advert_url = '';
$client_id = '';
$advert_video_url = '';
$dont_get_default = 0;
$page = mysql_real_escape_string($_GET['ref']);
$ourPatch = parse_url($page);
$domain = str_replace('www.','',$domain);
$page = str_replace('www.','',$page);
$style = array();
$ourPatch['host'] = str_replace('www.','',$ourPatch['host']);
$result=mysql_query("SELECT count(*) FROM ".$prefix."player_domains WHERE domain='".$ourPatch['host']."'"); 
list($countofit)=mysql_fetch_row($result);

if($countofit == 0){ $statsdomain = '';}else{$statsdomain = $ourPatch['host'];}

if(strpos($page, $domain)===false && $countofit == 0){
$player_loc = 'external';
}else{
$player_loc = 'internal';
}
$from = $player_loc.'_settings';

$styleIt = $player_loc.'_player_style'; 

if(isset($_GET['style']) && $_GET['style'] != 'undefined'){
$dont_get_default = 1;
$styleid = (int) $_GET['style'];
}

if($dont_get_default == 1){
$sql4 = "SELECT * FROM ".$prefix."player_styles WHERE id2='$styleid'";
$result4 = mysql_query($sql4);
while($wynik4=mysql_fetch_array($result4)){
$part = explode('::',$wynik4['val']);
foreach($part as $value){
$part2 = explode('>>',$value);
if(!empty($part2[0])){
$tab = $part2[0];
$part2[1] = str_replace('#','',$part2[1]);
$style[$tab] = $part2[1];
}
}
}
}
if(empty($part)){$dont_get_default = 0;}


$sql4 = "SELECT * FROM ".$prefix."player_config";
$result4 = mysql_query($sql4);
while($wynik4=mysql_fetch_array($result4)){
$tab = $wynik4['name'];
$config[$tab] = $wynik4['value'];

if($wynik4['name'] == $from && $dont_get_default == 0){

$part = explode('::',$wynik4['value']);
foreach($part as $value){
$part2 = explode('>>',$value);
if(!empty($part2[0])){
$tab2 = $part2[0];
$part2[1] = str_replace('#','',$part2[1]);
$style[$tab2] = $part2[1];
}
}
}
}


if($config['allow_statistics'] == 'yes'){
require_once('../system/user_data.php');
}
if(isset($_GET['ref'])){
if($config['player_timer'] != date("Y-m-d")){
mysql_query("delete FROM ".$prefix."player_stats");

mysql_query("UPDATE ".$prefix."player_config SET value=current_date WHERE name='player_timer'");
}

if($style['show_advert'] == 1){
if(!isset($_COOKIE['advert']) && $config['allow_adverts'] == 'yes'){
$sql2 = "SELECT * FROM ".$prefix."player_adverts WHERE active='yes' ORDER by rand() LIMIT 1";
$result2 = mysql_query($sql2);
$wynik2=mysql_fetch_array($result2);
$advert_video_url = $wynik2['video_url'];
$advert_url = $wynik2['url'];
$client_id = $wynik2['id'];
mysql_query("UPDATE ".$prefix."player_adverts SET views=views+1 WHERE id='".$wynik2['id']."'");
    setcookie('advert', 'ad1', time()+$config['cookie_time'], "/");
    
    if($wynik2['limit_views'] != 'none' && ($wynik2['views']+1) >= $wynik2['limit_views']){
    mysql_query("UPDATE ".$prefix."player_adverts SET active='no' WHERE id='".$wynik2['id']."'"); }
    if(mktime (0,0,0,date("m"),date("d"),date("Y")) > $wynik2['unix_date']){
    mysql_query("UPDATE ".$prefix."player_adverts SET active='no' WHERE id='".$wynik2['id']."'"); }
    }
if(empty($advert_video_url) or $config['allow_adverts'] == 'no'){ $style['show_advert'] = 0;} 
}

if($config['select_method'] == 1){
require_once('video_select.php');
}else{
$_GET['code'] = str_replace('[]','&',$_GET['code']);
require_once('selectit.php');
}

if($config['allow_statistics'] == 'yes'){
require_once('../system/video_data.php');
}

if (empty($video)){ $video = 0;}
if (empty($thumb)){ $thumb = 0;}
if (empty($link)){ $link = 0;}

if (isset($GLOBALS['just_playlist'])) 
    $style['autostart'] = '1';
    

echo'<?xml version="1.0" encoding="UTF-8"?>
<decoder>
<sys 
        serial="'.$serial.'" 

        urlMOV="'.$video.'" 
        thumbMOV="'.$thumb.'"
        linkMOV="'.$link.'" 

        logo="'.$style['logo_url'].'" 
        mount="'.$style['logo_h'].'" 
        logoWidth="'.$style['logo_w'].'"

        advLINK="http://'.$domain.'/'.$player_folder.'/system/move_to.php?url='.$advert_url.'&client_id='.$client_id.'" 
        advCHK="'.$style['show_advert'].'" 
        advMOV="'.$advert_video_url.'" rev="'.$style['reverse_menu'].'" 

        auto="'.$style['autostart'].'" 

        fontColor="'.$style['base_font_color'].'" 
        activeColor="'.$style['active_font_color'].'" 
        silverColor1="'.$style['interface_gradient_colors_1'].'" 
        silverColor2="'.$style['interface_gradient_colors_2'].'" 
        silverColor3="'.$style['interface_gradient_colors_3'].'"
        
        protectAspect="'.$style['ratio'].'"
        logoCorner="'.$style['logocorner'].'"
        
        isTransparent="'.$style['opacity'].'"
        interval="'.$style['opacitytime'].'"
        trans1="'.$style['gradient_transparency_1'].'"
        trans2="'.$style['gradient_transparency_2'].'"
        trans3="'.$style['gradient_transparency_3'].'"


        isGlow="'.$style['glow'].'"
        glowPower="'.$style['glowpower'].'"
        glowColor="'.$style['glowcolor'].'"

        forceNative="'.$style['nativemode'].'"
        
        model="'.$style['modes'].'"';
        if( $dl_archive ){
            echo "\nisLink=\"1\"";
            echo "isExternal=\"1\"\n";
        }else{
            echo "\nisLink=\"0\"";
            echo "isExternal=\"0\"\n";
        }
        echo '
        isShare="0"
        isSource="'.$style['show_source'].'" 
        isGears="'.$style['show_gears'].'" 

        isRate="" 
    
        isAfter="'.$style['show_menu'].'" 
        
        deblocking="'.$config['deblocking'].'" 
        smoothing="'.$config['allow_smoothing'].'" 
        aftermovieType="'.$config['after_video_mov'].'"

        isAdsense="'.$style['show_advert_google'].'"
        VideoID="'.$video_id.'"
        videoPublisherId="ca-video-pub-0635261750891122"
        videoHostingSite="video.google.com"
        videoDescription="'.$videoDescription.'"



/>
</decoder>
<channles>';
foreach($channels as $value){
    echo'<poz channel="'.$value.'"/>';
}

echo'</channels>

';
}
?>
