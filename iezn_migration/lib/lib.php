<?php

    /**
     * @brief izen 경로를 이용하여 DB정보를 얻어옴
     * @author zero@xpressengine.com
     **/
    function getDBInfo($path) {
        if(substr($path,-1)=='/') $path = substr($path, 0, strlen($path)-1);
        $config_file = sprintf('%s/global.conf.php',$path);

        if(!file_exists($config_file)) return;
	@include($config_file);

	$db_config_file = sprintf('%s/iezn/%s', DATABASE_PATH, 'dbconn.php');
        if(!file_exists($db_config_file)) return;
	@include($db_config_file);

        $output->hostname = trim($db['iezn']['host']);
        $output->userid = trim($db['iezn']['id']);
        $output->password = trim($db['iezn']['passwd']);
        $output->database = trim($db['iezn']['name']);
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
