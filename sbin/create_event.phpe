#!/usr/bin/php
<?php

    if ($ARGC < 5) {
        print "USAGE: {$ARGV[0]} service_tag type name state\n\n";
        exit(1);
    }

    $ev = new event();

    $ev->service_tag = $ARGV[1];
    $ev->type = $ARGV[2];
    $ev->name = $ARGV[3];
    $ev->state = $ARGV[4];
    $ev->time = true;

    $ev->save();

