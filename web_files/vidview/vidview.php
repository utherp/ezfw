<? require_once('uther.ezfw.php');
    load_definitions('FLAGS');
    define('DEBUG', 3);
    if (!flag_raised(STANDALONE_FLAG)) {
        new session_login();
        if (!$_SESSION['_login_']->validate_permission('secureView')) {
            fns_output::restricted('SecureView');
            exit;
        }
    }
    $ts = isset($_REQUEST['ts'])?('&ts=' . $_REQUEST['ts']):'';
    $id = isset($_REQUEST['id'])?('&id=' . $_REQUEST['id']):'';
?><html>
    <head>
        <title>SecureView for Room '<?=load_object(LOCATION_TYPE)->get_name()?>'</title>
    </head>
    <frameset cols='265,*' border="0" framespacing="0" frameborder="no">
        <frame name='browser' id='browser' src='browser.php?<?=$id?><?=$ts?>' />
        <frame name='player' id='player' src='player.php?<?=$id?><?=$ts?>' />
    </frameset>
</html>
