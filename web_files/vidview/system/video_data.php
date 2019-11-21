<?php
if(empty($video_name)){$video_name = $video;}

$ch23=mysql_query("SELECT video_id FROM ".$prefix."player_videos WHERE video_id='$video_id'");
$count=mysql_num_rows($ch23);

$ref_p = str_replace('www.','',$page);
if($ref_p == 'null'){$ref_p = '';}

if($count == 0){

mysql_query("INSERT INTO ".$prefix."player_videos(video_id, name, views, date, ref)
                       VALUES ('$video_id', '$video_name', 1, current_timestamp, '::$ref_p')");
}else{

    $sql = "SELECT * FROM ".$prefix."player_videos WHERE video_id='$video_id'";
    $result = mysql_query($sql);
    $wynik=mysql_fetch_array($result);
    
    $pages = explode('::',$wynik['ref']);
    if(in_array("$ref_p", $pages)){ $ref_p = $wynik['ref']; }else if(!empty($ref_p)){ $ref_p = $wynik['ref'].'::'.$ref_p;}
    
mysql_query("UPDATE ".$prefix."player_videos SET name='$video_name', views=views+1, ref='$ref_p' WHERE video_id='$video_id'");
}
?>
