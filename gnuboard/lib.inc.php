<?php
    /**
     * @brief gnuboard4의 경로를 이용하여 DB정보를 얻어옴
     * @author zero@xpressengine.com
     **/
    function getDBInfo($path) {
        if(substr($path,-1)=='/') $path = substr($path, 0, strlen($path)-1);
        $config_file = sprintf('%s/dbconfig.php',$path);

        if(!file_exists($config_file)) return;

        @include($config_file);

        $output->db_hostname = $mysql_host;
        $output->db_userid = $mysql_user;
        $output->db_password = $mysql_password;
        $output->db_database = $mysql_db;

        $common_file = sprintf('%s/config.php', $path);
        @include($common_file);

        $output->g4 = $g4;
        $output->db_prefix = $g4['table_prefix'];
        return $output;
    } 
?>
