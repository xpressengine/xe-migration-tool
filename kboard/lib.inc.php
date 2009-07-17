<?php

    /**
     * @brief kboardv2.3.0의 경로를 이용하여 DB정보를 얻어옴
     * @author zero@xpressengine.com / modify geusgod@gmail.com
     **/
    function getDBInfo($path) {
        if(substr($path,-1)=='/') $path = substr($path, 0, strlen($path)-1);
        $config_file = sprintf('%s/kconf.php',$path);

        if(!file_exists($config_file)) return;

        @include($config_file);

        $output->db_hostname = $host;
        $output->db_userid = $db_id;
        $output->db_password = $db_pass;
        $output->db_database = $db_name;

        return $output;
    }
?>