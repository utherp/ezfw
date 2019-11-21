#!/bin/bash

(( $# < 2 )) && exit 1;
_action=$1
_flag=$2

if [ "$_flag" = "nurse_privacy" ]; then
    _name=nurse
elif [ "$_flag" = "privacy" ]; then
    _name=patient
else
    exit 0;
fi

cd /usr/local/ezfw;
. lib/shell/ezfw-shell-common
load_hospital_conf;

# load flag name from ini
load_ini flags

_found=0;

for _mode in $cv_disable_recording_on_privacy; do
    [ "$_mode" = "$_name" ] && _found=1;
done;

(( $_found )) || exit 0;

myIP="`/sbin/ifconfig | grep 'inet addr.*Bcast' | sed 's/^.*inet addr:\([0-9\.]\+\).*$/\1/'`"

case "$_action" in
    raise)
        # do nothing if recording_disabled is already raised
        ./sbin/flag.php stat $RECORDING_DISABLED && break;

        # write $_name to $RECORDING_DISABLED flag, so we know why it was raised
        ./sbin/flag.php raise $RECORDING_DISABLED $_name
        wget -q -O /dev/null "http://server.cv-internal.com/ezfw/service/hospital_events.php?category=video&event=recorded_video_off&ip=$myIP"

        ;;
    lower)
        # only lower $RECORDING_DISABLED flag if the lowered flag is why it was raised
        _reason=`./sbin/flag.php read $RECORDING_DISABLED $_name`

        # do nothing if flag is not raised
        [ "$?" != "0" ] && break; 

        # do nothing if $_name is not written in flag
        [ "$_reason" = "$_name" ] || break;

        # lower $RECORDING_DISABLED flag
        ./sbin/flag.php lower $RECORDING_DISABLED
        wget -q -O /dev/null "http://server.cv-internal.com/ezfw/service/hospital_events.php?category=video&event=recorded_video_on&ip=$myIP"
        ;;
    *)
        # unsupported action
        exit 0;
        ;;
esac;

