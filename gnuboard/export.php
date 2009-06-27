<?php 
    /**
     * @brief gnuboard4 export tool
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
    $charset = $_GET['charset'];
    $db_type = $_GET['db_type'];

    // 입력받은 path를 이용하여 db 정보를 구함
    $db_info = getDBInfo($path);
    if(!$db_info) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    $db_info->db_type = $db_type;

    // zMigration DB 정보 설정
    $oMigration->setDBInfo($db_info);

    // 대상 정보 설정
    $oMigration->setModuleType($target_module, $module_id);

    // 언어 설정
    $oMigration->setCharset($charset, 'UTF-8');

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

        // 이미지닉네임, 이미지마크 경로, 프로필 이미지, 서명 구함
        $image_mark_path = sprintf('%s/data/member/',$path);

        // 헤더 정보를 출력
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        // 회원정보를 역순(오래된 순)으로 구해옴
        $query = sprintf("select * from %s %s", $db_info->g4['member_table'], $limit_query);
        $member_result = $oMigration->query($query) or die(mysql_error());

        // 회원정보를 하나씩 돌면서 migration format에 맞춰서 변수화 한후에 printMemberItem 호출
        while($member_info = mysql_fetch_object($member_result)) {
            $obj = null;

            // 일반 변수들
            $obj->user_id = $member_info->mb_id;
            $obj->password = $member_info->mb_password;
            $obj->user_name = $member_info->mb_name;
            $obj->nick_name = $member_info->mb_nick;
            if(!$obj->nick_name) $obj->nick_name = $obj->user_name;
            $obj->email = $member_info->mb_email;
            if(!$obj->email) $obj->email = $obj->user_id.'@'.$obj->user_id.'.temp';
            $obj->homepage = $member_info->mb_homepage;
            $obj->blog = $member_info->mb_blog;
            $obj->birthday = $member_info->mb_birth.'000000';
            $obj->allow_mailing = $member_info->mb_mailing!=0?'Y':'N';
            $obj->point = 0;
            $obj->regdate = str_replace(array('-',':',' ',),'',$member_info->mb_datetime);
            $obj->signature = $member_info->mb_signature;

            // 이미지이름, 이미지마크, 프로필이미지등은 경로를 입력
            $image_mark = sprintf("%s/%s/%s.gif", $image_mark_path, substr($member_info->mb_id,0,2), $member_info->mb_id);
            if(file_exists($image_mark)) $obj->image_mark = $image_mark;

            $obj->extra_vars = array(
                'tel' => $member_info->mb_tel,
                'hp' => $member_info->mb_hp,
                'address' => $member_info->mb_addr1.' '.$member_info->mb_addr2.' '.$member_info->mb_zip1.'-'.$member_info->mb_zip2,
            );

            for($i=1;$i<=10;$i++) $obj->extra_vars['mb_'.$i] = $member_info->{'mb_'.$i};

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
        $query = sprintf("select me_recv_mb_id as receiver, me_send_mb_id as sender, me_send_datetime as regdate, me_read_datetime as readed_date, me_memo as content from %s order by me_send_datetime %s", $db_info->g4['memo_table'], $limit_query);

        $message_result = $oMigration->query($query) or die(mysql_error());

        // 쪽지를 하나씩 돌면서 migration format에 맞춰서 변수화 한후에 printMessageItem 호출
        while($obj = mysql_fetch_object($message_result)) {

            // 일반 변수들
            if($obj->readed_date) $obj->readed = 'Y'; 
            else $obj->readed = 'N';
            $obj->regdate = str_replace(array('-',':',' '),'', $obj->regdate);
            $obj->readed_date = str_replace(array('-',':',' '),'', $obj->readed_date);
            $obj->title = preg_match('/.{10}/su', $obj->content, $arr) ? $arr[0].'...' : $obj->content;
            $obj->content = nl2br($obj->content);

            $oMigration->printMessageItem($obj);
        }

        // 푸터 정보를 출력
        $oMigration->printFooter();

    /**
     * 게시판 정보 export일 경우
     **/
    } else {

        // 게시판 정보를 구함
        $query = sprintf("select * from %s where bo_table='%s'", $db_info->g4['board_table'], $module_id);
        $module_info_result = $oMigration->query($query);
        $module_info = mysql_fetch_object($module_info_result);

        $table_name = sprintf("%s%s",$db_info->g4['write_prefix'],$module_id);

        // 헤더 정보를 출력
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        // 카테고리를 구함
        $query = sprintf("select ca_name from %s group by ca_name", $table_name);
        $category_result = $oMigration->query($query);
        while($category_info= mysql_fetch_object($category_result)) {
            $category_list[] = $category_info->ca_name;
        }

        // 카테고리 정보 출력
        $oMigration->printCategoryItem($category_list);

        // 게시글은 역순(오래된 순서)으로 구함
        $query = sprintf("select * from %s where wr_is_comment = 0 order by wr_num desc, wr_reply desc %s", $table_name, $limit_query);
        $document_result = $oMigration->query($query);

        while($document_info = mysql_fetch_object($document_result)) {
            $obj = null;

            $title_header = '';
            if($document_info->wr_reply) {
              $depth = strlen($document_info->wr_reply);
              for($i=0;$i<$depth;$i++) $title_header .= '[re]';
              $title_header .= ' ';
            }

            $obj->category = $document_info->ca_name;
            $obj->title = $title_header.$document_info->wr_subject;
            $obj->content = nl2br($document_info->wr_content);
            $obj->readed_count = $document_info->wr_hit;
            $obj->voted_count = $document_info->wr_good + $document_info->wr_nogood;
            $obj->user_id = $document_info->mb_id;
            $obj->nick_name = $document_info->wr_name;
            if(!$obj->nick_name) $obj->nick_name = '-';
            $obj->email = $document_info->wr_email;
            $obj->homepage = $document_info->wr_homepage;
            $obj->password = $document_info->wr_password;
            $obj->ipaddress = $document_info->wr_ip;
            $obj->allow_comment = 'Y';
            $obj->lock_comment = 'N';
            $obj->allow_trackback = 'Y';

            if(eregi('secret', $document_info->wr_option)) $obj->is_secret = 'Y';
            else $obj->is_secret = 'N';

            $obj->regdate = str_replace(array('-',':',' '),'',$document_info->wr_datetime);
            $obj->update = str_replace(array('-',':',' '),'',$document_info->wr_last);

            if($document_info->wr_link1) $obj->content = sprintf('<a href="%s" onclick="window.open(this.href);return false;">%s</a>%s<br />', $document_info->wr_link1, $document_info->wr_link1, $obj->content);
            if($document_info->wr_link2) $obj->content = sprintf('<a href="%s" onclick="window.open(this.href);return false;">%s</a>%s<br />', $document_info->wr_link2, $document_info->wr_link2, $obj->content);

            // 게시판의 기타 정보를 구함 (다른 기타 정보가 있을 경우 추가하면 됨 (20개까지 가능)
            for($i=1;$i<10;$i++) {
                $obj->extra_vars[] = $document_info->{'wr_'.$i};
            }

            // 게시글의 댓글을 구함
            $comments = array();
            if($document_info->wr_comment>0) {
                $query = sprintf("select * from %s where wr_parent = '%d' and wr_is_comment = 1 order by wr_comment asc, wr_comment_reply asc", $table_name, $document_info->wr_id);
                $comment_result = $oMigration->query($query);

                $pstree = array();

                while($comment_info = mysql_fetch_object($comment_result)) {
                    $cobj = null;

                    // 현재 사용중인 primary key값을 sequence로 넣어두면 parent와 결합하여 depth를 이루어서 importing함
                    $cobj->sequence = $comment_info->wr_id;

                    $depth = strlen($comment_info->wr_comment_reply);
                    $wr_comment = $comment_info->wr_comment;
                    $pstree[$wr_comment][$depth] = $comment_info->wr_id;

                    if($depth<1) $cobj->parent = 0; 
                    else $cobj->parent = $pstree[$wr_comment][$depth-1];

                    $cobj->is_secret = 'N';
                    $cobj->content = nl2br($comment_info->wr_content);
                    $cobj->voted_count = 0;
                    $cobj->notify_message = 'N';
                    $cobj->password = $comment_info->wr_password;
                    $cobj->user_id = $comment_info->mb_id;
                    $cobj->nick_name = $comment_info->wr_name;
                    $cobj->email = $comment_info->wr_email;
                    $cobj->homepage = $comment_info->wr_homepage;
                    $cobj->update = str_replace(array('-',':',' '),'',$comment_info->wr_datetime);
                    $cobj->regdate = str_replace(array('-',':',' '),'',$document_info->wr_last);
                    $cobj->ipaddress = $comment_info->wr_ip;

                    $comments[] = $cobj;
                }
            }
            $obj->comments = $comments;

            $files = array();

            // 첨부파일 처리 (기본 2개인데 일단 20개로 만들어 보았음)
            $query = sprintf("select * from %s where bo_table = '%s' and wr_id = '%d' order by bf_no asc", $db_info->g4['board_file_table'], $module_id, $document_info->wr_id);
            $file_result = $oMigration->query($query);
            while($file_info = mysql_fetch_object($file_result)) {
                $filename = $file_info->bf_source;
                $download_count = $file_info->bf_download;
                $file = sprintf("%s/data/file/%s/%s", $path, $module_id, $file_info->bf_file);

                $file_obj = null;
                $file_obj->filename = $filename;
                $file_obj->file = $file;
                $file_obj->download_count = $download_count;
                $files[] = $file_obj;

                // 이미지 파일이라면 내용 상단에 이미지 추가
                if(eregi('\.(jpg|gif|jpeg|png)$', $filename)) $obj->content = sprintf('<img src="%s" border="0" alt="" /><br />%s', $filename,  $obj->content);
            }

            $obj->attaches = $files;

            $oMigration->printPostItem($document_info->no, $obj, $exclude_attach);
        }

        // 푸터 정보를 출력
        $oMigration->printFooter();
    }
?>
