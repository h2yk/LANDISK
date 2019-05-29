<?php
include_once ('/usr/local/c4/c4c.class.php');
include_once (LD_CLASS_PATH . '/system/samba.class.php');
include_once (LD_CLASS_PATH . '/dropbox.class.php');
include_once (LD_CLASS_PATH . '/azukeru.class.php');
include_once (LD_CLASS_PATH . '/remotelinksync.class.php');

class share_class extends samba_class {
	function getInputData() {
		$userList = array();
		$userList = c4_explode(",", $this->getValue("追加ユーザー欄"));
		$readuserList = array();
		$readuserList = c4_explode(",", $this->getValue("読取追加ユーザー欄"));
		$writeuserList = array();
		$writeuserList = c4_explode(",", $this->getValue("書込追加ユーザー欄"));
		$valid_userList = "";
		if ($userList) {
			foreach ($userList as $key => $user_id) {
				if ($user_id) {
					if ($user_id === "user_id_admin") {
						$name = "admin";
					} else {
						$name = $this->value["ユーザ情報"][$user_id]["name"];
					}
					$valid_userList = $valid_userList . $name . ',';
				} else {
					unset($userList[$key]);
				}
			}
		}
		if ($valid_userList) $valid_userList = substr($valid_userList, 0, -1);
		$this->setValue("読み取りユーザー", $readuserList);
		$this->setValue("書き込みユーザー", $writeuserList);
		# 読み取りユーザ名リスト
		$readUserNameList = array();
		if ($readuserList) {
			foreach ($readuserList as $user_id) {
				if ($user_id) {
					if ($user_id === "user_id_admin") {
						$name = "admin";
					} else {
						$name = $this->value["ユーザ情報"][$user_id]["name"];
					}
					$readUserNameList[] = $name;
				}
			}
		}
		# 読み書きユーザ名リスト
		$writeUserNameList = array();
		if ($writeuserList) {
			foreach ($writeuserList as $user_id) {
				if ($user_id) {
					if ($user_id === "user_id_admin") {
						$name = "admin";
					} else {
						$name = $this->value["ユーザ情報"][$user_id]["name"];
					}
					$writeUserNameList[] = $name;
				}
			}
		}
		$this->setValue("追加名称リスト", "," . $valid_userList);
		$this->setValue("読み取りユーザ名リスト", implode($readUserNameList, ","));
		$this->setValue("読み書きユーザ名リスト", implode($writeUserNameList, ","));
	}
	function checkInputData($mode = null) {
		$foldercheck = $this->checkValue("foldername", "共有フォルダ名", 1, 14, true, __("フォルダー名"));
		$dupcheck = $this->checkValue("duplicate_foldername", "共有フォルダ名", $this->getValue("共有フォルダID"), __("フォルダー名"));
		if ($foldercheck && $dupcheck) {
			$path = LD_SHARE_ROOT_DIR . "/" . $this->getValue("共有フォルダ名");
			if (file_exists($path)) {
				if (is_file($path)) {
					$this->setValue("ファイル存在エラー", __("同名のファイルがあるため、共有フォルダーを作成できません。"), VALUE_MESSAGE);
				}
				if (is_dir($path)) {
					if ($mode == "add") {
						$this->setValue("DASフォルダ存在フラグ", true);
					}
					if ($mode == "edit" && ($this->getValue("共有フォルダ名") != $this->getValue("共有フォルダ名tmp"))) {
						$this->setValue("フォルダ存在エラー", __("同名のフォルダーがあるため、共有フォルダーを作成できません。"), VALUE_MESSAGE);
					}
				}
			}
		}
		$this->checkValue("comments", "共有フォルダコメント", 1, 48, false, __("共有フォルダーコメント"));
		$this->checkValue("numeric", "読み取り専用区分", 0, 1, true, __("読み取り専用区分"));
		if (is_array($this->getValue("サービス"))) {
			foreach ($this->getValue("サービス") as $this->value["サービスtmp"]) {
				$this->checkValue("numeric", "サービスtmp", 1, 8, true, __("サービス"));
				if ($this->value["サービスtmp"] == 7) {
					$this->checkValue("numeric", "クラウドストレージ選択", 0, 2, true, __("クラウドストレージ選択"));
					if ($this->getValue("クラウドストレージ選択") == 0) {
						$dropbox = new dropbox_class($this);
						if ($mode == "add") {
							$exist_access_token = $dropbox->existAccessToken($this->getValue("共有フォルダ名"));
						} else if ($mode == "edit") {
							$exist_access_token = $dropbox->existAccessToken($this->getValue("共有フォルダ名tmp"));
						}
						if(!$exist_access_token && !$this->getValue("Dropboxコード")){
							$this->setValue("ERROR", __("コードを入力してください。") . "<br>", VALUE_MESSAGE);
						}
					} else if ($this->getValue("クラウドストレージ選択") == 1) {
						$azukeru = new azukeru_class($this);
						$this->checkValue("fletsid", "フレッツID", 4, 60, true, __("ログインID"));
						$this->checkValue("alphanum", "フレッツパスワード", 6, 10, true, __("パスワード"));
						if (!$this->getValue("フレッツホスト") || ($this->getValue("フレッツホスト") != 'east') && ($this->getValue("フレッツホスト") != 'west')) {
							$this->setValue("ERROR_flets_host", "ホストを正しく選択してください。<br>", VALUE_MESSAGE);
						}
						if ($mode == "add") {
							$findid = $azukeru->findAzukeruShare($this->getValue("共有フォルダ名"), $this->getValue("フレッツID"), $this->getValue("フレッツパスワード"), $this->getValue("フレッツホスト"));
						} else if ($mode == "edit") {
							$findid = $azukeru->findAzukeruShare($this->getValue("共有フォルダ名tmp"), $this->getValue("フレッツID"), $this->getValue("フレッツパスワード"), $this->getValue("フレッツホスト"));
						}
					} else if ($this->getValue("クラウドストレージ選択") == 2) {
						$this->checkValue("rl3cloudsync_folder", "同期先共有名", 1, 64, true, __("共有先フォルダ名"));
						$this->checkValue("pincode", "PINコード", 32, 32, true, __("PINコード"));
						$this->checkValue("user", "RL3ユーザ名", 1, 20, true, __("ユーザ名"));
						$this->checkValue("name", "RL3パスワード", null, 20, false, __("パスワード"));
					}
				}
			}
		}
		$this->checkValue("numeric", "ごみ箱機能", 0, 1, true, __("ごみ箱機能"));
		if (!$this->getValue("詳細アクセス権設定") || ($this->getValue("詳細アクセス権設定") != 'all') && ($this->getValue("詳細アクセス権設定") != 'mix')) {
			$this->setValue("ERROR_access_setting", "詳細アクセス権設定を正しく選択してください。<br>", VALUE_MESSAGE);
		} else if ($this->getValue("詳細アクセス権設定") == 'mix') {
			$addUser = str_replace(",", "", $this->getValue("追加ユーザー欄"));
			if (!$addUser) {
				$this->setValue("ERROR_user_access_setting", __("「詳細アクセス権設定」が未入力です。<br>"), VALUE_MESSAGE);
			}
		}
		if ($this->getMessage()) {
			return false;
		}
		return true;
	}

	function getRecord($mode = null) {
		$this->loadConfig("share");
		if (isset($this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ])) {
			$this->setValue("共有フォルダ名", $this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ]["name"]);
			$this->setValue("共有フォルダ名tmp", $this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ]["name"]);
			$path = LD_SHARE_ROOT_DIR . "/" . $this->getValue("共有フォルダ名");
			if (file_exists($path) && is_dir($path)) {
				$this->setValue("ディレクトリ存在区分", true);
			} else {
				$this->setValue("ディレクトリ存在区分", false);
			}
			$this->setValue("共有フォルダコメント", $this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ]["comment"]);
			$this->setValue("サービス", $this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ]["service"]);
			$this->setValue("クラウドストレージ選択", $this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ]["cloudselect"]);
			$this->setValue("クラウドストレージ選択ID_hid", $this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ]["cloudselect"]);
			$this->setValue("読み取り専用区分", getArrayValue($this->value, array("共有フォルダ情報", $this->getValue("共有フォルダID"), "read_only"), "0"));
			$this->setValue("ごみ箱機能", getArrayValue($this->value, array("共有フォルダ情報", $this->getValue("共有フォルダID"), "trash"), "0"));
			$this->setValue("詳細アクセス権設定", $this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ]["detail_access_setting"]);

			if($mode == "init"){
				$dropbox = new dropbox_class($this);
				$share_name = $this->getValue("共有フォルダ名");
				$this->setValue("Dropboxアクセストークンフラグ",$dropbox->existAccessToken($share_name));
				if($this->getValue("Dropboxアクセストークンフラグ")){
					$this->setValue("Dropboxアカウント名",$dropbox->getAccountName($share_name));
				}
			}

			$this->loadConfig("azukeru");
			if (isset($this->value["フレッツ共有"][$this->getValue("共有フォルダID") ])) {
				$this->setValue("フレッツID", $this->value["フレッツ共有"][$this->getValue("共有フォルダID") ]["fletsid"]);
				$this->setValue("フレッツID_hid", $this->value["フレッツ共有"][$this->getValue("共有フォルダID") ]["fletsid"]);
				$this->setValue("フレッツパスワード", $this->value["フレッツ共有"][$this->getValue("共有フォルダID") ]["fletspassword"]);
				$this->setValue("フレッツパスワード_hid", $this->value["フレッツ共有"][$this->getValue("共有フォルダID") ]["fletspassword"]);
				$fletshost = "east";
				if (isset($this->value["フレッツ共有"][$this->getValue("共有フォルダID") ]["fletshost"])) {
					$fletshost = $this->value["フレッツ共有"][$this->getValue("共有フォルダID") ]["fletshost"];
				}
				$this->setValue("フレッツホスト", $fletshost);
				$this->setValue("フレッツホスト_hid", $fletshost);
			}
			$this->loadConfig("remotelink3sync");
			if (isset($this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID") ])) {
				$this->setValue("同期先共有名", $this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID") ]["同期先共有名"]);
				$this->setValue("同期先共有名_hid", $this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID") ]["同期先共有名"]);
				$this->setValue("PINコード", $this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID") ]["PINコード"]);
				$this->setValue("PINコード_hid", $this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID") ]["PINコード"]);
				$this->setValue("RL3ユーザ名", $this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID") ]["RL3ユーザ名"]);
				$this->setValue("RL3ユーザ名_hid", $this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID") ]["RL3ユーザ名"]);
				$this->setValue("RL3パスワード", $this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID") ]["RL3パスワード"]);
				$this->setValue("RL3パスワード_hid", $this->value["RemoteLink3Sync共有"][$this->getValue("共有フォルダID") ]["RL3パスワード"]);
			}
			if (is_array($this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ]["service"])) {
				foreach ($this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ]["service"] as $this->value["サービスtmp"]) {
					if ($this->value["サービスtmp"] == 7) {
						$this->setValue("サービス_hid", $this->value["サービスtmp"]);
					}
				}
			}
			$this->loadConfig("user");
			$readUser = array();
			if ($this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ]["read_user"]) {
				$readUser = explode(",", $this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ]["read_user"]);
			}
			$writeUser = array();
			if ($this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ]["write_user"]) {
				$writeUser = explode(",", $this->value["共有フォルダ情報"][$this->getValue("共有フォルダID") ]["write_user"]);
			}
			$allUserArray = array();
			$addUserArray = array();
			$readUserArray = array();
			$writeUserArray = array();
			$enable_user_admin = false;
			if ($enable_user_admin) {
				$allUserArray[] = "user_id_admin";
				foreach ($readUser as $name) {
					if ($name === "admin") {
						$addUserArray[] = "user_id_admin";
						$readUserArray[] = "user_id_admin";
					}
				}
				foreach ($writeUser as $name) {
					if ($name === "admin") {
						$addUserArray[] = "user_id_admin";
						$writeUserArray[] = "user_id_admin";
					}
				}
			}
			if ($this->value["ユーザ情報"]) {
				foreach ($this->value["ユーザ情報"] as $user_id => $user) {
					$allUserArray[] = $user_id;
					foreach ($readUser as $name) {
						if ($name === $user["name"]) {
							$addUserArray[] = $user_id;
							$readUserArray[] = $user_id;
						}
					}
					foreach ($writeUser as $name) {
						if ($name === $user["name"]) {
							$addUserArray[] = $user_id;
							$writeUserArray[] = $user_id;
						}
					}
				}
			}
			$this->setValue("全ユーザー欄", implode(",", $allUserArray));
			$this->setValue("追加ユーザー欄", implode(",", $addUserArray));
			$this->setValue("読取追加ユーザー欄", implode(",", $readUserArray));
			$this->setValue("書込追加ユーザー欄", implode(",", $writeUserArray));
			return true;
		}
		return false;
	}
	function setShareAccessDetail() {
		$this->setItemName("読み取りユーザー", "user"); # localguser
		$this->setItemName("書き込みユーザー", "user"); # localguser
		$this->setValue("読み取りユーザー", explode(",", $this->getValue("読取追加ユーザー欄")));
		$this->setValue("書き込みユーザー", explode(",", $this->getValue("書込追加ユーザー欄")));
	}
	# TwonkyServerデフォルト設定ファイル更新
	function updateMediaSetting() {
		$this->loadConfig("media");
		$this->loadConfig("network");
		$this->loadConfig("share");
		$enableweb = 1;
		exec("/usr/local/bin/isaudiomodel.sh", $output, $result);
		if ($result === 0) {
			$enableweb = 2;
		}
		$friendlyname = $this->getValue("LANDISK名");
		$list_contentdir = array();
		foreach ($this->value["共有フォルダ情報"] as $share_id => $share) {
			if (is_array($share["service"])) {
				if (array_search("8", $share["service"]) !== false) {
					$list_contentdir[] = "+A|" . LD_SHARE_ROOT_DIR . "/" . $share["name"];
				}
			}
		}
		$contentdir = implode(",", $list_contentdir);
		$language = $this->getValue("カテゴリ表示");
		$command = "sudo /usr/local/bin/twonky_setting.sh" . " '" . $enableweb . "'" . " '" . $friendlyname . "'" . " '" . $contentdir . "'" . " '" . $language . "'" . " >/dev/null 2>&1";
		$this->output_console($command);
		exec($command, $output, $result);
		if ($result != 0) {
			$this->output_console("WARNING:twonky_setting.sh");
			return false;
		}
		return true;
	}
	# TwonkyServer mDNS設定ファイル更新
	function updateMediaMdnsSetting() {
		$this->loadConfig("media");
		$mdns = $this->getValue("mDNS設定");
		$twonky_result = 0;
		$avahi_result = 0;
		function set_flag($flag_data, $flag_file) {
			$result = 0;
			if ($flag_data == "true") {
				if (file_exists($flag_file)) {
					exec("sudo rm " . $flag_file . " > /dev/null 2>&1", $output, $result);
				}
			} else {
				exec("sudo touch " . $flag_file . " > /dev/null 2>&1", $output, $result);
			}
			return $result;
		}
		$twonky_result = set_flag($mdns["twonky"], "/mnt/hda5/twonky/iodata_mdns_flag");
		$avahi_result = set_flag($mdns["avahi"], "/mnt/hda5/conf/avahi/iodata_mdns_flag");
		$this->restartConfig("media");
		$this->restartConfig("avahi-daemon");
		if ($twonky_result != 0 or $avahi_result != 0) {
			return false;
		}
		return true;
	}
	# 共有フォルダ設定
	function shareSetting() {
		$this->loadConfig("network");
		$this->loadConfig("share");
		$this->loadConfig("user");
		$this->loadConfig("microsoft");
		$this->loadConfig("others");
		# twonkyServer設定(DLNAは同時に使えない)
		$this->updateMediaSetting();
		# samba.conf設定
		if (is_array($this->value["共有フォルダ情報"])) {
			reset($this->value["共有フォルダ情報"]);
		}
		$this->makeConfig("/etc/samba/smb_conf");
	}
	# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #
	# # # # # # # #
	# 追加画面関連↓
	# # # # # # # #
	# ステートフル再開
	function restartStateFul() {
		# 非同期通信(POST)
		$this->setValue("ステートフルID", $this->getFormData("StateFulID"));
		if (!$this->getValue("ステートフルID")) {
			$this->setValue("ERROR:STID", __("エラーが発生しました。"), VALUE_MESSAGE);
		}
		# ステートフル開始
		$this->startStateFul($this->getValue("ステートフルID"));
	}
	# 最大共有フォルダ数制限
	function checkShareCount() {
		$this->loadConfig("share");
		if (count($this->value["共有フォルダ情報"]) >= LD_SHARE_LIMIT) {
			return false;
		}
		return true;
	}
	# 最大共有フォルダ数制限
	function checkShareCountValue() {
		# 共有フォルダ数OK
		$this->setValue("共有フォルダ数超過フラグ", "1");
		$this->setValue("共有フォルダ数超過エラー", "");
		if (!$this->getValue("共有フォルダID")) {
			# 最大共有フォルダ数チェック
			if (!$this->checkShareCount()) {
				# 共有フォルダ数超過
				$this->setValue("共有フォルダ数超過フラグ", "2");
				$this->setValue("共有フォルダ数超過エラー", __("共有フォルダー数が最大登録数に達しているため、新しい共有フォルダーを登録できません。<br>新しい共有フォルダーを登録するには、既存の共有フォルダーを削除する必要があります。"), VALUE_MESSAGE);
			}
		}
	}
	# 新規追加・編集・削除
	function shareDataSetting() {
		exec("sudo " . LD_PHP_COMMAND . " " . LD_BIN_PATH . "/share.php " . $this->getSession()->getSessionId() . " " . $this->stateFulID . " >& /dev/null &");
	}
}
?>
