#!/bin/bash

BUFFER_DEVICE_FSLABEL=CV_flashide
# warning: could be a sata device so instead do mounts by Filesystem Label
#ARCHIVE_DEVICE=/dev/sda3
#ARCHIVE_DEVICE=/dev/hda3
ARCHIVE_DEVICE_FSLABEL=CV_archive

function mount_video () {
    local DEVICE_FSLABEL=$1
    local DIR=$2
    echo "Asked to mount label $DEVICE_FSLABEL to $DIR"
    [ "x$DIR" = "x" ] && DIR="$DEVICE_FSLABEL"
    mount | grep -q " /usr/local/ezfw/video/${DIR} " && return 0;
    echo "Mounting label $DEVICE_FSLABEL to $DIR"
    [ -h video/${DIR} ] && rm video/${DIR};
    [ -d video/${DIR} ] || mkdir -p video/${DIR};
    if [ "$DIR" = "$DEVICE_FSLABEL" ]; then
        mount video/${DIR};
    else
        mount -L ${DEVICE_FSLABEL} video/${DIR};
    fi
    return $?
}

function link_video () {
    echo "Asked to link video dir $1"
    mount | grep -q " /usr/local/ezfw/video/$1 " && return 0;
    echo "Linking video dir $1"
    [ -d video/$1 ] && rm -Rf video/$1;
    if [ "$1" = "buffer" ]; then
        [ -d video/archive/buffer_backup ] || mkdir -p video/archive/buffer_backup;
        ln -s archive/buffer_backup video/buffer;
    else 
        [ -d /var/video/$1 ] || mkdir -p /var/video/$1;
        ln -s /var/video/$1 video/$1;
    fi
    return 0;
}

# mount filesystems for video
echo "`date`: Entered mount_fs.sh"
if [ -d /usr/local/ezfw ]; then
    pushd /usr/local/ezfw >/dev/null
    [ -d /var/video ] || mkdir -p /var/video
    if [ -h video ]; then
        rm video;
        mkdir video;
    fi
    if [ -e /dev/disk/by-label/${BUFFER_DEVICE_FSLABEL} ]; then
        if ! mount_video ${BUFFER_DEVICE_FSLABEL} buffer; then
            #mkfs.ext3 ${BUFFER_DEVICE};
            #Instead call CV_dboot_creation.sh in ./sbin
            /usr/local/ezfw/sbin/CV_dboot_creation.sh
            mount_video ${BUFFER_DEVICE_FSLABEL} buffer || link_video buffer;
        fi
    else
        link_video buffer;
    fi

    mount_video ${ARCHIVE_DEVICE_FSLABEL} archive || link_video archive;

    popd >/dev/null
fi
