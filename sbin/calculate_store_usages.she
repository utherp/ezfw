#!/bin/bash
#####################################################################################
#
# calculate_store_usages.sh:
#   written by Stephen Ecker, 2010-08-15
#
# This script determines the bytes that each store will use from its target device.
# Each store is named in cv_storage_stores, has an expression defining its usage of the
# total usage of the storage device declared in cv_storage_store_usages (see Usage 
# Expressions below), and a storage device declared in cv_storage_store_devices,
# which maps to a name declared in cv_storage_devices.
#
# Each storage device is named in cv_storage_devices, has a target mount declared
# in cv_storage_device_targets, and an expression defining its usage of the total disk
# space in cv_storage_device_usages.
# see Parameters below.
#

############################################
# sql command run to pipe queries through
MYSQL_CMD="mysql -u root -pib4exac -Dezfw"


##########################################################
# Usage Expressions:
#   All values declared cv_storage_device_usages, cv_storage_store_usages and the value 
#   declared in cv_storage_video_bitrate may be expressions which support the following
#   syntax:
#       "num[suf][ opr num[suf][ opr num[suf]....]]"
#
#     num: a decimal number
#     opr: an arithmetic operator (+, -, *, /)
#     suf: a suffix defining the expansion of 'num' from Suffixes below.
#
#   Suffixes:  (where nn is the number preceeding the suffix)
#               NOTE: for size expansions, the number MUST be an integer, no decimal point!
#     Size Type:
#       nnB:  bytes (really just removes the 'B', here for uniformity)
#       nnK:  kilobytes ( nn * 1024 )
#       nnM:  megabytes ( nn * 1000 * 1024 )
#       nnG:  gigabytes ( nn * 10000000 * 1024 )
#
#     Percent:
#       nn%:  percentage of total usage space (of device for store, of total blocks for device)
#
#     Duration:
#       nnH:  bytes needed for nn hours of video based on cv_video_bitrate
#       nnD:  bytes needed for nn days of video
#
#       NOTE: bytes needed per hour is calculated as (cv_video_bitrate * 60 * 60 / 8)
#
#   Also NOTE: cv_storage_video_bitrate may NOT contain the suffixes 'H', 'D' or '%'
#   
#   Usage examples:
#     "90% - 10G"           (90 percent of total space, minus 10 gigs)
#     "70% - 4D"            (70 percent of total space minus 4 days of video)
#     "100% - 10G - 24H"    (100% - 10Gb - 24 hours of video).
#
#


##########################################################
# Parameters:
#   All the following parameters may be overloaded in the
#   hospital.conf file which is loaded just after defining
#   all the default values.
#
#
# Overriding Parameters:
#
#   Parameters may be overridden in the hospital.conf file.
#   While the values below are all grouped together
#   in arrays, they do not have to be overridden that way.
#   you may override the entire store usage list as such:
#       cv_storage_store_usage=( these are my stores );
#
#   ...but, if you only wish to change one, say buffer, then
#   this syntax is also supported:
#       cv_storage_store_usage_buffer="80% - 100M"
#
#   Any of the following lists may have elements overwritten
#   this way:  
#       cv_storage_device_target
#       cv_storage_device_usage
#       cv_storage_store_device
#       cv_storage_store_usage
#
#   If you are defining a store or device which isn't in the
#   default list, there is no need to add the store or device's
#   name to the name list, when parsing overrides, new names
#   will automatically be added (NOTE: this will NOT add a
#   non-existant store to the database!)
#
#   e.g.: adding a store called 'testStore', using 50% of
#   the device called 'rootfs':
#       cv_storage_store_device_testStore='rootfs'
#       cv_storage_store_usage_testStore="50%"
#

######################
# Video bitrate:
#   in bits (100000, or 100000b), kilobits (500K) or megabits (2M)
#   NOTE: this does NOT set the rate, (at least, not based on its usage
#   in this script).  its just a hint used for calculating estimated
#   size of a video over a given duration).  The default here is 2 megabits
#   which currently the ceiling of the variable bitrate range used on
#   the nodes.
cv_storage_video_bitrate=2M


######################
# Storage devices:
#   Custom target name used to identify mounts for 
#   stores' usages.  This is NOT the device's dev
#   path: /dev/sda1 == WRONG.  Dev paths are not
#   used, the actual storage space is determined
#   by 'df' using the filesystem mounted at the
#   directory in cv_storage_device_targets.
cv_storage_device=( 
    bufferfs      # flash buffer filesystem
    historyfs     # history filesystem
    archivefs     # archive filesystem
    rootfs        # root filesystem
)
# NOTE: the history storage device is actually the
# same filesystem as the archive storage device,
# we seperate them because the archive filesystems
# use percentages, where history is a specific
# amount of time, so the archive storage device
# uses a given percent minus the static space the
# history store uses.


######################
# Storage device target:  (corralating with cv_storage_devices)
#   A directory path contained on the mounted filesystem of which
#   the total space is derived, used for dynamic space allocation.
#   (used by 'df' to determine mount's total drive space)
cv_storage_device_target=(
    /usr/local/ezfw/video/buffer    # buffer filesystem
    /usr/local/ezfw/video/archive   # history filesystem (allocation of archive)
    /usr/local/ezfw/video/archive   # archive filesystem
    /                                   # root filesystem (not currently used, but may be someday for panics)
)

######################
# Storage device usages:  (corralating with cv_storage_devices)
#   Specifies how much space is to be used for the storage device.
#   The values are usage values (See Usage Values above for description)
cv_storage_device_usage=(
    1600M       # 1.6G for buffer storage (bug #589, using 85% caused most of var 
                # file system to be used if the flash device was missing)
    90%-80G     # history store, 90% - 80Gb for movieview cache
    0%          # archive filesystem (95% - 8 days - 80Gb (reserved for movieview cache))
    50%         # (no store currently uses root fs, but maybe for panic in future)
)

######################
# Store names:
#   A list containing the names of all stores
#   this script is to update
cv_storage_store=(
    buffer          # buffer store (where the video goes initially as it is created in realtime)
    history         # history store (where video is kept for a set amount of time)
    purge           # purge store (where video goes before it is deleted, like recycle bin)
    archive3M       # most significant video from the past 3 months (or longer if none have replaced them)
    archive6M       # most significant video from the past 6 months
    archive9M       # most significant video from the past 9 months
    archivePerm     # most significant video ever
)

######################
# Store's designated device names:
#   indexes corralate with cv_storage_stores, the values are
#   the name of a storage device in cv_storage_devices
cv_storage_store_device=(
    bufferfs      # buffer is only store which uses the buffer device
                  # all other stores currently use archive store...
    historyfs     # ...history
    historyfs     # ...purge
    archivefs     # ...archive 3M
    archivefs     # ... 6M
    archivefs     # ... 9M
    archivefs     # ... Perm
)

######################
# Store's usage expressions:
#   the expression expanding to the bytes used
#   of its storage device's total usage bytes
#   (see Usage Expressions above)
cv_storage_store_usage=(
    100%    # buffer store will use all allocated space from the 'buffer' storage device
    95%     # history store uses all of allocated space (8 days from archive mount)
    5%      # purge store will use a mear 2.5% of the archive space
    2      # 3 Month store uses 45.5% of archive space
    2      # 6 Month uses 23%...
    2      # 9 Month uses 17%
    2      # Perm uses 12%
)

##########################################################
##########################################################
# Functions...
#

function parse_overrides () {
    for name in device_usage device_target store_usage store_device; do
        parse_param_overrides "cv_storage_$name";
    done
}

function parse_param_overrides () {
    local list_override="$1";
    local names_list_name=`sed 's,_[^_]*$,,' <<<"$list_override"`
    names_list=( `eval echo "\\${${names_list_name}[*]}" ` )
    local params=( `eval echo "\\${!${list_override}_*}"` );

    for ((i = 0; i < ${#params[*]}; i++)) {
        local pname=`sed 's,^'${list_override}'_,,' <<<"${params[$i]}"`;
        local value=`eval echo "\\$${params[$i]}"`;
        
        # find pname in names list
        local idx=-1;
        for ((n = 0; n < ${#names_list[*]}; n++)) {
            if [ "$pname" = "${names_list[$n]}" ]; then
                idx=$n;
                break;
            fi
        }
        if [ "$idx" = "-1" ]; then 
            idx=${#names_list[*]}
            eval $names_list_name[$idx]="$pname";
            names_list[$idx]="$pname"
        fi

        eval "$list_override[$idx]='$value'";
    }

    return;
}


######################################
#
#
function display_store_usages () {
    #################################
    # Execute select query
    #
    local query_ret="`$MYSQL_CMD <<<"select tag as 'Store', max_usage_bytes as 'Usage' from stores"`"
    
    ################################
    # Parse select query response
    #
    
    # split by line
    local IFS=$'\n';
    local lines=( $query_ret )
    IFS=$' \t\n';
    
    ################################
    # output store names and their
    # new disk usages
    #
    
    local max_store_name_len=0
    # get the longest store name (for alignment)
    for ((i=1; i<${#lines[*]}; i++)) { 
        local vals=( ${lines[$i]} ); 
        if [ ${#vals[0]} -gt $max_store_name_len ]; then
            max_store_name_len=${#vals[0]};
        fi
    }
    
    for ((i=1; i<${#lines[*]}; i++)) {
        local vals=( ${lines[$i]} );
        # pad name with extra spaces to align the columns
        while [ ${#vals[0]} -lt $max_store_name_len ]; do vals[0]=" ${vals[0]}"; done;
        printf "${vals[0]}  |  `readable_number "${vals[1]}"`\n";
        [[ "${vals[0]}" =~ ' *Store' ]] && printf -- "---------------------------------------\n";
    }

    return;
}
    

##########################
# readable_number N;
#   prints number N with every 3 places seperated by a comma
#   e.g.: readable_number 12345; # prints '12,345'
#
function readable_number () {
    local val=$1
    [[ "$val" =~ "[^0-9\.]" ]] && echo "$val" && return;
    [ -z "$val" ] && return "0";
    local out=''
    local lastz=''
    while [ $val -gt 0 ]; do
        local tmp=$(( $val % 1000 ));
        local str=''
        [ $tmp -lt 100 ] && str='0'
        [ $tmp -lt 10 ] && str="0$str"
        [ -z "$out" ] && out="$tmp" || out="$tmp,$lastz$out"
        lastz="$str";
        val=$(( $val / 1000 ));
    done;
    echo "$out";
}

#######################################
# get_mount_total_bytes /path/to/mnt
#   prints the total space on a mount point in bytes
#
function get_mount_total_bytes () {
    local path=$1;
    local output=`/bin/df "$path"`;
    local ret=$?;
    if [ "$ret" != '0' ]; then
        echo "Error getting total usage bytes for '$path'" >&2;
        return $ret;
    fi
    local kblocks=`tail -n1 <<<"$output" | awk '{print $2}'`;
    echo "$(( $kblocks * 1024 ))";
}

#######################################################
# expand_value TOTAL BPS EXPR
#   TOTAL: the total number of bytes available on dev
#   BPS:   Bits per second of the video
#   EXPR:  usage expression as described above at "Usage Expressions"
# returns an expanded algorithm to solve for EXPR
#
function expand_value () {
    local total=$1;
    local bps=$2;
    local exp=$3;

    sed -e 's,\([0-9]*\)[gG],\1000M,g' \
        -e 's,\([0-9]*\)[mM],\1000K,g' \
        -e 's,\([0-9]*\)[kK],( \1*1024 ),g' \
        -e 's,\([0-9]*\)[bB],\1,g' \
        -e 's,\([0-9\.]*\)[dD],( \1*24H ),g' \
        -e 's,\([0-9\.]*\)[hH],( \1*BPH ),g' \
        -e 's,\([0-9\.]*\)%,((TOTAL) * \1 / 100),g' \
        -e "s,TOTAL,$total,g" \
        -e "s,BPH,($bps*60*60/8),g" <<<"$exp"
}

#################################################################
# get_usage TOTAL_EXPR BPS_EXPR USAGE_EXPR
#   TOTAL_EXPR: expression defining total usage
#   BPS_EXPR: expression defining video bits per seconds
#   USAGE_EXPR: expression defining usage from total and bps
# returns an expanded algorithm to solve for USAGE_EXPR
#
function get_usage () {
    local total=`expand_value 0 0 "$1"`;
    local bps=`expand_value "$total" 0 "$2"`
    local usage=$3;
    expand_value "$total" "$bps" "$usage";
}


#################################################################
# get_device_usage PATH BPS
#   PATH:  path to mount point of device target
#   BPS:   bits per second of the video
# returns total usage bytes for device
#
function get_device_usage () {
    local path=$1;
    local bps=$2;
    local usage=$3;
    local total=`get_mount_total_bytes "$path"`;
    get_usage "$total" "$bps" "$usage";
}


##########################################################
##########################################################
# Main
#

######################################
# Now we include the ezfw shell functions lib
# which contains the function for loading the
# hospital.conf values, which may contain
# overrides to the above defaults
. /usr/local/ezfw/lib/shell/ezfw-shell-common

# load hospital.conf file
load_hospital_conf;

# parse override values
parse_overrides;


# this section is a fix to reduce the history size for
# nodes with smaller hard drives.
_archive_mount_size=`get_mount_total_bytes "/usr/local/ezfw/video/archive/"`
if (( $_archive_mount_size < 300000000000 )); then
    echo "NOTE: archive filesystem < 300G, using 4 day history";
    cv_storage_device_usage[1]=4D
    cv_storage_device_usage[2]=95%-4D-80G
elif (( $_archive_mount_size < 400000000000 )); then
    echo "NOTE: archive filesystem < 400G, using 6 day history";
    cv_storage_device_usage[1]=6D
    cv_storage_device_usage[2]=95%-6D-80G
fi


#############################################
# calculate storage device usage
#
for ((i = 0; i < ${#cv_storage_device[*]}; i++)) {
    dev_usage=`get_device_usage "${cv_storage_device_target[$i]}" "$cv_storage_video_bitrate" "${cv_storage_device_usage[$i]}"`
    eval "dev_total_${cv_storage_device[$i]}=\"$dev_usage\"";
}

#############################################
# calculate store usages
#
store_usages=( )
for ((i = 0; i < ${#cv_storage_store[*]}; i++)) {
    dev=${cv_storage_store_device[$i]}
    total=`eval 'echo "$dev_total_'${cv_storage_store_device[$i]}'"'`;
    if [ -z "$total" ]; then
        echo "Error: no total storage usage found for device '${cv_storage_store_device[$i]}', maybe a mispelled store device name?" >&2
        store_usages[$i]=0
        continue;
    fi
    store_usages[$i]=`get_usage "$total" "$cv_storage_video_bitrate" "${cv_storage_store_usage[$i]}"`
}

#############################################
# Generate SQL Query
#
query=''
for ((i = 0; i < ${#cv_storage_store[*]}; i++)) {
    query="$query update stores set max_usage_bytes=CONVERT(${store_usages[$i]}, UNSIGNED) where tag='${cv_storage_store[$i]}';\n";
#    [ -z "$query" ] && query="select " || query="$query, ";
#    query="$query CONVERT(${store_usages[$i]}, UNSIGNED) as '${cv_storage_store[$i]}'";
}

###############################
# debug query output?
#
echo "Full query: '$query'" >&2

echo "before update:"
display_store_usages;

##################################
# Execute update queries
#
query_ret="`$MYSQL_CMD <<<"$query"`";

echo "after update:"
display_store_usages;

exit 0;
