<?php
    /**
     * @brief springnote export tool
     * @author zero (zero@xpressengine.com)
     **/

    // zMigration class require
    require_once('./zMigration.class.php');
    $oMigration = new zMigration();

    // 사용되는 변수의 선언
    $path = $_POST['path'];
    $user_id = $_POST['user_id'];
    $nick_name = $_POST['nick_name'];
    if($path && substr($path,-1)!='/') $path .= '/';

    $errMsg = '';
    $step = 1;

    if($path) {
        // 스프링노트 데이터 파일이 있는지 확인
        if(!is_dir($path)) $errMsg = '백업받은 스프링노트 압축파일을 해제한 디렉토리를 지정해주셔야 합니다';
        else $step = 2;
    }

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="ko" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta name="generator" content="XpressEngine (http://www.xpressengine.com)" />
    <meta http-equiv="imagetoolbar" content="no" />

    <title>Springnote data export tool ver 0.2</title>
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

    <h1>Springnote data export tool ver 0.2</h1>

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
        <h3>경로 입력</h3>

        <ul>
            <li>
                Springnote 백업파일의 압축을 푼 디렉토리를 입력해주세요.<br/>
                사용자 아이디/닉네임은 XE에서 데이터를 입력할때 기입될 사용자 정보입니다.<br/>

                <blockquote>
                예1) /home/아이디/public_html/Springnote<br />
                예2) ../Springnote
                </blockquote>

                <ol>
                    <li>경로 : <input type="text" name="path" value="<?php echo $path?>" class="input_text" /></li>
                    <li>사용자 아이디 : <input type="text" name="user_id" value="<?php echo $user_id?>" class="input_text" /></li>
                    <li>닉네임 : <input type="text" name="nick_name" value="<?php echo $nick_name?>" class="input_text" /></li>
                </ol>
                <input type="submit" class="input_submit"value="설치 경로 입력" />
            </li>
        </ul>
    </form>

    <?php
        if($step>1) {
    ?>
    <hr />

        <h3>Step 2. XML 파일 전송</h3>
        <blockquote>
            스프링노트 백업 내용을 바탕으로 XE에서 import할 수 있는 XML파일을 만들게 됩니다.
            만들어진 XML 파일을 다운받거나 XML 파일의 URL을 이용해서 XE의 데이터이전 모듈에서 스프링노트의 데이터를 게시판이나 위키등에서 부를 수 있게 됩니다.
        </blockquote>

        <?php
            $real_path = 'http://'.$_SERVER['HTTP_HOST'].preg_replace('/\/index.php$/i','', $_SERVER['SCRIPT_NAME']);
            $url = sprintf("%s/export.php?filename=%s&amp;path=%s&amp;user_id=%s&amp;nick_name=%s", $real_path, 'springnote.xml', urlencode($path),urlencode($user_id),urlencode($nick_name));
        ?>
        <ul>
            <li>
                <a href="<?php print $url?>">springnote.xml</a> [<a href="#" onclick="doCopyToClipboard('<?php print $url?>'); return false;">URL 복사</a>]
            </li>
        </ul>
    <?
        }
    ?>

    <hr />
    <address>
        powered by zero (xpressengine.com)
    </address>
</body>
</html>
