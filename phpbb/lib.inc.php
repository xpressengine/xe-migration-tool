<?php
    /**
     * Method for retrieving database info from phpBB application
     **/
    function getDBInfo($path) {
        if(substr($path,-1)=='/') $path = substr($path, 0, strlen($path)-1);
        $config_file = sprintf('%s/config.php',$path);

        if(!file_exists($config_file)) return;

        $f = fopen($config_file,"r");
		$buff = '';
        while(!feof($f)) {
            $str = fgets($f,1024);
            if(preg_match('/require_once/i',$str)) continue;
            $buff .= $str;
        }
        fclose($f);

        @eval('?>'.$buff);

		$info = new stdClass;
        $info->db_type = $dbms;
        $info->db_port = $dbport;
        $info->db_hostname = $dbhost;
        $info->db_userid = $dbuser;
        $info->db_password = $dbpasswd;
        $info->db_database = $dbname;
        $info->db_table_prefix = preg_replace('/_$/','',$table_prefix);

        return $info;
    } 

?>
