#!/bin/bash

#
# Probes RCP hardware and maps it to an RCP version number.
#
# *** You should never NEVER *NEVER* rely on this for anything! ***
# *** This is purely a quick way to probe the hardware for      ***
# *** inventory and/or debugging.                               ***
#

deb_ver=$( cat /etc/debian_version )
case "${deb_ver%%.*}" in
    4) deb_name="Etch"    ;;
    5) deb_name="Lenny"   ;;
    6) deb_name="Squeeze" ;;
    *) deb_name="Unknown" ;;
esac
kernel_ver=$( uname -r )
card_type=$( v4l2-ctl --info | awk '/Card type/ {print $NF}' )
root_disk=$( mount | awk '/on \/ / {print $1}' )
wdt_com=/usr/local/sbin/wdt_com.pl
if $wdt_com test >/dev/null 2>&1 ; then
    wdt_ver="$( $wdt_com get major_version ).$( $wdt_com get minor_version )"
else
    wdt_ver="n/a"
fi        

function show_results() {
    echo "RCP Version: $1"
    echo "Debian Version: $deb_ver ($deb_name)"
    echo "Kernel Version: $kernel_ver"
    echo "Capture Card Type: $card_type"
    echo "Root Disk Device: $root_disk"
    echo "WDT PIC Version: $wdt_ver"
    exit ${2:-0}
}

if [ ".$card_type" = ".HVR-1600" -a ".$wdt_ver" != ".n/a" -a ".${root_disk#/dev/s}" != ".$root_disk" ] ; then
    show_results 2.7
elif [ ".$card_type" = ".HVR-1600" -a ".$wdt_ver" = ".n/a" -a ".${root_disk#/dev/s}" != ".$root_disk" ] ; then
    show_results 2.6
elif [ ".$card_type" = ".PVR-150" -a ".$wdt_ver" = ".n/a" -a ".${root_disk#/dev/s}" != ".$root_disk" ] ; then
    show_results 2.5
elif [ ".$card_type" = ".PVR-150" -a ".$wdt_ver" = ".n/a" -a ".${root_disk#/dev/h}" != ".$root_disk" ] ; then
    show_results 2.0
else
    show_results Unknown 1
fi
