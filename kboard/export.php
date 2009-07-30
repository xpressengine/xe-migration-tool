<?php
    /**
     * @brief kboard2.3.0 export tool
     * @author zero (zero@xpressengine.com) / modify geusgod (geusgod@gmail.com)
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
        $image_mark_path = sprintf('%s/data/_members/',$path);

        // 헤더 정보를 출력
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        // 회원정보를 역순(오래된 순)으로 구해옴
        $query = sprintf("select * from kport_mem_table order by no asc %s", $limit_query);
        $member_result = $oMigration->query($query) or die(mysql_error());

        // 회원정보를 하나씩 돌면서 migration format에 맞춰서 변수화 한후에 printMemberItem 호출
        while($member_info = mysql_fetch_object($member_result)) {
            $obj = null;

            // 일반 변수들
            $obj->user_id = $member_info->id;
            $obj->password = $member_info->pass;
            $obj->user_name = $member_info->name;
            $obj->nick_name = $member_info->name;
            $obj->email = $member_info->email;
            $obj->homepage = $member_info->homepage;
            $obj->blog = '';
            $obj->birthday = '';
            $obj->allow_mailing = $member_info->mailing!=0?'Y':'N';
            $obj->point = $member_info->point;
            $obj->regdate = date("YmdHis", $member_info->reg_date);
            $obj->signature = '';

            // 이미지이름, 이미지마크, 프로필이미지등은 경로를 입력
            $image_mark = sprintf("%s/%s", $image_mark_path, $member_info->attach);
            if(file_exists($image_mark)) $obj->image_mark = $image_mark;

            $obj->profile_image = '';

            $oMigration->printMemberItem($obj);
        }

        // 푸터 정보를 출력
        $oMigration->printFooter();

    /**
     * 게시판 정보 export일 경우
     **/
    } else {

        // 게시판 정보를 구함
        $query = "select * from kport_admin_table where tb_name='".$module_id."'";
        $module_info_result = $oMigration->query($query);
        $module_info = mysql_fetch_object($module_info_result);

        // 헤더 정보를 출력
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        // 카테고리를 구함
        if($module_info->use_category) {
            $query = "select * from kport_".$module_id."_category";
            $category_result = $oMigration->query($query);
            while($category_info= mysql_fetch_object($category_result)) {
                $category_list[$category_info->no] = strip_tags($category_info->name);
            }
        }

        // 카테고리 정보 출력
        $oMigration->printCategoryItem($category_list);

        // 게시글은 역순(오래된 순서)으로 구함
        $query = sprintf('select a.*, b.id from kport_%s_board a left outer join kport_mem_table b on a.id = b.id order by a.parent, a.depth desc %s', $module_id, $limit_query);
        $document_result = $oMigration->query($query);

        while($document_info = mysql_fetch_object($document_result)) {
            $obj = null;

            if($module_info->use_category && $document_info->cid) $obj->category = $category_list[$document_info->cid];
            $obj->is_notice = trim($document_info->notice)?'Y':'N';
            $obj->title = $document_info->title;
            $obj->content = $document_info->content;
            $obj->readed_count = $document_info->hit;
            $obj->voted_count = $document_info->vote;
            $obj->user_id = $document_info->id;
            $obj->nick_name = str_replace("<b>","",str_replace("</b>","",$document_info->name));
            $obj->email = $document_info->email;
            $obj->homepage = str_replace("http://","",$document_info->homepage);
            $obj->password = $document_info->pass;
            $obj->ipaddress = $document_info->ipp;
            $obj->allow_comment = 'Y';
            $obj->lock_comment = 'N';
            $obj->allow_trackback = 'Y';
            $obj->is_secret = trim($document_info->secret)?'Y':'N';
            $obj->regdate =  date("YmdHis", $document_info->reg_date);
            $obj->update = null;
            $obj->tags = '';

            // use_html옵션에 따른 컨텐츠 정리
            if($document_info->use_html != 2) $obj->content = nl2br($obj->content);

            // kboard의 sitelink가 있을 경우 본문 상단에 추가
            if($document_info->link) $obj->content = sprintf('<a href="%s" onclick="window.open(this.href);return false;">%s</a>%s<br />', $document_info->link, $document_info->link, $obj->content);

            // 게시판의 기타 정보를 구함 (다른 기타 정보가 있을 경우 추가하면 됨 (20개까지 가능)
            if($document_info->x) $obj->extra_vars[] = $document_info->x;
            if($document_info->y) $obj->extra_vars[] = $document_info->y;

            // 게시글의 댓글을 구함
            $comments = array();
            $query = sprintf('select a.*, b.id from kport_%s_comment a left outer join kport_mem_table b on a.mid = b.id where a.pid = %d order by no asc', $module_id, $document_info->no);
            $comment_result = $oMigration->query($query);
            while($comment_info = mysql_fetch_object($comment_result)) {
                $comment_obj = null;
                // 현재 사용중인 primary key값을 sequence로 넣어두면 parent와 결합하여 depth를 이루어서 importing함
                $comment_obj->sequence = $comment_obj->no;

                // kboard는 댓글에 depth가 없어서 parent를 0으로 세팅. 다른 프로그램이라면 부모 고유값을 입력해주면 됨
                $comment_obj->parent = 0;

                $comment_obj->is_secret = 'N';
                $comment_obj->content = nl2br($comment_info->content);
                $comment_obj->voted_count = 0;
                $comment_obj->notify_message = 'N';
                $comment_obj->password = $comment_info->pass;
                $comment_obj->user_id = $comment_info->mid;
                $comment_obj->nick_name = $comment_info->name;
                $comment_obj->email = $comment_info->email;
                $comment_obj->homepage = $comment_info->homepage;
                $comment_obj->update = $comment_info->reg_date;
                $comment_obj->regdate = date('YmdHis', $comment_info->reg_date);
                $comment_obj->ipaddress = $comment_info->ipp;

                $comments[] = $comment_obj;

            }
            $obj->comments = $comments;

            // 첨부파일 구함
            $files = array();

            // 첨부파일 처리
            $query = sprintf("select * from kport_%s_attach where pid = '%d' order by no asc", $module_id, $document_info->no);
            $file_result = $oMigration->query($query);
            while($file_info = mysql_fetch_object($file_result)) {
                $filename = $file_info->name;
                $download_count = $file_info->download;
                $file = sprintf("%s/data/%s/%s", $path, $module_id, $file_info->name);

                $file_obj = null;
                $file_obj->filename = $filename;
                $file_obj->file = $file;
                $file_obj->download_count = $download_count;
                $files[] = $file_obj;

                // 이미지 파일이라면 내용 상단에 이미지 추가
                if(eregi('\.(jpg|gif|jpeg|png)$', $filename)) $obj->content = sprintf('<img src="%s" border="0" alt="" /><br />%s', $filename,  $obj->memo);
            }

            $obj->attaches = $files;

            $oMigration->printPostItem($document_info->no, $obj, $exclude_attach);
        }

        // 푸터 정보를 출력
        $oMigration->printFooter();
    }
?>