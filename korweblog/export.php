<?php 
    /**
     * @brief korweblog export tool
     * @author zero (zero@xpressengine.com)
     **/
    @set_time_limit(0);
    require_once('./lib.inc.php');
    require_once('./zMigration.class.php');
    $oMigration = new zMigration();

    $path = $_GET['path'];
    if(substr($path,-1)=='/') $path = substr($path,0,strlen($path)-1);
    $target_module = $_GET['target_module'];
    $module_id = $_GET['module_id'];
    $start = $_GET['start'];
    $limit_count = $_GET['limit_count'];
    $exclude_attach = $_GET['exclude_attach']=='Y'?'Y':'N';
    $filename = $_GET['filename'];

    $db_info = getDBInfo($path);
    if(!$db_info) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    $oMigration->setDBInfo($db_info);

    $oMigration->setModuleType($target_module, $module_id);

    $oMigration->setCharset('EUC-KR', 'UTF-8');

    $oMigration->setFilename($filename);

    $oMigration->setPath($path);

    if($oMigration->dbConnect()) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    $limit_query = $oMigration->getLimitQuery($start, $limit_count);
    
    if($target_module == 'member') {

        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        $query = sprintF("select * from T_Users order by uid %s", $db_info->db_table_prefix, $limit_query);
        $member_result = $oMigration->query($query);
        
        while($member_info = $oMigration->fetch($member_result)) {
            $obj = null;

	    if(preg_match('/^[a-z0-9\_]$/',$member_info->name)) $obj->user_id = $member_info->name;
	    else $obj->user_id = substr($member_info->email, 0, strpos('@', $member_info->email));
            $obj->user_name = $obj->nick_name = $obj->user_id = $member_info->name;
            $obj->password = $member_info->pass;
            $obj->email = $member_info->email;
            $obj->homepage = $member_info->url;
            $obj->allow_mailing = 'N';
            $obj->regdate = str_replace(array('-',':',' '),array('','',''),$member_info->rdate);

            unset($extra_vars);
            $extra_vars = unserialize($member_info->extra_vars);
            if($form && $extra_vars) {
                foreach($form as $f) if($extra_vars->{$f}) $obj->extra_vars[$f] = $extra_vars->{$f};
            }

            $oMigration->printMemberItem($obj);
        }

        $oMigration->printFooter();


    } else {

        $module_srl = $module_id;

        $query = sprintf("select * from T_Topics where Rid = '%s'", $module_srl);
        $module_info_result = $oMigration->query($query);
        $module_info = $oMigration->fetch($module_info_result);
        $module_title = $module_info->Topic;
        
        $oMigration->setItemCount($limit_count);
        $oMigration->printHeader();

        $query = sprintf("select * from T_Stories where Topic = '%d' order by Repostamp asc %s", $module_srl, $limit_query);
        $document_result = $oMigration->query($query);

        while($document_info = $oMigration->fetch($document_result)) {
            $obj = null;

            $obj->title = $document_info->Heading;
            $obj->content = nl2br($document_info->Content);
            $obj->readed_count = $document_info->Hits;
            $obj->voted_count = 0;
            $obj->user_id = $document_info->Author;
            $obj->nick_name = $document_info->Author;
            $obj->email = $document_info->AuthorEmail;
            $obj->homepage = $document_info->AuthorURL;
            $obj->password = $document_info->replys;
            $obj->ipaddress = $document_info->Host;
            $obj->allow_comment = 'Y';
            $obj->lock_comment = 'N';
            $obj->allow_trackback = 'Y';
            $obj->is_notice = 'N';
            $obj->is_secret = 'N';
            $obj->regdate = str_replace(array('-',':',' '),array('','',''),$document_info->Repostamp);
            $obj->update = str_replace(array('-',':',' '),array('','',''),$document_info->Timestamp);
            $obj->tags = '';

            $query = sprintf("select * from T_Trackback where Rid = '%s' order by tid", $document_info->Rid);

            $trackbacks = array();
            $trackback_result = $oMigration->query($query);
            while($trackback_info = $oMigration->fetch($trackback_result)) {
                $trackback_obj = null;
                $trackback_obj->url = $trackback_info->url;
                $trackback_obj->title = $trackback_info->title;
                $trackback_obj->blog_name = $trackback_info->blog_name;
                $trackback_obj->excerpt = $trackback_info->excerpt;
                $trackback_obj->regdate = str_replace(array('-',':',' '),array('','',''),$trackback_info->time);
                $trackbacks[] = $trackback_obj;
            }
            $obj->trackbacks = $trackbacks;

            $comments = array();
            $query = sprintf("select * from T_Comments where TopRid = '%d' order by Rid asc", $document_info->Rid);
            $comment_result = $oMigration->query($query);
            while($comment_info = $oMigration->fetch($comment_result)) {
                $comment_obj = null;

                $comment_obj->is_secret = 'N';
                $comment_obj->content = nl2br($comment_info->Content);
                $comment_obj->voted_count = 0;
                $comment_obj->notify_message = 'N';
                $comment_obj->password = $comment_info->passwd;
                $comment_obj->user_id = $comment_info->Author;
                $comment_obj->nick_name = $comment_info->Author;
                $comment_obj->email = $comment_info->AuthorEmail;
                $comment_obj->homepage = $comment_info->AuthorUrl;
                $comment_obj->regdate = str_replace(array('-',':',' '),array('','',''),$comment_info->Birthstamp);
                $comment_obj->update = str_replace(array('-',':',' '),array('','',''),$comment_info->Timestamp);
                $comment_obj->ipaddress = $comment_info->Host;
                $comments[] = $comment_obj;
            }

            $files = array();
	    if($document_info->bofile) {
                $download_count = $file_info->download_count;

                $file_obj->filename = $download_info->bofile;
                $file_obj->file = realpath(sprintf("%s/upload/%s/%s/%s", $path, $document_info->Topic, $document_info->bcfile, $document_info->bofile));
                $file_obj->download_count = 0;
                $files[] = $file_obj;

		if(preg_match('/\.(jpg|gif|png|jpeg)/',$download_info->bofile)) $obj->content .= '<br /><img src="'.$download_info->bofile.'" alt="" /><br />';
	    }
            $obj->attaches = $files;

            $oMigration->printPostItem($document_info->document_srl, $obj, $exclude_attach);

        }

        $oMigration->printFooter();
    }
?>
