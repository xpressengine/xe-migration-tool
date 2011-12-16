    <?php
	/**
     * @brief phpBB export tool
     * @author Arnia Software (xe_dev@arnia.ro)
     **/

	error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED);
	 
    // zMigration class require
    require_once('./lib.inc.php');
    require_once('./zMigration.class.php');
    $oMigration = new zMigration();
	
	$target_module = '';
	
    // Retrieve form variables
	if($_POST){
		if($_POST['path']) $path = $_POST['path'];
		if($_POST['division']) $division = (int)($_POST['division']);
		if($_POST['exclude_attach']) $exclude_attach = $_POST['exclude_attach'];
	}
	if(!$division) $division = 1;

    $step = 1;
    $errMsg = '';

    // Step 1
    if($path) {
        $db_info = getDBInfo($path);
        if(!$db_info) {
            $errMsg = "Could not retrieve phpBB database config file.";
        } else {
            $oMigration->setDBInfo($db_info);
            $oMigration->setCharset('UTF-8', 'UTF-8');
            $message = $oMigration->dbConnect();
            if($message) $errMsg = $message;
            else $step = 2;
        }
    }

    // Step 2
    if($step == 2) {
	$query = sprintf("select count(*) as count from %s_posts", $db_info->db_table_prefix);
    	$result = $oMigration->query($query);
        $data = $oMigration->fetch($result);
        $total_count = $data->count;

        // Generate count for when data is split into multiple xml files
        if($total_count>0) $division_cnt = (int)(($total_count-1)/$division) + 1;
    }
	
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="ko" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta name="generator" content="XpressEngine (http://www.xpressengine.com)" />
    <meta http-equiv="imagetoolbar" content="no" />

    <title>phpBB3 Data Export Tool version 1.0</title>
    <style type="text/css">
        body { font-family:arial; font-size:9pt; }
        input.input_text { width:400px; }
        blockquote.errMsg { color:red; }
        select.module_list { display:block; width:500px; }
    </style>
    <link rel="stylesheet" href="./default.css" type="text/css" />

    <script type="text/javascript">
        function doCopyToClipboard(value) {
            if(window.event) {
                window.event.returnValue = true;
                window.setTimeout(function() { copyToClipboard(value); },25);
            }
        }
        function copyToClipboard(value) {
            if(window.clipboardData) {
                var result = window.clipboardData.setData('Text', value);
                alert("URL? ???????. Ctrl+v ?? ????? ??? ???");
            }
        }
    </script>
</head>
<body>

    <h1>phpBB3 Data Export Tool version 1.0</h1>

    <?php
        if($errMsg) {
    ?>
    <hr />
        <blockquote class="errMsg">
            <?php echo $errMsg; ?>
        </blockquote>
    <?php
        }
    ?>

    <hr />

    <form action="./index.php" method="post">
        <h3>Step 1. Enter phpBB installation path</h3>

        <ul>
            <li>
                Please enter server path (not URL). Here are some examples:

                <blockquote>
                1) /home/user/public_html/phpBB<br />
                2) ../phpBB
                </blockquote>

                <input type="text" name="path" value="<?php print $_POST['path']?>" class="input_text" /><input type="submit" class="input_submit"value="Submit" />
            </li>
        </ul>
    </form>

    <?php
        if($step>1) {
    ?>
    <hr />

    <form action="./index.php" method="post">
    <input type="hidden" name="path" value="<?php echo $path?>" />
    <input type="hidden" name="target_module" value="<?php echo $target_module?>" />
    <input type="hidden" name="module_id" value="<?php echo $module_id?>" />

        <h3>Step 2. Checking total number of posts to migrate</h3>
        <blockquote>
            Please enter how many posts should be exported at a time (split step)<br />
            The total number of articles will be divided by the number below.<br />
            It is best if you choose a number that will make parts similar (exact division).
        </blockquote>

        <ul>
            <li>Total count : <?php print $total_count; ?></li>
            <li>
                Partition size : <input type="text" name="division" value="<?php echo $division?>" />
                <input type="submit" value="Update partition size" class="input_submit" />
            </li>
            <?php if($target_module == "module") {?>
            <li>
                Without the attachments: <input type="checkbox" name="exclude_attach" value="Y" <?php if($exclude_attach=='Y') print "checked=\"checked\""; ?> />
                <input type="submit" value="Without attachments" class="input_submit" />
            </li>
            <?php } ?>
        </ul>

        <blockquote>
            Download the generated file<br />
            You can download it by clicking the link.<br />
        </blockquote>

        <ol>
        <?php
            $real_path = 'http://'.$_SERVER['HTTP_HOST'].preg_replace('/\/index.php$/i','', $_SERVER['SCRIPT_NAME']);
            for($i=0;$i<$division;$i++) {
                $start = $i*$division_cnt;
                $filename = sprintf("phpBB.%06d.xml", $i+1);
                $url = sprintf("%s/export.php?filename=%s&amp;path=%s&amp;start=%d&amp;limit_count=%d&amp;exclude_attach=%s", $real_path, urlencode($filename), urlencode($path), $start, $division_cnt, $exclude_attach);
        ?>
            <li>
                <a href="<?php print $url?>">
					<?php print $filename?>
				</a> 
				( <?php print $start+1?> ~ <?php print $start+$division_cnt?> ) 
				[<a href="#" onclick="doCopyToClipboard('<?php print $url?>'); return false;">URL Copy</a>]
            </li>
        <?php
            }   
        ?>
        </ol>
    </form>
    <?php
        }
    ?>

    <hr />
    <address>
        powered by Xpressengine.org
    </address>
</body>
</html>