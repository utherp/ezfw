<?php
	require_once('uther.ezfw.php');


	class resource {

		static $table = 'permissions';
		static $identifier = 'access';
		static $fields = array (
						'access' =>	'access',
						'tag'	 =>	'tag',
						'name'	 =>	'name',
						'url'	 =>	'url',
						'has_menu'=> 'menu',
						'menu_membership' => 'menu_membership',
					);


		static function get_start_url($id) {
			get_db_connection();
			return $GLOBALS['__db__']->fetchOne('select ' . self::$fields['url'] . ' from ' . self::$table . ' where ' . $GLOBALS['__db__']->quoteInto(self::$fields['tag'] . ' = ?', $id));
		}
		static function has_menu($id) {
			get_db_connection();
			$m = $GLOBALS['__db__']->fetchOne('select ' . self::$fields['has_menu'] . ' from '. self::$table . ' where ' . $GLOBALS['__db__']->quoteInto(self::$fields['tag'] . ' = ?', $id));
?><!--
select <?=self::$fields['has_menu']?> from <?=self::$table?> where <?=$GLOBALS['__db__']->quoteInto(self::$fields['tag'] . ' = ?', $id)?>
--><?
			if ($m == '0') return false;
			else return true;
		}

		static function fetch($param, $value) {
			return unserialize(file_get_contents('http://server.cv-internal.com/hrc.new/fetch/resource.php?p='.$param.'&v='.$value));
		}
	}
