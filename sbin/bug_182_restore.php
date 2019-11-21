#!/usr/bin/php
<?php
	require_once('uther.ezfw.php');
	if (!is_dir('/tmp/bug_182'))
		exit (0);
	
	$dh = opendir('/tmp/bug_182');

	while (($fn = readdir($dh)) !== false) {
		if (!preg_match('/([0-9]*?)-([0-9]*?)\./', $fn, $matches))
			continue;
		$start = intval($matches[1]);
		$target = abs_path('video', 'archive', date('Y/m/d', $start), $fn);
		rename('/tmp/bug_182/' . $fn, $target);

		chown($target, 'root');
		chgrp($target, 'root');
	}

	closedir($dh);

