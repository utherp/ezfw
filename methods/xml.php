<?php
	require_once('uther.ezfw.php');

	 /********************************************\
	|********** XML Generation Functions **********|
	 \********************************************/
	
	class xml {
	
	
		//Generate Header
			static function header() {
				header("Content-Type: text/xml");						//xml content
				header("Content-ID: Careview XML");						//Id
				header('<META HTTP-EQUIV="Pragma" CONTENT="no-cache">');//Do Not Cache
				header('<META HTTP-EQUIV="Expires" CONTENT="-1">');		//Negative Expiry
				echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; 	//specify xml content
			}
			static function get_header_array() {
				return array(
					'Content-Type'	=> 'text/xml',
					'Content-ID'	=> 'Careview XML',
					'Pragma'		=> 'no-cache',
					'Expires'		=> '-1',
				);
			}
			static function get_xml_version_tag() {
				return '<?xml version="1.0" encoding="UTF-8"?>' . "\x0d\n";
			}
	
		/****** Generate a generic message in xml *****/
			static function message($tag, $msg) {
				xml::write(array($tag => $msg));
			}
		/**********************************************/
	
		/****** Generate an error in xml and exit *****/
			static function error ($err) {
				xml::write(array('error' => $err));
			}
		/**********************************************/
	
		/**** Generate success in xml and exit ********/
			static function success ($msg) {
				xml::write(array('success'=>$msg));
			}
		/**********************************************/
	
			static function write($data) {
				print xml::output($data);
			}
	
		/******* Generate XML Code from Array *********/
			static function output($data, $tag = '') {
				$buffer = '';
				if (is_array($data)) {
	
					foreach (array_keys($data) as $key) {
	
						if (is_numeric($key)) {
	
							if ($key != 0 && $key != count($data)) {
								$buffer .= "</$tag><$tag>";
							}
	
							$buffer .= self::output($data[$key], $tag);
	
						} else {
	
							$buffer .= "<$key>";
							$buffer .= self::output($data[$key], $key);
							$buffer .= "</$key>";
	
						}
					}
					return $buffer;
				} else {
					return $data;
				}
			}

		/**********************************************/
	}

?>
