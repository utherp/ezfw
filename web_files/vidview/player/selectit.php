<?php
if (!empty($_GET['code'])) 
    $get = explode('::',$_GET['code']);

if (!empty($get[0])) 
    $video = str_replace('video=','',$get[0]);

if (!empty($get[1])) 
    $thumb = str_replace('thumb=','',$get[1]); }

if (!empty($get[2]))
    $link = str_replace('url=','',$get[2]); }
?> 
