<?php
    class secureService extends service {
        public function weigh_state ($state, $last = false, $count = false) {
            //print "called to weigh state {$state->type}\n";
            $dur = $state->duration;
            if ($dur == -1)
                $dur = time() - $state->start;

            $vid = $state->video;
            if (is_a($vid, 'video') && $dur > $vid->duration) {
                if ($state->start > $vid->start)
                    $dur = $vid->end - $state->start;
                else
                    $dur = $state->end - $vid->start;
            }

            /* where did this const of 498 come from? --Stephen */
            if ($dur > 498)
                $dur = 498;


            if (!$dur) $dur = 1;

            $w = ($dur / ((log(500-$dur)+.001)*10) / 10);

            switch ($state->type) {
                case ('detection'):
                    if ($state->name != 'full')
                        $w *= .05;
                    break;
                case ('privacy'):
                    $w *= -.5;   /* privacy states should count negativly for saving the video */
                    break;
                default:
                    $w *= .5;   /* drop unknown type weights */
            }

            return $w;
        }
        public function weigh_event ($event, $last = false, $count = false) {
            switch ($event->type) {
                case ('vbr'):
                    return 1.0;
                default:
                    return 0.1;
            }
        }

    }
