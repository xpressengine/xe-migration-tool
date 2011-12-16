<?php 
    /**
     * @brief phpBB export tool
     * @author corina (xe_dev@arnia.ro)
	 *
	 * Parameters:
	 *   filename - name of the xml file that will be generated // mandatory
	 *   path - path where phpBB is installed
	 *   start - number of records to skip // mandatory
	 *   limit_count - number of records to take // mandatory
	 *   exclude_attach - Y/N - tells whether attachements should be downloaded too // optional, default 'N'
     **/
    @set_time_limit(0);
    // zMigration class require
	require_once('./BBCode.php');
    require_once('./lib.inc.php');
    require_once('./zMigration.class.php');
    $oMigration = new zMigration();
	$code_converter = new BBCodeConverter();

    // Retrieve request data
    $path = $_GET['path'];
    if(substr($path,-1)=='/') $path = substr($path,0,strlen($path)-1);
    $start = $_GET['start'];
    $limit_count = $_GET['limit_count'];
    $exclude_attach = $_GET['exclude_attach']=='Y'?'Y':'N';
    $filename = $_GET['filename'];

    // Get phpBB database info
    $db_info = getDBInfo($path);
    if(!$db_info) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    $target_module = 'module';
    $module_id = 'phpbb';

    // set DB info in zMigration class
    $oMigration->setDBInfo($db_info);

    // setup target module info
    $oMigration->setModuleType($target_module, $module_id);

    // make sure charset used is UTF-8
    $oMigration->setCharset('UTF-8', 'UTF-8');

    // init filename
    $oMigration->setFilename($filename);

    // init path
    $oMigration->setPath($path);

    // attempt to connect to database
    if($oMigration->dbConnect()) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    // get the limit part of the query
    $limit_query = $oMigration->getLimitQuery($start, $limit_count);
    
    /**
     * Start export
     **/
    // Print XML file header
    $oMigration->setItemCount($limit_count);
    $oMigration->printHeader();

	/**************************
	 * Categories (document_categories)
	 **************************/
	 
    // Retrieve phpBB categories
    $query = sprintf("select category.forum_id as category_srl
						, category.parent_id as parent_srl
						, category.forum_name as title
					  from %s_forums as category", $db_info->db_table_prefix);

    $category_result = $oMigration->query($query);
    while($category_info= $oMigration->fetch($category_result)) {
        $obj = new stdClass;
        $obj->title = strip_tags($category_info->title);
        $obj->sequence = $category_info->category_srl;
        $obj->parent = $category_info->parent_srl;
        $category_list[$category_info->category_srl] = $obj;
        $category_titles[$obj->title] = $category_info->category_srl;
    }

    // Write categories to XML file
    $oMigration->printCategoryItem($category_list);

	/**************************
	 * Documents
	 **************************/	
    // Retrieve phpBB topics 
	// topic_status - A coded field 0 = Unlocked, 1 = Locked, 2 = Moved
    $query = "
		select topic.topic_id as document_srl
			 , topic.topic_title as title
			 , first_post.post_text as content
			 , user.username_clean as user_id
			 , user.username as nick_name
			 , user.username as user_name
			 , user.user_email as email_address
			 , user.user_website as homepage
			 , user.user_password as password
			 , user.user_ip as ipaddress
			 , case when topic.topic_status = 1 then 'N' else 'Y' end as allow_comment
			 , case when topic.topic_approved = 1 then 'N' else 'Y' end as is_secret
			 , date_format(from_unixtime(topic_time),'%Y%m%d%H%i%S') as regdate
			 , date_format(from_unixtime(topic_last_post_time),'%Y%m%d%H%i%S') as last_update
		from {$db_info->db_table_prefix}_topics as topic
			inner join {$db_info->db_table_prefix}_posts as first_post on topic.topic_first_post_id = first_post.post_id
			inner join {$db_info->db_table_prefix}_users as user on user.user_id = topic.topic_poster
		order by topic.topic_id desc
		{$limit_query}";
    $document_result = $oMigration->query($query);

    while($document_info = $oMigration->fetch($document_result)) {
        $obj = new stdClass;

		// Setup document common attributes
        $obj->title = $document_info->title;		
		$new_content = $code_converter->toXhtml($document_info->content);
        $obj->content = nl2br($new_content);
        $obj->readed_count = $obj->voted_count = 0;
        $obj->user_id = $document_info->user_id;
        $obj->user_name = $document_info->user_name;
        $obj->nick_name = $document_info->nick_name;
        $obj->email = $document_info->email_address;
        $obj->homepage = $document_info->homepage;
        $obj->password = $document_info->password;
        $obj->ipaddress = $document_info->ipaddress;
        $obj->allow_comment = $document_info->allow_comment;
        $obj->lock_comment = 'N';
        $obj->allow_trackback = 'Y';
        $obj->is_notice = 'N';
        $obj->is_secret = $document_info->is_secret;
        $obj->regdate =  $document_info->regdate;
        $obj->update = $document_info->last_update;

		// Retrieve document categories 
        $query = sprintf("select cat.forum_id as category_srl
								, cat.forum_name as cat_name
							from %s_topics topic
							  inner join %s_forums cat on cat.forum_id = topic.forum_id
							where topic.topic_id = %d"
					,$db_info->db_table_prefix,$db_info->db_table_prefix, $document_info->document_srl);

		$cat_result = $oMigration->query($query);
		$tags = array();
		while($cat_info = $oMigration->fetch($cat_result)) {
				$tags[] = $cat_info->cat_name;
				if(!isset($obj->category)) $obj->category = $cat_info->cat_name;
			}
		
        // Retrieve document comments
        $comments = array();
        $query = "
            select post.post_id as comment_srl
			   , 0 as parent_srl
			   , post.post_approved as  is_secret
			   , post.post_text as content
			   , 0 as voted_count
			   , 'N' as notify_message
			   , null as password
			   , null as user_id
			   , user.username as nick_name
			   , user.user_email as email_address
			   , user.user_website as homepage
			   , date_format(from_unixtime(post_time),'%Y%m%d%H%i%S') as regdate
			   , date_format(from_unixtime(post_edit_time),'%Y%m%d%H%i%S') as last_update
			   , user.user_ip as ipaddress
			from {$db_info->db_table_prefix}_topics as topic
			  inner join {$db_info->db_table_prefix}_posts as post 
				 on post.topic_id = topic.topic_id 
				   and post.post_id != topic.topic_first_post_id
			  inner join {$db_info->db_table_prefix}_users as user
				 on user.user_id = post.poster_id
			where topic.topic_id = {$document_info->document_srl}
			order by post.post_id asc
        ";

        $comment_result = $oMigration->query($query);
        while($comment_info = $oMigration->fetch($comment_result)) {
            $comment_obj = new stdClass;

            // ?? ???? primary key?? sequence? ???? parent? ???? depth? ???? importing?
            $comment_obj->sequence = $comment_info->comment_srl;
            $comment_obj->parent = $comment_info->parent_srl; 
            $comment_obj->is_secret = !$comment_info->is_secret?'Y':'N';
			$new_content = $code_converter->toXhtml($comment_info->content);
            $comment_obj->content = nl2br($new_content);
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
			$comment_obj->attaches = array();
            $comments[] = $comment_obj;
        }

        $obj->comments = $comments;

		// Set extra_vars and trackbaks to null, because there is nothing to transfer for them
		$obj->extra_vars = null;
		$obj->trackbacks = null;
		
        // Retrieve document files
        $GLOBALS['files'] = array();

        $obj->content = preg_replace_callback('/<img([^>]*)>/i', 'replaceImage', $obj->content);
        $obj->attaches = $GLOBALS['files'];

        $oMigration->printPostItem($document_info->document_srl, $obj, $exclude_attach);
    }

    // Print XML file footer
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
