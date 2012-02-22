<?php
    /**
     * check if the file is existed or not  
     **/

	sleep(1);
    $path = $_POST['filename'];
	$config_file = sprintf('%s/config/config_global.php',$path);

	if(file_exists($config_file)){
		echo "success";
	}else{
		echo "failed";
	}

?>
