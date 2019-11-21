<?php

	passthru('printf "GET / HTTP/1.0\n\n" | nc localhost 9001');
?>

