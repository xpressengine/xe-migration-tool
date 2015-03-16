<?php
    /**
     * @brief KIMSQ RB 경로를 이용하여 DB정보를 얻어옴
     * @author upgle@xpressengine.com
     **/
    function getDBInfo($path) {
        if(substr($path,-1)=='/') $path = substr($path, 0, strlen($path)-1);
        $config_file = sprintf('%s/_var/db.info.php',$path);

        if(!file_exists($config_file)) return;

        @include($config_file);

        $output->db_hostname = $DB['host'];
        $output->db_userid = $DB['user'];
        $output->db_password = $DB['pass'];
        $output->db_database = $DB['name'];
        $output->db_prefix = $DB['head'];
        return $output;
    }

    function getMemberID($uid)
    {
        global $oMigration;
        global $db_info;

        if(!isset($db_info)) {
            $db_info = new stdclass();
            $db_info->db_prefix = 'rb';
        }
        if(!$uid) return false;

        // 회원 아이디 정보를 구함
        $query = sprintf("select * from %s_s_mbrid where uid='%s'", $db_info->db_prefix, $uid);
        $module_info_result = $oMigration->query($query);
        $member_info = mysql_fetch_object($module_info_result);
        return $member_info->id;
    }

    function getMemberInfo($memberuid)
    {
        global $oMigration;
        global $db_info;

        if(!isset($db_info)) {
            $db_info = new stdclass();
            $db_info->db_prefix = 'rb';
        }
        if(!$memberuid) return false;

        // 회원 정보를 구함
        $query = sprintf("select * from %s_s_mbrdata where memberuid='%s'", $db_info->db_prefix, $memberuid);
        $module_info_result = $oMigration->query($query);
        $member_info = mysql_fetch_object($module_info_result);
        return $member_info;
    } 
?>
