<?php 
    /**
     * @brief wordpress export tool
     * @author zero (zero@xpressengine.com)
     **/
    @set_time_limit(0);
    // zMigration class require
    require_once('./lib.inc.php');
    require_once('./zMigration.class.php');
    $oMigration = new zMigration();

    // Variable declarations
    $path = $_GET['path'];
    if(substr($path,-1)=='/') $path = substr($path,0,strlen($path)-1);
    $start = $_GET['start'];
    $limit_count = $_GET['limit_count'];
    $exclude_attach = $_GET['exclude_attach']=='Y'?'Y':'N';
    $filename = $_GET['filename'];

    // get db path info
    $db_info = getDBInfo($path);
    if(!$db_info) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    $target_module = 'module';
    $module_id = 'wp';

    // zMigration set db info
    $oMigration->setDBInfo($db_info);

    // set module type
    $oMigration->setModuleType($target_module, $module_id);

    // set charset
    $oMigration->setCharset('UTF-8', 'UTF-8');

    // set filename
    $oMigration->setFilename($filename);

    // set path
    $oMigration->setPath($path);

    // check connection
    if($oMigration->dbConnect()) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    // limit query
    $limit_query = $oMigration->getLimitQuery($start, $limit_count);
    
    /**
     * Export
     **/
    // Print header information
    $oMigration->setItemCount($limit_count);
    $oMigration->printHeader();

    // Get wanted category
    $query = sprintf("select terms.term_id as category_srl, taxonomy.parent as parent_srl, terms.name as title from %s_terms terms, %s_term_taxonomy taxonomy where taxonomy.taxonomy = 'category' and taxonomy.term_id = terms.term_id", $db_info->db_table_prefix, $db_info->db_table_prefix);

    $category_result = $oMigration->query($query);
    while($category_info= $oMigration->fetch($category_result)) {
        $obj = null;
        $obj->title = strip_tags($category_info->title);
        $obj->sequence = $category_info->category_srl;
        $obj->parent = $category_info->parent_srl;
        $category_list[$category_info->category_srl] = $obj;
        $category_titles[$obj->title] = $category_info->category_srl;
    }

    // Print category item
    $oMigration->printCategoryItem($category_list);

    // Wanted posts in reverse order (older first)
    $query = "
    select 
        posts.id as document_srl,
        posts.post_title as title,
        posts.post_content as content,
        user.user_login as user_id,
        user.display_name as nick_name,
        user.display_name as user_name,
        user.user_email as email_address,
        user.user_url as homepage,
        user.user_pass as password,
        '127.0.0.1' as ipaddress,
        posts.comment_status as allow_comment,
        post_status as is_secret,
        date_format(post_date,'%Y%m%d%H%i%S') as regdate,
        date_format(post_modified,'%Y%m%d%H%i%S') as last_update
    from 
        {$db_info->db_table_prefix}_posts posts,
        {$db_info->db_table_prefix}_users as user
    where 
	posts.post_type = 'post' and
        posts.post_author = user.id
    order by posts.id asc
    {$limit_query}";
    $document_result = $oMigration->query($query);

    while($document_info = $oMigration->fetch($document_result)) {
        $obj = null;

        $obj->title = $document_info->title;
        $obj->content = nl2br($document_info->content);
        $obj->readed_count = $obj->voted_count = 0;
        $obj->user_id = $document_info->user_id;
        $obj->user_name = $document_info->user_name;
        $obj->nick_name = $document_info->nick_name;
        $obj->email = $document_info->email_address;
        $obj->homepage = $document_info->homepage;
        $obj->password = $document_info->password;
        $obj->ipaddress = $document_info->ipaddress;
        $obj->allow_comment = $document_info->allow_comment!='closed'?'Y':'N';
        $obj->lock_comment = 'N';
        $obj->allow_trackback = 'Y';
        $obj->is_notice = 'N';
        $obj->is_secret = in_array($document_info->is_secret,array('private','draft'))?'Y':'N';
        $obj->regdate =  $document_info->regdate;
        $obj->update = $document_info->last_update;

        $query = sprintf("select terms.term_id as category_id, terms.name as cat_name from %s_terms terms, %s_term_taxonomy tax, %s_term_relationships rel where rel.object_id = %d and rel.term_taxonomy_id = tax.term_taxonomy_id and terms.term_id = tax.term_id",$db_info->db_table_prefix,$db_info->db_table_prefix,$db_info->db_table_prefix,$document_info->document_srl);

	$cat_result = $oMigration->query($query);
	$tags = array();
	while($cat_info = $oMigration->fetch($cat_result)) {
            $tags[] = $cat_info->cat_name;
            if(!$obj->category) $obj->category = $cat_info->cat_name;
        }

        $query = sprintf("select tags.tag as tag from %s_post2tag tag, %s_tags tags where tag.post_id = %d and tags.tag_id = tag.tag_id", $db_info->db_table_prefix,$db_info->db_table_prefix,$document_info->document_srl);
	$tag_result = $oMigration->query($query);
	while($tag_info = $oMigration->fetch($tag_result)) {
            $tags[] = $tag_info->tag;
        }
        $obj->tags = implode(',',$tags);


        // get posts trackbacks
        $query = "
            select 
                comment_author_url as url,
                substring(comment_content,1,20) as title,
                comment_author as blog_name,
                comment_content as excerpt,
                date_format(comment_date,'%Y%m%d%H%i%S') as regdate,
                comment_author_ip as ipaddress
            from 
                {$db_info->db_table_prefix}_comments 
            where 
                comment_post_id = '{$document_info->document_srl}' and
                comment_approved != 'spam' and
		comment_type  = 'trackback'
            order by 
            comment_id asc
        ";

        $trackbacks = array();
        $trackback_result = $oMigration->query($query);
        while($trackback_info = $oMigration->fetch($trackback_result)) {
            $trackback_obj = null;
            $c_pos = strpos($trackback_info->excerpt,'</strong>')+strlen('</strong>');
            if($c_pos) {
                $trackback_obj->title = strip_tags(substr($trackback_info->excerpt, 0, $c_pos));
                $trackback_obj->excerpt = htmlspecialchars(substr($trackback_info->excerpt, $c_pos));
            } else {
                $trackback_obj->title = htmlspecialchars(strip_tags($trackback_info->title));
                $trackback_obj->excerpt = htmlspecialchars(strip_tags($trackback_info->excerpt));
            }
            $trackback_obj->url = $trackback_info->url;
            $trackback_obj->blog_name = $trackback_info->blog_name;
            $trackback_obj->regdate = $trackback_info->regdate;
            $trackback_obj->ipaddress = $trackback_info->ipaddress;
            $trackbacks[] = $trackback_obj;
        }
        $obj->trackbacks = $trackbacks;

        // get post comments
        $comments = array();
        $query = "
            select 
                comment_id as comment_srl,
                comment_parent as parent_srl,
                comment_approved as is_secret,
                comment_content as content,
                0 as voted_count,
                'N' as notify_message,
                null as password,
                null as user_id,
                comment_author as nick_name,
                comment_author_email as email_address,
                date_format(comment_date,'%Y%m%d%H%i%S') as regdate,
                date_format(comment_date,'%Y%m%d%H%i%S') as last_update,
                comment_author_ip as ipaddress
            from 
                {$db_info->db_table_prefix}_comments 
            where 
                comment_post_id = '{$document_info->document_srl}' and
                comment_approved != 'spam' and
		comment_type  = ''
            order by 
            comment_id asc
        ";

        $comment_result = $oMigration->query($query);
        while($comment_info = $oMigration->fetch($comment_result)) {
            $comment_obj = null;

            // store comment information
            $comment_obj->sequence = $comment_info->comment_srl;
            $comment_obj->parent = $comment_info->parent_srl; 
            $comment_obj->is_secret = !$comment_info->is_secret?'Y':'N';
            $comment_obj->content = nl2br($comment_info->content);
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

        $obj->comments = $comments;

        // get attachements
        $GLOBALS['files'] = array();

        $obj->content = preg_replace_callback('/<img([^>]*)>/i', replaceImage, $obj->content);
        $obj->attaches = $GLOBALS['files'];

        $oMigration->printPostItem($document_info->document_srl, $obj, $exclude_attach);
    }

        // print footer information
	$oMigration->printFooter();

	function replaceImage($matches) {
		global $path;
		$url = $_SERVER['HTTP_HOST'].str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath($path));

		$target = $matches[1];
		$pos = strpos(strtolower($target), 'src="');
		if($pos===false) return $matches[0];
		
		$tmp_str = substr($target, $pos+5);
		$pos2 = strpos($tmp_str,'"');
		if($pos2===false) $pos2 = strpos($tmp_str,'\'');
		if($pos2===false) return $matches[0];

		$target = substr($tmp_str,0,$pos2);
		$pos = strpos($target, $url);
		if($pos===false) return $matches[0];

		$filepath = $path.substr($target, $pos+strlen($url));
		$url = $url.$filepath;

		$tmp_arr = explode('/',$filepath);
		$filename = $tmp_arr[count($tmp_arr)-1];

		$file_obj->filename = $filename;
		$file_obj->file = $filepath;
		$file_obj->download_count = 0;

		$GLOBALS['files'][] = $file_obj;

		$content = str_replace($target, $filename, $matches[0]);
		return $content;
	}
?>
