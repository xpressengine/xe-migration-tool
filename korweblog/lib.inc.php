<?php
    function getDBInfo($path) {
        if(substr($path,-1)=='/') $path = substr($path, 0, strlen($path)-1);
        $config_file = sprintf('%s/include/common.inc.php', $path);

        if(!file_exists($config_file)) return;

	$matches = array('db_port'=>'$G_PORT','db_hostname'=>'$G_HOST', 'db_userid'=>'$G_USER', 'db_password'=>'$G_PASS', 'db_database' => '$G_DB');

	$f = fopen($config_file,'r');
	while(!feof($f)) {
		$str = trim(fgets($f, 1024));

		foreach($matches as $key => $val) {
			if(!preg_match('/^\\'.$val.'([ \=]+)"([^"]+)"/i',$str, $output)) continue;
			$info->{$key} = $output[2];
		}
		if(!$str) continue;

	}
	fclose($f);

        $info->db_type = 'mysql';
        $info->db_table_prefix = '';

        return $info;
    } 

?>
