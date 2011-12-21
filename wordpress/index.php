<?php
    /**
     * @brief wordpress export tool
     * @author zero (zero@xpressengine.com)
     **/

    // zMigration class require
    require_once('./lib.inc.php');
    require_once('./zMigration.class.php');
    $oMigration = new zMigration();

    // variable declaration
    $path = $_POST['path'];
    $division = (int)($_POST['division']);
    if(!$division) $division = 1;
    $exclude_attach = $_POST['exclude_attach'];

    $step = 1;
    $errMsg = '';

    // check path
    if($path) {
        $db_info = getDBInfo($path);
        if(!$db_info) {
            $errMsg = "DB path information you enter is invalid or does not have a file that can be obtained";
        } else {
            $oMigration->setDBInfo($db_info);
            $oMigration->setCharset('UTF-8', 'UTF-8');
            $message = $oMigration->dbConnect();
            if($message) $errMsg = $message;
            else $step = 2;
        }
    }

    // check if step 2
    if($step == 2) {
	$query = sprintf("select count(*) as count from %s_posts where post_type = 'post'", $db_info->db_table_prefix);
    	$result = $oMigration->query($query);
        $data = $oMigration->fetch($result);
        $total_count = $data->count;

        // url
        if($total_count>0) $division_cnt = (int)(($total_count-1)/$division) + 1;
    }

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="ko" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta name="generator" content="XpressEngine (http://www.xpressengine.org)" />
    <meta http-equiv="imagetoolbar" content="no" />

    <title>wordpress ver 3.x data export tool</title>
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
                alert("URL has been copied");
            }
        }
    </script>
</head>
<body>

    <h1>wordpress 3.x data export tool</h1>

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
        <h3>Step 1. Enter path</h3>

        <ul>
            <li>
                Please enter the installation path for wordpress

                <blockquote>
                Example 1: /home/username/public_html/wp<br />
                Example 2: ../wp
                </blockquote>

                <input type="text" name="path" value="<?php print $_POST['path']?>" class="input_text" /><input type="submit" class="input_submit"value="Submit path" />
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

        <h3>Step 2. Checking and splitting the total number of transfer</h3>
        <blockquote>
        Extraction of all content. Please decide the number of parts to split.<br />
        If you have a suitable number of targeted articles than extraction by partitioning is recommended.
        </blockquote>

        <ul>
            <li>To extract: <?php print $total_count; ?></li>
            <li>
                Pieces to split in: <input type="text" name="division" value="<?php echo $division?>" />
                <input type="submit" value="Submit number of pieces" class="input_submit" />
            </li>
            <?php if($target_module == "module") {?>
            <li>
                Exclude attachement : <input type="checkbox" name="exclude_attach" value="Y" <?php if($exclude_attach=='Y') print "checked=\"checked\""; ?> />
                <input type="submit" value="Exclude attachement" class="input_submit" />
            </li>
            <?php } ?>
        </ul>

        <blockquote>
        These are the filed to download<br />
        You can download them by clcking on the links below.<br />
        </blockquote>

        <ol>
        <?php
            $real_path = 'http://'.$_SERVER['HTTP_HOST'].preg_replace('/\/index.php$/i','', $_SERVER['SCRIPT_NAME']);
            for($i=0;$i<$division;$i++) {
                $start = $i*$division_cnt;
                $filename = sprintf("wp.%06d.xml", $i+1);
                $url = sprintf("%s/export.php?filename=%s&amp;path=%s&amp;start=%d&amp;limit_count=%d&amp;exclude_attach=%s", $real_path, urlencode($filename), urlencode($path), $start, $division_cnt, $exclude_attach);
        ?>
            <li>
                <a href="<?php print $url?>"><?php print $filename?></a> ( <?print $start+1?> ~ <?print $start+$division_cnt?> ) [<a href="#" onclick="doCopyToClipboard('<?php print $url?>'); return false;">copy URL</a>]
            </li>
        <?php
            }   
        ?>
        </ol>
    </form>
    <?
        }
    ?>

    <hr />
    <address>
        powered by www.xpressengine.org
    </address>
</body>
</html>