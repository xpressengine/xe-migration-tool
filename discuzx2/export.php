<?php 
    /**
     * @brief discuz!x2 export tool
     * @author xe developer (developers@nhn.com)
     **/

    @set_time_limit(0);
    // load required class
    require_once('./classes/lib.inc.php');
    require_once('./classes/zMigration.class.php');
    require_once('./classes/ubbcode.php');
    
    // variable declaration
	$oMigration = new zMigration();
    $path = $_GET['path'];
    if(substr($path,-1)=='/') $path = substr($path,0,strlen($path)-1);
    $start = $_GET['start'];
    $limit_count = $_GET['limit_count'];
    $exclude_attach = $_GET['exclude_attach']=='Y'?'Y':'N';
    $filename = $_GET['filename'];
	$target = $_GET['target'];

	switch($target){
		case "post": 
			$target_post = true;
		    break;
		case "post_comment": 
			$target_post = true;
			$target_comment = true;
		    break;
		case "members": 
			$target_member = true;
		    break;
		default: 
			$target_post =  false;
			$target_comment = false;
			$target_member = false;
	}

    // get db information from db path
    $db_info = getDBInfo($path);
    if(!$db_info) {
        exit();
    }

    $target_module = 'module';
    $module_id = 'discuz';

    // zMigration DB configration
    $oMigration->setDBInfo($db_info);

    // module type configration
    $oMigration->setModuleType($target_module, $module_id);

    // charset configration
    $oMigration->setCharset('UTF-8', 'UTF-8');

    // file configration
    $oMigration->setFilename($filename);

    // path configration
    $oMigration->setPath($path);

    // db connection
    if($oMigration->dbConnect()) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    // limit number per query
    $limit_query = $oMigration->getLimitQuery($start, $limit_count);
    $oMigration->setItemCount($limit_count);


	if($target_post) {
		// print xml header
		$oMigration->printHeader();

		/**
		 * export posts from discuz
		 **/
		$query = "select 
					posts.pid as document_srl, posts.subject as title, posts.message as content, posts.invisible as is_secret, posts.useip as ipaddress, date_format(FROM_UNIXTIME(posts.dateline),'%Y%m%d%H%i%S') as regdate,
					members.username as user_name, members.username as nick_name, members.email as email_address
				  from 
					{$db_info->tablepre}forum_post posts, {$db_info->tablepre}common_member as members
				  where 
					posts.authorid = members.uid and posts.first = 1
				  order by posts.pid asc {$limit_query}";


		$document_result = $oMigration->query($query);

		if($document_result){
			while($document_info = $oMigration->fetch($document_result)) {
				$obj = null;
				$obj->title = $document_info->title;
				$obj->content = nl2br($document_info->content);
				$obj->content = ubbcode($obj->content);
				$obj->content = "<div>".$obj->content."</div>";
				$obj->readed_count = $obj->voted_count = 0;
				$obj->user_id = $document_info->user_id;
				$obj->user_name = $document_info->user_name;
				$obj->nick_name = $document_info->nick_name;
				$obj->email = $document_info->email_address;
				$obj->password = '';
				$obj->ipaddress = $document_info->ipaddress;
				$obj->allow_comment = $document_info->allow_comment!='0'?'N':'Y';
				$obj->lock_comment = 'N';
				$obj->allow_trackback = 'Y';
				$obj->is_notice = 'N';
				$obj->is_secret = $document_info->is_secret!='0'?'Y':'N';
				$obj->regdate =  $document_info->regdate;
				$obj->update = $document_info->last_update;
			
				if($target_comment) {
					// export comments from the post 
					$comments = array();

					$query = "select 
								posts.pid as comment_srl, posts.tid as parent_srl, posts.status as is_secret, posts.message as content, posts.useip as ipaddress, date_format(FROM_UNIXTIME(posts.dateline),'%Y%m%d%H%i%S') as regdate,
								members.username as user_name, members.username as nick_name, members.email as email_address
							  from 
								{$db_info->tablepre}forum_post posts, {$db_info->tablepre}common_member members
							  where 
								posts.tid = '{$document_info->document_srl}' and posts.authorid = members.uid and posts.first != 1
							  order by 
								posts.pid asc
							";

					$comment_result = $oMigration->query($query);
					if($comment_result){
						while($comment_info = $oMigration->fetch($comment_result)) {
							$comment_obj = null;
							$comment_obj->sequence = $comment_info->comment_srl;
							$comment_obj->parent = $comment_info->parent_srl; 
							$comment_obj->is_secret = !$comment_info->is_secret?'N':'Y';
							$comment_obj->content = nl2br($comment_info->content);
							$comment_obj->content = ubbcode($comment_info->content);
							$comment_obj->voted_count = $comment_info->voted_count;
							$comment_obj->notify_message = $comment_info->notify_message;
							$comment_obj->password = $comment_info->password;
							$comment_obj->user_id = $comment_info->user_id;
							$comment_obj->nick_name = $comment_info->nick_name;
							$comment_obj->email = $comment_info->email_address;
							$comment_obj->homepage = $comment_info->homepage;
							$comment_obj->regdate = $comment_info->regdate;
							$comment_obj->update = $comment_info->last_update;
							$comment_obj->ipaddress = $comment_info->ipaddress;
							$comments[] = $comment_obj;					
						}
					}

					$obj->comments = $comments;
				}

			/*$obj->content = preg_replace_callback('/<img([^>]*)>/i', replaceImage, $obj->content);
			$obj->attaches = $GLOBALS['files'];*/

				$oMigration->printPostItem($document_info->document_srl, $obj, $exclude_attach);
			}
		}
		// print xml footer
		$oMigration->printFooter();
	}

	/**
	 * export members from discuz
	 **/
	if($target_member) {
		// module type configration
		$oMigration->setModuleType('member', 'discuz');
		// print xml header
		$oMigration->printHeader();

		$query = "select members.*, date_format(FROM_UNIXTIME(members.regdate),'%Y%m%d%H%i%S') as regdate, uc_members.password as member_passwd, uc_members.salt as salt
				  from 
					{$db_info->tablepre}common_member members,
					{$db_info->tablepre}ucenter_members uc_members
				  where 
				    members.uid = uc_members.uid
				  order by members.uid asc {$limit_query}";


		$member_result = $oMigration->query($query);
		if($member_result){
			while($member_info = $oMigration->fetch($member_result)) {
				$member_obj = null;
				$member_obj->user_id = $member_info->username;
				$member_obj->email = $member_info->email;
				$member_obj->password = $member_info->member_passwd;
				$member_obj->user_name = $member_info->username;
				$member_obj->nick_name = $member_info->username;
				$member_obj->allow_mailing = 'N';
				$member_obj->allow_message = 'Y';
				$member_obj->denied = 'N';
				$member_obj->regdate = $member_info->regdate;
				$member_obj->last_login = $member_info->regdate;
				$member_obj->is_admin = 'Y';
				$member_obj->extra_vars->discuz = 'Y';
				$member_obj->extra_vars->salt = $member_info->salt;
				$oMigration->printMemberItem($member_obj);
			}
		}
		// print xml footer
		$oMigration->printFooter();
	}


?>
