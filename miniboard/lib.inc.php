<?php
    /**
     * @brief miniboard의 경로를 이용하여 DB정보를 얻어옴
     * @author zero@xpressengine.com
     **/
    function getDBInfo($path) {
        if(substr($path,-1)=='/') $path = substr($path, 0, strlen($path)-1);

        $config_file = sprintf('%s/setting.ini.php',$path);
        if(!file_exists($config_file)) return;

		$info = parse_ini_file($config_file);
        $db_info->db_hostname = $info['hostname'];
        $db_info->db_userid = $info['userid'];
        $db_info->db_password = $info['userpass'];
        $db_info->db_database = $info['dbname'];
        $db_info->db_prefix = $info['db_global_value'];
        $db_info->charset = $info['charset'];
        $db_info->db_type = 'mysql';

        return $db_info;
    } 

?>
