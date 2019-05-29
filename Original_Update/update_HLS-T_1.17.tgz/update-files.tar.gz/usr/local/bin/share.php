#!/usr/bin/php
<?
$filePath = get_included_files();
chdir(preg_replace('/(.+)[\\\|\/].*$/i',"\\1",$filePath[0]));

define("C4_SESSION_DEFAULT_ENABLED",false);
define("C4_CLOUD_SERVICE_NUM",7);

include_once('/usr/local/c4/c4c.class.php');
include_once(LD_CLASS_PATH.'/system/detail/share/share/share.class.php');
include_once(LD_CLASS_PATH.'/dropbox.class.php');
include_once(LD_CLASS_PATH.'/azukeru.class.php');
include_once(LD_CLASS_PATH.'/remotelinksync.class.php');

$_COOKIE[C4_SESSION_ID_NAME] = $argv[1];

function debug_output($str) {
	if ( ! file_exists("/etc/beta") ) {
		return;
	}
	$esc_str = escapeshellarg($str);
	exec("echo $esc_str > /dev/console");
}

class action extends share_class{
	# ============================================================================== #
	# コンストラクタ定義
	# ============================================================================== #

	function action($stateFulID){
		$this->setValue("ステートフルID",$stateFulID);
		C4EAction::C4EAction();
	}

	# ============================================================================== #
	# イベント定義
	# ============================================================================== #

	function onLoad(){
		debug_output("onLoad");
		$this->startSession();
		$this->startStateFul($this->getValue("ステートフルID"));

		$sessionId = $this->getSession()->getSessionId();

		$this->endSession();
		$lock = $this->writeMount(false);

		// ロックファイルのリフレッシュ起動
		exec(LD_PHP_COMMAND." ".LD_BIN_PATH."/refresh_proc_lock.php ".$sessionId." ".$this->stateFulID." >& /dev/null &");

		if($this->getValue("動作モード") == "add"){
			$this->addShare();
		}
		else if($this->getValue("動作モード") == "edit"){
			$this->updateShare();
		}
		else if($this->getValue("動作モード") == "delete"){
			$this->deleteShare();
		}

		$this->readMount($lock);
		$this->standardOutFlag = true;

		$this->startSession();
		$this->startStateFul($this->getValue("ステートフルID"));

		$this->setValue("proc_message",			$this->getValue("proc_error"));
		$this->setValue("Enable_Dropbox_Status",	$this->getValue("enable_dropbox_status"));
		$this->setValue("Flets_Status",			$this->getValue("flets_status"));
		$this->setValue("Enable_Flets_Status",	$this->getValue("enable_flets_status"));
		$this->setValue("RemoteCloudSelect", 	$this->getValue("remote_cloud_select"));
		$this->setValue("RemoteCloudStatus", 	$this->getValue("remote_cloud_status"));

		$this->setValue("proc_result",0);

		$this->endSession();
	}

	# 新規追加
	function addShare(){
		debug_output("addShare");
		$share_name = $this->getValue("共有フォルダ名");
		$this->loadConfig("share");
		$this->loadConfig("remotelink3sync");
		$this->loadConfig("azukeru");

		$id = md5(uniqid(rand(), true));
		$this->value["共有フォルダ情報"][$id]["name"] = $this->getValue("共有フォルダ名");
		$this->value["共有フォルダ情報"][$id]["comment"] = $this->getValue("共有フォルダコメント");
		$this->value["共有フォルダ情報"][$id]["read_only"] = $this->getValue("読み取り専用区分");
		$this->value["共有フォルダ情報"][$id]["service"] = $this->getValue("サービス");
		$this->value["共有フォルダ情報"][$id]["cloudselect"] = $this->getValue("クラウドストレージ選択");
		$this->value["共有フォルダ情報"][$id]["trash"] = $this->getValue("ごみ箱機能");
		$this->value["共有フォルダ情報"][$id]["detail_access_setting"] = $this->getValue("詳細アクセス権設定");
		$this->value["共有フォルダ情報"][$id]["read_user"] = $this->getValue("読み取りユーザ名リスト");
		$this->value["共有フォルダ情報"][$id]["write_user"] = $this->getValue("読み書きユーザ名リスト");
		$this->value["共有フォルダ情報"][$id]["valid_user"] = $this->getValue("追加名称リスト");
		$this->value["共有フォルダ情報"][$id]["user_list"] = preg_replace("/^@,/",",",$this->getValue("追加ユーザー欄"));
		$this->value["フレッツ共有"][$id]["fletsid"] = $this->getValue("フレッツID");
		$this->value["フレッツ共有"][$id]["fletspassword"] = $this->getValue("フレッツパスワード");
		$this->value["フレッツ共有"][$id]["fletshost"] = $this->getValue("フレッツホスト");
		$this->value["RemoteLink3Sync共有"][$id]["同期元共有名"] = $this->getValue("共有フォルダ名");
		$this->value["RemoteLink3Sync共有"][$id]["同期先共有名"] = $this->getValue("同期先共有名");
		$this->value["RemoteLink3Sync共有"][$id]["PINコード"] = $this->getValue("PINコード");
		$this->value["RemoteLink3Sync共有"][$id]["RL3ユーザ名"] = $this->getValue("RL3ユーザ名");
		$this->value["RemoteLink3Sync共有"][$id]["RL3パスワード"] = $this->getValue("RL3パスワード");
		#$dropbox_delete_access_token = $this->getValue("Dropboxアクセストークン削除");
		$dropbox_code = $this->getValue("Dropboxコード");
		debug_output("dropbox_code=" . $dropbox_code);

		$this->loadConfig("user");

		$share_dir = LD_SHARE_ROOT_DIR."/".$this->getValue("共有フォルダ名");
		if(!file_exists($share_dir)){
			# ゲスト共有（アクセス権の選択が「全てのユーザを許可」）
			$chmod = "777";
			$chown = LD_GUEST_USER;
			$chgrp = LD_GUEST_GROUP;

			# 共有フォルダ作成
			$mkdir_result = -1;
			exec("mkdir $share_dir",$mkdir_output,$mkdir_result);
			if(file_exists($share_dir) && is_dir($share_dir)){
				$mkdir_result = 0;
			}
			if($mkdir_result != 0) {
				$this->setValue("proc_error",1);
				$this->logging("share:add_error",$share_name);
				return false;
			}

			# パーミッション変更
			exec("chmod $chmod $share_dir",$chmod_output,$chmod_result);
			# オーナー変更
			exec("chown $chown:$chgrp $share_dir",$chown_output,$chown_result);

			if($chmod_result != 0 || $chown_result != 0){
				$this->setValue("proc_error",1);
				$this->logging("share:add_error",$share_name);
				return false;
			}

			# 中間設定登録
			$this->saveConfig("share");

			if(is_array($this->value["共有フォルダ情報"][$id]["service"])){
				$service = array_flip($this->value["共有フォルダ情報"][$id]["service"]);
			}

			# クラウドストレージ共有
			$this->restartConfig("nasdsync_stop");
			$this->waitCloudService($share_name);

			$this->setValue("enable_dropbox_status", "");
			$this->setValue("flets_status", "");
			$this->setValue("enable_flets_status", "");
			$this->setValue("remote_cloud_select", "");
			$this->setValue("remote_cloud_status", "");
			if(isset($service[7])){

				if($this->getValue("クラウドストレージ選択") == 0){
					if (!$this->enableDropboxService($share_name, $dropbox_code)) {
						debug_output("!enableDropboxService");
						$this->setValue("enable_dropbox_status", "1");
						#仮登録・情報誤りの場合、中間情報の再書き込みを行う
						$new_service = $this->value["共有フォルダ情報"][$id]["service"];
						#クラウドストレージの値は0にする
						$this->settingCloudConfig($id,$new_service,0);
					}
				}else if($this->getValue("クラウドストレージ選択") == 1){
					if(!$this->enableAzukeruService($share_name)){
						#仮登録・情報誤りの場合、中間情報の再書き込みを行う
						$new_service = $this->value["共有フォルダ情報"][$id]["service"];
						#クラウドストレージの値は0にする
						$this->settingCloudConfig($id,$new_service,0);
					}
				}else if($this->getValue("クラウドストレージ選択") == 2){
					$this->setValue("remote_cloud_select", "1");
 					# クラウドシンク設定登録
 					if(!$this->enableRemoteLinkService($share_name,$this->getValue("同期先共有名"),$this->getValue("PINコード"),$this->getValue("RL3ユーザ名"),$this->getValue("RL3パスワード"))) {
 						#仮登録・情報誤りの場合、中間情報の再書き込みを行う
 						$new_service = $this->value["共有フォルダ情報"][$id]["service"];
 						#クラウドストレージの値は0にする
 						$this->settingCloudConfig($id,$new_service,0);
 					}
				}
			}

			# コンフィグファイル生成
			$this->loadConfig("network");
			$this->loadConfig("share");
			$this->loadConfig("user");
			$this->loadConfig("microsoft");
			$this->loadConfig("others");

			# samba.conf設定
			$this->makeConfig("/etc/samba/smb_conf");

			# twonkyServer設定/再起動
			$this->updateMediaSetting();

			# 各機能再起動
			$this->restartConfig("nasdsync_start");# Dropbox関連

			# samba再起動
			$this->restartConfig("samba");

			$this->setValue("proc_error",0);
			$this->logging("share:add",$share_name);
		}
	}

	# 編集
	function updateShare(){
		debug_output("updateShare");
		$this->loadConfig("share");
		$this->loadConfig("remotelink3sync");
		$this->loadConfig("azukeru");

		if(isset($this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")])){
			$old_share_name = $this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["name"];
			$old_share_dir = LD_SHARE_ROOT_DIR."/".$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["name"];
			$old_dlna_dir = LD_DLNA_ROOT_DIR."/".$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["name"];
			$old_itunes_dir = LD_ITUNES_ROOT_DIR."/".$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["name"];

			$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")] = array();
			$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["name"] = $this->getValue("共有フォルダ名");
			$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["comment"] = $this->getValue("共有フォルダコメント");
			$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["read_only"] = $this->getValue("読み取り専用区分");
			$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["service"] = $this->getValue("サービス");
			$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["cloudselect"] = $this->getValue("クラウドストレージ選択");
			$this->value["フレッツ共有"][$this->getValue("共有フォルダID")]["fletsid"] = $this->getValue("フレッツID");
			$this->value["フレッツ共有"][$this->getValue("共有フォルダID")]["fletspassword"] = $this->getValue("フレッツパスワード");
			$this->value["フレッツ共有"][$this->getValue("共有フォルダID")]["fletshost"] = $this->getValue("フレッツホスト");
			$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["trash"] = $this->getValue("ごみ箱機能");
			$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["detail_access_setting"] = $this->getValue("詳細アクセス権設定");

			$this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID")]["同期元共有名"] = $this->getValue("共有フォルダ名");
			$this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID")]["同期先共有名"] = $this->getValue("同期先共有名");
			$this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID")]["PINコード"] = $this->getValue("PINコード");
			$this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID")]["RL3ユーザ名"] = $this->getValue("RL3ユーザ名");
			$this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID")]["RL3パスワード"] = $this->getValue("RL3パスワード");

			$dropbox_delete_access_token = $this->getValue("Dropboxアクセストークン削除");
			$dropbox_code = $this->getValue("Dropboxコード");

			if($this->getValue("詳細アクセス権設定") == "mix"){
				$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["read_user"] = $this->getValue("読み取りユーザ名リスト");
				$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["write_user"] = $this->getValue("読み書きユーザ名リスト");
				$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["valid_user"] = $this->getValue("追加名称リスト");
				$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["user_list"] = $this->getValue("追加ユーザー欄");
			}else{
				$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["read_user"] = null;
				$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["write_user"] = null;
				$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["valid_user"] = null;
				$this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["user_list"] = null;
			}

			$share_name = $this->getValue("共有フォルダ名");
			$share_dir = LD_SHARE_ROOT_DIR."/".$this->getValue("共有フォルダ名");

			# 共有フォルダをリネーム
			$mv_result = -1;
			if($old_share_dir != $share_dir){
				exec("mv $old_share_dir $share_dir",$mv_output,$mv_result);
			} else {
				$mv_result = 0;
			}

			if($mv_result != 0) {
				$this->setValue("proc_error",1);
				$this->logging("share:edit_error",$share_name);
				return false;
			}

			# ゲスト共有（アクセス権の選択が「全てのユーザを許可」）
			$chmod = "777";
			$chown = LD_GUEST_USER;
			$chgrp = LD_GUEST_GROUP;

			$this->saveConfig("share");

			if(is_array($this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["service"])){
				$service = array_flip($this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["service"]);
				$new_service = $this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["service"];
				$serviceId = $this->getValue("共有フォルダID");
			}

			# コンフィグファイル生成
			$this->loadConfig("network");
			$this->loadConfig("share");
			$this->loadConfig("user");
			$this->loadConfig("microsoft");
			$this->loadConfig("others");

			# samba.conf設定
			$this->makeConfig("/etc/samba/smb_conf");

			$this->restartConfig("share_stop");
			$this->waitCloudService($old_share_name);

			$this->setValue("enable_dropbox_status", "");
			$this->setValue("flets_status", "");
			$this->setValue("enable_flets_status", "");
			$this->setValue("remote_cloud_select", "");
			$this->setValue("remote_cloud_status", "");
			if(isset($service[7])){
				if($this->getValue("クラウドストレージ選択") == 0){
					$this->disableAzukeruService($old_share_name);
 					$this->disableRetemoLinkService($old_share_name);

					if (!$this->enableDropboxService($share_name, $dropbox_code, $old_share_name)) {
						debug_output("!enableDropboxService");
						$this->setValue("enable_dropbox_status", "1");
						#仮登録・情報誤りの場合、中間情報の再書き込みを行う
						$this->settingCloudConfig($serviceId,$new_service,$this->getValue("クラウドストレージ選択ID_hid"),$this->getValue("サービス_hid"));
					}
				}else if($this->getValue("クラウドストレージ選択") == 1){
					$this->disableDropboxService($share_name, $dropbox_delete_access_token, $old_share_name);
 					$this->disableRetemoLinkService($old_share_name);

					if(!$this->enableAzukeruService($share_name,$old_share_name)){
						#仮登録・情報誤りの場合、中間情報の再書き込みを行う
						$this->settingCloudConfig($serviceId,$new_service,$this->getValue("クラウドストレージ選択ID_hid"),$this->getValue("サービス_hid"));
					}
				}else if($this->getValue("クラウドストレージ選択") == 2){
					$this->disableDropboxService($share_name, $dropbox_delete_access_token, $old_share_name);
					$this->disableAzukeruService($old_share_name);

 					# sync設定登録
					$this->setValue("remote_cloud_select", "1");
 					$this->disableRetemoLinkService($old_share_name);
 					if(!$this->enableRemoteLinkService($share_name,$this->getValue("同期先共有名"),$this->getValue("PINコード"),$this->getValue("RL3ユーザ名"),$this->getValue("RL3パスワード"))) {
 						#情報誤りの場合、中間情報の再書き込みを行う
						$this->settingCloudConfig($serviceId,$new_service,$this->getValue("クラウドストレージ選択ID_hid"),$this->getValue("サービス_hid"));
 					}
				}
			}else{
				# ストレージ情報が存在する場合クリアを行う
				$this->disableDropboxService($share_name, $dropbox_delete_access_token, $old_share_name);
				$this->disableAzukeruService($old_share_name);
 				$this->disableRetemoLinkService($old_share_name);
			}

			# twonkyServer設定/再起動
			$this->updateMediaSetting();

			$this->restartConfig("share_start");
			reset($this->value["共有フォルダ情報"]);

			# samba再起動
			$this->restartConfig("samba");

			$this->setValue("proc_error",0);
			$this->logging("share:edit",$share_name);
		}
	}

	# 削除
	function deleteShare(){
		debug_output("deleteShare");
		$this->loadConfig("share");
		$this->loadConfig("remotelink3sync");
		$this->loadConfig("azukeru");

		if(isset($this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")])){
			$share_name = $this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]["name"];
			$share_dir = LD_SHARE_ROOT_DIR."/".$share_name;

			unset($this->value["共有フォルダ情報"][$this->getValue("共有フォルダID")]);
			unset($this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID")]);
			unset($this->value["フレッツ共有"][$this->getValue("共有フォルダID")]);

			# 共有フォルダを削除
			$rm_result = -1;
			if(file_exists($share_dir) && is_dir($share_dir)){
				exec("rm -rf $share_dir",$rm_output,$rm_result);
			}

			if($rm_result != 0){
				$this->setValue("proc_error",1);
				$this->logging("share:delete_error",$share_name);
				return false;
			}

			# 中間設定登録
			$this->saveConfig("share");
			$this->saveConfig("remotelink3sync");
			$this->saveConfig("azukeru");

			# コンフィグファイル生成
			$this->loadConfig("network");
			$this->loadConfig("share");
			$this->loadConfig("user");
			$this->loadConfig("microsoft");
			$this->loadConfig("others");

			# samba.conf設定
			$this->makeConfig("/etc/samba/smb_conf");

			$this->restartConfig("nasdsync_stop");# Dropbox関連
			$this->waitCloudService($share_name);

			$dropbox_delete_access_token = 1;
			$this->disableDropboxService($share_name, $dropbox_delete_access_token);
			$this->disableAzukeruService($share_name);
 			$this->disableRetemoLinkService($share_name);

			# twonkyServer設定/再起動
			$this->updateMediaSetting();

			# 各機能再起動
			$this->restartConfig("nasdsync_start");# Dropbox関連

			# samba再起動
			$this->restartConfig("samba");

			$this->setValue("proc_error",0);
			$this->logging("share:delete",$share_name);
		}
	}

	# ============================================================================== #
	# 内部処理定義
	# ============================================================================== #

	function enableDropboxService($share_name, $code, $old_share_name = ""){
		debug_output("enableDropboxService(share_name=" .$share_name . ", code=" . $code . ", old_share_name=" . $old_share_name . ")");
		$dropbox = new dropbox_class($this);

		if ($old_share_name && ($old_share_name !== $share_name)) {
			if ($dropbox->existAccessToken($old_share_name)) {
				if (!$dropbox->changeDropbox($old_share_name, $share_name)) {
					debug_output("!changeDropbox");
					return false;
				}
			}
		}
		if(!$dropbox->enableDropbox($share_name, $code)) {
			debug_output("!enableDropbox");
			return false;
		}
		return true;
	}

	function enableAzukeruService($share_name,$old=""){
		$azukeru = new azukeru_class($this);

		if($old && $azukeru->findAzukeruShare($old) == 0) {
			#フォルダ名が変わっている場合設定ファイルの変更を行う
			$azukeru->changeAzukeru($old,$share_name);
		}
		$flets_id = $this->getValue("フレッツID");
		$flets_pass =$this->getValue("フレッツパスワード");
		$flets_host =$this->getValue("フレッツホスト");

		$this->setValue("flets_status", $azukeru->testEnableAzukeru($flets_id,$flets_pass,$flets_host));

		if($this->getValue("flets_status") == 0) {
			$azukeru_sta = $azukeru->enableAzukeru($share_name,$flets_id,$flets_pass,$flets_host);
			if($azukeru_sta == 0) {
				#中間設定ファイル書き込み
				$this->saveConfig("azukeru");
				return true;
			} else {
				$this->setValue("enable_flets_status", "1");
			}
		}
		return false;
	}

	function enableRemoteLinkService($share_name,$otherDisk,$pincode,$user,$pass){
		$rl3sync = new remotelinksync_class($this);
		$this->setValue("remote_cloud_status", $rl3sync->testRemoteLinkSync($otherDisk, $pincode, $user, $pass));
		if($this->getValue("remote_cloud_status") == "OK") {
			if($rl3sync->enableRemoteLinkSync($share_name,$otherDisk,$pincode,$user,$pass) == 0) {
				$this->saveConfig("remotelink3sync");
				return true;
			}
		}
		return false;
	}

	function disableDropboxService($share_name, $delete_access_token, $old_share_name = ""){
		debug_output("disableDropboxService(share_name=" .$share_name . ", delete_access_token=" . $delete_access_token . ", old_share_name=" . $old_share_name . ")");
		# Dropbox共有
		$dropbox = new dropbox_class($this);

		if ($old_share_name && ($old_share_name !== $share_name)) {
			if ($dropbox->existAccessToken($old_share_name)) {
				if (!$dropbox->changeDropbox($old_share_name, $share_name)) {
					debug_output("!changeDropbox");
					return false;
				}
			}
		}
		if (!$dropbox->disableDropbox($share_name, $delete_access_token)) {
			debug_output("!disableDropbox");
			return false;
		}
		return true;
	}

	function disableAzukeruService($share_name){
		# フレッツ・あずけ～る共有
		$azukeru = new azukeru_class($this);
		if($azukeru->findAzukeruShare($share_name) == 0) {
			$azukeru->disableAzukeru($share_name);
		}
	}

	function disableRetemoLinkService($share_name){
		# RL3RemoteSync共有
		$rl3sync = new remotelinksync_class($this);
		$rl3sync->disableRetemoLinkSync($share_name);
	}

	function settingCloudConfig($id,$service,$cloud,$old=""){
		$cloudCnt = array_search(C4_CLOUD_SERVICE_NUM,$service);
		unset($service[$cloudCnt]);
		$this->value["共有フォルダ情報"][$id]["service"] = $service;
		$this->value["共有フォルダ情報"][$id]["cloudselect"] = $cloud;
		#中間設定の再設定
		$this->saveConfig("share");
	}

	function waitCloudService($share){
		exec("/mnt/hda5/bin/nasdsync --wait-setting --share $share");
	}
}
new action($argv[2]);
exit(0);

?>

