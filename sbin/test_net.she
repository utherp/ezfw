#!/bin/bash

REG=`/usr/local/ezfw/sbin/registered.php`;
HNAME=`hostname`;
date;

if [ "x$REG" != "xTRUE" ]; then
	printf "Not Registered!\n";
	exit;
fi

if [ "x$HNAME" = "x" ]; then
	printf "No Hostname '$HNAME'\n";
	/etc/init.d/networking restart;
	exit;
fi
if ! /bin/ping $HNAME -c1 > /dev/null 2>&1; then
	printf "Host Not Reachable... Restarting Network!\n";
	/etc/init.d/networking restart;
	exit;
fi

if ! /bin/ping server.cv-internal.com -c1 > /dev/null 2>&1; then
	printf "Server not reachable.... restarting network!\n";
	/etc/init.d/networking restart;
	exit;
fi





