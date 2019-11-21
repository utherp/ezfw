<?php
    define('CELLNET_BIN', "/usr/src/cellnet/build/testbed");

    $all_maps = array(
        "King's Moves",
        "King's x 2",
        "Knight's Moves",
        "Knight / King"
    );

    $lvls = 0;
    if (file_exists('.sample_root')) {
        $my_path = '';
        $go_up = false;
        $back = '.';
    } else {
        $lwd = getcwd();
        $back = '';
        while (!file_exists('.sample_root')) {
            $lvls++;
            chdir('..');
            $back .= '../';
        }
        $root_dir = getcwd();
        $my_path = substr($lwd, strlen($root_dir));
        $go_up = true;
        chdir($lwd);
    }

    function param($name, $def = null, $req = false) {
        if (isset($_REQUEST[$name]))
            return $_REQUEST[$name];
        if (!$req) return $def;
        print "parameter error";
        exit(1);
    }

    $map = param('map', 0);
    $step = param('step', array('x'=>1, 'y'=>1));
    $power = param('power', 2);
    $samp = str_replace('/', '', param('samp', ''));

    if ($samp && !is_dir($samp)) $samp = '';

    if ($samp && !file_exists("$samp/f.yuv")) {
        if (!file_exists("$samp/samples.php"))
            symlink("../samples.php", "$samp/samples.php");
        header("Location: $samp/samples.php");
        exit(0);
    }

    $subcat = array();
    if ($go_up) $subcat[] = '..';
    $samples = array();
    $list = glob("*");
    foreach ($list as $l) {
        if (!is_dir($l)) continue;
        if (file_exists("$l/f.yuv")) {
            $samples[] = $l;
            if (!$samp) $samp = $l;
            continue;
        }
        $subcat[] = $l . '/';
    }

    $samples = array_merge($subcat, $samples);

    $refresh = param('refresh', false);

    if (is_dir($samp) && !file_exists("$samp/edges.bmp"))
        $refresh = true;

    if ($refresh && file_exists($samp . '/f.yuv')) {
        $yuv = $samp . '/f.yuv';
        $edges = $samp . '/edges.bmp';
        $log = $samp . '/edges.log';

        $cmd = CELLNET_BIN . " $yuv $edges $log {$step['x']} {$step['y']} $power $map";
        $msg = "executing:    '$cmd'\n";
        exec($cmd, $out, $ret);
        $msg .= "program returned $ret, output:\n";
        $msg .= '## ' . implode("\n## ", $out) . "\n\n";
    }

?>

<html>
  <head>
    <!--
        <?=$msg?>
    -->
    <title>Samples '<?=$my_path?>'</title>
    <link rel='stylesheet' type='text/css' href='<?=$back?>/samples.css' />
    <script type='text/javascript' src='<?=$back?>/samples.js'></script>
  </head>

  <body style='background-color: black; margin: 0px; padding: 0px; spacing: 0px; border: 0px; border-collapse: collapse;' onload='init();'>
    <div style='position: absolite; width: 100%; height: 100%; top: 0px; overflow: hidden;'>
      <form id=frm0 method=post action='samples.php?refresh=1&t=<?=date('S')?>'>

      <table style='width: 100%; height: 100%; top: 0px'>
        <tr class='wadjust'>
<!--          
          <td style='height: 0px; width: 40px'></td>
          <td style='width: 100px'></td>
  
          <td style='width: 40px'></td>
          <td style='width: 40px'></td>
 --> 
          <td style='height: 0px; width: 30px'></td>
          <td style='width: 100px'></td>
  
          <td style='width: 30px'></td>
          <td style='width: 30px'></td>
          <td></td>
          <td style='width: 30px'></td>
        </tr>
        <tr onmouseover='set_inside(false);' onmouseout='set_inside(true);'>
<!--          
          <td style='height: 40px;'><h2>Step: </h2></td>
          <td style='color: lightgreen;'>
              <input class=step type=text name=xstride value="<?=$step['x']?>" /> x 
              <input class=step type=text name=ystride value="<?=$step['y']?>" />
          </td>
  
          <td><h2>Power: </h2></td>
          <td><input class=power id=str type=text name=strength value="<?=$power?>" /></td>
-->  
          <td style='height: 30px;'><h2>Map: </h2></td>
          <td>
            <select class=map name=map  onChange='document.getElementById("frm0").submit();'>
<?          for ($i = 0; $i < count($all_maps); $i++) {
?>            <option value=<?=$i?> <?=($map == $i)?'selected':''?>><?=$all_maps[$i]?></option>

<?          }
?>        </td>
  
          <td><h2>Sample:</h2></td>
          <td><h2 style='color: brown; white-space: nowrap;'>
<?        $i = $lvls;
          $paths = explode('/', $my_path);
          array_unshift($paths, 'root');
          foreach ($paths as $p) {
            if (!$p) continue;
            if (!$i) print "$p / ";
            else {
?>            <a href='<?=str_repeat('../', $i--)?>samples.php'><?=$p?></a> / 
<?          }
          }
?>        
          </h2></td>
          <td>
            <select id=samplebox class=sample name=samp onChange='document.getElementById("frm0").submit();'>

<?          foreach ($samples as $s) {
?>            <option value='<?=$s?>' <?=($samp == $s)?'selected':''?>><?=$s?></option>
<?          }

?>          </select>
          </td>
          <td><input class=go type=submit name=go value='refresh' /></td>
        </tr>
        <tr>
          <td colspan=6 style='padding: 0px; border-collapse: collapse;'>
            <table style='height: 100%; width: 100%'><tr>
            
            <td style='vertical-align: top; width: 50%; padding: 0px 5px 0px 0px;'>

<?          if ($samp) {
?>            <img class=pane id=frame src='<?=$samp?>/f.jpg?t=<?=date('s')?>' style='height: auto; width: 100%;' />
<?          }

?>          </td>
            <td style='vertical-align: top; width: 50%; padding: 0px 0px 0px 5px;'>

<?          if ($samp) {
?>            <img class=pane id=edges src='<?=$samp?>/edges.bmp?t=<?=date('s')?>' style='height: auto; width: 100%; ' />
<?          }            
?>
            </td></tr></table>
          </td>
        </tr>
      </table>

      <div id=frame_mark class=marker><img src='/ezfw/img/crosshair.gif' /></div>
      <div id=edge_mark class=marker><img src='/ezfw/img/crosshair.gif' /></div>

      </form>
    </div>
  </body>
</html>


