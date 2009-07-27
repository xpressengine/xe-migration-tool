<?php 
    /**
     * @brief xe export tool
     * @author zero (zero@xpressengine.com)
     **/
    @set_time_limit(0);

    // 사용되는 변수의 선언
    $path = $_GET['path'];
    if(substr($path,-1)=='/') $path = substr($path,0,strlen($path)-1);
    $user_id = $_POST['user_id'];
    $nick_name = $_POST['nick_name'];

    // 입력받은 path를 이용하여 db 정보를 구함
    if(!is_dir($path)) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    require_once('./zMigration.class.php');
    $oMigration = new zMigration();
    $oMigration->setModuleType('module','springnote');
    $oMigration->setCharset('UTF-8', 'UTF-8');
    $oMigration->setFilename('springnote.xml');
    $oMigration->setPath($path);

    function findDir($path, &$list) {
        $oDir = dir($path);
        while($file = $oDir->read()) {
            if(substr($file,0,1)=='.') continue;
            $entry = $path.'/'.$file;
            if(is_file($entry) && $file=='index.html') $list[] = $path.'/';
            elseif(is_dir($entry)) findDir($entry, $list);
        }
        $oDir->close();
    }
    // 디렉토리를 recursive하게 돌면서 XML로 전환
    findDir($path, $dirs = array());
    if(!count($dirs)) {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    $oMigration->printHeader(0);
    $oMigration->printCategoryItem(array());

    for($i=0,$c=count($dirs);$i<$c;$i++) {
        unset($obj);

        $dir = $dirs[$i];
        $f = fopen($dir.'index.html','r');
        $buff = '';
        $started = false;
        while(!feof($f)) {
            $str = trim(fgets($f,1024));
            if(!$str) continue;
            if(substr($str,0,5)=='<body') {
                $started = true;
                continue;
            }
            if(!$started) continue;
            if(substr($str,0,3)=='<h1') {
                $obj->title = strip_tags($str);
                continue;
            }
            if(substr($str,0,7)=='</body>') break;
            $buff .= $str."\r\n";
        }
        fclose($f);

        $obj->content = $buff;
        $obj->user_id = $user_id;
        $obj->nick_name = $nick_name;
        $obj->allow_comment = 'Y';
        $obj->lock_comment = 'N';
        $obj->allow_trackback = 'Y';
        $obj->is_notice = 'N';
        $obj->is_secret = 'N';
        $obj->regdate =  date("YmdHis");
        $obj->update = null;
        $obj->tags = null;

        $files = array();
        $oDir = dir($dir);
        while($file = $oDir->read()) {
            if(substr($file,0,1)=='.') continue;
            $entry = $dir.'/'.$file;
            if(is_file($entry) && $file!='index.html') {
                $file_obj = null;
                $file_obj->filename = $file;
                $file_obj->file = realpath($entry);
                $file_obj->download_count = 0;
                $files[] = $file_obj;
            }
        }
        $oDir->close();
        $obj->attaches = $files;
        $oMigration->printPostItem($i, $obj);
    }

    // 푸터 정보를 출력
    $oMigration->printFooter();
?>
