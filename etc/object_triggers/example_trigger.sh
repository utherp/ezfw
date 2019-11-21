#!/bin/bash
#
# This is an example of an object trigger. 
# When an object gets set, removed or changed, all files set
# executable under /usr/local/ezfw/etc/object_triggers/[OBJECT_TYPE]/
# get executed.  The object type is currently one of room, node or patient
# but more may be added later (such as site, services, doctors, ect...)
#

TYPE=$1     # the object type (patient, room, node, ...)
ACTION=$2   # the action performed on the cache (set, remove, change)
OLD_FILE=$3 # the filename of the previous cache item (will be deleted after triggers run)
            # (for action 'set', this value will be an empty string)
NEW_FILE=$4 # thie filename of the new cache item (this is the final cache filename)
            # (for action 'remove', this value will be an empty string)

if [ -z "$TYPE" -o -z "$ACTION" ]; then
    echo "insufficient parameters passed!";
    exit 1;
fi

case "$TYPE" in
    patient) 
        # do something for actions on patient cache
        # the script would only be run on 'patient' if its in 
        # the object_triggers/patient directory.  The type
        # value is passed in case you wish to link a trigger script
        # in multiple directories to operate on multiple object types
        case "$ACTION" in
            set)
                # this is a new cache object, no previous 'patient' cache existed, $OLD_FILE is empty
                echo "new patient object cached"
            ;;
            change)
                # the patient object in cache has changed, the previous cache is in $OLD_FILE
                echo "patient object cache changed"
            ;;
            remove)
                # the patient object in cache has been removed, $NEW_FILE is empty
                echo "patient object cache removed"
            ;;
            *)
                echo "An unknown action '$ACTION' was specified!"
                exit 2;
            ;;
        esac;
    ;;
    room)
        # do something for actions on room object cache
        # I don't think further elaboration is nessessary...
        echo "action '$ACTION' on room object."
    ;;
    *)
        echo "an unhandled object type '$TYPE' was specified!";
        exit 3;
    ;;
esac

exit 0;


