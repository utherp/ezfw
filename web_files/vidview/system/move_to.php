<?php
require_once('../config.php');

$client_id = (int) $_GET['client_id'];
mysql_query("UPDATE ".$prefix."player_adverts SET clicks=clicks+1 WHERE id='$client_id'");

$sql2 = "SELECT * FROM ".$prefix."player_adverts WHERE id='$client_id'";
$result2 = mysql_query($sql2);
$wynik2=mysql_fetch_array($result2);

if($wynik2['limit_clicks'] != 'none' && $wynik2['clicks'] >= $wynik2['limit_clicks']){
    mysql_query("UPDATE ".$prefix."player_adverts SET active='no' WHERE id='".$wynik2['id']."'"); }
    
header("location:".$_GET['url']."");

?>
