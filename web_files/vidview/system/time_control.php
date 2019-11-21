<?php
$today = date("Y-m-d");
  
$sql2 = "SELECT * FROM ".$prefix."player_stats_uu";
$result2 = mysql_query($sql2);
while($wynik2=mysql_fetch_array($result2)){

$ch2=mysql_query("SELECT id FROM ".$prefix."player_stats_uu_month WHERE month='".getTimeCount($wynik2['date'],'month')."' AND year='".getTimeCount($wynik2['date'],'year')."' AND domain='".$wynik2['domain']."'");
$iss=mysql_num_rows($ch2);
if($iss == 0){
mysql_query("INSERT INTO ".$prefix."player_stats_uu_month(uu, date, year, month, domain)
                       VALUES (0, '".$wynik2['date']."', '".getTimeCount($wynik2['date'],'year')."', '".getTimeCount($wynik2['date'],'month')."', '".$wynik2['domain']."')");
}
$day = getTimeCount($wynik2['date'],'day');
mysql_query("UPDATE ".$prefix."player_stats_uu_month SET ".$day."d=".$wynik2['uu']." WHERE year='".getTimeCount($wynik2['date'],'year')."' AND month='".getTimeCount($wynik2['date'],'month')."'  AND domain='".$wynik2['domain']."'");        
}            

//
$sql9 = "SELECT * FROM ".$prefix."player_stats_uu_month";
$result9 = mysql_query($sql9);
while($wynik9=mysql_fetch_array($result9)){
$id = $wynik9['id'];
    for($i=1;$i<32;$i++){
        $nm = $i.'d';
        if(strlen($nm) == 2){ $nm = '0'.$nm; }
        if(!isset($total3[$id])){$total3[$id] =0; }
        $total3[$id] = $total3[$id] + $wynik9[$nm];
    }
mysql_query("UPDATE ".$prefix."player_stats_uu_month SET uu=".$total3[$id]." WHERE id='".$id."' AND domain='".$wynik9['domain']."'");        
}
//       
                   
$sql3 = "SELECT * FROM ".$prefix."player_stats_uu_month";
$result3 = mysql_query($sql3);
while($wynik3=mysql_fetch_array($result3)){

$ch3=mysql_query("SELECT id FROM ".$prefix."player_stats_uu_year WHERE year='".getTimeCount($wynik3['date'],'year')."' AND domain='".$wynik3['domain']."'");
$iss3=mysql_num_rows($ch3);
if($iss3 == 0){
mysql_query("INSERT INTO ".$prefix."player_stats_uu_year(uu, year, date, domain)
                       VALUES (0, ".getTimeCount($wynik3['date'],'year').", '".$wynik3['date']."', '".$wynik3['domain']."')");
}
    
$month = getTimeCount($wynik3['date'],'month');
mysql_query("UPDATE ".$prefix."player_stats_uu_year SET ".$month."m=".$wynik3['uu']."  WHERE year='".getTimeCount($wynik3['date'],'year')."' AND domain='".$wynik3['domain']."'");
}    

//
$sql0 = "SELECT * FROM ".$prefix."player_stats_uu_year";
$result0 = mysql_query($sql0);
while($wynik0=mysql_fetch_array($result0)){
$id2 = $wynik0['id'];
    for($i=1;$i<13;$i++){
        $nm = $i.'m';
        if(strlen($nm) == 2){ $nm = '0'.$nm; }
        if(!isset($total0[$id2])){$total0[$id2] =0; }
        $total0[$id2] = $total0[$id2] + $wynik0[$nm];
    }
mysql_query("UPDATE ".$prefix."player_stats_uu_year SET uu=".$total0[$id2]." WHERE id='".$id2."' AND domain='".$wynik0['domain']."'");        
}
//               


// views

 
$sql2 = "SELECT * FROM ".$prefix."player_stats_views";
$result2 = mysql_query($sql2);
while($wynik2=mysql_fetch_array($result2)){

$ch2=mysql_query("SELECT id FROM ".$prefix."player_stats_views_month WHERE month='".getTimeCount($wynik2['date'],'month')."' AND year='".getTimeCount($wynik2['date'],'year')."' AND domain='".$wynik2['domain']."'");
$iss=mysql_num_rows($ch2);
if($iss == 0){
mysql_query("INSERT INTO ".$prefix."player_stats_views_month(views, date, year, month, domain)
                       VALUES (0, '".$wynik2['date']."', '".getTimeCount($wynik2['date'],'year')."', '".getTimeCount($wynik2['date'],'month')."', '".$wynik2['domain']."')");
}
$day = getTimeCount($wynik2['date'],'day');
mysql_query("UPDATE ".$prefix."player_stats_views_month SET ".$day."d=".$wynik2['views']." WHERE year='".getTimeCount($wynik2['date'],'year')."' AND month='".getTimeCount($wynik2['date'],'month')."' AND domain='".$wynik2['domain']."'");        
}            

//
$sql19 = "SELECT * FROM ".$prefix."player_stats_views_month";
$result19 = mysql_query($sql19);
while($wynik19=mysql_fetch_array($result19)){
$id2 = $wynik19['id'];
    for($i=1;$i<32;$i++){
        $nm = $i.'d';
        if(strlen($nm) == 2){ $nm = '0'.$nm; }
        if(!isset($total33[$id2])){$total33[$id2] =0; }
        $total33[$id2] = $total33[$id2] + $wynik19[$nm];
    }
    
mysql_query("UPDATE ".$prefix."player_stats_views_month SET views=".$total33[$id2]." WHERE id='".$id2."' AND domain='".$wynik19['domain']."'");        

}
//       
                   
$sql3 = "SELECT * FROM ".$prefix."player_stats_views_month";
$result3 = mysql_query($sql3);
while($wynik3=mysql_fetch_array($result3)){

$ch3=mysql_query("SELECT id FROM ".$prefix."player_stats_views_year WHERE year='".getTimeCount($wynik3['date'],'year')."' AND domain='".$wynik3['domain']."'");
$iss3=mysql_num_rows($ch3);
if($iss3 == 0){
mysql_query("INSERT INTO ".$prefix."player_stats_views_year(views, year, date, domain)
                       VALUES (0, ".getTimeCount($wynik3['date'],'year').", '".$wynik3['date']."', '".$wynik3['domain']."')");
}
    
$month = getTimeCount($wynik3['date'],'month');

mysql_query("UPDATE ".$prefix."player_stats_views_year SET ".$month."m=".$wynik3['views']."  WHERE year='".getTimeCount($wynik3['date'],'year')."' AND domain='".$wynik3['domain']."'");

}    
//
$sqls = "SELECT * FROM ".$prefix."player_stats_views_year";
$results = mysql_query($sqls);
while($wyniks=mysql_fetch_array($results)){
$idt = $wyniks['id'];
    for($i=1;$i<13;$i++){
        $nm = $i.'m';
        if(strlen($nm) == 2){ $nm = '0'.$nm; }
        if(!isset($totals[$idt])){$totals[$idt] =0; }
        $totals[$idt] = $totals[$idt] + $wyniks[$nm];
    }
mysql_query("UPDATE ".$prefix."player_stats_views_year SET views=".$totals[$idt]." WHERE id='".$idt."' AND domain='".$wyniks['domain']."'");        
}
//               




?>
