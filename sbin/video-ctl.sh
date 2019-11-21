#!/bin/bash

. /usr/local/ezfw/lib/shell/ezfw-shell-login

function identify_driver () {
    # Figure out which card we're using

    DRVR=
    grep -q -e '00707444' -e '14f15b7a' /proc/bus/pci/devices && DRVR=cx18 #WinTV HVR-1600 by Hauppauge
    grep -q -e '00700009' -e '44440016' /proc/bus/pci/devices && DRVR=ivtv #WinTV PVR-150 by Hauppauge
    
    if [ "x$DRVR" = "x" ]; then
        echo "ERROR: Unable to find compatable video capture device on pci bus!" >&2
        echo "  Defaulting to cx18." >&2
        DRVR=cx18
    fi

    echo $DRVR;
    return;
}

function service_paths () {
    local path=
    for ((i=0; i<${#SERVICES[*]}; i++)); do
        [ "x${SERVICES[$i]}" = "x" ] && continue;
        path="$path /etc/service/${SERVICES[$i]}"
    done;
    echo $path;
    return;
}

function wait_services () {
    local act=$1
    local ret=0

    echo "waiting for services ${SERVICES[*]} to $act...";

    for ((lp=5; lp>0; lp--)); do
        ret=0
        for ((i=0; i<${#SERVICES[*]}; i++)); do     
            local _lc=$( cvps ${SERVICES[$i]} | wc -l  )
            if (( $_lc > 1 )); then
                [ "$act" == "stop" ] && let ret++;
            else
                [ "$act" == "start" ] && let ret++;
            fi
        done

        (( $ret == 0 )) && return 0;
    done;

    return $ret;
}

function start_cam () {
    local _svcs=

    /usr/bin/svc -u $( service_paths ) || return $?

    wait_services start
    sleep 2;
    return 0;
}

function stop_cam () {
    local _c=1
    local _t=5

    while true; do 
        let _c--;
        if (( _c == 0 )); then 
            /usr/bin/svc -d $( service_paths )
            sleep 2;
            let _c=10
            let _t--;
            (( t )) || return 1;
        fi
    
        wait_services stop && break;
        
        sleep 2;
    done;

    return 0;
}



function unload () {
    local ret=
    stop_cam
    # just remove them all...
    rmmod cx18_alsa
    rmmod cx18;
    rmmod ivtv;
    return 0;
}

function load () {
    local ret=
    # just load them all...
    modprobe cx18;
    modprobe ivtv;

    /usr/local/ezfw/sbin/configure_card.sh;

    return 0;
}

function keep_service () {
    local svc=$1
    local tmp=( ${SERVICES[@]} )
    local cng=0

    for ((i = 0; i < ${#tmp}; i++)); do
        [ "${tmp[$i]}" = $svc ] || continue;

        SERVICES=( )
        local k=0
        for ((j = 0; j < ${#tmp}-1; j++)); do
            (( $j == $i )) && let k++;
            SERVICES[$j]="${tmp[$k]}"
            let k++;
        done;
        return 0;
    done

    return 0;
}

_base=`basename $0`
#echo "called $_base $@" | tee -a /tmp/video_ctrl.log

action=$1
shift;
SERVICES=( yuv_to_jpeg copier stream_test )

while (( $# )); do
    case "$1" in
        --keep)
            keep_service $2;
            shift;
            ;;
        *)
            echo "Invalid parameter '$1'";
            exit 1;
            ;;
    esac
    shift;
done;

case "$_base" in 
    video-ctl\.sh)
        case "$action" in
            stop)
                unload
                exit $?
                ;;
            start)
                load
                exit $?
                ;;
            restart)
                unload || exit $?
                load
                exit $?
                ;;
            identify)
                identify_driver 
                exit $?
                ;;
            *)
                echo "Usage: video-ctl.sh start|stop|restart"
                exit 1;
                ;;
        esac
        return $?
    ;;
    camera-ctl.sh)
        case "$action" in 
            start)
                start_cam;
                ;;
            stop)
                stop_cam;
                ;;
            restart)
                stop_cam
                start_cam
                exit $?;
                ;;
            *)
                exit 1;
                ;;
        esac

        exit 0;
    ;;
    *)
        exit 1;
    ;;
esac


