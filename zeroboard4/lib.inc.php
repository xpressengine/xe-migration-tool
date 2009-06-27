<?php

    /**
     * @brief 제로보드4의 경로를 이용하여 DB정보를 얻어옴
     * @author zero@xpressengine.com
     **/
    function getDBInfo($path) {
        if(substr($path,-1)=='/') $path = substr($path, 0, strlen($path)-1);
        $config_file = sprintf('%s/config.php',$path);

        if(!file_exists($config_file)) return;

        $buff = file($config_file);

        $info->db_hostname = trim($buff[1]);
        $info->db_userid = trim($buff[2]);
        $info->db_password = trim($buff[3]);
        $info->db_database = trim($buff[4]);

        return $info;
    } 

?>
