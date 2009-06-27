<?php 
    /**
     * @brief xml 데이터를export하는 파일
     **/

    // 일단 많은 수의 회원이 있을 수 있기에 time limit을 0으로 세팅
    set_time_limit(0);

    // zMigration class파일 load
    require_once("./classes/zMigration.class.php");

    // library 파일 load
    require_once("lib/lib.php");

    // 입력받은 post 변수를 구함
    $charset = $_POST['charset'];
    $path = $_POST['path'];
    if(substr($path,-1)=='/') $path = substr($path,0,strlen($path)-1);
    $target_module = $_POST['target_module'];
    $module_id = $_POST['module_id'];

    // 입력받은 path를 이용하여 db 정보를 구함
    $db_info = getDBInfo($path);
    if(!$db_info) doError("입력하신 경로가 잘못되었거나 dB 정보를 구할 수 있는 파일이 없습니다");

    // zMigration 객체 생성
    $oMigration = new zMigration($path, $target_module, $module_id, $charset);

    // db 접속
    $message = $oMigration->dbConnect($db_info);
    if($message) doError($message);

    /**
     * 회원 정보 export일 회원 관련 정보를 모두 가져와서 처리
     **/
    if($target_module == 'member') {

        // 전체 대상을 구해서 설정
        $query = "select count(*) as count from cs_member";
        $count_result = $oMigration->query($query);
        $count_info = mysql_fetch_object($count_result);
        $oMigration->setItemCount($count_info->count);

        // 헤더 정보를 출력
        $oMigration->printHeader();

        // 회원정보를 역순(오래된 순)으로 구해옴
        $query = "
		select 
			userid as user_id,
			passwd as password,
			name as name,
			email as email,
			mailing as mailing,
			register as regdate,
			tel1 as tel1,
			tel2 as tel2,
			tel3 as tel3,
			phone1 as phone1,
			phone2 as phone2,
			phone3 as phone3,
			add1 as add1,
			add2 as add2,
			content as signature
		from 
			cs_member
		order by idx asc";
        $member_result = $oMigration->query($query) or die(mysql_error());

        // 회원정보를 하나씩 돌면서 migration format에 맞춰서 변수화 한후에 printMemberItem 호출
        while($member_info = mysql_fetch_object($member_result)) {
            $obj = null;

            // 일반 변수들
            $obj->user_id = $member_info->user_id;
            $obj->password = md5($member_info->password);
            $obj->user_name = $member_info->name;
            $obj->nick_name = $member_info->name;
            $obj->email = $member_info->email;
            $obj->homepage = $member_info->homepage;
            $obj->blog = $member_info->blog;
            $obj->birthday = date("YmdHis", $member_info->birth);
            $obj->allow_mailing = $member_info->mailing !=0?'Y':'N';
            $obj->point = $member_info->point1+$member_info->point2;
            $obj->regdate = str_replace(array('-',' ',':'),'',$member_info->reg_date);
            $obj->signature = $member_info->signature;

            if($member_info->tel1) $obj->extra_vars['tel'] = sprintf('%s|@|%s|@|%s',$member_info->tel1, $member_info->tel2, $member_info->tel3);
            if($member_info->phone1) $obj->extra_vars['phone'] = sprintf('%s|@|%s|@|%s',$member_info->phone1, $member_info->phone2, $member_info->phone3);
            if($member_info->add1) $obj->extra_vars['address'] = sprintf('%s|@|%s', $member_info->add1, $member_info->add2);

            $oMigration->printMemberItem($obj);
        }

        // 헤더 정보를 출력
        $oMigration->printFooter();

    /**
     * 게시판 정보 export일 경우
     **/
    } else {

        // 게시물의 수를 구함
        $query = sprintf("select count(*) as count from cs_bbs_data where code = '%s'", $module_id);
        $count_info_result = $oMigration->query($query);
        $count_info = mysql_fetch_object($count_info_result);
        $oMigration->setItemCount($count_info->count);

        // 헤더 정보를 출력
        $oMigration->printHeader();

        // 카테고리 정보 출력
        $oMigration->printCategoryItem($category_list);

        // 게시글은 역순(오래된 순서)으로 구함
        $query = sprintf("
			select
				idx as idx,
				subject as title,
				content as content,
				read_cnt as readed_count,
				name as name,
				pwd as password,
				reg_date as reg_date,
				bbs_file as filename,
				re_step as depth
			from
				cs_bbs_data 
			where 
				code = '%s'
			order by
				ref asc,
				re_level desc
			",
			$module_id
		);

        $document_result = $oMigration->query($query);

        while($document_info = mysql_fetch_object($document_result)) {
            $obj = null;

            $obj->title = $document_info->title;
	    if($document_info->depth) $obj->title = '[re] '.$obj->title;
            $obj->content = nl2br($document_info->content);
            $obj->readed_count = $document_info->readed_count;
            $obj->voted_count = 0;
            $obj->user_id = '';
            $obj->nick_name = $document_info->name;
            $obj->email = '';
            $obj->homepage = '';
            $obj->password = md5($document_info->password);
            $obj->ipaddress = '';
            $obj->allow_comment = 'Y';
            $obj->lock_comment = 'N';
            $obj->allow_trackback = 'Y';
            $obj->is_secret = 'N';
            $obj->regdate =  str_replace(array('-',':',' '),'',$document_info->reg_date);
            $obj->update = null;
            $obj->tags = '';

            // 게시글의 댓글을 구함
            $comments = array();
            $query = sprintf("select * from cs_bbs_coment where link = '%d' order by idx asc", $document_info->idx);
            $comment_result = $oMigration->query($query);
            while($comment_info = mysql_fetch_object($comment_result)) {
                $comment_obj = null;
                // 현재 사용중인 primary key값을 sequence로 넣어두면 parent와 결합하여 depth를 이루어서 importing함
                $comment_obj->sequence = $comment_obj->idx;

                // 제로보드4는 댓글에 depth가 없어서 parent를 0으로 세팅. 다른 프로그램이라면 부모 고유값을 입력해주면 됨
                $comment_obj->parent = 0; 

                $comment_obj->is_secret = 'N';
                $comment_obj->content = nl2br($comment_info->coment);
                $comment_obj->voted_count = 0;
                $comment_obj->notify_message = 'N';
                $comment_obj->password = md5($comment_info->password);
                $comment_obj->user_id = '';
                $comment_obj->nick_name = $comment_info->name;
                $comment_obj->email = '';
                $comment_obj->homepage = '';
                $comment_obj->update = '';
                $comment_obj->regdate = str_replace(array('-',':',' '),'',$document_info->reg_date);
                $comment_obj->ipaddress = '';

                $comments[] = $comment_obj;

            }
            $obj->comments = $comments;


	    $file_name = $document_info->filename;
	    if($filem_name!='none' ) {
	            $file_info = explode('&&',$file_name);	
		    $filename = $file_info[1];
                    $download_count = 0;
                    $file = sprintf("%s/data/bbsData/%s", $path, $file_name);
    
                    $file_obj = null;
                    $file_obj->filename = $filename;
                    $file_obj->file = $file;
                    $file_obj->download_count = $download_count;
    
                    // 이미지 파일이라면 내용 상단에 이미지 추가
                    if(eregi('\.(jpg|gif|jpeg|png)$', $file_name)) $obj->content = sprintf('<img src="%s" border="0" alt="" /><br />%s', $filename,  $obj->content);
    
		    $obj->attaches[] = $file_obj;
            }	

	    $oMigration->printPostItem($document_info->idx, $obj);
        }

        // 헤더 정보를 출력
        $oMigration->printFooter();
    }
?>
