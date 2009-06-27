<?php 
    /**
     * @brief cs 마이그레이션 index 파일
     * cs의 마이그레이션은 경로와 charset을 입력받고 회원 또는 게시판 ID를 선택받아 xml 파일을 출력하는 순서로 동작
     **/

    include "./tpl/header.php"; 
?>

    <form action="./module_list.php" method="post">
        <div class="title">Step 1. cs 경로 입력</div>
        <div class="desc">
            DB정보가 등록된 config.php파일의 위치를 입력해주세요.	
            <br />
            예1) /home/아이디/public_html/bbs <br />
            예2) ../bbs <br />
        </div>

        <div class="content">
            <div class="header">Charset</div>
            <div class="tail"><select name="charset"><option value="EUC-KR">EUC-KR</option><option value="UTF-8">UTF-8</option></select></div>
            <div class="clear"></div>
            <div class="header">경로</div>
            <div class="tail"><input type="text" name="path" class="input_text" value="" /></div>
            <div class="tail"><input type="submit" class="input_submit"value="next" /></div>
        </div>
    </form>

<?php 
    include "./tpl/footer.php"; 
?>
