#!/bin/sh

handle(){
	echo "handling event $1 for file $2" > /tmp/handle
	[ "x$1" = "xCREATE" ] && initctl emit $2.create;
	[ "x$1" = "xDELETE"] && initctl emit $2.delete;
	[ "x$1" = "xMOVED_TO" ] && initctl emit $2.create;
	[ "x$1" = "xMOVED_FROM" ] && initctl emit $2.delete;
}

inotifywait -me CREATE -e DELETE -e MOVED_TO -e MOVED_FROM /etc/event.d | \
	while read _dir _action _file; \
	do echo "event $_action for $_file" >> /tmp/handle; handle $_action $_file; \
	done
