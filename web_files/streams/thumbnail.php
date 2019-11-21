<?php
	passthru('printf "GET / HTTP/1.0\n\n" | nc s205 9000 ');
?>

