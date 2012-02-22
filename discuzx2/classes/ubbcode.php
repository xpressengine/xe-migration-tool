<?php
function ubbcode($str){
	//$str = str_replace("file:","file :",$str);
	//$str = str_replace("files:","files :",$str);
	//$str = str_replace("script:","script :",$str);
	//$str = str_replace("js:","js :",$str);
	   
	// Image UBB
	$str = preg_replace("/\[img\](http|https|ftp):\/\/(.[^\[]*)\[\/img\]/i", "<a onfocus=\"this.blur()\" href=\"\${1}://\${2}\" target=new><img src=\"\${1}://\${2}\" border=\"0\"></a>", $str);
	$str = preg_replace("/\[img=*([0-9]*),*([0-9]*)\](http|https|ftp):\/\/(.[^\[]*)\[\/img\]/i", "<a onfocus=\"this.blur()\" href=\"\${3}://\${4}\" target=new><img src=\"\${3}://\${4}\" border=\"0\" width=\"\${1}\" heigh=\"\${2}\"></a>", $str);

	$str = preg_replace("/(\[img\])images\/face\/em(.*?)\.gif(\[\/img\])/i", "<img src=\"images/face/em\${2}.gif\" />", $str);


	// URL UBB
	$str = preg_replace("/(\[url\])(.[^\[]*)(\[url\])/i", "<a href=\"\${2}\" target=\"new\">\${1}</a>", $str);
	$str = preg_replace("/\[url=(.[^\[]*)\]/i", "<a href=\"\${1}\" target=\"new\">", $str);


	// Mail UBB
	$str = preg_replace("/(\[email\])(.*?)(\[\/email\])/i", "<img align=\"absmiddle\" \"src=image/email1.gif\"><a href=\"mailto:\${2}\">\${2}</a>", $str);
	$str = preg_replace("/\[email=(.[^\[]*)\]/i", "<img align=\"absmiddle\" src=\"image/email1.gif\"><a href=\"mailto:\${1}\" target=\"new\">", $str);


	// QQ NumberUBB
	$str = preg_replace("/\[qq=([0-9]*)\]([0-9]*)\[\/qq\]/i", "<a target=\"new\" href=\"tencent://message/?uin=\${2}&Site=www.52515.net&Menu=yes\"><img border=\"0\" src=\"http://wpa.qq.com/pa?p=1:\${2}:\${1}\" alt=\"点击这里给我发消息\"></a>", $str);


	// Color UBB
	$str = preg_replace("/\[color=(.[^\[]*)\]/i", "<font color=\"\${1}\">", $str);


	// Font family UBB
	$str = preg_replace("/\[font=(.[^\[]*)\]/i", "<font face=\"\${1}\">", $str);


	// Font size UBB
	$str = preg_replace("/\[size=([0-9]*)\]/i", "<font size=\"\${1}\">", $str);

	// Font Align UBB
	$str = preg_replace("/\[align=(center|left|right)\]/i", "<div align=\"\${1}\">", $str);


	// Table UBB
	$str = preg_replace("/\[table=(.[^\[]*)\]/i", "<table width=\"\${1}\" border=\"1\" style=\"border-collapse:collapse\">", $str);


	// Table UBB2
	$str = preg_replace("/\[td=([0-9]*),([0-9]*),([0-9]*)\]/i", "<td colspan=\"\${1}\" rowspan=\"\${2}\" width=\"\${3}\">", $str);


	// Em
	$str = preg_replace("/\[i\]((.|\n)*?)\[\/i\]/i", "<i>\${1}</i>", $str);

	/* new paragraph */
	$str = preg_replace("/\[p=(\d{1,2}), (\d{1,2}), (left|center|right)\]/i", "<p style=\"line-height: $1px; text-indent: $2em; text-align: $3;\">",$str);
	$str = str_replace('[/p]', '</p>', $str); 


	// FLASH UBB
	$str = preg_replace("/(\[flash\])(http:\/\/.[^\[]*(.swf))(\[\/flash\])/i", "<a href=\"\${2}\" target=\"new\"><img src=\"image/swf.gif\" border=\"0\" alt=\"点击开新窗口欣赏该flash动画!\" height=\"16\" width=\"16\">[全屏欣赏]</a><br><center><object codebase=\"http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=4,0,2,0\" classid=\"clsid:d27cdb6e-ae6d-11cf-96b8-444553540000\" width=\"300\" height=\"200\"><param name=\"movie\" value=\"\${2}\"><param name=\"quality\" value=\"high\"><embed src=\"\${2}\" quality=\"high\" pluginspage=\"http://www.macromedia.com/shockwave/download/index.cgi?p1_prod_version=shockwaveflash\" type=\"application/x-shockwave-flash\" width=\"300\" height=\"200\">\${2}</embed></object></center>", $str);


	$str = preg_replace("/(\[flash=*([0-9]*),*([0-9]*)\])(http:\/\/.[^\[]*(.swf))(\[\/flash\])/i", "<a href=\"\${4}\" target=\"new\"><img src=\"image/swf.gif\" border=\"0\" alt=\"点击开新窗口欣赏该flash动画!\" height=\"16\" width=\"16\">[全屏欣赏]</a><br><center><object codebase=\"http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=4,0,2,0\" classid=\"clsid:d27cdb6e-ae6d-11cf-96b8-444553540000\" width=\"\${2}\" height=\"\${3}\"><param name=\"movie\" value=\"\${4}\"><param name=quality value=high><embed src=\"\${4}\" quality=\"high\" pluginspage=\"http://www.macromedia.com/shockwave/download/index.cgi?p1_prod_version=shockwaveflash\" type=\"application/x-shockwave-flash\" width=\"\${2}\" height=\"\${3}\">\${4}</embed></object></center>", $str);
	   
	//MEDIA Player UBB
	$str = preg_replace("/\[wmv\](.[^\[]*)\[\/wmv]/i", "<object align=\"middle\" classid=\"clsid:22d6f312-b0f6-11d0-94ab-0080c74c7e95\" class=\"object\" id=\"mediaplayer\" width=\"300\" height=\"200\" ><param name=\"showstatusbar\" value=\"-1\"><param name=\"filename\" value=\"\${1}\"><embed type=\"application/x-oleobject\" codebase=\"http://activex.microsoft.com/activex/controls/mplayer/en/nsmp2inf.cab#version=5,1,52,701\" flename=\"mp\" src=\"\${1}\" width=\"300\" height=\"200\"></embed></object>", $str);
	$str = preg_replace("/\[wmv=*([0-9]*),*([0-9]*)\](.[^\[]*)\[\/wmv]/i", "<object align=\"middle\" classid=\"clsid:22d6f312-b0f6-11d0-94ab-0080c74c7e95\" class=\"object\" id=\"mediaplayer\" width=\"\${1}\" height=\"\${2}\" ><param name=\"showstatusbar\" value=\"-1\"><param name=\"filename\" value=\"\${3}\"><embed type=\"application/x-oleobject\" codebase=\"http://activex.microsoft.com/activex/controls/mplayer/en/nsmp2inf.cab#version=5,1,52,701\" flename=\"mp\" src=\"\${3}\" width=\"\${1}\" height=\"\${2}\"></embed></object>", $str);
	   
	//REALPLAY UBB
	$str = preg_replace("/\[rm\](.[^\[]*)\[\/rm]/i", "<object classid=\"clsid:cfcdaa03-8be4-11cf-b84b-0020afbbccfa\" class=\"object\" id=\"raocx\" width=\"300\" height=\"200\"><param name=\"src\" value=\"\${1}\"><param name=\"console\" value=\"clip1\"><param name=\"controls\" value=\"imagewindow\"><param name=\"autostart\" value=\"true\"></object><br><object classid=\"clsid:cfcdaa03-8be4-11cf-b84b-0020afbbccfa\" height=\"32\" id=\"video2\" width=\"300\"><param name=\"src\" value=\"\${1}\"><param name=\"autostart\" value=\"-1\"><param name=\"controls\" value=\"controlpanel\"><param name=\"console\" value=\"clip1\"></object>", $str);


	$str = preg_replace("/\[rm=*([0-9]*),*([0-9]*)\](.[^\[]*)\[\/rm]/i", "<object classid=\"clsid:cfcdaa03-8be4-11cf-b84b-0020afbbccfa\" class=\"object\" id=\"raocx\" width=\"\${1}\" height=\"\${2}\"><param name=\"src\" value=\"\${3}\"><param name=\"console\" value=\"clip1\"><param name=\"controls\" value=\"imagewindow\"><param name=\"autostart\" value=\"true\"></object><br><object classid=\"clsid:cfcdaa03-8be4-11cf-b84b-0020afbbccfa\" height=\"32\" id=\"video2\" width=\"\${1}\"><param name=\"src\" value=\"\${3}\"><param name=\"autostart\" value=\"-1\"><param name=\"controls\" value=\"controlpanel\"><param name=\"console\" value=\"clip1\"></object>", $str);

	//$str = str_replace("\r\n", "<BR/>", $str);


	$str = preg_replace("/\[rm=*([0-9]*),*([0-9]*)\](.[^\[]*)\[\/rm]/i", "<object classid=\"clsid:cfcdaa03-8be4-11cf-b84b-0020afbbccfa\" class=\"object\" id=\"raocx\" width=\"\${1}\" height=\"\${2}\"><param name=\"src\" value=\"\${3}\"><param name=\"console\" value=\"clip1\"><param name=\"controls\" value=\"imagewindow\"><param name=\"autostart\" value=\"true\"></object><br><object classid=\"clsid:cfcdaa03-8be4-11cf-b84b-0020afbbccfa\" height=\"32\" id=\"video2\" width=\"\${1}\"><param name=\"src\" value=\"\${3}\"><param name=\"autostart\" value=\"-1\"><param name=\"controls\" value=\"controlpanel\"><param name=\"console\" value=\"clip1\"></object>", $str);
	/*
	re.pattern="\[code\]((.|\n)*?)\[\/code\]"
	Set tempcodes=re.Execute($str)
	For i=0 To tempcodes.count-1
	   re.pattern="<BR/>"
	   tempcode=Replace(tempcodes(i),"<BR/>",vbcrlf)
	   $str=replace($str,tempcodes(i),tempcode)
	next
	*/
		$searcharray = array("[/url]","[/email]","[/color]", "[/size]", "[/font]", "[/align]", "[b]", "[/b]","[u]", "[/u]", "[list]", "[list=1]", "[list=a]","[list=A]", "[*]", "[/list]", "[indent]", "[/indent]","[ DISCUZ_CODE_0 ]","[quote]","[/quote]","[tr]","[td]","[/td]","[/tr]","[/table]");
	$replacearray= array("</a>","</a>","</font>", "</font>", "</font>", "</div>", "<b>", "</b>","<u>", "</u>", "<ul>", "<ol type=1>", "<ol type=a>","<ol type=A>", "<li>", "</ul></ol>", "<blockquote>", "</blockquote>","<div><textarea name=\"codes\" id=\"codes\" rows=\"12\" cols=\"65\">","</textarea><br/><input type=\"button\" value=\"运行代码\" onclick=\"RunCode()\"> <input type=\"button\" value=\"复制代码\" onclick=\"CopyCode()\"> <input type=\"button\" value=\"另存代码\" onclick=\"SaveCode()\"> <input type=\"button\" value=\"跳&nbsp;&nbsp;转\" onclick=\"Goto(prompt('请输入要跳转到第几行？','1'))\" accesskey=\"g\"> &nbsp;提示：您可以先修改部分代码再运行</div>","<div style=\"background:#E2F2FF;width:90%;height:auto;border:1px solid #3CAAEC;padding:5px;\">","</div>","<tr>","<td>","</td>","</tr>","</table>");
	for ($i=0; $i<count($searcharray); $i++){
	   $str = str_replace($searcharray[$i], $replacearray[$i], $str);
	}
	return $str;
}
?>