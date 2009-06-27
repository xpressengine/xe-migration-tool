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
        $query = "select count(*) as count from iezn_member";
        $count_result = $oMigration->query($query);
        $count_info = mysql_fetch_object($count_result);
        $oMigration->setItemCount($count_info->count);

        // 헤더 정보를 출력
        $oMigration->printHeader();

        // 회원정보를 역순(오래된 순)으로 구해옴
        $query = "select * from iezn_member order by m_num asc ";
        $member_result = $oMigration->query($query) or die(mysql_error());

        // 회원정보를 하나씩 돌면서 migration format에 맞춰서 변수화 한후에 printMemberItem 호출
        while($member_info = mysql_fetch_object($member_result)) {
            $obj = null;

            // 일반 변수들
            $obj->user_id = $member_info->m_id;
            $obj->password = $member_info->m_passwd;
            $obj->user_name = $member_info->m_name;
            $obj->nick_name = $member_info->m_nick;
            $obj->email = $member_info->m_email;
            $obj->homepage = $member_info->m_homepage;
            $obj->birthday = str_replace('-','',$member_info->m_birthday);
            $obj->allow_mailing = $member_info->m_mailing?'Y':'N';
            $obj->point = $member_info->m_point;
            $obj->regdate = str_replace(array('-',' ',':'),'', $member_info->m_regdate);
            $obj->last_login = date("YmdHis", $member_info->m_lastdate);
            if($obj->last_login < '19710000000000') $obj->last_login = $obj->regdate;
            $obj->signature = '';

            // 이미지이름, 이미지마크, 프로필이미지등은 경로를 입력
            $obj->image_nickname = sprintf("%s%d.gif", $image_nickname_path, $member_info->no);
            $obj->image_mark = sprintf("%s%d.gif", $image_mark_path, $member_info->no);
            $obj->profile_image = '';

            // 확장변수 칸에 입력된 변수들은 XE의 멤버 확장변수를 통해서 사용될 수 있음
            $obj->extra_vars = array(
                'phone' => str_replace('-','|@|',$member_info->m_phone),
                'hphone' => str_replace('-','|@|',$member_info->m_hphone),
                'faxphone' => str_replace('-','|@|',$member_info->m_faxphone),
            );

            $oMigration->printMemberItem($obj);
        }

        // 헤더 정보를 출력
        $oMigration->printFooter();

    /**
     * 게시판 정보 export일 경우
     **/
    } else {

        // 게시물의 수를 구함
        $query = sprintf("select count(*) as count from iezn_board_%s_list", $module_id);
        $count_info_result = $oMigration->query($query);
        $count_info = mysql_fetch_object($count_info_result);
        $oMigration->setItemCount($count_info->count);

        // 헤더 정보를 출력
        $oMigration->printHeader();

        // 게시글은 역순(오래된 순서)으로 구함
        $query = sprintf('select a.*, b.m_nick, b.m_name from iezn_board_%s_list a left join iezn_member b on a.id = b.m_id order by no desc, sub desc', $module_id);
        $document_result = $oMigration->query($query);

        while($document_info = mysql_fetch_object($document_result)) {
            $obj = null;
            $obj->title = $document_info->subject;
	    if($document_info->depth) $obj->title = '[re]'.$obj->title;
            $obj->content = $document_info->contents;
            $obj->readed_count = $document_info->hit;
            $obj->voted_count = $document_info->chu_up - $document_info->chu_down;
            $obj->user_id = $document_info->id;
	    if($document_info->m_nick) $obj->nick_name = $document_info->m_nick;
            else $obj->nick_name = $document_info->nick;
            $obj->user_name = $document_info->m_name;
            $obj->email = $document_info->email;
            $obj->homepage = $document_info->homepage;
            $obj->password = $document_info->passwd;
            $obj->ipaddress = $document_info->ip;
            $obj->allow_comment = 'Y';
            $obj->lock_comment = 'N';
            $obj->allow_trackback = 'Y';
            $obj->is_secret = 'N';
            $obj->regdate =  date("YmdHis", $document_info->regdate);
            $obj->update = null;
            $obj->tags = '';

            // 게시글의 댓글을 구함
            $comments = array();
            $query = sprintf("select a.*, b.m_nick, b.m_name from iezn_board_%s_comment a left join iezn_member b on a.id = b.m_id where num = '%d' order by no desc, sub desc", $module_id, $document_info->num);
            $comment_result = $oMigration->query($query);
            while($comment_info = mysql_fetch_object($comment_result)) {
                $comment_obj = null;
                // 현재 사용중인 primary key값을 sequence로 넣어두면 parent와 결합하여 depth를 이루어서 importing함
                $comment_obj->sequence = $comment_info->c_num;

                // 제로보드4는 댓글에 depth가 없어서 parent를 0으로 세팅. 다른 프로그램이라면 부모 고유값을 입력해주면 됨
                $comment_obj->parent = 0; 

                $comment_obj->is_secret = 'N';
                $comment_obj->content = nl2br($comment_info->contents);
                $comment_obj->voted_count = 0;
                $comment_obj->notify_message = 'N';
                $comment_obj->password = $comment_info->passwd;
                $comment_obj->user_id = $comment_info->id;
		if($comment_info->m_nick) $comment_obj->nick_name = $comment_info->m_nick;
		else $comment_obj->nick_name = $comment_info->nick;
		$comment_obj->user_name = $comment_info->m_name;
                $comment_obj->email = $comment_info->email;
                $comment_obj->homepage = $comment_info->homepage;
                $comment_obj->update = '';
                $comment_obj->regdate = date('YmdHis', $comment_info->regdate);
                $comment_obj->ipaddress = $comment_info->ip;

                $comments[] = $comment_obj;

            }
            $obj->comments = $comments;

            // 첨부파일 구함 (제로보드4의 경우 이미지박스 + 첨부파일1,2(..more) 를 관리
	   $image_header = '';
            $files = array();
            if($document_info->file_su>0) {
		$file_list = unserialize($document_info->file);
		    if(is_array($file_list) && count($file_list)) {
		       foreach($file_list as $file_item) {
			  $file_obj->filename = $file_item['name'];
			  $file_obj->download_count = $file_item['view'];
			  $file_obj->file = sprintf('%s/iezn_board/%s/f3/%s',DATA_PATH,$module_id,$file_item['tmp_name']);
			  $files[] = $file_obj;

			// 이미지 파일이라면 내용 상단에 이미지 추가
			if(eregi('\.(jpg|gif|jpeg|png)$', $file_obj->filename)) $image_header .= sprintf('<img src="%s" border="0" alt="" /><br /><br />', $file_obj->filename);
		       }
		    } 
            } 

	    $obj->content = $image_header . $obj->content;

            $obj->attaches = $files;

            $oMigration->printPostItem($document_info->no, $obj);
        }

        // 헤더 정보를 출력
        $oMigration->printFooter();
    }
?>
