<?php
    /**
     * get DB information from discuz config file 
     * $discuz/config/config_global.php
     **/

    function getDBInfo($path) {
        if(substr($path,-1)=='/') $path = substr($path, 0, strlen($path)-1);
		
		$config_file = sprintf('%s/config/config_global.php',$path);

        if(!file_exists($config_file)) return;
		
        $f = fopen($config_file,"r");


        while(!feof($f)) {
            $str = fgets($f,1024);
			$buff .= $str;
        }
 
        fclose($f);

        @eval('?>'.$buff);

		// get discuz!x2 db information
		$info->db_type = 'mysql';
        $info->db_port = 3306;
		$info->db_host = $_config['db']['1']['dbhost'];
		$info->db_user = $_config['db']['1']['dbuser'];
		$info->db_pw = $_config['db']['1']['dbpw'];
		$info->db_dbname = $_config['db']['1']['dbname'];
		$info->dbcharset = $_config['db']['1']['dbcharset'];
		$info->pconnect = $_config['db']['1']['pconnect'];
		$info->tablepre = $_config['db']['1']['tablepre'];
        
		// get UCenter infor
        $info->db_UC_type = 'mysql';
        $info->db_UC_port = 3306;
        $info->db_UC_hostname = UC_DBHOST;
        $info->db_UC_userid = UC_DBUSER;
        $info->db_UC_password = UC_DBPW;
        $info->db_UC_database = UC_DBNAME;
        $info->db_UC_table_prefix = preg_replace('/_$/','',UC_DBTABLEPRE);

        return $info;
    } 

?>
