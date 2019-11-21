<?php
class Auth
{
    function Auth()
    {
        session_start();
        
        if(!isset($_SESSION['Auth']))
            $_SESSION['Auth'] = array();
    }
    
    function Login($sUsername, $sPassword, $sCurrentPassword, $iAccess = 0)
    {
        if($sPassword != $sCurrentPassword)
            return FALSE;
        
        $_SESSION['Auth']['username'] = $sUsername;
        $_SESSION['Auth']['access'] = (int) $iAccess;
        return TRUE;
    }
    
    function Logout()
    {
        $_SESSION['Auth']['username'] = NULL;
        $_SESSION['Auth']['access'] = NULL;
        
        unset($_SESSION['Auth']['username'], $_SESSION['Auth']['access']);
        
        session_destroy();
    }
    
    function IsAuth()
    {
        return (bool) ((isset($_SESSION['Auth']['username']) && is_int($_SESSION['Auth']['access'])) ? TRUE : FALSE);
    }
    
    function IsAccess($iAccess)
    {
        return (bool) (($_SESSION['Auth']['access'] <= $iAccess) ? TRUE : FALSE);
    }
    
    function IsGroup($iAccess)
    {
        return (bool) (($_SESSION['Auth']['access'] == $iAccess) ? TRUE : FALSE);
    }
    
    function UserName()
    {
        return ((isset($_SESSION['Auth']['username'])) ? $_SESSION['Auth']['username'] : NULL);
    }
}

?>
