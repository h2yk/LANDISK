true// 共有フォルダー

function SHARE_selector_esc(val){
    return val.replace(/[ !"#$%&'()*+,.\/:;<=>?@\[\\\]^`{|}~]/g, '\\$&');
}

function SHARE_set_value(obj_id, prop, value){
	// obj_idのelementが不在の際でもプロパティアクセスでエラーとならないようにするため

	var obj = $("#" + SHARE_selector_esc(obj_id));
	obj.prop(prop, value);
}

function SHARE_get_value(obj_id, prop){
	// obj_idのelementが不在の際でもプロパティアクセスでエラーとならないようにするため
	var obj = $("#" + SHARE_selector_esc(obj_id));
	return obj.prop(prop);
}

function SHARE_changeCheck(){
	//Dropbox
	if(SHARE_get_value("SHARE_service[][7]", "checked")) {
		document.form.SHARE_cloud.disabled = false;
		//document.getElementsByName("SHARE_dbcheck")[0].disabled = false;
		document.form.SHARE_dbcode.disabled = false;
		document.getElementsByName("SHARE_fletshost")[0].disabled = false;
		document.getElementsByName("SHARE_fletshost")[1].disabled = false;
		document.form.SHARE_fletsid.readOnly = false; 
		document.form.SHARE_fletspass.readOnly = false;
 		document.form.SHARE_fletshost.disabled = false;
		document.form.SHARE_rl3syncfname.readOnly = false;
		document.form.SHARE_rl3syncpincode.readOnly = false;
		document.form.SHARE_rl3syncusername.readOnly = false;
		document.form.SHARE_rl3syncpassword.readOnly = false;
	}else{
		document.form.SHARE_cloud.disabled = true;
		//document.getElementsByName("SHARE_dbcheck")[0].disabled = true;
		document.form.SHARE_dbcode.disabled = true;
		document.getElementsByName("SHARE_fletshost")[0].disabled = true;
		document.getElementsByName("SHARE_fletshost")[1].disabled = true;
		document.form.SHARE_fletsid.readOnly = true;
		document.form.SHARE_fletspass.readOnly = true;
		document.form.SHARE_rl3syncfname.readOnly = true;
		document.form.SHARE_rl3syncpincode.readOnly = true;
		document.form.SHARE_rl3syncusername.readOnly = true;
		document.form.SHARE_rl3syncpassword.readOnly = true;
	}
	SHARE_detail_cloud_setting();
}

function SHARE_detail_cloud_setting() {
	if(document.form.SHARE_cloud.selectedIndex == '0') {
		document.getElementById("SHARE_cloud_setting_1").style.display="";
		document.getElementById("SHARE_cloud_setting_2").style.display="none";
		document.getElementById("SHARE_cloud_setting_3").style.display="none";
	} else if (document.form.SHARE_cloud.selectedIndex == '1') {
		document.getElementById("SHARE_cloud_setting_1").style.display="none";
		document.getElementById("SHARE_cloud_setting_2").style.display="";
		document.getElementById("SHARE_cloud_setting_3").style.display="none";
	} else {
		document.getElementById("SHARE_cloud_setting_1").style.display="none";
		document.getElementById("SHARE_cloud_setting_2").style.display="none";
		document.getElementById("SHARE_cloud_setting_3").style.display="";
	}

	if(typeof isSolidModel !== "undefined"){
		document.getElementById("SHARE_cloud_setting_1").style.display="none";
		document.getElementById("SHARE_cloud_setting_2").style.display="none";
		document.getElementById("SHARE_cloud_setting_3").style.display="none";
	}
	
	SHARE_change_dropbox_revoke_display();
}

function SHARE_change_dropbox_revoke_display(){
	var access_token_flag = "";

	$.ajax({
		url : "./share/share/get_dropbox_access_token.php",
		type : "post",
		cache: false,
		timeout : 30000,
		data : ({
			StateFulID: document.getElementsByName("StateFulID")[0].value
		}),
		success: function(request){
			var parseAr = JSON.parse(request);
		
			access_token_flag = parseAr["accesstokenflag"];

			if(access_token_flag){
				if(SHARE_get_value("SHARE_service[][7]", "checked") && document.form.SHARE_cloud.selectedIndex == '0') {
					document.getElementById("SHARE_dropbox_revoke_flag").style.display="none";
					document.getElementsByName("SHARE_dbrevokeflag")[0].disabled = true;
				}else{
					document.getElementById("SHARE_dropbox_revoke_flag").style.display="";
					document.getElementsByName("SHARE_dbrevokeflag")[0].disabled = false;
				}
			}else{
				document.getElementById("SHARE_dropbox_revoke_flag").style.display="none";
				document.getElementsByName("SHARE_dbrevokeflag")[0].disabled = true;
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			communicationError("dropbox初期化処理", XMLHttpRequest, textStatus, errorThrown);
		}
	});
		
}

function SHARE_change_flets_link(){
	if(document.getElementById("SHARE_fletshost[east]").checked){
		document.getElementById("east_link").style.display="";
		document.getElementById("west_link").style.display="none";
	}else if(document.getElementById("SHARE_fletshost[west]").checked){
		document.getElementById("east_link").style.display="none";
		document.getElementById("west_link").style.display="";
	}
}

function SHARE_detail_setting(){
	if(document.form.SHARE_access_setting[0].checked) {
		document.getElementById("SHARE_detail_access_setting").style.display="";
	}else{
		document.getElementById("SHARE_detail_access_setting").style.display='none';
	}
}

// // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // //

function SHARE_readMoveForm(){
	var add_user_list = document.form.SHARE_add_user_list.value.split(',');
	var read_add_user_list = document.form.SHARE_read_add_user_list.value.split(',');

	for(var i=0; i < document.getElementsByName("SHARE_usrCategory[]").length; i++){
		if(document.getElementsByName("SHARE_usrCategory[]")[i].checked == true){
			add_user_list.push(document.getElementsByName("SHARE_usrCategory[]")[i].value);
			read_add_user_list.push(document.getElementsByName("SHARE_usrCategory[]")[i].value);
		}
	}
	document.form.SHARE_add_user_list.value = add_user_list;
	document.form.SHARE_read_add_user_list.value = read_add_user_list;

	SHARE_changeUserTag();
}

function SHARE_writeMoveForm(){
	var add_user_list = document.form.SHARE_add_user_list.value.split(',');
	var write_add_user_list = document.form.SHARE_write_add_user_list.value.split(',');

	for(var i=0; i < document.getElementsByName("SHARE_usrCategory[]").length; i++){
		if(document.getElementsByName("SHARE_usrCategory[]")[i].checked == true){
			add_user_list.push(document.getElementsByName("SHARE_usrCategory[]")[i].value);
			write_add_user_list.push(document.getElementsByName("SHARE_usrCategory[]")[i].value);
		}
	}
	document.form.SHARE_add_user_list.value = add_user_list;
	document.form.SHARE_write_add_user_list.value = write_add_user_list;

	SHARE_changeUserTag();
}

function SHARE_deleteMoveForm(type,user_id){
	if(type == "read"){
		var read_add_user_list = document.form.SHARE_read_add_user_list.value.split(',');
		for(var i=0; i < read_add_user_list.length; i++){
			if(read_add_user_list[i]){
				if(read_add_user_list[i] == user_id){
					read_add_user_list[i] = "";
				}
			}
		}
		document.form.SHARE_read_add_user_list.value = read_add_user_list;
	}else if(type == "write"){
		var write_add_user_list = document.form.SHARE_write_add_user_list.value.split(',');
		for(var i=0; i < write_add_user_list.length; i++){
			if(write_add_user_list[i]){
				if(write_add_user_list[i] == user_id){
					write_add_user_list[i] = "";
				}
			}
		}
		document.form.SHARE_write_add_user_list.value = write_add_user_list;
	}
	var add_user_list = document.form.SHARE_add_user_list.value.split(',');
	for(var i=0; i < add_user_list.length; i++){
		if(add_user_list[i]){
			if(add_user_list[i] == user_id){
				add_user_list[i] = "";
			}
		}
	}
	document.form.SHARE_add_user_list.value = add_user_list;

	SHARE_changeUserTag();
}

function SHARE_changeUserTag(){
	$.ajax({
		url : "./share/share/userList.php",
		type : "post",
		cache: false,
		timeout : 30000,
		data : ({
			remains_user_list: document.form.SHARE_remains_user_list.value,
			add_user_list: document.form.SHARE_add_user_list.value,
			read_add_user_list: document.form.SHARE_read_add_user_list.value,
			write_add_user_list: document.form.SHARE_write_add_user_list.value,
			StateFulID: document.getElementsByName("StateFulID")[0].value
		}),
		success: function(request){
			var parseAr = JSON.parse(request);

			if(parseAr["lock_timeout"] == ""){
				var permissionUserTagList = "";
				var permissionUserTagArray = parseAr["permission_user_tag"];
				for(i in permissionUserTagArray){
					if(permissionUserTagArray[i] != ""){
						permissionUserTagList = permissionUserTagList + "\n" + permissionUserTagArray[i];
					}
				}
				document.getElementById("SHARE_permission_user_area").innerHTML = permissionUserTagList;

				var notPermissionUserTagList = "";
				var notPermissionUserTagArray = parseAr["not_permission_user_tag"];
				for(i in notPermissionUserTagArray){
					if(notPermissionUserTagArray[i] != ""){
						notPermissionUserTagList = notPermissionUserTagList + "\n" + notPermissionUserTagArray[i];
					}
				}
				document.getElementById("SHARE_not_permission_user_area").innerHTML = notPermissionUserTagList;
			}else{
				document.location.href = parseAr["lock_timeout"];
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			communicationError("共有フォルダーユーザ取得", XMLHttpRequest, textStatus, errorThrown);
		}
	});
}

// // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // // //

function SHARE_list(){
	var dfd = $.Deferred();
	$.ajax({
		url : "./share/share/list.php",
		type : "post",
		cache: false,
		timeout : 30000,
		success: function(request){
			var parseAr = JSON.parse(request);

			if(parseAr["lock_timeout"] == ""){
				document.getElementById("SHARE_list").innerHTML = parseAr["disp_share_list"];
				dfd.resolve();
			}else{
				document.location.href = parseAr["lock_timeout"];
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			if (XMLHttpRequest.status === 0 && textStatus == "error") {
				// 接続エラー時、再処理を行う。
				$.ajax(this);
			} else {
				// それ以外は例外
				communicationError("共有フォルダー一覧取得", XMLHttpRequest, textStatus, errorThrown);
			}
		}
	});
	return dfd.promise();
}

function SHARE_onInitialization(share_id){
	$('#SHARE_myModal4').modal('hide');

	document.getElementById("SHARE_input_button_area").style.display="block";

	document.getElementById("SHARE_detail_access_setting").style.display='none';
	document.getElementById("SHARE_permission_user_area").innerHTML = "";
	document.getElementById("SHARE_not_permission_user_area").innerHTML = "";

	document.getElementById("SHARE_folder_fault_area").style.display="none";

	if(share_id){
		document.getElementById("SHARE_add").style.display="none";
		document.getElementById("SHARE_edit").style.display="block";
	}else{
		document.getElementById("SHARE_add").style.display="block";
		document.getElementById("SHARE_edit").style.display="none";
	}

	document.getElementById("SHARE_name").value = "";
	document.getElementById("SHARE_comment").value = "";
	document.getElementById("SHARE_read_only[1]").checked = false;
	SHARE_set_value("SHARE_service[][1]", "checked", false);
	SHARE_set_value("SHARE_service[][5]", "checked", false);
	SHARE_set_value("SHARE_service[][7]", "checked", false);
	SHARE_set_value("SHARE_service[][8]", "checked", false);
	document.getElementById("SHARE_cloud").value = "";
	//document.getElementById("SHARE_dbcheck[1]").checked = false;
	document.getElementById("SHARE_dbrevokeflag[1]").checked = false;
	document.getElementById("SHARE_dbcode").value = "";
	document.getElementById("SHARE_fid").value = "";
	document.getElementById("SHARE_fpass").value = "";
	document.getElementById("SHARE_fletshost[east]").cheked = true;
	document.getElementById("SHARE_rl3syncfname").value = "";
	document.getElementById("SHARE_rl3syncpincode").value = "";
	document.getElementById("SHARE_rl3syncusername").value = "";
	document.getElementById("SHARE_rl3syncpassword").value = "";
	document.getElementById("SHARE_trash[0]").checked = false;
	document.getElementById("SHARE_access_setting[all]").checked = true;

	document.form.SHARE_fletsid_hid.value = "";
	document.form.SHARE_fletspass_hid.value = "";
	document.form.SHARE_fletshost_hid.value = "";
	document.form.SHARE_cloud_sel_hid.value = "";
	document.form.SHARE_service_hid.value = "";
	document.form.SHARE_rl3syncother_hid.value = "";
	document.form.SHARE_rl3syncpincode_hid.value = "";
	document.form.SHARE_rl3syncuser_hid.value = "";
	document.form.SHARE_rl3syncpassword_hid.value = "";
	document.form.SHARE_remains_user_list.value = "";
	document.form.SHARE_add_user_list.value = "";
	document.form.SHARE_read_add_user_list.value = "";
	document.form.SHARE_write_add_user_list.value = "";

	$.ajax({
		url : "./share/share/initialization.php",
		type : "post",
		cache: false,
		timeout : 30000,
		data : ({
			share_id: share_id,
			StateFulID: document.getElementsByName("StateFulID")[0].value
		}),
		success: function(request){
			var parseAr = JSON.parse(request);

			if(parseAr["lock_timeout"] == ""){
				document.getElementById("SHARE_message").innerHTML = parseAr["message"];

				if(parseAr["folder_fault_area"] == "2"){
					document.getElementById("SHARE_folder_fault_area").style.display="block";
				}

				document.getElementById("SHARE_name").value = parseAr["name"];
				document.getElementById("SHARE_comment").value = parseAr["comment"];

				for(var i=0; i < document.getElementsByName("SHARE_service[]").length; i++){
					switch (Number(parseAr["service"][i])){
						case 1:
							SHARE_set_value("SHARE_service[][1]", "checked", true);
							break;
						case 5:
							SHARE_set_value("SHARE_service[][5]", "checked", true);
							break;
						case 7:
							SHARE_set_value("SHARE_service[][7]", "checked", true);
							break;
						case 8:
							SHARE_set_value("SHARE_service[][8]", "checked", true);
							break;
					}
				}

				document.getElementById("SHARE_cloud").value = parseAr["cloud"];
				//if(parseAr["dbcheck"]){
					//document.getElementById("SHARE_dbcheck[1]").checked = true;
				//}else{
					//document.getElementById("SHARE_dbcheck[1]").checked = false;
				//}
				if(parseAr["dbrevokeflag"]){
					document.getElementById("SHARE_dbrevokeflag[1]").checked = true;
				}else{
					document.getElementById("SHARE_dbrevokeflag[1]").checked = false;
				}
				document.getElementById("SHARE_dbcode").value = parseAr["dbcode"];
				document.getElementById("SHARE_fid").value = parseAr["fid"];
				document.getElementById("SHARE_fpass").value = parseAr["fpass"];
				var dbaccesstoken = parseAr["dbtokenflag"];
				if (dbaccesstoken){ //existToken
					if (parseAr["dbaccountname"]){ //getAccountName
						document.getElementById("SHARE_dbaccountname").innerHTML = parseAr["dbaccountname"];
						document.getElementById("SHARE_dropbox_edit").style.display="";
						document.getElementById("SHARE_dropbox_ng").style.display="none";
						document.getElementById("SHARE_dropbox_new").style.display="none";
					} else {
						document.getElementById("SHARE_dropbox_edit").style.display="none";
						document.getElementById("SHARE_dropbox_ng").style.display="";
						document.getElementById("SHARE_dropbox_new").style.display="none";
					}
				} else {
					document.getElementById("SHARE_dropbox_edit").style.display="none";
					document.getElementById("SHARE_dropbox_ng").style.display="none";
					document.getElementById("SHARE_dropbox_new").style.display="";
				}

				document.getElementById("SHARE_rl3syncfname").value = parseAr["rl3syncfname"];
				document.getElementById("SHARE_rl3syncpincode").value = parseAr["rl3syncpincode"];
				document.getElementById("SHARE_rl3syncusername").value = parseAr["rl3syncusername"];
				document.getElementById("SHARE_rl3syncpassword").value = parseAr["rl3syncpassword"];

				if(parseAr["fhost"] == "east"){
					document.getElementById("SHARE_fletshost[east]").checked = true;
					document.getElementById("east_link").style.display="";
					document.getElementById("west_link").style.display="none";
				}else if(parseAr["fhost"] == "west"){
					document.getElementById("SHARE_fletshost[west]").checked = true;
					document.getElementById("east_link").style.display="none";
					document.getElementById("west_link").style.display="";
				}

				switch (Number(parseAr["read_only"])){
				        case 0:
				                document.getElementById("SHARE_read_only[1]").checked = false;
				                break;
				        case 1:
				                document.getElementById("SHARE_read_only[1]").checked = true;
				                break;
				}
				switch (Number(parseAr["trash"])){
				        case 0:
				                document.getElementById("SHARE_trash[0]").checked = true;
				                break;
				        case 1:
				                document.getElementById("SHARE_trash[1]").checked = true;
				                break;
				}

				if(parseAr["access"] == "mix"){
					document.getElementById("SHARE_access_setting[mix]").checked = true;
				}else if(parseAr["access"] == "all"){
					document.getElementById("SHARE_access_setting[all]").checked = true;
				}

				SHARE_changeCheck(dbaccesstoken);
				SHARE_detail_setting();

				document.form.SHARE_fletsid_hid.value = parseAr["fletsid_hid"];
				document.form.SHARE_fletspass_hid.value = parseAr["fletspass_hid"];
				document.form.SHARE_fletshost_hid.value = parseAr["fletshost_hid"];
				document.form.SHARE_cloud_sel_hid.value = parseAr["cloud_sel_hid"];
				document.form.SHARE_service_hid.value = parseAr["service_hid"];

				document.form.SHARE_rl3syncother_hid.value = parseAr["rl3syncfname_hid"];
				document.form.SHARE_rl3syncpincode_hid.value = parseAr["rl3syncpincode_hid"];
				document.form.SHARE_rl3syncuser_hid.value = parseAr["rl3syncusername_hid"];
				document.form.SHARE_rl3syncpassword_hid.value = parseAr["rl3syncpassword_hid"];

				document.form.SHARE_remains_user_list.value = parseAr["remains_user_list"];
				document.form.SHARE_add_user_list.value = parseAr["add_user_list"];
				document.form.SHARE_read_add_user_list.value = parseAr["read_add_user_list"];
				document.form.SHARE_write_add_user_list.value = parseAr["write_add_user_list"];

				var permissionUserTagList = "";
				var permissionUserTagArray = parseAr["permission_user_tag"];
				for(i in permissionUserTagArray){
					if(permissionUserTagArray[i] != ""){
						permissionUserTagList = permissionUserTagList + "\n" + permissionUserTagArray[i];
					}
				}
				document.getElementById("SHARE_permission_user_area").innerHTML = permissionUserTagList;

				var notPermissionUserTagList = "";
				var notPermissionUserTagArray = parseAr["not_permission_user_tag"];
				for(i in notPermissionUserTagArray){
					if(notPermissionUserTagArray[i] != ""){
						notPermissionUserTagList = notPermissionUserTagList + "\n" + notPermissionUserTagArray[i];
					}
				}
				document.getElementById("SHARE_not_permission_user_area").innerHTML = notPermissionUserTagList;
			}else{
				document.location.href = parseAr["lock_timeout"];
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			communicationError("共有フォルダー初期化処理", XMLHttpRequest, textStatus, errorThrown);
		}
	});
}

function SHARE_onInputDone(){
	upDataStart();

	var serviceArray = [];
	var checkbox = document.getElementsByName("SHARE_service[]");
	for(var i=0; i < checkbox.length; ++i){
		if(checkbox[i].checked == true){
			serviceArray.push(checkbox[i].value);
		}
	}

	//var dbcheck = "";
	//if(document.getElementById("SHARE_dbcheck[1]").checked){
		//dbcheck = document.getElementById("SHARE_dbcheck[1]").value;
	//}

	var dbrevokeflag = "";
	if(document.getElementById("SHARE_dbrevokeflag[1]").checked){
		dbrevokeflag = document.getElementById("SHARE_dbrevokeflag[1]").value;
	}

	var flets_host = "";
	if(document.getElementById("SHARE_fletshost[east]").checked){
		flets_host = "east";
	}else if(document.getElementById("SHARE_fletshost[west]").checked){
		flets_host = "west";
	}

	var access_setting = "";
	if(document.getElementById("SHARE_access_setting[mix]").checked){
		access_setting = "mix";
	}else if(document.getElementById("SHARE_access_setting[all]").checked){
		access_setting = "all";
	}

	var read_only = "";
	if(document.getElementById("SHARE_read_only[1]").checked){
		read_only = "1";
	}else{
		read_only = "0";
	}

	var trash = "";
	if(document.getElementById("SHARE_trash[0]").checked){
		trash = "0";
	}else if(document.getElementById("SHARE_trash[1]").checked){
		trash = "1";
	}

	var status = "ng";
	$.ajax({
		url : "./share/share/input.php",
		async: false,
		type : "post",
		cache: false,
		timeout : 30000,
		data : ({name: document.getElementById("SHARE_name").value,
			comment: document.getElementById("SHARE_comment").value,
			read_only: read_only,
			service: serviceArray,
			cloud: document.getElementById("SHARE_cloud").value,
			//dbcheck: dbcheck,
			dbrevokeflag: dbrevokeflag,
			dbcode: document.getElementById("SHARE_dbcode").value,
			fid: document.getElementById("SHARE_fid").value,
			fpass: document.getElementById("SHARE_fpass").value,
			fhost: flets_host,
			rl3syncfname: document.getElementById("SHARE_rl3syncfname").value,
			rl3syncpincode: document.getElementById("SHARE_rl3syncpincode").value,
			rl3syncusername: document.getElementById("SHARE_rl3syncusername").value,
			rl3syncpassword: document.getElementById("SHARE_rl3syncpassword").value,
			trash: trash,
			access_setting: access_setting,
			remains_user_list: document.form.SHARE_remains_user_list.value,
			add_user_list: document.form.SHARE_add_user_list.value,
			read_add_user_list: document.form.SHARE_read_add_user_list.value,
			write_add_user_list: document.form.SHARE_write_add_user_list.value,
			StateFulID: document.getElementsByName("StateFulID")[0].value
			}),
		success: function(request){
			var parseAr = JSON.parse(request);

			if(parseAr["lock_timeout"] == ""){
				document.getElementById("SHARE_message").innerHTML = parseAr["message"];

				if(parseAr["folder_fault_area"] == "2"){
					document.getElementById("SHARE_folder_fault_area").style.display="block";
				}

				if(parseAr["message"] == ""){
					status = "ok";
				}else{
					status = "ng";

					document.getElementById("SHARE_input_button_area").style.display="block";
					upDataStop();
				}
			}else{
				document.location.href = parseAr["lock_timeout"];
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			communicationError("共有フォルダー登録処理", XMLHttpRequest, textStatus, errorThrown);
		}
	});

	if(status == "ok"){
		document.getElementById("SHARE_input_button_area").style.display="none";
		SHARE_onInputWait();
	}
}

function SHARE_onInputWait(){
	var proc_result = "wait";
	var disp_change = "";
	var error_flag = "";
	var proc_message = "";
	var Enable_Dropbox_Status = "";
	var Flets_Status = "";
	var Enable_Flets_Status = "";
	var Enable_RemoteLink_Status = "";
	$.ajax({
		url : "./share/share/wait.php",
		async: false,
		timeout : 30000,
		cache: false,
		data : ({
			StateFulID: document.getElementsByName("StateFulID")[0].value
		}),
		success: function(request){
			var parseAr = JSON.parse(request);

			proc_result = parseAr["proc_result"];
			disp_change = parseAr["disp_change"];

			if(disp_change == "1"){
				if(parseAr["proc_message"]){
					error_flag = 1;
					proc_message = parseAr["proc_message"] + "\n";
				}

				if(parseAr["Enable_Dropbox_Status"]){
					error_flag = 1;
					Enable_Dropbox_Status = parseAr["Enable_Dropbox_Status"];
				}

				if(parseAr["Flets_Status"]){
					error_flag = 1;
					Flets_Status = parseAr["Flets_Status"] + "\n";
				}

				if(parseAr["Enable_Flets_Status"]){
					error_flag = 1;
					Enable_Flets_Status = parseAr["Enable_Flets_Status"];
				}

				if(parseAr["RemoteLink_Status"]) {
					error_flag = 1;
					Enable_RemoteLink_Status = parseAr["RemoteLink_Status"];
				}

			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			communicationError("共有フォルダー登録中処理", XMLHttpRequest, textStatus, errorThrown);
		}
	});
	if(proc_result == "end"){
		$('#SHARE_myModal1').modal('hide');

		if(error_flag != ""){
			document.getElementById("SHARE_proc_message_area").style.display="none";
			document.getElementById("SHARE_Enable_Dropbox_Status_area").style.display="none";
			document.getElementById("SHARE_Flets_Status_area").style.display="none";
			document.getElementById("SHARE_Enable_Flets_Status_area").style.display="none";
			document.getElementById("SHARE_RempteCloud_Status_area").style.display="none";

			$('#SHARE_myModal5').modal('show');

			if(proc_message != ""){
				document.getElementById("SHARE_proc_message_area").style.display="block";
				document.getElementById("SHARE_proc_message_area").innerHTML = proc_message;
			}
			if(Enable_Dropbox_Status != ""){
				document.getElementById("SHARE_Enable_Dropbox_Status_area").style.display="block";
				document.getElementById("SHARE_Enable_Dropbox_Status_area").innerHTML = Enable_Dropbox_Status;
			}
			if(Flets_Status != ""){
				document.getElementById("SHARE_Flets_Status_area").style.display="block";
				document.getElementById("SHARE_Flets_Status_area").innerHTML = Flets_Status;
			}
			if(Enable_Flets_Status != ""){
				document.getElementById("SHARE_Enable_Flets_Status_area").style.display="block";
				document.getElementById("SHARE_Enable_Flets_Status_area").innerHTML = Enable_Flets_Status;
			}
			if(Enable_RemoteLink_Status != "") {
				document.getElementById("SHARE_RempteCloud_Status_area").style.display="block";
				document.getElementById("SHARE_RempteCloud_Status_area").innerHTML = Enable_RemoteLink_Status;
			}
		}

		$.when(
			SHARE_list(),
			MEDIA_list(),
			LOG_onChange(0)
		).done(function() {
			upDataStop();
		});
	}else{
		setTimeout( function() {SHARE_onInputWait();}, 1500 );
	}
}

function SHARE_onDetail(share_id){
	document.getElementById("disp_SHARE_cloud").style.display="none";
	document.getElementById("disp_SHARE_flets_id").style.display="none";
	document.getElementById("disp_SHARE_flets_password").style.display="none";
	document.getElementById("disp_SHARE_flets_host").style.display="none";
	document.getElementById("disp_SHARE_rl3syncfname").style.display="none";
	document.getElementById("disp_SHARE_rl3syncpincode").style.display="none";
	document.getElementById("disp_SHARE_rl3syncusername").style.display="none";
	document.getElementById("disp_SHARE_rl3syncpassword").style.display="none";
	document.getElementById("disp_SHARE_access_area").style.display="none";

	if(share_id){
		$.ajax({
			url : "./share/share/detail.php",
			type : "post",
			cache: false,
			timeout : 30000,
			data : ({
				share_id: share_id
			}),
			success: function(request){
				var parseAr = JSON.parse(request);

				if(parseAr["lock_timeout"] == ""){
					document.getElementById("SHARE_edit_link").innerHTML = parseAr["edit_link"];
					document.getElementById("SHARE_delete_link").innerHTML = parseAr["delete_link"];

					document.getElementById("disp_SHARE_share_name1").innerHTML = parseAr["disp_share_name"];
					document.getElementById("disp_SHARE_share_name2").innerHTML = parseAr["disp_share_name"];
					document.getElementById("disp_SHARE_share_comment").innerHTML = parseAr["disp_share_comment"];

					document.getElementById("disp_SHARE_service").innerHTML = "-";
					if(parseAr["disp_service"]){
						document.getElementById("disp_SHARE_service").innerHTML = parseAr["disp_service"];
						if(parseAr["value_cloud"]){
							document.getElementById("disp_SHARE_cloud").innerHTML = parseAr["disp_cloud"];
							document.getElementById("disp_SHARE_cloud").style.display="block";
							if(parseAr["value_cloud"] == 1){
								document.getElementById("disp_SHARE_flets_host").innerHTML = "ホスト:" + parseAr["disp_flets_host"];
								document.getElementById("disp_SHARE_flets_id").innerHTML = "ログインID:" + parseAr["disp_flets_id"];
								
								document.getElementById("disp_SHARE_flets_host").style.display="block";
								document.getElementById("disp_SHARE_flets_id").style.display="block";
								document.getElementById("disp_SHARE_flets_password").style.display="block";
							}else if(parseAr["value_cloud"] == 2){
								document.getElementById("disp_SHARE_rl3syncpincode").innerHTML = "接続機器のPINコード:" + parseAr["disp_rl3syncpincode"];
								document.getElementById("disp_SHARE_rl3syncusername").innerHTML = "ユーザー名:" + parseAr["disp_rl3syncusername"];
								document.getElementById("disp_SHARE_rl3syncfname").innerHTML = "共有フォルダー名:" + parseAr["disp_rl3syncfname"];

								document.getElementById("disp_SHARE_rl3syncfname").style.display="block";
								document.getElementById("disp_SHARE_rl3syncpincode").style.display="block";
								document.getElementById("disp_SHARE_rl3syncusername").style.display="block";
								document.getElementById("disp_SHARE_rl3syncpassword").style.display="block";
							}
						}
					}

					
					document.getElementById("disp_SHARE_read_only").innerHTML = parseAr["disp_read_only"];
					document.getElementById("disp_SHARE_trash").innerHTML = parseAr["disp_trash"];

					document.getElementById("disp_SHARE_access").innerHTML = parseAr["disp_access"];
					if(parseAr["value_access"] == "mix"){
						document.getElementById("disp_SHARE_read_user").innerHTML = parseAr["disp_read_user"];
						document.getElementById("disp_SHARE_write_user").innerHTML = parseAr["disp_write_user"];
						document.getElementById("disp_SHARE_access_area").style.display="block";
					}
				}else{
					document.location.href = parseAr["lock_timeout"];
				}
			},
			error: function(XMLHttpRequest, textStatus, errorThrown){
				communicationError("共有フォルダー情報取得", XMLHttpRequest, textStatus, errorThrown);
			}
		});
	}
}

function SHARE_onDeleteConfirm(share_id){
	$('#SHARE_myModal4').modal('hide');

	document.getElementById("SHARE_error_button_area").style.display="none";
	document.getElementById("SHARE_success_button_area").style.display="none";

	if(share_id){
		$.ajax({
			url : "./share/share/delete_confirm.php",
			type : "post",
			cache: false,
			timeout : 30000,
			data : ({
				share_id: share_id,
				StateFulID: document.getElementsByName("StateFulID")[0].value
			}),
			success: function(request){
				var parseAr = JSON.parse(request);

				if(parseAr["lock_timeout"] == ""){
					document.getElementById("SHARE_delete_button").innerHTML = parseAr["delete_button"];

					document.getElementById("SHARE_message3").innerHTML = parseAr["message3"];
					document.getElementById("SHARE_dlete_message_area").innerHTML = parseAr["delete_message"];

					if(parseAr["message3"] != ""){
						document.getElementById("SHARE_error_button_area").style.display="block";
						document.getElementById("SHARE_success_button_area").style.display="none";
					}
					else{
						document.getElementById("SHARE_error_button_area").style.display="none";
						document.getElementById("SHARE_success_button_area").style.display="block";
					}
				}else{
					document.location.href = parseAr["lock_timeout"];
				}
			},
			error: function(XMLHttpRequest, textStatus, errorThrown){
				communicationError("共有フォルダー削除確認", XMLHttpRequest, textStatus, errorThrown);
			}
		});
	}
}

function SHARE_onDelete(share_id){
	upDataStart();

	document.getElementById("SHARE_error_button_area").style.display="none";
	document.getElementById("SHARE_success_button_area").style.display="block";

	var status = "ng";
	$.ajax({
		url : "./share/share/delete.php",
		async: false,
		type : "post",
		cache: false,
		timeout : 30000,
		data : ({
			share_id: share_id,
			StateFulID: document.getElementsByName("StateFulID")[0].value
		}),
		success: function(request){
			var parseAr = JSON.parse(request);

			if(parseAr["lock_timeout"] == ""){
				document.getElementById("SHARE_message3").innerHTML = parseAr["message3"];

				if(parseAr["message3"] == ""){
					status = "ok";
				}else{
					document.getElementById("SHARE_error_button_area").style.display="block";
					document.getElementById("SHARE_success_button_area").style.display="none";

					upDataStop();
				}
			}else{
				document.location.href = parseAr["lock_timeout"];
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			communicationError("共有フォルダー削除処理", XMLHttpRequest, textStatus, errorThrown);
		}
	});

	if(status == "ok"){
		document.getElementById("SHARE_error_button_area").style.display="none";
		document.getElementById("SHARE_success_button_area").style.display="none";

		SHARE_onDeleteWait();
	}
}

function SHARE_onDeleteWait(){
	var proc_result = "wait";
	var error_flag = "";

	$.ajax({
		url : "./share/share/wait.php",
		async: false,
		cache: false,
		timeout : 30000,
		data : ({
			StateFulID: document.getElementsByName("StateFulID")[0].value
		}),
		success: function(request){
			var parseAr = JSON.parse(request);

			proc_result = parseAr["proc_result"];
			disp_change = parseAr["disp_change"];

			if(disp_change == "1"){
				if(parseAr["proc_message"]){
					error_flag = 1;
					proc_message = parseAr["proc_message"] + "\n";
				}
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			communicationError("共有フォルダー削除中処理", XMLHttpRequest, textStatus, errorThrown);
		}
	});
	if(proc_result == "end"){
		$('#SHARE_myModal2').modal('hide');

		if(error_flag != ""){
			document.getElementById("SHARE_proc_message_area").style.display="none";

			$('#SHARE_myModal5').modal('show');

			if(proc_message != ""){
				document.getElementById("SHARE_proc_message_area").style.display="block";
				document.getElementById("SHARE_proc_message_area").innerHTML = proc_message;
			}
		}
	
		$.when(	
			SHARE_list(),
			MEDIA_list(),
			LOG_onChange(0)
		).done(function() {
			upDataStop();
		});
	}else{
		setTimeout( function() {SHARE_onDeleteWait();}, 1500 );
	}
}
