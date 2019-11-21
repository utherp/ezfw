#!/usr/bin/php
<?php
$allowed_events = array('chr', 'full', 'vbr');
if (isset($argv[1]) && in_array($argv[1], $allowed_events)) {
    $c = new Memcache();
    $c->connect("localhost");
    $c->set("video/active_zones", $argv[1] . ":" . time() . "|", 0, 30);
    print("Triggered " . $argv[1] . " event.\n");
} else {
    print("Failed to trigger event.\n");
    print("simulate_event.php <event_type> - available event types [" . implode($allowed_events, ', ') . "]\n");
}
