<?php
    /**
     * @brief Discuz! X2 export tool
     * @author xe developer (developers@nhn.com)
     **/

    // load required files
    require_once('./classes/lib.inc.php');
    require_once('./classes/zMigration.class.php');

    // load lang files
	$select_lang = $_GET['select_lang']?$_GET['select_lang']:$_POST['select_lang'];
    if(!$select_lang) $select_lang = 'en';
	if($select_lang == 'en'){
		require_once('./lang/en.lang.php');
	}elseif($select_lang == 'ko'){
		require_once('./lang/ko.lang.php');
	}elseif($select_lang == 'zh-CN'){
		require_once('./lang/zh-CN.lang.php');
	}

    // variable declairation
	$oMigration = new zMigration();
    $path = $_POST['path'];
	$target = $_POST['target'];
    $division = (int)($_POST['division']);
	if(!$division) $division = 1;

    $step = 1;
    $errMsg = '';

    // 1 check db path
    if($path) {
        $db_info = getDBInfo($path);
        if(!$db_info) {
            $errMsg = $lang->discuz_error;
        } else {
            $oMigration->setDBInfo($db_info);
            $oMigration->setCharset('UTF-8', 'UTF-8');
            $message = $oMigration->dbConnect();
            if($message) $errMsg = $message;
            else $step = 2;
        }
    }

    // 2 check total posts
    if($step == 2) {
		if($target=='post' || $target=='post_comment'){
			$query = sprintf("select count(*) as count from %sforum_post posts where posts.first = 1", $db_info->tablepre);
    		$result = $oMigration->query($query);
			$data = $oMigration->fetch($result);
			$post_count = $data->count;
			$total_count += $post_count;
			if($target=='post_comment'){
				$query = sprintf("select count(*) as count from %sforum_post posts where posts.first <> 1", $db_info->tablepre);
				$result = $oMigration->query($query);
				$data = $oMigration->fetch($result);
				$comment_count = $data->count;
				$total_count += $comment_count;
			}
		}
		if($target=='members'){
			$query = sprintf("select count(*) as member_count from %scommon_member members", $db_info->tablepre);
    		$result = $oMigration->query($query);
			$data = $oMigration->fetch($result);
			$member_count = $data->member_count;
			$total_count += $member_count;
		}

        // division
        if($total_count>0) $division_cnt = (int)(($total_count-1)/$division) + 1;
    }

?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link rel="stylesheet" href="./css/default.css" type="text/css" />
<script src="./js/jquery.min.js"></script>
<script src="./js/discuz.js"></script>
<title>XE Migration Tool - Discuz!</title>
</head>
<body>
	<div class="topbar">
		<div class="header">
			<h1><a href="index.php">XE Migration Tool - Discuz! X2</a></h1>
			<div class="lang_select">
				<div class="selected off"><span class="ico <?php if($select_lang == 'en') echo "lang_en"; elseif($select_lang == 'ko') echo "lang_ko"; elseif($select_lang == 'zh-CN') echo "lang_cn";?>"><?php echo $lang->language; ?></span></div>
				<ul class="lang_list">
					<li><a href="index.php?select_lang=en" class="lang_en">English</a></li>
					<li><a href="index.php?select_lang=ko" class="lang_ko">한국어</a></li>
					<li><a href="index.php?select_lang=zh-CN" class="lang_cn">简体中文</a></li>
				</ul>
			</div>
		</div>
	</div>

	<div class="container">
	<?php if($step==1){ ?>
		<h2 class="m_title"><? echo $lang->oTitle; ?></h2>
    <?php if($errMsg){ ?>
        <div class="message error">
            <p><?php echo $errMsg; ?></p>
        </div>
    <?php } ?>
		<form action="./index.php" method="post">
		<input type="hidden" name="select_lang" value="<?php echo $select_lang;?>">
		<div class="step">	
			<ol class="program">
				<li>
					<h3>1. <?php echo $lang->discuz_path; ?></h3>
					<div class="item_cont">
						<input id="filepath" type="text" class="input_txt" name="path" value="<? if($_POST['path']) print $_POST['path'];else print './' ?>"> 
						<a id="ck_path" class="check_path white_btn" href="#" onclick="return false;"><?php echo $lang->check_path; ?></a>
						<em class="desc error" id="path_fail" style="display: none;"><?php echo $lang->path_fail; ?></em>
						<em class="desc success" id="path_suc" style="display: none;"><?php echo $lang->path_success; ?></em>
						<p class="eg"><?php echo $lang->about_discuz_path; ?></p>
					</div>
				</li>
				<li>
					<h3>2. <?php echo $lang->mig_target; ?></h3>
					<div class="item_cont">
						<select class="data_target" name="target">
							<option value="post"><?php echo $lang->post_rec; ?></option>
							<option value="post_comment"><?php echo $lang->post_comment_rec; ?></option>
							<option value="members"><?php echo $lang->member_rec; ?></option>
						</select>
						<p class="eg"><?php echo $lang->about_mig_target; ?></p>
					</div>
				</li>
				<li class="last_li">
					<h3>3. <?php echo $lang->data_distri; ?></h3>
					<div class="item_cont">
						<input type="number" class="input_txt input_step" step="1" min="1" name="division" value="<?php if($_POST['division']) print $_POST['division'];else print 1;?>"> 
						<p class="eg"><?php echo $lang->about_data_distri; ?></p>
					</div>
				</li>
			</ol>
		</div>
		<div class="btn_box">
			<input type="submit" class="input_submit" value="Submit" />
		</div>
		</form>
	<?php } ?>

    <?php if($step==2){ ?>
        <h2 class="m_title"><?php echo $lang->download_title; ?></h2>
		<div class="step">
        <ol>
			<li>
				<h3><?php echo $lang->transfer_title; ?></h3>
				<p><?php echo $lang->about_transfer; ?></p>
			</li>
            <li>
				<h3><?php echo $lang->data_retrieve; ?></h3>
				<p><?php if($target=='post' || $target=='post_comment') { ?><span class="cst_span"><?php echo $lang->post_num; ?>:</span><?php print $post_count.'<br/>'; }?>
				<?php if($target=='post_comment') { ?><span class="cst_span"><?php echo $lang->comment_num; ?>:</span><?php print $comment_count.'<br/>'; }?>
				<?php if($target=='members') { ?><span class="cst_span"><?php echo $lang->member_num; ?>:</span><?php print $member_count.'<br/>'; }?>
				<span class="cst_span total"><?php echo $lang->total; ?>:</span><span class="total"><?php print $total_count;?></span></p>
			</li>
            <li class="last_li">
				<h3><?php echo $lang->xml_data; ?></h3>
				
			<div class="well">
				<?php
				$real_path = 'http://'.$_SERVER['HTTP_HOST'].preg_replace('/\/index.php$/i','', $_SERVER['SCRIPT_NAME']); 
				for($i=0;$i<$division;$i++) {
					$start = $i*$division_cnt;
					$filename = sprintf("discuz.%06d.xml", $i+1);
					$url = sprintf("%s/export.php?filename=%s&amp;path=%s&amp;target=%s&amp;discuzurl=%s&amp;start=%d&amp;limit_count=%d", $real_path, urlencode($filename),  urlencode($path), urlencode($target), urlencode($discuzurl), $start, $division_cnt);
					//echo $url;
				?>
				<a class="xml_file" href="<?php print $url?>"><?php print $filename?></a> ( <?print $start+1?> ~ <?print $start+$division_cnt?> ) <!--[<a href="#" onclick="doCopyToClipboard('<?php print $url?>'); return false;"> 复制URL </a>]--> [<a class="btn_dld" href="<?php print $url?>"><?php echo $lang->download; ?></a>]
				<br>
				<?php  }  ?>
			</div><!-- // well -->
			</li>
        </ol>
		
		</div><!-- //step -->
		<div class="btn_box">
			<a onclick="history.back(); return false;" class="btn_back" href="#"><?php echo $lang->back; ?></a>
		</div>
    <? } ?>
	
	</div><!-- // container -->
	<div class="footer">
	<address>Powered by <a target="_blank" href="http://www.xpressengine.com/" class="xe_official">Xpress Engine</a></address>
	</div>
</body>
</html>
