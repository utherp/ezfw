<?php
require_once('../config.php');

$from = htmlspecialchars($_GET['from']);
$from = mysql_real_escape_string($from);
$to = htmlspecialchars($_GET['to']);
$to = mysql_real_escape_string($to);

$url = htmlspecialchars($_GET['url']);
$url = mysql_real_escape_string($url);

$sql = "SELECT * FROM ".$prefix."player_config";
$result = mysql_query($sql);
while($wynik=mysql_fetch_array($result)){
$tab = $wynik['name'];
$config[$tab] = $wynik['value'];
}


$do = 'Spectator <'.$to.'>';

/* temat */
$temat = $config['email_topic'];

/* wiadomosc */
$wiadomosc = '
<html>
<head>
 <title>'.$config['email_topic'].'</title>
</head>
<body>
'.$config['email_content'].'
<br><br>

<a href="'.$url.'">'.$url.'</a>
</body>
</html>
';

$naglowki  = "MIME-Version: 1.0\r\n";
$naglowki .= "Content-type: text/html; charset=iso-8859-2\r\n";

$naglowki .= "From: ".$from."\r\n";

$naglowki .= "Cc: archiwum_mailingu@example.com\r\n";
$naglowki .= "Bcc: kontrola_mailingu@example.com\r\n";

mail($do, $temat, $wiadomosc, $naglowki);
?>