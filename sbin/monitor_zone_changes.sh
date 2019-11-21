#!/bin/bash

while /bin/true; do
    printf "waiting...\n";

    _found=2;
    while [ $_found != 0 ]; do
        /usr/bin/inotifywait -t 15 -e create -e delete -e modify /usr/local/ezfw/etc/zones/
        _found=$?;
    done;

    printf "break! waiting 2 seconds for more changes...\n";

    while /usr/bin/inotifywait -t 2 -e create -e delete -e modify /usr/local/ezfw/etc/zones/; do
        printf "more changes... still waiting\n";
    done;

    printf "no more changes, restarting detection...\n";

    sleep 3;
    killall detection;  # might as well leave it there, in case an old engine is running
    killall cellnet;
    killall virtual_bed_rails.php;
    printf "restarted detection!\n";
done;

