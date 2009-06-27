<?php

    /**
     * @brief zb5beta의 경로를 이용하여 DB정보를 얻어옴
     * @author zero@xpressengine.com
     **/
    function getDBInfo($path) {
        if(substr($path,-1)=='/') $path = substr($path, 0, strlen($path)-1);
        $config_file = sprintf('%s/files/db_config.inc.php',$path);

        if(!file_exists($config_file)) return;

        $file_buff = file($config_file);
        $db_info = explode("\n",base64_decode(trim($file_buff[1])));

        for($i=0;$i<count($db_info);$i++) {
            $buff[$i+1] = base64_decode($db_info[$i]);
        }

        $output->db_hostname = trim($buff[1]);
        $output->db_userid = trim($buff[2]);
        $output->db_password = trim($buff[3]);
        $output->db_database = trim($buff[4]);
        $output->db_prefix = $buff[5];
        return $output;
    } 

?>
