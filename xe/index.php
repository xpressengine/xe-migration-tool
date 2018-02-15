<?php
    /**
     * @brief xe export tool
     * @author zero (zero@xpressengine.com)
     **/

    // zMigration class require
    require_once('./lib.inc.php');
    require_once('./zMigration.class.php');
    $oMigration = new zMigration();

    // 사용되는 변수의 선언
    $path = @$_POST['path'];
    $target_module = @$_POST['target_module'];
    $module_id = @$_POST['module_id'];
    if($target_module!='module') $module_id = null;
    $division = @(int)($_POST['division']);
    if(!$division) $division = 1;
    $exclude_attach = @$_POST['exclude_attach'];

    $step = 1;
    $errMsg = '';

    // 1차 체크
    if($path) {
        $db_info = getDBInfo($path);
        if(!$db_info) {
            $errMsg = "입력하신 경로가 잘못되었거나 dB 정보를 구할 수 있는 파일이 없습니다";
        } else {
            $oMigration->setDBInfo($db_info);
            $oMigration->setCharset('UTF-8', 'UTF-8');
            $message = $oMigration->dbConnect();
            if($message) $errMsg = $message;
            else $step = 2;
        }
    }

    // 2차 체크
    if($step == 2) {
        // charset을 맞춤

        // 모듈 목록을 구해옴
		if($db_info->db_type == 'cubrid')
		{
			$query = 'select * from "'.$db_info->db_table_prefix.'_modules" where "module" in (\'board\')';
		}
		else
		{
			$query = "select * from {$db_info->db_table_prefix}_modules where module in ('board')";
		}

        $module_list_result = $oMigration->query($query);
        while($module_info = $oMigration->fetch($module_list_result)) {
            $module_list[$module_info->module_srl] = $module_info;
        }
        if(!$module_list || !count($module_list)) $module_list = array();
    }

    // 3차 체크
    if($target_module) {
        if($target_module == 'module' && !$module_id) {
            $errMsg = "게시판 선택시 어떤 게시판의 정보를 추출 할 것인지 선택해주세요";
        } else {
			switch($target_module) {
				case 'member' :
					if($db_info->db_type == 'cubrid')
					{
						$query = sprintf('select count(*) as "count" from "%s_%s"', $db_info->db_table_prefix, 'member');
					}
					else
					{
						$query = sprintf("select count(*) as count from %s_%s", $db_info->db_table_prefix, 'member');
					}
					break;
				case 'message' :
					if($db_info->db_type == 'cubrid')
					{
						$query = sprintf('select count(*) as "count" from "%s_%s" where "message_type" = \'S\'', $db_info->db_table_prefix, 'member_message');
					}
					else
					{
						$query = sprintf("select count(*) as count from %s_%s where message_type = 'S'", $db_info->db_table_prefix, 'member_message');
					}
					break;
				case 'module' :
					if($db_info->db_type == 'cubrid')
					{
						$query = sprintf('select count(*) as "count" from "%s_documents" where "module_srl" = \'%d\'', $db_info->db_table_prefix, $module_id);
					}
					else
					{
						$query = sprintf("select count(*) as count from %s_documents where module_srl = '%d'", $db_info->db_table_prefix, $module_id);
					}
					break;
			}
            $result = $oMigration->query($query);
            $data = $oMigration->fetch($result);
            $total_count = $data->count;

            $step = 3;

            // 다운로드 url생성
            if($total_count>0) {
                $division_cnt = (int)(($total_count-1)/$division) + 1;
            }
        }
    }

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="ko" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta name="generator" content="XpressEngine (http://www.xpressengine.com)" />
    <meta http-equiv="imagetoolbar" content="no" />

    <title>xe data export tool ver 0.6</title>
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
                alert("URL이 복사되었습니다. Ctrl+v 또는 붙여넣기를 하시면 됩니다");
            }
        }
    </script>
</head>
<body>

    <h1>xe data export tool ver 0.6</h1>

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
        <h3>Step 1. 경로 입력</h3>

        <ul>
            <li>
                xe 가 설치된 경로를 입력해주세요.

                <blockquote>
                예1) /home/아이디/public_html/xe<br />
                예2) ../xe
                </blockquote>

                <input type="text" name="path" value="<?php print $path; ?>" class="input_text" /><input type="submit" class="input_submit"value="설치 경로 입력" />
            </li>
        </ul>
    </form>

    <?php
        if($step>1) {
    ?>
    <hr />

    <form action="./index.php" method="post">
    <input type="hidden" name="path" value="<?php echo $path?>" />

        <h3>Step 2. 추출할 대상을 선택해주세요. (회원정보 또는 게시판)</h3>
        <blockquote>xe는 회원정보와 그외 모듈 종류를 나누어 추출하실 수 있습니다.</blockquote>

        <ul>
            <li>
                <label for="member">
                    <input type="radio" name="target_module" value="member" id="member" <?php if($target_module=="member") print "checked=\"checked\""?>/>
                    회원정보
                </label>
            </li>
            <li>
                <label for="message">
                    <input type="radio" name="target_module" value="message" id="message" <?php if($target_module=="message") print "checked=\"checked\""?> />
                    쪽지
                </label>
            </li>
            <li>
                <label for="module">
                    <input type="radio" name="target_module" value="module" id="module"  <?php if($target_module=="module") print "checked=\"checked\""?>/>
                    게시판
                </label>

                    <select name="module_id" size="10" class="module_list" onclick="this.form.target_module[2].checked=true;">
                    <?php
                        foreach($module_list as $module_info) {
                        $srl = $module_info->module_srl;
                        $title = sprintf('%s (%s)', $module_info->browser_title, $module_info->mid);
                    ?>
                        <option value="<?php echo $srl?>" <?php if($module_id == $srl){?>selected="selected"<?php }?>><?php echo $title?></option>
                    <?php 
                        } 
                    ?>
                    </select><br />
                    <input type="submit" value="추출 대상 선택" class="input_submit" />
            </li>
        </ul>
    </form>
    <?php
        }
    ?>

    <?php
        if($step>2) {
    ?>
    <hr />

    <form action="./index.php" method="post">
    <input type="hidden" name="path" value="<?php echo $path?>" />
    <input type="hidden" name="target_module" value="<?php echo $target_module?>" />
    <input type="hidden" name="module_id" value="<?php echo $module_id?>" />

        <h3>Step 3. 전체 개수 확인 및 분할 전송</h3>
        <blockquote>
            추출 대상의 전체 개수를 보시고 분할할 개수를 정하세요<br />
            추출 대상 수 / 분할 수 만큼 추출 파일을 생성합니다.<br />
            대상이 많을 경우 적절한 수로 분할하여 추출하시는 것이 좋습니다.
        </blockquote>

        <ul>
            <li>추출 대상 수 : <?php print $total_count; ?></li>
            <li>
                분할 수 : <input type="text" name="division" value="<?php echo $division?>" />
                <input type="submit" value="분할 수 결정" class="input_submit" />
            </li>
            <?php if($target_module == "module") {?>
            <li>
                첨부파일 미포함 : <input type="checkbox" name="exclude_attach" value="Y" <?php if($exclude_attach=='Y') print "checked=\"checked\""; ?> />
                <input type="submit" value="첨부파일 미포함" class="input_submit" />
            </li>
            <?php } ?>
        </ul>

        <blockquote>
            추출 파일 다운로드<br />
            차례대로 클릭하시면 다운로드 하실 수 있습니다<br />
            다운을 받지 않고 URL을 직접 zbXE 데이터이전 모듈에 입력하여 데이터 이전하실 수도 있습니다.
        </blockquote>

        <ol>
        <?php
            $real_path = 'http://'.$_SERVER['HTTP_HOST'].preg_replace('/\/index.php$/i','', $_SERVER['SCRIPT_NAME']);
            for($i=0;$i<$division;$i++) {
                $start = $i*$division_cnt;
                $filename = sprintf("%s%s.%06d.xml", $target_module, $module_id?'_'.$module_id:'', $i+1);
                $url = sprintf("%s/export.php?filename=%s&amp;path=%s&amp;target_module=%s&amp;module_id=%s&amp;start=%d&amp;limit_count=%d&amp;exclude_attach=%s", $real_path, urlencode($filename), urlencode($path), urlencode($target_module), urlencode($module_id), $start, $division_cnt, $exclude_attach);
        ?>
            <li>
                <a href="<?php print $url?>"><?php print $filename?></a> ( <?print $start+1?> ~ <?print $start+$division_cnt?> ) [<a href="#" onclick="doCopyToClipboard('<?php print $url?>'); return false;">URL 복사</a>]
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
        powered by zero (xpressengine.com)
    </address>
</body>
</html>
