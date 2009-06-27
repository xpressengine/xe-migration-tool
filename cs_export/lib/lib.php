<?php

    /**
     * @brief 제로보드4의 경로를 이용하여 DB정보를 얻어옴
     * @author zero@xpressengine.com
     **/
    function getDBInfo($path) {
        if(substr($path,-1)=='/') $path = substr($path, 0, strlen($path)-1);
        $config_file = sprintf('%s/config.php',$path);

        if(!file_exists($config_file)) return;

	@include($config_file);
        $output->hostname = $DB_HOST;
        $output->userid = $DB_USER;
        $output->password = $DB_PWD;
        $output->database = $DB_NAME;
        $output->root_dir = $ROOT_DIR;
        return $output;
    } 

    /**
     * @brief javascript로 에러 메세지 출력
     **/
    function doError($message) {
        include "./tpl/header.php"; 
        printf('<script type="text/javascript">alert("%s"); location.href="./";</script>', $message);
        include "./tpl/footer.php"; 
        exit();
    }

?>
