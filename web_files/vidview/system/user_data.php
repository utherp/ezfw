<?php
function getTimeCount($time,$type){
$a = explode(' ', $time);
$b = explode('-', $a[0]);

if($type == 'day'){
return $b[2];
}elseif($type == 'month'){
return $b[1];
}else{
return $b[0];
}
}

//ip check
function IP_prawdziwe(){
@ $srv = $_SERVER['HTTP_X_FORWARDED_FOR'];
if ($srv) {
    $ip_prawdziwe = $srv;
}
else {
  $ip_prawdziwe = $_SERVER['REMOTE_ADDR'];
}

return $ip_prawdziwe;
}
$ip = IP_prawdziwe(); 

$today = date("Y-m-d");
$h = date("H");

$ch23=mysql_query("SELECT id FROM ".$prefix."player_stats_views WHERE date='$today' AND domain='$statsdomain'");
$iss3=mysql_num_rows($ch23);
if($iss3 == 0){
mysql_query("INSERT INTO ".$prefix."player_stats_views(views, ".$h."h, date, month, domain)
                       VALUES (1, 1, '".$today."', ".getTimeCount($today,'month').", '$statsdomain')");
}else{
mysql_query("UPDATE ".$prefix."player_stats_views SET views=views+1, ".$h."h=".$h."h+1 WHERE date='$today' AND domain='$statsdomain'");
}        

$ch=mysql_query("SELECT ip FROM ".$prefix."player_stats WHERE ip='$ip'");
$is=mysql_num_rows($ch);

$ref_page = mysql_real_escape_string($_GET['ref_page']);
$resolution = mysql_real_escape_string($_GET['sx']).'x'.mysql_real_escape_string($_GET['sy']);
$lang = mysql_real_escape_string($_GET['lang']);
$system = mysql_real_escape_string($_GET['os']);
$browser = $_SERVER['HTTP_USER_AGENT'];

if(strpos($page, $domain)===false && $page !='' && $statsdomain == '' && $page !='null' && $page != 'undefined'){
    $ourPatch2 = parse_url($page);
    $usdomain = $ourPatch2['host'];
    $page2 = $ourPatch2['path'];
    
    if($is == 0){ $and = ", uu=uu+1"; }
    
    $chw=mysql_query("SELECT * FROM ".$prefix."player_external_location WHERE page='$usdomain'");
    $isit=mysql_num_rows($chw);
    if($isit == 0){
    mysql_query("INSERT INTO ".$prefix."player_external_location(page, sub, views, uu, date)
                       VALUES ('$usdomain', '::$page2>>1', 1, 0, current_timestamp)");
    }else{
    $chw=mysql_query("SELECT * FROM ".$prefix."player_external_location WHERE sub LIKE '%::$page2%'");
    $isit2=mysql_num_rows($chw);

        if($isit2 != 0){
        
        $sql = "SELECT * FROM ".$prefix."player_external_location WHERE page='$usdomain'";
        $result = mysql_query($sql);
        $wynik=mysql_fetch_array($result);
        
        $subs = explode('::',$wynik['sub']);
            $new_sub = '';
                foreach($subs as $value){
                    $valueS = explode('>>', $value); 
                    $value_page = $valueS[0];
                    $value_count = $valueS[1];
                    if($value_page == $page2){ $value_count++;}
                    if(!empty($value_page) && !empty($value_count)){
                    $new_sub .= '::'.$value_page.'>>'.$value_count;
                    }
                }
    
            mysql_query("UPDATE ".$prefix."player_external_location SET views=views+1, sub='$new_sub' $and WHERE page='$usdomain'");
        }else{
        $sql = "SELECT * FROM ".$prefix."player_external_location WHERE page='$usdomain'";
        $result = mysql_query($sql);
        $wynik=mysql_fetch_array($result);
            mysql_query("UPDATE ".$prefix."player_external_location SET views=views+1, sub='".$wynik['sub']."::$page2>>1' $and WHERE page='$usdomain'");
        }
    }
}

if($is == 0){

mysql_query("INSERT INTO ".$prefix."player_stats(ip, page, ref_page, views, active_date, system, browser, resolution, language, add_date, hour, domain)
                       VALUES ('$ip', '$page', '$ref_page', 1, current_timestamp, '$system', '$browser', '$resolution', '$lang', current_timestamp, '".date("H")."', '$statsdomain')");


$ch2=mysql_query("SELECT id FROM ".$prefix."player_stats_uu WHERE date='$today' AND domain='$statsdomain'");
$iss=mysql_num_rows($ch2);

if($iss == 0){
mysql_query("INSERT INTO ".$prefix."player_stats_uu(uu, ".$h."h, date, month, domain)
                       VALUES (1, 1, '".$today."', ".getTimeCount($today,'month').", '$statsdomain')");
}else{
mysql_query("UPDATE ".$prefix."player_stats_uu SET uu=uu+1, ".$h."h=".$h."h+1 WHERE date='$today' AND domain='$statsdomain'");
}               

}else{
mysql_query("UPDATE ".$prefix."player_stats SET views=views+1 WHERE ip='$ip' AND domain='$statsdomain'");
}

?>
