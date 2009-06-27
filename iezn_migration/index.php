<?php 
    /**
     * @brief iezn 마이그레이션 index 파일
     * iezn의 마이그레이션은 경로와 charset을 입력받고 회원 또는 게시판 ID를 선택받아 xml 파일을 출력하는 순서로 동작
     **/

    include "./tpl/header.php"; 
?>

    <form action="./module_list.php" method="post">
        <div class="title">Step 1. iezn 경로 입력</div>
        <div class="desc">
            iezn의 global.conf.php 가 설치된 경로를 입력해주세요.<br />
            <br />
            예1) /home/아이디/public_html/iezn<br />
            예2) ../iezn<br />
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
