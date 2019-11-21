#!/bin/bash

f=/usr/local/ezfw/web_files/img/zones/bed_area.vbr.png
if [ -e $f ] ; then
    echo "Removing now unused $f"
    rm -f $f
fi
