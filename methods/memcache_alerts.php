<?php

	function &get_memcache_connection($host = 'localhost') {
		if (!isset($GLOBALS['_memcache_'][$host])) {
			if (!is_array($GLOBALS['_memcache_']))
				$GLOBALS['_memcache_'] = array();
		
			$GLOBALS['_memcache_'][$host] = new Memcache();
			$GLOBALS['_memcache_'][$host]->connect($host);
		}
		return $GLOBALS['_memcache_'][$host];
	}

	function add_alert($rawname, $value, $expires) {
		$name = preg_replace('/;/', '\\;', $rawname);
		$name = preg_quote($name, '/');
	
		$c = get_memcache_connection();
		while ($c->get('alerts/lock')) {
			usleep(10000);
		}
		$c->set('alerts/lock', 'adding', 0, 2);

		$tmp = $c->get('alerts/*');
		if (!$tmp) {
			$alerts = array();
		} else {
			$alerts = split_alert_list($tmp);
		}

		$rehash = fill_alert_list($alerts);

		if (!isset($alerts[$rawname])) {
			$alerts[$rawname] = true;
			$rehash = true;
		}

		$c->set('alerts/' . $rawname, $value, 0, $expires);

		if ($rehash)
			save_alert_list($alerts);

		$c->delete('alerts/lock');
		return;
	}

	function save_alert_list($alerts) {
		$c = get_memcache_connection();
		$str = pack_alert_list($alerts);
		if ($str)
			$c->set('alerts/*', $str);
		else
			$c->delete('alerts/*');
		return;
	}

	function remove_alert($rawname) {
		$name = preg_replace('/;/', '\\;', $rawname);
		$name = preg_quote($name, '/');

		$c = get_memcache_connection();
		while ($c->get('alerts/lock'))
			usleep(10000);

		$c->set('alerts/lock', 'removing', 0, 2);
		$tmp = $c->get('alerts/*');

		$alerts = split_alert_list($tmp);
		$rehash = fill_alert_list($alerts);

		if (isset($alerts[$rawname])) {
			unset($alerts[$rawname]);
			$rehash = true;
		}

		$c->delete('alerts/' . $rawname);

		if ($rehash)
			save_alert_list($alerts);

		$c->delete('alerts/lock');
		return;
	}

	function read_alerts() {
		$c = get_memcache_connection();
		$tmp = $c->get('alerts/*');
		if ($tmp === false) return array();
		if ($tmp[0] == ';') $tmp = substr($tmp, 1);
		$l = strlen($tmp);
		if ($l > 1 && $tmp[$l] == ';' && $tmp[$l-1] != "\\") $tmp = substr($tmp, 0, -1);
		$alerts = split_alert_list($tmp);
		if (fill_alert_list($alerts))
			save_alert_list($alerts);

		return $alerts;
	}

	function pack_alert_list($alerts) {
		if (!count($alerts)) return '';
		return ';' . implode(';', array_keys($alerts)) . ';';
	}

	function split_alert_list($list) {
		if (!$list) return array();
		$alerts = array();
		$rec = false;
		foreach (preg_split("/([^\\\\]);/", $list, -1, PREG_SPLIT_DELIM_CAPTURE) as $r)
			if ($rec === false) $rec = $r;
			else {
				$alerts[$rec . $r] = false; //$c->get('alerts/'.$rec . $r);
				$rec = false;
			}
		if ($rec) 
			$alerts[$rec] = false;
		return $alerts;
	}

	function fill_alert_list(&$list) {
		$c = get_memcache_connection();
		$changed = false;
		foreach (array_keys($list) as $n) {
			$list[$n] = $c->get('alerts/'.$n);
			if ($list[$n] === false) {
				unset($list[$n]);
				$changed = true;
			}
		}
		return $changed;
	}

