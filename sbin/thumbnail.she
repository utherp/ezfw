#!/bin/bash

PIDFILE=/tmp/thumbnail.pid
CACHE_PATH="/usr/local/ezfw/video/cache/thumbnails"



function read_pid () {
    cat "$PIDFILE";
}

function remove_pid () {
    rm "$PIDFILE"
}

function wait_for_pid () {
    local matchstr=`basename "$0"`
    while [ -e "$PIDFILE" ]; do
        TPID=`cat "$PIDFILE"`;
        if grep -q "$matchstr" /proc/$TPID/cmdline; then
            # thumbnailer still running... wait for it to complete
            sleep 1;
            continue;
        fi
        # invalid thumbnailer pid
        break;
    done;

    echo "$$" > "$PIDFILE"
    trap remove_pid EXIT;
    return;
}


if [ "x$3" = "x" ]; then
    printf "Usage: $0 input_filename output_base_name offset\n";
    exit 1;
fi

INPUT="$1"
OUTNAME="$2"
OFFSET="$3"

wait_for_pid;

if ! mkdir /tmp/thumb_$$; then
    printf "Error: could not make temp directory!\n";
    exit 2;
fi

if ! /usr/local/bin/ffmpeg -ss $OFFSET -vframes 2 -i $INPUT -r 2 -y -f image2 -sameq /tmp/thumb_$$/full_%d.jpg -s 100x60 -r 2 -y -f image2 -sameq /tmp/thumb_$$/mini_%d.jpg; then
    printf "\n\nError: mplayer failed!\n";
    [ -d /tmp/thumb_$$ ] && rm -Rf /tmp/thumb_$$;
    exit 3;
fi

if [ ! -e /tmp/thumb_$$/full_2.jpg ]; then
    printf "\n\nError: mplayer failed to generate thumbnail!\n";
    [ -d /tmp/thumb_$$ ] && rm -Rf /tmp/thumb_$$;
    exit 4;
fi

if ! mv /tmp/thumb_$$/full_2.jpg $CACHE_PATH/full/${OUTNAME}.jpg; then
    printf "\n\nError: failed moving thumbnail to cache path!\n";
    [ -d /tmp/thumb_$$ ] && rm -Rf /tmp/thumb_$$;
    exit 5;
fi

if ! mv /tmp/thumb_$$/mini_2.jpg $CACHE_PATH/mini/${OUTNAME}.jpg; then
    printf "\n\nError: failed moving thumbnail to cache path!\n";
    [ -d /tmp/thumb_$$ ] && rm -Rf /tmp/thumb_$$;
    exit 5;
fi

[ -d /tmp/thumb_$$ ] && rm -Rf /tmp/thumb_$$;

exit 0;

