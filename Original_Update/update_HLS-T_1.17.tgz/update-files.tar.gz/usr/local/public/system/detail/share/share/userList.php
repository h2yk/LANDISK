<?php
include_once ('../../../../../c4/c4c.class.php');
class userList extends C4CAction {
	# 非同期処理 許可したユーザー 許可しないユーザー タグ生成
	function onLoad() {
		$this->standardOutFlag = true;
		# ログイン確認
		$this->checkLogon2();
		# ステートフル再開
		$this->restartStateFul();
		# 非同期通信(POST)
		$this->setValue("全ユーザー欄", $this->getFormData("remains_user_list"));
		$this->setValue("追加ユーザー欄", $this->getFormData("add_user_list"));
		$this->setValue("読取追加ユーザー欄", $this->getFormData("read_add_user_list"));
		$this->setValue("書込追加ユーザー欄", $this->getFormData("write_add_user_list"));
		# 許可したユーザー(タグ生成)
		$permission_user_tag = array();
		# 許可しないユーザー(タグ生成)
		$not_permission_user_tag = array();
		if (!$this->getMessage()) {
			$allUserArray = $this->userArray($this->getValue("全ユーザー欄"));
			$addUserArray = $this->userArray($this->getValue("追加ユーザー欄"));
			$readUserArray = $this->userArray($this->getValue("読取追加ユーザー欄"));
			$writeUserArray = $this->userArray($this->getValue("書込追加ユーザー欄"));
			# 中間設定情報取得
			$this->loadConfig("user");
			# 許可したユーザー(タグ生成)
			$userCount = 0;
			if ($addUserArray) {
				foreach ($addUserArray as $user_id) {
					if ($user_id) {
						if($user_id === "user_id_admin") {
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
			# 許可しないユーザー(タグ生成)
			$userCount = 0;
			if ($allUserArray) {
				foreach ($allUserArray as $user_id) {
					if ($user_id) {
						if (isset($addUserArray[$user_id])) {
							continue;
						}
						if($user_id === "user_id_admin") {
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
		}
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
		$valueArray["permission_user_tag"] = $permission_user_tag;
		$valueArray["not_permission_user_tag"] = $not_permission_user_tag;
		# 配列をエンコードして返す
		echo json_encode($valueArray);
	}
	# ============================================================================== #
	# 内部処理定義
	# ============================================================================== #
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
new userList();
?>
