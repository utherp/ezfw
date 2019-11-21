<?php
	require_once('uther.ezfw.php');
	load_definitions('FLAGS');
	header('Content-type: text/javascript');

	if (!flag_raised(STANDALONE_FLAG)) {
		new session_login(NULL, NULL, true);
		if (!$_SESSION['_login_']->validate_permission('node_comm')) {
			?>document.location = '/ezfw/login.php
			<?
			exit;
		}
	}

	if (flag_raised(PROCEDURE_FLAG)) {
?>		procedure.active = true;
		procedure.name = '<?=read_flag(PROCEDURE_FLAG)?>';
		procedure.start_time = <?=filectime(flag_path(PROCEDURE_FLAG))?>;
		if (procedure.callback) procedure.callback();
<?	} else {
?>		procedure.active = false;
		if (procedure.callback) procedure.callback();
<?	}
	exit;
?>
