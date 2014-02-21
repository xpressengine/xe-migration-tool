<?php 
    /**
     * @brief xe export tool
     * @author zero (zero@xpressengine.com)
     **/
    @set_time_limit(0);
    // zMigration class require
    require_once('./lib.inc.php');
    require_once('./zMigration.class.php');
    $oMigration = new zMigration();

    // 사용되는 변수의 선언
    $path = $_GET['path'];
    if(substr($path,-1)=='/') $path = substr($path,0,strlen($path)-1);
    $target_module = $_GET['target_module'];
    $module_id = $_GET['module_id'];
    $start = $_GET['start'];
    $limit_count = $_GET['limit_count'];
    $exclude_attach = $_GET['exclude_attach']=='Y'?'Y':'N';
    $filename = $_GET['filename'];

    // 입력받은 path를 이용하여 db 정보를 구함
    $db_info = getDBInfo($path);
    if(!$db_info) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    // zMigration DB 정보 설정
    $oMigration->setDBInfo($db_info);

    // 대상 정보 설정
    $oMigration->setModuleType($target_module, $module_id);

    // 언어 설정
    $oMigration->setCharset('UTF-8', 'UTF-8');

    // 다운로드 파일명 설정
    $oMigration->setFilename($filename);

    // 경로 지정
    $oMigration->setPath($path);

    // db 접속
    if($oMigration->dbConnect()) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    // limit쿼리 생성 (mysql외에도 적용하기 위함)
    $limit_query = $oMigration->getLimitQuery($start, $limit_count);
    
    /**
     * 회원 정보 추출
     **/
    if($target_module == 'member') {

        // 헤더 정보를 출력
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        // 추가폼 정보를 구함
		if($db_info->db_type == 'cubrid')
		{
			$query = sprintF('select * from "%s_member_join_form"', $db_info->db_table_prefix);
		}
		else
		{
			$query = sprintF("select * from %s_member_join_form", $db_info->db_table_prefix);
		}
        $form_result = $oMigration->query($query);
        while($form_item = $oMigration->fetch($form_result)) {
            $form[] = $form_item->column_name;
        }

        // 회원정보를 구함
		if($db_info->db_type == 'cubrid')
		{
			$query = sprintF('select * from "%s_member" order by "member_srl" %s', $db_info->db_table_prefix, $limit_query);
		}
		else
		{
			$query = sprintF("select * from %s_member order by member_srl %s", $db_info->db_table_prefix, $limit_query);
		}
        $member_result = $oMigration->query($query);
        
        // 회원정보를 하나씩 돌면서 migration format에 맞춰서 변수화 한후에 printMemberItem 호출
        while($member_info = $oMigration->fetch($member_result)) {
            $obj = null;

            // 일반 변수들
            $obj->user_id = $member_info->user_id;
            $obj->password = $member_info->password;
            $obj->user_name = $member_info->user_name;
            $obj->nick_name = $member_info->nick_name;
            $obj->email = $member_info->email_address;
            $obj->homepage = $member_info->homepage;
            $obj->blog = $member_info->blog;
            $obj->birthday = $member_info->birthday;
            $obj->allow_mailing = $member_info->mailing;
            $obj->regdate = $member_info->regdate;

            // 이미지네임
            $image_name_file = sprintf('%s/files/member_extra_info/image_name/%s%d.gif', $path, $oMigration->getNumberingPath($member_info->member_srl), $member_info->member_srl);
            if(file_exists($image_name_file)) $obj->image_nickname = $image_name_file;

            // 이미지마크
            $image_mark_file = sprintf('%s/files/member_extra_info/image_mark/%s%d.gif', $path, $oMigration->getNumberingPath($member_info->member_srl), $member_info->member_srl);
            if(file_exists($image_mark_file)) $obj->image_mark = $image_mark_file;

            // 프로필 이미지
            $image_profile_file = sprintf('%s/files/member_extra_info/profile_image/%s%d.gif', $path, $oMigration->getNumberingPath($member_info->member_srl), $member_info->member_srl);
            if(file_exists($image_profile_file)) $obj->profile_image = $image_profile_file;

            // 서명
            $sign_filename = sprintf('%s/files/member_extra_info/signature/%s%d.signature.php', $path, $oMigration->getNumberingPath($member_info->member_srl), $member_info->member_srl);
            if(file_exists($sign_filename)) {
                $f = fopen($sign_filename, "r");
                $signature = trim(fread($f, filesize($sign_filename)));
                fclose($f);

                $obj->signature = $signature;
            }

            // 확장변수 칸에 입력된 변수들은 XE의 멤버 확장변수를 통해서 사용될 수 있음
            unset($extra_vars);
            $extra_vars = unserialize($member_info->extra_vars);
            if($form && $extra_vars) {
                foreach($form as $f) if($extra_vars->{$f}) $obj->extra_vars[$f] = $extra_vars->{$f};
            }

            $oMigration->printMemberItem($obj);
        }

        // 푸터 정보를 출력
        $oMigration->printFooter();

    /**
     * 쪽지 정보
     * 쪽지의 경우 받은 쪽지함+보관함을 모두 받아와서 처리함.
     **/
    } else if($target_module == 'message') {

        // 헤더 정보를 출력
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        // 쪽지 정보를 오래된 순부터 구해옴
		if($db_info->db_type == 'cubrid')
		{
			$query = sprintf('
					select 
					"b"."user_id" as "sender",
					"c"."user_id" as "receiver",
					"a"."title" as "title",
					"a"."content" as "content",
					"a"."readed" as "readed",
					"a"."regdate" as "regdate",
					"a"."readed_date" as "readed_date"
					from 
					"%s_member_message" as "a",
					"%s_member" as "b",
					"%s_member" as "c"
					where 
					"a"."sender_srl" = "b"."member_srl" and
					"a"."receiver_srl" = "c"."member_srl" and 
					"a"."message_type" = \'S\'
					order by "a"."message_srl"
					%s
					',
					$db_info->db_table_prefix,
					$db_info->db_table_prefix,
					$db_info->db_table_prefix,
					$limit_query
						);
		}
		else
		{
			$query = sprintf("
					select 
					b.user_id as sender,
					c.user_id as receiver,
					a.title as title,
					a.content as content,
					a.readed as readed,
					a.regdate as regdate,
					a.readed_date as readed_date
					from 
					%s_member_message a,
					%s_member b,
					%s_member c
					where 
					a.sender_srl = b.member_srl and
					a.receiver_srl = c.member_srl and 
					a.message_type = 'S'
					order by a.message_srl
					%s
					",
					$db_info->db_table_prefix,
					$db_info->db_table_prefix,
					$db_info->db_table_prefix,
					$limit_query
						);
		}

        $message_result = $oMigration->query($query);

        // 쪽지를 하나씩 돌면서 migration format에 맞춰서 변수화 한후에 printMessageItem 호출
        while($obj = $oMigration->fetch($message_result)) {
            $oMigration->printMessageItem($obj);
        }

        // 푸터 정보를 출력
        $oMigration->printFooter();


    /**
     * 게시판 정보 export일 경우
     **/
    } else {
        // module_srl 변수를 세팅
        $module_srl = $module_id;

        // 모듈 정보를 구함
		if($db_info->db_type == 'cubrid')
		{
			$query = sprintf('select * from "%s_modules" where "module_srl" = \'%s\'', $db_info->db_table_prefix, $module_srl);
		}
		else
		{
			$query = sprintf("select * from %s_modules where module_srl = '%s'", $db_info->db_table_prefix, $module_srl);
		}
        $module_info_result = $oMigration->query($query);
        $module_info = $oMigration->fetch($module_info_result);
        $module_title = $module_info->browser_title;
        
        // 헤더 정보를 출력
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        // 카테고리를 구함
		if($db_info->db_type == 'cubrid')
		{
			$query = sprintf('select * from "%s_document_categories" where "module_srl" = \'%d\' order by "list_order" asc, "parent_srl" asc', $db_info->db_table_prefix, $module_srl);
		}
		else
		{
			$query = sprintf("select * from %s_document_categories where module_srl = '%d' order by list_order asc, parent_srl asc", $db_info->db_table_prefix, $module_srl);
		}
        $category_result = $oMigration->query($query);
        while($category_info= $oMigration->fetch($category_result)) {
            $obj = null;
            $obj->title = strip_tags($category_info->title);
            $obj->sequence = $category_info->category_srl;
            $obj->parent = $category_info->parent_srl;
            $category_list[$category_info->category_srl] = $obj;
        }

        // 카테고리 정보 출력
        $oMigration->printCategoryItem($category_list);

        // 게시글은 역순(오래된 순서)으로 구함
		if($db_info->db_type == 'cubrid')
		{
			$query = sprintf('select * from "%s_documents" where "module_srl" = \'%d\' order by "document_srl" %s', $db_info->db_table_prefix, $module_srl, $limit_query);
		}
		else
		{
			$query = sprintf("select * from %s_documents where module_srl = '%d' order by document_srl %s", $db_info->db_table_prefix, $module_srl, $limit_query);
		}
        $document_result = $oMigration->query($query);

        while($document_info = $oMigration->fetch($document_result)) {
            $obj = null;

			if(!isset($document_info->status))
				$document_info->status = 'PUBLIC';

            if($document_info->category_srl) $obj->category = $category_list[$document_info->category_srl]->title;
            $obj->title = $document_info->title;
            $obj->content = $document_info->content;
            $obj->readed_count = $document_info->readed_count;
            $obj->voted_count = $document_info->voted_count;
            $obj->user_id = $document_info->user_id;
            $obj->nick_name = $document_info->nick_name;
            $obj->email = $document_info->email_address;
            $obj->homepage = $document_info->homepage;
            $obj->password = $document_info->password;
            $obj->ipaddress = $document_info->ipaddress;
            $obj->status = $document_info->status;
            $obj->allow_comment = $document_info->allow_comment;
            $obj->lock_comment = $document_info->lock_comment;
            $obj->allow_trackback = $document_info->allow_trackback;
            $obj->is_notice = $document_info->is_notice;
            $obj->is_secret = $document_info->is_secret;
            $obj->regdate =  $document_info->regdate;
            $obj->update = $document_info->last_update;
            $obj->tags = $document_info->tags;

			// 게시글의 엮인글을 구함 
			if($db_info->db_type == 'cubrid')
			{
				$query = sprintf('select * from "%s_trackbacks" where "document_srl" = \'%s\' order by "trackback_srl"', $db_info->db_table_prefix, $document_info->document_srl);
			}
			else
			{
				$query = sprintf("select * from %s_trackbacks where document_srl = '%s' order by trackback_srl", $db_info->db_table_prefix, $document_info->document_srl);
			}

            $trackbacks = array();
            $trackback_result = $oMigration->query($query);
            while($trackback_info = $oMigration->fetch($trackback_result)) {
                $trackback_obj = null;
                $trackback_obj->url = $trackback_info->url;
                $trackback_obj->title = $trackback_info->title;
                $trackback_obj->blog_name = $trackback_info->blog_name;
                $trackback_obj->excerpt = $trackback_info->excerpt;
                $trackback_obj->regdate = $trackback_info->regdate;
                $trackback_obj->ipaddress = $trackback_info->ipaddress;
                $trackbacks[] = $trackback_obj;
            }
            $obj->trackbacks = $trackbacks;

            // 게시글의 댓글을 구함
            $comments = array();
			if($db_info->db_type == 'cubrid')
			{
				$query = sprintf('select * from "%s_comments" where "document_srl" = \'%d\' order by "comment_srl"', $db_info->db_table_prefix, $document_info->document_srl);
			}
			else
			{
				$query = sprintf("select * from %s_comments where document_srl = '%d' order by comment_srl", $db_info->db_table_prefix, $document_info->document_srl);
			}
            $comment_result = $oMigration->query($query);
            while($comment_info = $oMigration->fetch($comment_result)) {
                $comment_obj = null;
				if(!isset($comment_info->status))
					$comment_info->status = 1;

                // 현재 사용중인 primary key값을 sequence로 넣어두면 parent와 결합하여 depth를 이루어서 importing함
                $comment_obj->sequence = $comment_info->comment_srl;
                $comment_obj->parent = $comment_info->parent_srl; 

                $comment_obj->is_secret = $comment_info->is_secret;
                $comment_obj->content = $comment_info->content;
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
                $comment_obj->status = $comment_info->status;

                // 댓글의 첨부파일 체크
                $files = array();

				if($db_info->db_type == 'cubrid')
				{
					$file_query = sprintf('select * from "%s_files" where "upload_target_srl" = \'%d\'', $db_info->db_table_prefix, $comment_info->comment_srl);
				}
				else
				{
					$file_query = sprintf("select * from %s_files where upload_target_srl = '%d'", $db_info->db_table_prefix, $comment_info->comment_srl);
				}
                $file_result = $oMigration->query($file_query);
                while($file_info = $oMigration->fetch($file_result)) {
                    $filename = $file_info->source_filename;
                    $download_count = $file_info->download_count;
                    $file = realpath(sprintf("%s/%s", $path, $file_info->uploaded_filename));

                    $file_obj = null;
                    $file_obj->filename = $filename;
                    $file_obj->file = $file;
                    $file_obj->download_count = $download_count;
                    $files[] = $file_obj;

                    // 이미지등의 파일일 경우 직접 링크를 수정
                    if($file_info->direct_download == 'Y') {
                        preg_match_all('/("|\')([^"^\']*?)('.preg_quote(($filename)).')("|\')/i',$comment_obj->content,$matches);
                        $mat = $matches[0];
                        if(count($mat)) {
                            foreach($mat as $m) {
                                $comment_obj->content = str_replace($m, '"'.$filename.'"', $comment_obj->content);
                            }
                        }
                    // binary 파일일 경우 역시 링클르 변경
                    } else {
                        preg_match_all('/("|\')([^"^\']*?)('.preg_quote(($file_info->sid)).')("|\')/i',$comment_obj->content,$matches);
                        $mat = $matches[0];
                        if(count($mat)) {
                            foreach($mat as $m) {
                                $comment_obj->content = str_replace($m, '"'.$filename.'"', $comment_obj->content);
                            }
                        }
                    }
                }
                if(count($files)) $comment_obj->attaches = $files;

                $comments[] = $comment_obj;
            }

            $obj->comments = $comments;


            // 첨부파일 구함
            $files = array();

			if($db_info->db_type == 'cubrid')
			{
				$file_query = sprintf('select * from "%s_files" where "upload_target_srl" = \'%d\'', $db_info->db_table_prefix, $document_info->document_srl);
			}
			else
			{
				$file_query = sprintf("select * from %s_files where upload_target_srl = '%d'", $db_info->db_table_prefix, $document_info->document_srl);
			}
            $file_result = $oMigration->query($file_query);

            while($file_info = $oMigration->fetch($file_result)) {
                $filename = $file_info->source_filename;
                $download_count = $file_info->download_count;
                $file = realpath(sprintf("%s/%s", $path, $file_info->uploaded_filename));

                $file_obj = null;
                $file_obj->filename = $filename;
                $file_obj->file = $file;
                $file_obj->download_count = $download_count;
                $files[] = $file_obj;

                // 이미지등의 파일일 경우 직접 링크를 수정
                if($file_info->direct_download == 'Y') {
                    preg_match_all('/("|\')([^"^\']*?)('.preg_quote(($filename)).')("|\')/i',$obj->content,$matches);
                    $mat = $matches[0];
                    if(count($mat)) {
                        foreach($mat as $m) {
                            $obj->content = str_replace($m, '"'.$filename.'"', $obj->content);
                        }
                    }
                // binary 파일일 경우 역시 링클르 변경
                } else {
                    preg_match_all('/("|\')([^"^\']*?)('.preg_quote(($file_info->sid)).')("|\')/i',$obj->content,$matches);
                    $mat = $matches[0];
                    if(count($mat)) {
                        foreach($mat as $m) {
                            $obj->content = str_replace($m, '"'.$filename.'"', $obj->content);
                        }
                    }
                }
            }
            $obj->attaches = $files;

            // 확장변수 구함
			if($db_info->db_type == 'cubrid')
			{
				$vars_query = sprintf('select "var_idx", "lang_code", "value", "eid" from "%s_document_extra_vars" where document_srl = \'%d\'', $db_info->db_table_prefix, $document_info->document_srl);
			}
			else
			{
				$vars_query = sprintf("select var_idx, lang_code, value, eid from %s_document_extra_vars where document_srl = '%d'", $db_info->db_table_prefix, $document_info->document_srl);
			}
            $vars_result = $oMigration->query($vars_query);
            $extra_vars = array();
            while($var = $oMigration->fetch($vars_result)) {
                $extra_vars[] = $var;
            }
            $obj->extra_vars = $extra_vars;

            $oMigration->printPostItem($document_info->document_srl, $obj, $exclude_attach);
        }

        // 푸터 정보를 출력
        $oMigration->printFooter();
    }
?>
