<?php
// fns/auth.php
require_once('config.php');   
class fns_auth {
	protected $tblname = 'tblusr';
	
	/**
	 * Construct
	 * use database table to verify user and password
	 * tblusr need column uname and upasswd
	 * @param $tblname string
	 * @return	void
	 */
	
	public function __construct($tblname) {
		$this->tblname = $tblname;
		$obj_db = util_mysql::getInstance();
		$this->db = $obj_db->getdb();
	}
	
	/**
	 * isLogin($username, $password)
	 * check database tblusr to authentificate user
	 * @param $username string
	 * @param $password string
	 * @return  bool
	 */
	
	public function isLogin($username, $password) {
		$sql = "SELECT * FROM $this->tblname WHERE uname='$username' and upasswd=PASSWORD('$password')";
		$rows = $GLOBALS['__db__']->fetchAll($sql);
//		return (count($rows)>0) ? true : false;	
		if(count($rows)>0) {
			$this->save_usr_session($rows[0]);
//			$_SESSION = $rows;
			return true;
		} else {
			return false;	
		}
	}
	
	/**
	 * isFirstLogin($username, $password)
	 * check database tblusr to authentificate user
	 * @param $username string
	 * @param $password string
	 * @return  bool
	 */
	
	public function isFirstLogin($username, $password) {
		$sql = "SELECT * FROM $this->tblname WHERE uname='$username' and upasswd='$password'";
		$rows = $GLOBALS['__db__']->fetchAll($sql);
		if(count($rows)>0) {
			$this->save_usr_session($rows[0]);
			return true;
		} else {
			return false;	
		}
	}
	
	/**
	 * isPasswordLogin($password)
	 * do not require user name login
	 * check database tblusr to authentificate user
	 * @param $password string
	 * @return  bool
	 */
	//this is for patient login use different table
	public function isPasswordLogin($password) {
		$sql = "SELECT * FROM $this->tblname WHERE upasswd=PASSWORD('$password')";
		$rows = $GLOBALS['__db__']->fetchAll($sql);
		return (count($rows)>0) ? true : false;	
	}
	
	public function save_usr_passwd($password) {
		$uname = $_SESSION['uname'];
		$sql = "UPDATE $this->tblname SET upasswd=PASSWORD('$password') WHERE uname='$uname'";
		$GLOBALS['__db__']->query($sql);
	}
	
	public function save_usr_session($ary) {
		@session_start();
		$_SESSION['gid'] = $ary['ugid'];
		$_SESSION['uid'] = $ary['uid'];
		$_SESSION['uname'] = $ary['uname'];
		$_SESSION['valid_user'] = $ary['uname'];
		
	}
}
