<?php
    class vbrService extends service {

        public function weigh_state ($state, $last = false, $count = false) {
            $dur = $state->duration;
            if ($dur == -1) return 0;

            $vid = $state->video;
            if (is_a($vid, 'video') && $dur > $vid->duration) {
                if ($state->start > $vid->start)
                    $dur = $vid->end - $state->start;
                else
                    $dur = $start->end - $vid->start;
            }

            switch ($state->name) {
                case ('armed'):
                    $mul = .001;
                    break;
                case ('rails'):
                    $mul = .0005;
                    break;
                default:
                    return 0;
            }

            return $dur * $mul;
        }

        public function weigh_event ($event, $last = false, $count = false) {
            switch ($event->type) {
                case ('alarm'):
                    return 1.0;
                default:
                    return 0;
            }
        }

        public function state_start ($st) {
            /* nothing currently... called when a state belonging to this service starts...
             * ...should state_overlay be called from here? */
            return;
        }

        public function state_end ($st) {
            /* nothing currently... called when a state belonging to this service ends */
        }

        public function state_overlay ($st) {
            load_libs('stream/overlay');
            $fn = streamOverlay::overlay_filename('state', $st->id);
            if (file_exists($fn))
                return true; /* overlay already exists */

            if ($st->type == 'rails') {
                $points = $st->points;
                $color = CV_VBR_UNARMED_COLOR;
            } else if ($st->type == 'armed') {
                /* get the points from the last vbr rails state */
                $draw_state = state::latest('vbr', 'rails', $st->name);
                if (!$draw_state) {
                    /* no previous rails state for this named collection */
                    return;
                }
                $points = $draw_state->points;
                /* lets make the ARMED color fully opaque so the unarmed rails aren't seen behind it in secureview */
                $color = explode(' ', CV_VBR_ARMED_COLOR);
                $color[3] = 0;
                $color = implode(' ', $color);
            } else {
                /* make overlays for armed and rails states only */
                return true;
            }

            $target = abs_path('web_files', 'images', 'zones', $st->name . '.vbr.png');
            $starttime = $st->start;
            if (!is_numeric($starttime))
                $starttime = strtotime($starttime);

            $overlay = new streamOverlay(FRAME_WIDTH, FRAME_HEIGHT);
            $overlay->type = 'state';
            $overlay->id = $st->id;

            $overlay->draw_rails($points, $color);
            $ret = $overlay->save();
            if (!$ret) return $ret;

            return true;
        }
    }
