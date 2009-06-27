<?php 
    /**
     * @brief 게시판 경로를 입력받은 후 회원 또는 게시판중 어떤 것을 export할 것인지를 선택함
     **/

    // zMigration class파일 load
    require_once("./classes/zMigration.class.php");

    // library 파일 load
    require_once("lib/lib.php");

    // 입력받은 post 변수를 구함
    $charset = $_POST['charset'];
    $path = $_POST['path'];

    // 입력받은 path를 이용하여 db 정보를 구함
    $db_info = getDBInfo($path);
    if(!$db_info) doError("입력하신 경로가 잘못되었거나 dB 정보를 구할 수 있는 파일이 없습니다");

    // zMigration 객체 생성
    $oMigration = new zMigration($path,'','',$charset);

    // db 접속
    $message = $oMigration->dbConnect($db_info);
    if($message) doError($message);

    // 게시판 목록 구하기
    $query = "select gs_num, gs_name from iezn_gs order by g_num asc";
    $module_list_result = $oMigration->query($query);
    
    include "./tpl/header.php"; 
?>

    <form action="./export.php" method="post">
    <input type="hidden" name="path" value="<?php echo $path?>" />
    <input type="hidden" name="charset" value="<?php echo $charset?>" />

        <div class="title">Step 2. 백업할 대상을 선택해주세요. (회원정보 또는 게시판)</div>
        <div class="desc">iezn 데이터는 회원정보와 그외 게시판으로 종류를 나누어 백업하실 수 있습니다.</div>

        <div class="content">
            <input type="radio" name="target_module" value="member" id="member" checked="checked"/>
            <label for="member">회원정보</label>
        </div>
        <div class="content">
            <input type="radio" name="target_module" value="module" id="module" />
            <label for="module">
                게시판<br />
                <select name="module_id" size="10" class="module_list" onclick="this.form.target_module[1].checked=true;">
    <?php
        while($module_info = mysql_fetch_object($module_list_result)) {
    ?>
                    <option value="<?php echo $module_info->gs_num?>"><?php echo $module_info->gs_name?></option>
    <?php
        }
    ?>
                </select>
            </label>
        </div>

        <div class="button_area">
            <input type="submit" value="next" class="input_submit" />
        </div>

    </form>

<?php include "./tpl/footer.php"; ?>
