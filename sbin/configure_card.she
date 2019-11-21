#!/bin/bash

. /etc/hospital.conf
cd /usr/local/ezfw/;

# convinience function
function get_video_param () {
    eval "echo \${cv_video_"$1"_"$2":-\${cv_video_default_"$2"}}"
}

cv_video_default_std=${cv_video_default_std:-ntsc}
# some safe defaults and cx18 overrides
cv_video_default_input=${cv_video_default_input:-2}
cv_video_default_width=${cv_video_default_width:-352}
cv_video_default_height=${cv_video_default_height:-288}
cv_video_default_bitrate=${cv_video_default_bitrate:-1000000}
cv_video_default_peak_bitrate=${cv_video_default_peak_bitrate:-2000000}
cv_video_default_device=${cv_video_default_device:-/dev/video32}

cv_video_cx18_width=${cv_cx18_video_width:-352}
cv_video_cx18_height=${cv_cx18_video_height:-480}

DRVR=`./sbin/video-ctl.sh identify`
# Get video parameters potentially set in hospital.conf

video_std=$( get_video_param $DRVR std )
video_input=$( get_video_param $DRVR input )
video_height=$( get_video_param $DRVR height )
video_width=$( get_video_param $DRVR width )
video_bitrate=$( get_video_param $DRVR bitrate )
video_peak_bitrate=$( get_video_param $DRVR peak_bitrate )
video_device=$( get_video_param $DRVR device )

# write parameters out to flags files for synchronicity
echo "$video_std" > flags/video_std;
echo "$video_input" > flags/video_input;
echo "$video_height" > flags/video_height;
echo "$video_width" > flags/video_width;
echo "$video_bitrate" > flags/video_bitrate;
echo "$video_peak_bitrate" > flags/video_peak_bitrate;
echo "$video_device" > flags/video_device;

function test_signal_strength () {
    v4l2-ctl --all | grep "strength.* 0%" >/dev/null && return 1;
    return 0;
}

function configure_card (){
    echo "configuring"
    if ! which v4l2-ctl; then
    # Old legacy stuff (first run machines w/ kernels < 2.6.18)
    
        ${DRVR}ctl --set-format=width=${video_width},height=${video_height}
        ${DRVR}ctl -cbitrate=${video_bitrate}
        ${DRVR}ctl -cbitrate_peak=${video_peak_bitrate}
        ${DRVR}ctl -p${video_input}
    
        exit;
    fi
    
    # Newer machines ( kernels >= 2.6.18 )
    
    # set the input
    v4l2-ctl -i ${video_input}
    # set the video standard (ntsc/pal/seccam/etc..)
    v4l2-ctl -s ${video_std}
    # set the bitrate range
    v4l2-ctl -cvideo_bitrate=${video_bitrate},video_peak_bitrate=${video_peak_bitrate}
    # set the video size
    v4l2-ctl -vwidth=${video_width},height=${video_height}
}

#We're giving a best effort try here at configuring the card.  We configure it, and then test
#the video signal if it's a card which supports cx18.  If the signal strength is 0, we rmmod,
#modprobe, and reconfigure the card again.
configure_card
if [ "$DRVR" = "cx18" ]; then
    #Test the signal strength, and re-run if it did not pass
    if ! test_signal_strength; then 
        rmmod cx18_alsa cx18
        modprobe cx18
        configure_card
        if ! test_signal_strength; then
            # reprobe did not fix problem
            echo "disconnected" > flags/video_input_signal
            exit 1;
        fi
    fi
fi

echo "connected" > flags/video_input_signal
exit 0;


