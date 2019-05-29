<?php
include_once ('../../../../../c4/c4c.class.php');
include_once (LD_CLASS_PATH . '/system/samba.class.php');
include_once (LD_CLASS_PATH . '/system/detail/share/share/share.class.php');
include_once (LD_CLASS_PATH . '/dropbox.class.php');

class share_initialization extends share_class {
	# 非同期処理 入力欄初期化
	function onLoad() {
		$this->standardOutFlag = true;
		# ログイン確認
		$this->checkLogon2();
		# ステートフル再開
		$this->restartStateFul();
		$this->value = null;
		# LANDISK製品情報取得
		$this->getLanDiskInfo();
		# 非同期通信(POST)
		$this->setValue("共有フォルダID", $this->getFormData("share_id"));
		# 許可したユーザー(タグ生成)
		$permission_user_tag = array();
		# 許可しないユーザー(タグ生成)
		$not_permission_user_tag = array();
		if (!$this->getMessage()) {
			# 入力値初期化
			$this->setValue("共有フォルダ名", "");
			$this->setValue("共有フォルダコメント", $this->value["LANDISK製品情報"]["model"] . " share");
			$this->setValue("サービス", array(1));
			$this->setValue("サービス_hid", "");
			$this->setValue("クラウドストレージ選択", "0");
			#$this->setValue("Dropboxアカウント設定", "");
			$this->setValue("Dropboxアクセストークン削除", "");
			$this->setValue("Dropboxコード", "");
			$this->setValue("Dropboxアクセストークンフラグ", "");
			$this->setValue("Dropboxアクセストークン_hid", "");
			$this->setValue("Dropboxアカウント名", "");
			$this->setValue("フレッツID", "");
			$this->setValue("フレッツID_hid", "");
			$this->setValue("フレッツパスワード", "");
			$this->setValue("フレッツパスワード_hid", "");
			$this->setValue("フレッツホスト", "east");
			$this->setValue("フレッツホスト_hid", "east");
			$this->setValue("詳細アクセス権設定", "all");
			$this->setValue("同期先共有名", "");
			$this->setValue("同期先共有名_hid", "");
			$this->setValue("PINコード", "");
			$this->setValue("PINコード_hid", "");
			$this->setValue("RL3ユーザ名", "");
			$this->setValue("RL3ユーザ名_hid", "");
			$this->setValue("RL3パスワード", "");
			$this->setValue("RL3パスワード_hid", "");
			$this->setValue("読み取り専用区分", "0");
			$this->setValue("ごみ箱機能", "0");
			$this->setValue("全ユーザー欄", array());
			$this->setValue("追加ユーザー欄", array());
			$this->setValue("読取追加ユーザー欄", array());
			$this->setValue("書込追加ユーザー欄", array());
			if ($this->getValue("共有フォルダID")) {
				# 編集
				# 登録情報取得
				$this->getRecord("init");
				# ユーザ選択欄
				$allUserArray = $this->userArray($this->getValue("全ユーザー欄"));
				$addUserArray = $this->userArray($this->getValue("追加ユーザー欄"));
				$readUserArray = $this->userArray($this->getValue("読取追加ユーザー欄"));
				$writeUserArray = $this->userArray($this->getValue("書込追加ユーザー欄"));
				# 許可するユーザ
				$userCount = 0;
				if ($addUserArray) {
					foreach ($addUserArray as $user_id) {
						if ($user_id) {
							if ($user_id === "user_id_admin") {
								$name = "admin";
							} else {
								$name = $this->value["ユーザ情報"][$user_id]["name"];
							}
							if ($name) {
								if (isset($readUserArray[$user_id])) {
									$userCount++;
									$permission_user_tag[] = $this->permissionReadUserTag($user_id, $name);
								}
								if (isset($writeUserArray[$user_id])) {
									$userCount++;
									$permission_user_tag[] = $this->permissionWriteUserTag($user_id, $name);
								}
							}
						}
					}
				}
				if ($userCount === 0) {
					$permission_user_tag[] = "<li class=\"cf\">&nbsp;</li>";
				}
				# 許可しないユーザ
				$userCount = 0;
				if ($allUserArray) {
					foreach ($allUserArray as $user_id) {
						if ($user_id) {
							if (isset($addUserArray[$user_id])) {
								continue;
							}
							if ($user_id === "user_id_admin") {
								$name = "admin";
							} else {
								$name = $this->value["ユーザ情報"][$user_id]["name"];
							}
							if ($name) {
								$userCount++;
								$not_permission_user_tag[] = $this->permissionNoneUserTag($user_id, $name);
							}
						}
					}
				}
				if ($userCount === 0) {
					$not_permission_user_tag[] = "<li class=\"cf\">&nbsp;</li>";
				}
			} else {
				# 新規追加
				# 許可するユーザ(なし)
				$permission_user_tag[] = "<li class=\"cf\">&nbsp;</li>";
				# 許可しないユーザ(全ユーザ)
				$this->loadConfig("user");
				$userCount = 0;
				$allUserArray = array();
				$enable_user_admin = false;
				if ($enable_user_admin) {
					$user_id = "user_id_admin";
					$name = "admin";
					$userCount++;
					$allUserArray[] = $user_id;
					$not_permission_user_tag[] = $this->permissionNoneUserTag($user_id, $name);
				}
				foreach ($this->value["ユーザ情報"] as $user_id => $user) {
					if ($user_id) {
						$name = $this->value["ユーザ情報"][$user_id]["name"];
						if ($name) {
							$userCount++;
							$allUserArray[] = $user_id;
							$not_permission_user_tag[] = $this->permissionNoneUserTag($user_id, $name);
						}
					}
				}
				if ($userCount === 0) {
					$not_permission_user_tag[] = "<li class=\"cf\">&nbsp;</li>";
				}
				$this->setValue("全ユーザー欄", $allUserArray);
			}
		}
		# 最大共有フォルダ数チェック
		$this->checkShareCountValue();
		# エラーメッセージ(1文字化)
		$message = "";
		if ($this->getMessage()) {
			foreach ($this->getMessage() as $key => $value) {
				$message.= $value;
			}
		}
		# 返却値配列化
		$valueArray = array();
		$valueArray["lock_timeout"] = "";
		$valueArray["message"] = $message;
		$valueArray["share_fault_flag"] = $this->getValue("共有フォルダ数超過フラグ"); # 警告
		$valueArray["name"] = $this->getValue("共有フォルダ名");
		$valueArray["comment"] = $this->getValue("共有フォルダコメント");
		$valueArray["service"] = $this->getValue("サービス");
		$valueArray["cloud"] = $this->getValue("クラウドストレージ選択");
		#$valueArray["dbcheck"] = $this->getValue("Dropboxアカウント設定");
		$valueArray["dbrevokeflag"] = $this->getValue("Dropboxアクセストークン削除");
		$valueArray["dbcode"] = $this->getValue("Dropboxコード");
		$valueArray["dbtokenflag"] = $this->getValue("Dropboxアクセストークンフラグ");
		$valueArray["dbaccountname"] = $this->getValue("Dropboxアカウント名");
		$valueArray["fid"] = $this->getValue("フレッツID");
		$valueArray["fpass"] = $this->getValue("フレッツパスワード");
		$valueArray["fhost"] = $this->getValue("フレッツホスト");
		$valueArray["access"] = $this->getValue("詳細アクセス権設定");
		$valueArray["fletsid_hid"] = $this->getValue("フレッツID_hid");
		$valueArray["fletspass_hid"] = $this->getValue("フレッツパスワード_hid");
		$valueArray["fletshost_hid"] = $this->getValue("フレッツホスト_hid");
		$valueArray["cloud_sel_hid"] = $this->getValue("クラウドストレージ選択ID_hid");
		$valueArray["service_hid"] = $this->getValue("サービス_hid");
		$valueArray["rl3syncfname"] = $this->getValue("同期先共有名");
		$valueArray["rl3syncfname_hid"] = $this->getValue("同期先共有名_hid");
		$valueArray["rl3syncpincode"] = $this->getValue("PINコード");
		$valueArray["rl3syncpincode_hid"] = $this->getValue("PINコード_hid");
		$valueArray["rl3syncusername"] = $this->getValue("RL3ユーザ名");
		$valueArray["rl3syncusername_hid"] = $this->getValue("RL3ユーザ名_hid");
		$valueArray["rl3syncpassword"] = $this->getValue("RL3パスワード");
		$valueArray["rl3syncpassword_hid"] = $this->getValue("RL3パスワード_hid");
		$valueArray["read_only"] = $this->getValue("読み取り専用区分");
		$valueArray["trash"] = $this->getValue("ごみ箱機能");
		$valueArray["remains_user_list"] = $this->getValue("全ユーザー欄");
		$valueArray["add_user_list"] = $this->getValue("追加ユーザー欄");
		$valueArray["read_add_user_list"] = $this->getValue("読取追加ユーザー欄");
		$valueArray["write_add_user_list"] = $this->getValue("書込追加ユーザー欄");
		$valueArray["permission_user_tag"] = $permission_user_tag;
		$valueArray["not_permission_user_tag"] = $not_permission_user_tag;
		# 配列をエンコードして返す
		echo json_encode($valueArray);
	}
	function userArray($userList) {
		$userArray = array();
		if ($userList) {
			$userIdArray = c4_explode(",", $userList);
			foreach ($userIdArray as $user_id) {
				if ($user_id) {
					$userArray[$user_id] = $user_id;
				}
			}
		}
		return $userArray;
	}
	function permissionReadUserTag($user_id, $name) {
		return "<li class=\"cf\" id=\"SHARE_readUser[" . $user_id . "]\" style=\"background-color:#398439;color:#fff;\">(読み取り) " . $name . "<span class=\"sakujo\"><a style=\"color:#FFFFFF;\" onClick=\"SHARE_deleteMoveForm('read','" . $user_id . "')\">削除</a></span></li>";
	}
	function permissionWriteUserTag($user_id, $name) {
		return "<li class=\"cf\" id=\"SHARE_writeUser[" . $user_id . "]\" style=\"background-color:#46b8da;color:#fff;\">(読み書き) " . $name . "<span class=\"sakujo\"><a style=\"color:#FFFFFF;\" onClick=\"SHARE_deleteMoveForm('write','" . $user_id . "')\">削除</a></span></li>";
	}
	function permissionNoneUserTag($user_id, $name) {
		return "<li class=\"cf\" id=\"SHARE_not_permission_user[" . $user_id . "]\"><span><input name=\"SHARE_usrCategory[]\" id=\"SHARE_usrCategory[][" . $name . "]\" value=\"" . $user_id . "\" type=\"checkbox\"></span> " . $name . "</li>";
	}
}
new share_initialization();
?>
