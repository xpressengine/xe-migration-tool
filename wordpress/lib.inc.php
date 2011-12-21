<?php
    /**
     * get database info
     **/
    function getDBInfo($path) {
        if(substr($path,-1)=='/') $path = substr($path, 0, strlen($path)-1);
        $config_file = sprintf('%s/wp-config.php',$path);

        if(!file_exists($config_file)) return;

        $f = fopen($config_file,"r");
        while(!feof($f)) {
            $str = fgets($f,1024);
            if(preg_match('/require_once/i',$str)) continue;
            $buff .= $str;
        }
        fclose($f);

        @eval('?>'.$buff);

        $info->db_type = 'mysql';
        $info->db_port = 3306;
        $info->db_hostname = DB_HOST;
        $info->db_userid = DB_USER;
        $info->db_password = DB_PASSWORD;
        $info->db_database = DB_NAME;
        $info->db_table_prefix = preg_replace('/_$/','',$table_prefix);

        return $info;
    } 

?>
