<?php 
    /**
     * @brief miniboard export tool
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
    $oMigration->setCharset($db_info->charset, 'UTF-8');

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
     * 회원 정보 export일 회원 관련 정보를 모두 가져와서 처리
     **/
    if($target_module == 'member') {

        // 헤더 정보를 출력
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        // 회원정보를 역순(오래된 순)으로 구해옴
        $query = sprintf("select * from %s_member order by no asc %s ",$db_info->db_prefix, $limit_query);
        $member_result = $oMigration->query($query);

        // 회원정보를 하나씩 돌면서 migration format에 맞춰서 변수화 한후에 printMemberItem 호출
        while($member_info = mysql_fetch_object($member_result)) {
            $obj = null;

            // 일반 변수들
            $obj->user_id = $member_info->id;
            $obj->password = $member_info->pass;
            $obj->user_name = $member_info->real_name;
            $obj->nick_name = $member_info->name;
            $obj->email = $member_info->email;
            $obj->homepage = $member_info->homepage;
            $obj->blog = $member_info->blog;
            $obj->birthday = date("YmdHis", $member_info->birth);
            $obj->allow_mailing = $member_info->mailing!=0?'Y':'N';
            $obj->point = $member_info->point_sum;
            $obj->regdate = date("YmdHis", $member_info->reg_date);
            $obj->signature = $member_info->mysign;

            // 이미지이름, 이미지마크, 프로필이미지등은 경로를 입력
            //$obj->image_nickname = sprintf("%s%d.gif", $image_nickname_path, $member_info->no);
            if($member_info->icon) $obj->image_mark = sprintf("%s/%s", $path, $member_info->icon);
            $obj->profile_image = '';

            // 확장변수 칸에 입력된 변수들은 XE의 멤버 확장변수를 통해서 사용될 수 있음
            $obj->extra_vars = array(
                'icq' => $member_info->icq,
                'aol' => $member_info->aol,
                'msn' => $member_info->msn,
                'job' => $member_info->job,
                'hobby' => $member_info->hobby,
                'address' => $member_info->address,
            );

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
        $query = sprintf('
            select 
                b.id as receiver, 
                c.id as sender,
                a.ment as content,
                a.isread as readed,
                a.time as regdate,
                a.read_time as readed_date
            from 
				%s_memo a,
				%s_member b,
				%s_member c
            where 
                a.target_no = b.no and 
                a.from_no = c.no 
            order by a.no 
			%s
			',
			$db_info->db_prefix,
			$db_info->db_prefix,
			$db_info->db_prefix,
			$limit_query
		);

        $message_result = $oMigration->query($query);

        // 쪽지를 하나씩 돌면서 migration format에 맞춰서 변수화 한후에 printMessageItem 호출
        while($obj = mysql_fetch_object($message_result)) {

            // 일반 변수들
            if($obj->readed) $obj->readed = 'Y'; 
            else $obj->readed = 'N';
            $obj->regdate = date("YmdHis", $obj->regdate);
            $obj->readed_date = date("YmdHis", $obj->readed_date);
            $obj->title = preg_match('/^([\xa1-\xfe]{2}|.){20}/s', $obj->content, $m)?$m[0].'...':$obj->content;
            $obj->content = nl2br($obj->content);

            $oMigration->printMessageItem($obj);
        }

        // 헤더 정보를 출력
        $oMigration->printFooter();

    /**
     * 게시판 정보 export일 경우
     **/
    } else {
        // 게시판 정보를 구함
        $query = sprintf("select * from %s_admin_board where id='%s'", $db_info->db_prefix, $module_id);
        $module_info_result = $oMigration->query($query);
        $module_info = mysql_fetch_object($module_info_result);

        // 헤더 정보를 출력
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        // 카테고리를 구함
		$query = sprintf("select num, name from %s_category where id='%s'", $db_info->db_prefix, $module_id);
		$category_result = $oMigration->query($query);
		while($category_info= mysql_fetch_object($category_result)) {
			$category_list[$category_info->num] = strip_tags($category_info->name);
		}

        // 카테고리 정보 출력
        $oMigration->printCategoryItem($category_list);

        // 게시글은 역순(오래된 순서)으로 구함
        $query = sprintf('select a.*, b.id as user_id from %s_board_%s a left outer join %s_member b on a.member_no = b.no order by a.no %s', $db_info->db_prefix, $module_id, $db_info->db_prefix,  $limit_query);
        $document_result = $oMigration->query($query);

        while($document_info = mysql_fetch_object($document_result)) {
            $obj = null;

            if($document_info->category) $obj->category = $category_list[$document_info->category];
            $obj->title = $document_info->title;
			if(!$obj->title) continue;
            $obj->content = $document_info->ment;
            $obj->readed_count = $document_info->hit;
            $obj->voted_count = $document_info->vote-abs($document_info->hate);
            $obj->user_id = $document_info->user_id;
            $obj->nick_name = $document_info->name;
            $obj->email = $document_info->email;
            $obj->homepage = $document_info->homepage;
            $obj->password = $document_info->pass;
            $obj->ipaddress = $document_info->ip;
            $obj->allow_comment = 'Y';
            $obj->lock_comment = 'N';
            $obj->allow_trackback = 'Y';
            $obj->is_secret = trim($document_info->secret)?'Y':'N';
            $obj->is_notice = trim($document_info->notice)?'Y':'N';
            $obj->regdate =  date("YmdHis", $document_info->reg_date);
            $obj->update = null;
            $obj->tags = '';

            // use_html옵션에 따른 컨텐츠 정리
            if($document_info->auto_br == 1) $obj->content = nl2br($obj->content);

            if($document_info->link) $obj->content = sprintf('<a href="%s" onclick="window.open(this.href);return false;">%s</a>%s<br />', $document_info->link, $document_info->link, $obj->content);

            // 게시글의 댓글을 구함
            $comments = array();
            $query = sprintf('select a.*, b.id as user_id from %s_comment_%s a left outer join %s_member b on a.member_no = b.no where a.target = %d order by num asc', $db_info->db_prefix, $module_id, $db_info->db_prefix, $document_info->no);
            $comment_result = $oMigration->query($query);
            while($comment_info = mysql_fetch_object($comment_result)) {
                $comment_obj = null;
                // 현재 사용중인 primary key값을 sequence로 넣어두면 parent와 결합하여 depth를 이루어서 importing함
                $comment_obj->sequence = $comment_obj->no;

                // 제로보드4는 댓글에 depth가 없어서 parent를 0으로 세팅. 다른 프로그램이라면 부모 고유값을 입력해주면 됨
                $comment_obj->parent = $comment_info->reply_num; 

                $comment_obj->is_secret = 'N';
                $comment_obj->content = nl2br($comment_info->ment);
                $comment_obj->voted_count = 0;
                $comment_obj->notify_message = 'N';
                $comment_obj->password = $comment_info->pass;
                $comment_obj->user_id = $comment_info->user_id;
                $comment_obj->nick_name = $comment_info->name;
                $comment_obj->regdate = date('YmdHis', $comment_info->reg_date);
                $comment_obj->ipaddress = $comment_info->ip;

                $comments[] = $comment_obj;

            }
            $obj->comments = $comments;


            // 첨부파일 구함 (제로보드4의 경우 이미지박스 + 첨부파일1,2(..more) 를 관리
            $files = array();

			$query = sprintf("select file_name, directory from %s_file where id='%s' and target = '%d'", $db_info->db_prefix, $module_id, $document_info->no);
			$result = mysql_query($query);
			$image_header = '';
			while($item = mysql_fetch_object($result)) {
				$file = sprintf("%s/file/%s/%s", $path, $item->directory, $item->file_name);
				if(!file_exists($file)) continue;
				$file_obj = null;
				$file_obj->filename = $item->file_name;
				$file_obj->file = $file;
				$file_obj->download_count = 0;
				$files[] = $file_obj;
					
				if(eregi('\.(jpg|gif|jpeg|png)$', $file_obj->filename)) $image_header .= sprintf('<img src="%s" border="0" alt="" /><br /><br />', $file_obj->filename);
			}

            $obj->content = $image_header . $obj->content;

            $obj->attaches = $files;

            $oMigration->printPostItem($document_info->no, $obj, $exclude_attach);
        }

        // 헤더 정보를 출력
        $oMigration->printFooter();
    }
?>
