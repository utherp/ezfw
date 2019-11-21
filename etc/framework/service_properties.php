<?php

    class service_properties {
        static $file_path = '/etc/hospital_services.conf';
        static $logfile = '/usr/local/ezfw/logs/service_properties.log';
        protected $properties = array();

        function __construct() {
            $this->parse_file();
        }

        private function log_error ( $msg ) { file_put_contents(self::$logfile, date(DATE_RSS) . "    $msg\n", FILE_APPEND); }

        public function parse_file ( $infile = '' ) {
            if ( $infile == '' ) { $infile = self::$file_path; }

            if ( !file_exists($infile) ) {
                $this->log_error("Config file '$infile' doesn't exist!");
                return false;
            }

            # clear anything that may be there now
            $this->properties = array();

            $lcount = 1;
            foreach( file($infile) as $line ) {
                if ( preg_match('/^[[:space:]]*([[:alnum:]_\.]+)[[:space:]]*=[[:space:]]*"(.*)<startblock>([[:print:]\n]*)$/', $line, $bits) > 0 ) {
                    # this line begins a block
                    $assembling = $bits[1];
                    $this->properties[$assembling] = $bits[3];
                } elseif ( preg_match('/^(.*)<endblock>(.*)$/', $line, $bits) > 0 ) {
                    # we're ending a block here, append everything infront of <endblock> and throw the rest away
                    $this->properties[$assembling] .= $bits[1];
                    unset($assembling);
                } elseif ( isset($assembling) ) {
                    # middle of a block, slap the whole line on the end of our current key
                    $this->properties[$assembling] .= $line;
                } elseif ( preg_match('/^[[:space:]]*([[:alnum:]_\.]+)[[:space:]]*=[[:space:]]*"(.*)"[^"]*$/', $line, $bits) > 0 ) {
                    # standard whatever="stuff" line
                    $this->properties[ $bits[1] ] = $bits[2];
                } elseif ( preg_match('/^[[:space:]]*#/', $line) > 0 || preg_match('/^[[:space:]]*$/', $line) > 0 ) {
                    # comments and blank lines - ignore
                } else {
                    # then what the hell is this line?
                    # note that if this happens in the middle of a block, it could easily cause
                    # the rest of the file to be marked as invalid
                    $this->log_error("Malformed line ($lcount) in '$infile'! Ignored.");
                }

                $lcount++;
            }

            return true;
        }

        public function get_property( $name ) { return $this->properties[$name]; }
        public function property_exists( $name ) { return array_key_exists($name, $this->properties); }
        public function get_service_properties( $svc ) { return $this->get_property_regex("/^$svc\..*$/"); }

        public function get_property_regex( $name, $multi = true ) {
            $result = array();
            foreach ( array_keys($this->properties) as $k ) {
                if ( preg_match($name, $k) > 0 ) {
                    $result[$k] = $this->properties[$k];
                    # if not multi, just return the first one you find
                    if ( !$multi ) { return $result[$k]; }
                }
            }

            return $result;
        }

        public function get_service_items( $svc ) {
            $result = array();
            foreach ( $this->get_service_properties($svc) as $k => $v ) {
                if ( preg_match('/\.item([0-9]+)\.(.*)$/', $k, $bits) > 0 ) {
                    $result[ $bits[1] ][ $bits[2] ] = $v;
                }
            }

            return $result;
        }
    }   # end class

?> 
