jQuery(function($){
	$('#ck_path').click(function() {
		var filepath = $('#filepath').val();
		$('#filepath').attr('class', 'loading');
		$.ajax({
			url: '/discuztoxe/classes/ajax.php',
			data: {filename: filepath},
			type: 'post',
			success: function(output) {
				if(output == "success"){
					$('#path_suc').show();
					$('#path_fail').hide();
				}
				if(output == "failed"){
					$('#path_fail').show();
					$('#path_suc').hide();
				}
				$('#filepath').attr('class', 'input_txt');
            }

		});
	});

	// select language comboBox
	var combo = $('div.lang_select');
	var list_li = combo.find('ul.lang_list>li');
	list_li.mouseover(function(){$(this).css('background-color','#dedede')});
	list_li.mouseout(function(){$(this).css('background-color','transparent')});
	$('.lang_select .selected').click(listToggle);
	function listToggle()
	{
		var sel = $(this).parent().find($("ul"));
		
		sel.slideToggle();
		$(this).toggleClass('on');
	}

});

function doCopyToClipboard(value) {
	if(window.event) { //IE
		window.event.returnValue = true;
		window.setTimeout(function() { copyToClipboard(value); },25);
	}
	else if(window.netscape){ //Fire Fox  
		try {
			netscape.security.PrivilegeManager.enablePrivilege("UniversalXPConnect");
		} catch (e) {
			alert("被浏览器拒绝！\n请在浏览器地址栏输入'about:config'并回车\n然后将'signed.applets.codebase_principal_support'设置为'true'");
		}
		var clip = Components.classes['@mozilla.org/widget/clipboard;1'].createInstance(Components.interfaces.nsIClipboard);
		if (!clip) return;
		var trans = Components.classes['@mozilla.org/widget/transferable;1'].createInstance(Components.interfaces.nsITransferable);
		if (!trans) return;
		trans.addDataFlavor('text/unicode');
		var str = new Object();
		var len = new Object();
		var str = Components.classes["@mozilla.org/supports-string;1"].createInstance(Components.interfaces.nsISupportsString);
		var copytext = value;
		str.data = copytext;
		trans.setTransferData("text/unicode",str,copytext.length*2);
		var clipid = Components.interfaces.nsIClipboard;
		if (!clip) return false;
		clip.setData(trans,null,clipid.kGlobalClipboard);
		alert("URL复制成功. 按Ctrl+v 进行粘贴")
	}
}

function copyToClipboard(value) {
	if(window.clipboardData) {
		var result = window.clipboardData.setData('Text', value);
		alert("URL复制成功. 按Ctrl+v 进行粘贴");
	}
}

