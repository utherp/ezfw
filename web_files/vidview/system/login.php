<?php
if(isset($_POST['username']) && isset($_POST['userpass'])){

$usname = mysql_real_escape_string($_POST['username']);

        $sUsername =                $admin_login;
        $sPassword =                md5($_POST['userpass']);
        $sPasswordFromDatabase =    md5($admin_pass);
        $iAccess =                  '';
        
    if($usname == $sUsername){
        if(!$oAuth->Login($sUsername, $sPassword, $sPasswordFromDatabase, $iAccess))
        {
        $alert1 = $lang['incorrectpass'];
        }
    }
    else
    {
      $alert1 = $lang['incorrectlogin'];
    }
}

?>
