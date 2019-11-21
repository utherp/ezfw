<?php
    require_once('uther.ezfw.php');

/********************************************************************************************/
/****  State management functions ***********************************************************/
/********************************************************************************************/

    // Can be called with the name/type/ts args shifted down one position
    function &start_state (&$stateRef, $name, $type = false, $ts = false) {
        global $states;

        $state = false;
        $list = false;
        if ($stateRef instanceof state) {
            $state =& $stateRef;
            list ($ts, $name, $type) = array($name, $state->name, $state->type);
        } else {
            if (is_array($stateRef)) {
                $list =& $stateRef;
            } else if (is_string($stateRef)) {
                $list =& $states;
                // This call has the name/type/ts args shifted down one position
                list ($name, $type, $ts) = array($stateRef, $name, $type);
            }
            $state =& $list[$name];
        }

        if ($state) {
            /* there is already a $name state, so we'll pass
             * into state_active, then return the state obj
             * (calling state_active is the prefered method
             * anyway)
             */
            state_active($state, $name, $type, $ts);
            return $states[$name];
        }

        $state = new state();
        $state->video_id = true;
        $state->service_tag = 'secure';
        $state->type = $type;
        $state->name = $name;
        $state->start = $ts;
        $state->recalc = 1;

        /* activity is not a real property of the state class,
         * it will be saved in the meta field, this is a temporary
         * storage of the last time we received a trigger
         * message about this state, used to determine if
         * a state has gone stale (we did not receive an
         * inactive message regarding this state, and it
         * has been longer than CV_VBR_STATE_STALE_TIMEOUT seconds
         */
        $state->activity = $ts;
        
        $state->save();

        $states[$name] =& $state;

        return $state;
    }

    function &close_state (&$stateRef, $name = false) {
        $state = false;
        $list = false;

        if ($stateRef instanceof state) {
            $state =& $stateRef;
            $name = $state->name;
        } else {
            if (is_array($stateRef))
                $list =& $stateRef;
            else if (is_string($stateRef)) {
                global $states;
                $list =& $states;
                $name = $stateRef;
            }
            $state =& $list[$name];
        }

        if (!$state) return NULL;
        if ($list) unset($list[$name]);

        if (!$state->end) {
            /* state does not have an ending time,
             * we either use the time of last activity,
             * or start time + CV_VBR_STATE_SPLICE_MAX if none set 
             * (shouldn't happen though)
             */
            if (!$state->activity) {
                if (!$state->start) {
                    /* never started??? */
                    $state->delete();
                    return NULL;
                }
                $state->end = $state->start + CV_VBR_STATE_SPLICE_MAX;
            } else {
                $state->end = $state->activity;
            }
        }

        # make sure to update ending time before saving
        if ( $state->activity > $state->end ) {
            $state->end = $state->activity;
        }

        if ($state->duration < CV_VBR_STATE_MINIMUM_DURATION) {
            // the duration is less that the configured minimum duration
            debugger("Throwing away '$name' zone state, duration < CV_VBR_STATE_MINIMUM_DURATION (" . CV_VBR_STATE_MINIMUM_DURATION . " seconds)", 3);
            $state->delete();
            return NULL;
        }

        // save the state and return it
        $state->save();

        return $state;
    }

    function state_latest_time (&$state) {
        $ts = $state->end;
        if (!$ts || ($ts < $state->activity))
            $ts = $state->activity;
        if (!$ts || ($ts < $state->start))
            $ts = $state->start;
        return $ts;
    }

    function is_state_continuable (&$state, $ts = false) {
        /* return true if this state's end time was less
         * than CV_VBR_STATE_SPLICE_MAX seconds before $ts, 
         * indicating that it should be reopened.
         * if the state has not been closed will
         * also return true
         */
        if (!$ts) $ts = time();
        $endtime = state_latest_time($state);
        return ($endtime >= ($ts - CV_VBR_STATE_SPLICE_MAX));
    }
    
    function is_state_stale (&$state, $ts = false) {
        /* returns true if this state's activity is
         * more than CV_VBR_STATE_STALE_TIMEOUT seconds before,
         * $ts indicating a stale state, such as if we
         * missed the inactive message
         */
        if (!$ts) $ts = time();
        $endtime = state_latest_time($state);
        return ($endtime < ($ts - CV_VBR_STATE_STALE_TIMEOUT));
    }

    // Can be called with name/ts in ts/param1 args
    function validate_state (&$stateRef, $ts = false, $param1 = false) {
        $state = false;
        $name = false;
        $list = false;
        if (is_object($stateRef)) {
            $state =& $stateRef;
            $name = $state->name;
        } else {
            if (is_array($stateRef)) {
                $list =& $stateRef;
            } else {
                global $states;
                $list =& $states;
            } 
            // name/ts valutes are in ts/param1 args
            list ($name, $ts) = array($ts, $param1);
            $state =& $list[$name];
        }
            
        if (!$state) return false;
        if (!$ts) $ts = time();

        $fail = '';
        if (is_state_stale($state, $ts)) {
            $fail = 'stale ';
        } else if (!is_state_continuable($state, $ts)) {
            $fail .= 'non-continuable';
        }

        if ($fail) {
            /* the existing state has either become stale or is not continuable
             * (see functions above for defintion of stale and continuable)
             */
            logger("Note: closing stale state '$name' ({$state->id}) on failed validation ($fail)", true);
            close_state($state);
            if (is_array($list)) unset($list[$name]);
            return false;
        }

        /* the state exists and is current */
        return true;
    }
    
    // Can be called with the name/type/ts args shifted down one position
    function &state_active (&$stateRef, $name, $type = false, $ts = false) {
        $state = false;
        $list = false;
        if ($stateRef instanceof state) {
            $state =& $stateRef;
            $name = $state->name;
            $type = $state->type;
        } else {
            if (is_array($stateRef)) {
                $list =& $stateRef;
            } else if (is_string($stateRef)) {
                global $states;
                $list =& $states;
                // This call has the name/type/ts args shifted down one position
                list ($name, $type, $ts) = array($stateRef, $name, $type);
            }
            $state =& $list[$name];
        }

        if (!$state) { 
            $state = start_state($name, $type, $ts);
            if ($list) $list[$name] =& $state;
        }

        if ($state->end) unset($state->end);
        $state->activity = $ts;
        $state->save();

        return $state;
    }

    // Can be called with name/ts in ts/param1 args
    function &state_inactive (&$stateRef, $ts, $param1 = false) {
        $state = false;
        $list = false;
        if ($stateRef instanceOf state) {
            $state =& $stateRef;
        } else {
            if (is_array($stateRef)) {
                $list =& $stateRef;
                // name/ts valutes are in ts/param1 args
                list ($name, $ts) = array($ts, $param1);
            } else if (is_string($stateRef)) {
                global $states;
                $list =& $states;
                $name = $stateRef;
            }
            $state =& $list[$name];
        }

        if (!$state) return true;

        /* set state's ending time, we hold on to this
         * state in case a new one starts within CV_VBR_STATE_SPLICE_MAX
         * seconds, in which case we'll reopen it.  A
         * periodic call to commit_states will close
         * this state when it has exceeded this timeout
         */
        $state->end = $ts;

        /* we also set the activity to $ts so a "continuable"
         * state does not appear stale 
         */
        $state->activity = $ts;

        return $state;
    }

    function commit_states (&$list) {
        $now = time();
        foreach ($list as $name => $state) {
            /* validate_state determines if the state
             * is invalid (stale or non-continuable)
             * if it is invalid, it will close it
             */
            if (validate_state($state, $now)) continue;
            unset($list[$name]);
        }
        return;
    }

    function close_stale_states (&$list) {
        $ts = time();
        foreach ($list as $name => $state) {
            if (is_state_stale($state, $ts)) {
                close_state($state);
                unset($list[$name]);
            }
        }
        return;
    }

    function close_all_states ($list = false) {
        if (!$list) {
            global $states;
            $list =& $states;
        }
        $ts = time();
        foreach ($list as $st) close_state($st);
        return;
    }

    function &reopen_all_states ($service = 'secure', $type = 'detection') {
//        global $states;
        $states = array();
        $resp = ezObj::fetch_all('state', 'service_tag = ? AND type = ? and end is null', array($service, $type));
        foreach ($resp as $s) {
            if (is_state_continuable($s)) {
                // state is continuable...
                $states[$s->name] =& $s;
                continue;
            }
            // state is not continuable... close it.
            close_state($s);
        }

        return $states;
    }
