<?php
/**
 * @file discuzlogin.addon.php
 * @author Ray (ryin005@nhn.com)
 * @brief Discuz Login add-on
 **/

if(!defined('__XE__')) exit();

if($called_position == "before_module_proc" && Context::get('act') == 'procMemberLogin'){

	 $user_id = Context::get('user_id');
	 $oMemberModel = &getModel('member');
	 $memberInfo = $oMemberModel->getMemberInfoByEmailAddress($user_id);
	
	 if($memberInfo->discuz && $memberInfo->salt){
		$salt = $memberInfo->salt;
		$password_discuz = md5(Context::get('password')).$salt;
		Context::set('password',$password_discuz);
	 }
}

?>
