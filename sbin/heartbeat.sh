#!/bin/bash

IFCONFIG=/sbin/ifconfig
HWMAC=`$IFCONFIG eth0 |grep eth0 | sed 's,  *, ,g' | cut -d' ' -f 5`

wget -O /usr/local/ezfw/logs/last_heartbeat.log "http://server.cv-internal.com/ezfw/service/heartbeat.php?mac=$HWMAC" > /usr/local/ezfw/logs/heartbeat.log 2>&1 
