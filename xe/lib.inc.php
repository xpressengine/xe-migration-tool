<?php
    /**
     * DB의 정보를 구하는 함수 (대상 tool마다 다름)
     * db에 접속할 수 있도록 정보를 구한 후 형식을 맞춰 zMigration에서 쓸수 있도록 return
     **/
    function getDBInfo($path) {
        if(substr($path,-1)=='/') $path = substr($path, 0, strlen($path)-1);
        $config_file = sprintf('%s/files/config/db.config.php',$path);

        define('__ZBXE__',true);

        if(!file_exists($config_file)) return;
        include($config_file);

        $info->db_type = $db_info->db_type;
        $info->db_port = $db_info->db_port;
        $info->db_hostname = $db_info->db_hostname;
        $info->db_userid = $db_info->db_userid;
        $info->db_password = $db_info->db_password;
        $info->db_database = $db_info->db_database;
        $info->db_table_prefix = $db_info->db_table_prefix;

        return $info;
    } 

?>
