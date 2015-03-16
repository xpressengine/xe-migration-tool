<?php 
    /**
     * @brief KIMSQ RB v1 export tool
     * @author UPGLE (upgle@xpressengine.com)
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
        $image_mark_path = sprintf('%s/_var/simbol',$path);

        // 헤더 정보를 출력
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        // 회원정보를 역순(오래된 순)으로 구해옴
        $db_prefix = $db_info->db_prefix;
        $query = sprintf("select * from {$db_prefix}_s_mbrid inner join {$db_prefix}_s_mbrdata where {$db_prefix}_s_mbrid.uid = {$db_prefix}_s_mbrdata.memberuid %s", $limit_query);
        $member_result = $oMigration->query($query) or die(mysql_error());

        // 회원정보를 하나씩 돌면서 migration format에 맞춰서 변수화 한후에 printMemberItem 호출
        while($member_info = mysql_fetch_object($member_result)) {
            $obj = null;

            // 일반 변수들
            $obj->user_id = $member_info->id;
            $obj->password = $member_info->pw;
            $obj->user_name = $member_info->name;
            $obj->nick_name = $member_info->nick;
            if(!$obj->nick_name) $obj->nick_name = $obj->user_name;
            $obj->email = $member_info->email;
            if(!$obj->email) $obj->email = $obj->user_id.'@'.$obj->user_id.'.temp';
            $obj->homepage = $obj->blog = $member_info->home;
            $obj->birthday = $member_info->birth1.$member_info->birth2.'000000';
            $obj->allow_mailing = $member_info->mailing!=0?'Y':'N';
            $obj->point = $member_info->point;
            $obj->regdate = $member_info->d_regis;
            $obj->signature = '';

            // 이미지이름, 이미지마크, 프로필이미지등은 경로를 입력
            $image_mark = sprintf("%s/%s",  $image_mark_path, $member_info->photo);
            if($member_info->photo && file_exists($image_mark)) $obj->image_mark = $image_mark;

            // EXTRA 정보 입력
            $obj->extra_vars = array(
                'tel' => $member_info->tel1,
                'hp' => $member_info->tel2
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
        $query = sprintf("select * from %s_s_paper order by d_regis %s", $db_info->db_prefix, $limit_query);
        $message_result = $oMigration->query($query) or die(mysql_error());

        // 쪽지를 하나씩 돌면서 migration format에 맞춰서 변수화 한후에 printMessageItem 호출
        while($message = mysql_fetch_object($message_result)) {

            $receiver = getMemberID($message->my_mbruid);
            $sender = getMemberID($message->by_mbruid);

            if(!$receiver || !$sender) continue;

            $obj->sender = $sender;
            $obj->receiver = $receiver;
            if($message->d_read) $obj->readed = 'Y'; 
            else $obj->readed = 'N';
            $obj->readed_date = $message->d_read;
            $obj->regdate = $message->d_regis;
            $obj->content = nl2br($message->content);
            $obj->title = preg_match('/.{10}/su', $obj->content, $arr) ? $arr[0].'...' : $obj->content;

            $oMigration->printMessageItem($obj);
        }

        // 푸터 정보를 출력
        $oMigration->printFooter();

    /**
     * 게시판 정보 export일 경우
     **/
    } else {

        // 게시판 정보를 구함
        $query = sprintf("select * from %s_bbs_data where bbsid='%s'", $db_info->db_prefix, $module_id);
        $module_info_result = $oMigration->query($query);
        $module_info = mysql_fetch_object($module_info_result);

        $table_name = sprintf("%s%s",$db_info->g4['write_prefix'],$module_id);

        // 헤더 정보를 출력
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        // 카테고리를 구함
        $query = sprintf("select category from %s_bbs_data where bbsid='%s' group by category", $db_info->db_prefix, $module_id);
        $category_result = $oMigration->query($query);
        while($category_info= mysql_fetch_object($category_result)) {
            if($category_info->category == '') continue;
            $category_list[] = $category_info->category;
        }

        // 카테고리 정보 출력
        $oMigration->printCategoryItem($category_list);

        // 게시글은 역순(오래된 순서)으로 구함
        $query = sprintf("select * from %s_bbs_data where bbsid='%s' order by Floor(gid) desc, gid asc %s", $db_info->db_prefix, $module_id, $limit_query);
        $document_result = $oMigration->query($query);

        while($document_info = mysql_fetch_object($document_result)) {

            $obj = null;

            $title_header = '';
            if($document_info->depth) {
              $depth = $document_info->depth;
              for($i=0;$i<$depth;$i++) $title_header .= '[re]';
              $title_header .= ' ';
            }

            $obj->category = $document_info->category;
            $obj->title = $title_header.$document_info->subject;
            $obj->content = nl2br($document_info->content);
            $obj->readed_count = $document_info->hit;
            $obj->voted_count = $document_info->score1;
            $obj->user_id = $document_info->id;
            $obj->nick_name = $document_info->nic;
            if(!$obj->nick_name) $obj->nick_name = '-';
            if($document_info->mbruid) 
            {   
                $member_info = getMemberInfo($document_info->mbruid);
                $obj->email = $member_info ? $member_info->email : '';
                $obj->homepage = $member_info ? $member_info->home : '';
            }

            $obj->password = $document_info->pw;
            $obj->ipaddress = $document_info->ip;
            $obj->allow_comment = 'Y';
            $obj->lock_comment = 'N';
            $obj->allow_trackback = 'Y';

            if($document_info->hidden) $obj->is_secret = 'Y';
            else $obj->is_secret = 'N';

            $obj->regdate = $document_info->d_regis;
            $obj->update = ($document_info->modify) ? $document_info->modify : $document_info->d_regis;

            // 게시글의 댓글을 구함
            $comments = array();
            if($document_info->comment>0) {
                $query = sprintf("select * from %s_s_comment where parent = 'bbs%s' order by uid desc", $db_info->db_prefix, $module_info->uid);
                $comment_result = $oMigration->query($query);

                $pstree = array();
                while($comment_info = mysql_fetch_object($comment_result)) {
                    $cobj = null;

                    // 현재 사용중인 primary key값을 sequence로 넣어두면 parent와 결합하여 depth를 이루어서 importing함
                    $cobj->sequence = $comment_info->uid;
                    $cobj->parent = 0;
                    $cobj->is_secret = 'N';
                    $cobj->content = nl2br($comment_info->content);
                    $cobj->voted_count = $comment_info->score1;
                    $cobj->notify_message = 'N';
                    $cobj->password = $comment_info->pw;
                    $cobj->user_id = $comment_info->id;
                    $cobj->nick_name = $comment_info->nic;
                    $cobj->email = '';
                    $cobj->homepage = '';
                    $cobj->regdate = $comment_info->d_regis;
                    $cobj->update = ($comment_info->d_modify) ? $comment_info->d_modify : $comment_info->d_regis;
                    $cobj->ipaddress = $comment_info->ip;
                    $comments[] = $cobj;

                    // 한줄 코멘트를 불러옴
                    if($comment_info->oneline>0) {

                        $query = sprintf("select * from %s_s_oneline where parent = '%s' order by uid asc", $db_info->db_prefix, $comment_info->uid);
                        $oneline_result = $oMigration->query($query);
                        while($oneline_info = mysql_fetch_object($oneline_result)) {
                            
                            $cobj = null;
                            $cobj->parent = $comment_info->uid;
                            $cobj->is_secret = 'N';
                            $cobj->content = nl2br($oneline_info->content);
                            $cobj->voted_count = 0;
                            $cobj->notify_message = 'N';
                            $cobj->password = '';
                            $cobj->user_id = $oneline_info->id;
                            $cobj->nick_name = $oneline_info->nic;
                            $cobj->email = '';
                            $cobj->homepage = '';
                            $cobj->regdate = $oneline_info->d_regis;
                            $cobj->update = ($oneline_info->d_modify) ? $oneline_info->d_modify : $oneline_info->d_regis;
                            $cobj->ipaddress = $oneline_info->ip;
                            $comments[] = $cobj;
                        }
                    }
                }


            }
            $obj->comments = $comments;

            // 첨부파일 처리
            $files = array();
            if(preg_match_all("/\[(\d+)\]/", $document_info->upload, $filelist))
            {
                $ids = join(',',$filelist[1]);

                $query = sprintf("select * from %s_s_upload where uid in (%s) order by gid asc", $db_info->db_prefix, $ids);
                $file_result = $oMigration->query($query);
                while($file_info = mysql_fetch_object($file_result)) {

                    $filename = $file_info->name;
                    $download_count = $file_info->down;
                    $file = sprintf("%s/files/%s/%s", $path, $file_info->folder,  $file_info->tmpname);

                    $file_obj = null;
                    $file_obj->filename = $filename;
                    $file_obj->file = $file;
                    $file_obj->download_count = $download_count;
                    $files[] = $file_obj;
                    // 이미지 파일이라면 내용 상단에 이미지 추가
                    if(eregi('\.(jpg|gif|jpeg|png)$', $filename)) $obj->content = sprintf('<img src="%s" border="0" alt="" /><br />%s', $filename,  $obj->content);
                }

                $obj->attaches = $files;
            }

            $oMigration->printPostItem($document_info->no, $obj, $exclude_attach);
        }

        // 푸터 정보를 출력
        $oMigration->printFooter();
    }
?>
