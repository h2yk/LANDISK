// 詳細設定ボタン押下
function rl3_detail(){
	if(typeof isSolidModel !== "undefined"){ return; }

	if(document.getElementById('rl3_detail_setting').style.display!="block"){
		document.getElementById('rl3_detail_setting').style.display = "block";
	} else {
		document.getElementById('rl3_detail_setting').style.display = "none";
	}
}
function rl3_use(){
	if(typeof isSolidModel !== "undefined"){ return; }

	if(document.form.rl3_enabled[0].checked){
		document.getElementById('rl3_use_setting').style.display = "block";
	}else{
		document.getElementById('rl3_use_setting').style.display = "none";
	}
}
function rl3_externalAccess(){
	if(typeof isSolidModel !== "undefined"){ return; }

	if(document.form.rl3_externalAccess_enabled[0].checked){
		document.getElementById('rl3_externalAccess_setting').style.display = "block";
	}else{
		document.getElementById('rl3_externalAccess_setting').style.display = "none";
	}
}

// 初期値取得
function RL3_onInput(){
	if(typeof isSolidModel !== "undefined"){ return; }

	var dfd = $.Deferred();
	document.getElementById("RL3_display_initial_button").style.display = "none";
	document.getElementById("RL3_display_initial_message").style.display = "none";
	document.getElementById("RL3_display_usestart_detail").style.display = "none";
	document.getElementById("RL3_display_usestart_button").style.display = "none";
	$.ajax({
		url : "./service/remotelink3/remotelink3.php",
		type : "post",
		timeout : 30000,
		cache: false,
		success: function(request){
			var parseAr = JSON.parse(request);

			if(parseAr["lock_timeout"] == ""){
				if (parseAr["rl3_pincode_check"]!=1) {
					// 初期画面
					document.getElementById("RL3_display_initial_button").style.display = "block";
					document.getElementById("RL3_display_initial_message").style.display = "block";
					document.getElementById("RL3_display_usestart_detail").style.display = "none";
					document.getElementById("RL3_display_usestart_button").style.display = "none";
					document.getElementById("RL3_display_ioportal").style.display = "block";
					document.getElementById("RL3_display_ioregist").style.display = "block";
				} else {
					document.getElementById("RL3_display_initial_button").style.display = "none";
					document.getElementById("RL3_display_initial_message").style.display = "none";
					document.getElementById("RL3_display_usestart_detail").style.display = "block";
					document.getElementById("RL3_display_usestart_button").style.display = "block";
					document.getElementById("RL3_display_ioportal").style.display = "block";
					document.getElementById("RL3_display_ioregist").style.display = "block";

					// form初期値設定

					var radioList = document.getElementsByName("rl3_enabled");
					// RL3利用区分
					for(var i=0; i<radioList.length; i++){
						if (radioList[i].value == parseAr["rl3_enabled"]) {
							radioList[i].checked = true;
							break;
						}
					}
					// ポート番号
					document.getElementsByName("rl3_remoteAccess_port1")[0].value = parseAr["rl3_remoteAccess_port1"];
					document.getElementsByName("rl3_remoteAccess_port2")[0].value = parseAr["rl3_remoteAccess_port2"];

					radioList = document.getElementsByName("rl3_useupnp");
					// UPNP機能利用
					for(var i=0; i<radioList.length; i++){
						if (radioList[i].value == parseAr["rl3_useupnp"]) {
							radioList[i].checked = true;
							break;
						}
					}

					radioList = document.getElementsByName("rl3_externalAccess_enabled");
					// 外部ポート区分
					for(var i=0; i<radioList.length; i++){
						if (radioList[i].value == parseAr["rl3_externalAccess_enabled"]) {
							radioList[i].checked = true;
							break;
						}
					}
					// 外部ポート番号
					document.getElementsByName("rl3_externalAccess_port1")[0].value = parseAr["rl3_externalAccess_port1"];
					document.getElementsByName("rl3_externalAccess_port2")[0].value = parseAr["rl3_externalAccess_port2"];

					radioList = document.getElementsByName("rl3_use_remoteUi");
					// リモートUI利用
					for(var i=0; i<radioList.length; i++){
						if (radioList[i].value == parseAr["rl3_use_remoteUi"]) {
							radioList[i].checked = true;
							break;
						}
					}
					// PINコード変更
					document.getElementsByName("rl3_change_pincode")[0].checked = false;

					if (parseAr["rl3_use_tmp"]!=1) {
						document.getElementById("RL3_pincode_info").style.display = "none";
					} else {
						document.getElementById("RL3_pincode_info").style.display = "block";
					}
					document.getElementById("rl3_message_acccess").innerHTML = parseAr["rl3_message_acccess"];
					document.getElementById("rl3_pincode").innerHTML = parseAr["rl3_pincode"];

// 					if (parseAr["rl3_message_raps"]!=null) {
// 						document.getElementById("rl3_message_raps").innerHTML = parseAr["rl3_message_raps"];
// 					}

					rl3_use();
					rl3_externalAccess();
					document.getElementById('rl3_detail_setting').style.display = "none";
				}
				dfd.resolve();
			}else{
				document.location.href = parseAr["lock_timeout"];
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			if (XMLHttpRequest.status === 0 && textStatus == "error") {
				$.ajax(this);
			} else {
				// それ以外は例外
				communicationError("Remote Link 3初期値取得処理", XMLHttpRequest, textStatus, errorThrown);
			}
		}
	});
	return dfd.promise();
}

// 確認表示
function onRl3Confirm(){
	if(typeof isSolidModel !== "undefined"){ return; }

	document.getElementById("rl3_message_error").innerHTML = "";
	$.ajax({
		url : "./service/remotelink3/remotelink3_confirm.php",
		type : "post",
		cache: false,
		timeout : 30000,
		data : ({
			rl3_enabled: $('[name="rl3_enabled"]:checked').val()
			, rl3_remoteAccess_port1: $('[name="rl3_remoteAccess_port1"]').val()
			, rl3_remoteAccess_port2: $('[name="rl3_remoteAccess_port2"]').val()
			, rl3_useupnp: $('[name="rl3_useupnp"]:checked').val()
			, rl3_externalAccess_enabled: $('[name="rl3_externalAccess_enabled"]:checked').val()
			, rl3_externalAccess_port1: $('[name="rl3_externalAccess_port1"]').val()
			, rl3_externalAccess_port2: $('[name="rl3_externalAccess_port2"]').val()
			, rl3_use_remoteUi: $('[name="rl3_use_remoteUi"]:checked').val()
			, rl3_change_pincode: $('[name="rl3_change_pincode"]:checked').val()
			, rl3_qrcode_user: $('[name="rl3_qrcode_user"]').val()
			, StateFulID: document.getElementsByName("StateFulID")[0].value
		}),
		success: function(request){
			var parseAr = JSON.parse(request);

			if(parseAr["lock_timeout"] == ""){
				if (parseAr["message"] != "") {
					document.getElementById('remotelink3').focus();
					document.getElementById("rl3_message_error").innerHTML = parseAr["message"];
				} else {
					$('#myModal_remotelink3_confirm').modal('show');
					document.getElementById("rl3_enabled_name").innerHTML = parseAr["rl3_enabled_name"];
					document.getElementById("rl3_remoteAccess_port1_confirm").innerHTML = parseAr["rl3_remoteAccess_port1"];
					document.getElementById("rl3_remoteAccess_port2_confirm").innerHTML = parseAr["rl3_remoteAccess_port2"];
					document.getElementById("rl3_useupnp_name").innerHTML = parseAr["rl3_useupnp_name"];
					document.getElementById("rl3_externalAccess_enabled_name").innerHTML = parseAr["rl3_externalAccess_enabled_name"];
					document.getElementById("rl3_externalAccess_port1_confirm").innerHTML = parseAr["rl3_externalAccess_port1"];
					document.getElementById("rl3_externalAccess_port2_confirm").innerHTML = parseAr["rl3_externalAccess_port2"];
					document.getElementById("rl3_use_remoteUi_name").innerHTML = parseAr["rl3_use_remoteUi_name"];

					if (parseAr["rl3_change_pincode_name"]) {
						document.getElementById("rl3_change_pincode_name").innerHTML = parseAr["rl3_change_pincode_name"];
						document.getElementById("rl3_confirm_message_warning").style.display = "block";
					} else {
						document.getElementById("rl3_change_pincode_name").innerHTML = "-";
						document.getElementById("rl3_confirm_message_warning").style.display = "none";
					}

					document.getElementById("rl3_qrcode_user").innerHTML = parseAr["rl3_qrcode_user"];

					if(parseAr["rl3_externalAccess_enabled"]!="0"){
						document.getElementById('rl3_externalAccess_setting_confirm').style.display = "block";
					}else{
						document.getElementById('rl3_externalAccess_setting_confirm').style.display = "none";
					}

					if(parseAr["rl3_enabled"]!="0"){
						document.getElementById('rl3_use_setting_confirm').style.display = "block";
					}else{
						document.getElementById('rl3_use_setting_confirm').style.display = "none";
					}
				}
			}else{
				document.location.href = parseAr["lock_timeout"];
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			communicationError("Remote Link 3登録確認", XMLHttpRequest, textStatus, errorThrown);
		}
	});
}

// 設定処理
function onRl3Setting(path,val){
	if(typeof isSolidModel !== "undefined"){ return; }

	document.getElementById("rl3_setting_button_area").style.display = "none";
	upDataStart();

	$.ajax({
		url : "./service/remotelink3/remotelink3_setting.php",
		type : "post",
		cache: false,
		timeout : 540000,
		data : ({
			val: val
			, StateFulID: document.getElementsByName("StateFulID")[0].value
		}),
		success: function(request){
			var parseAr = JSON.parse(request);

			if(parseAr["lock_timeout"] == ""){
				if ( parseAr["message"] != "") {
					document.getElementById("rl3_message_error").innerHTML = parseAr["message"];
				} else  {
					location.href='./main.php';
				}
				$('#myModal_remotelink3_confirm').modal('hide');
				upDataStop();
			}else{
				document.location.href = parseAr["lock_timeout"];
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			communicationError("Remote Link 3登録処理", XMLHttpRequest, textStatus, errorThrown);
		}
	});
}

