<?php
include_once('../../../../../c4/c4c.class.php');
include_once(LD_CLASS_PATH.'/system/samba.class.php');
include_once(LD_CLASS_PATH.'/system/detail/share/share/share.class.php');

class share_wait extends share_class{
	# 非同期処理 入力欄初期化
	function onLoad(){
		$this->standardOutFlag=true;

		# ログイン確認
 		$this->checkLogon2();

		# ステートフル再開
		$this->restartStateFul();

		$this->setValue("画面切り替えフラグ",			"");
		$this->setValue("追加編集エラー",				"");
		#$this->setValue("DropboxURL表示",				"");
		$this->setValue("フレッツあずけ～る表示",		"");
		$this->setValue("あずけ～るサーバ接続",			"");
		$this->setValue("RemoteCloud表示",				"");

		if(!isset($this->value["proc_result"])){
			$completeFlag = "wait";
		}else{
			$completeFlag = "end";

			if($this->getValue("proc_message")){
				if($this->getValue("動作モード") == "add"){
					$this->setValue("追加編集エラー",__("新しい共有フォルダーの作成に失敗しました。"));
					$this->setValue("画面切り替えフラグ","1");
				}else if($this->getValue("動作モード") == "edit"){
					$this->setValue("追加編集エラー",__("共有フォルダーの変更に失敗しました。"));
					$this->setValue("画面切り替えフラグ","1");
				} else if ($this->getValue("動作モード") == "delete"){
					$this->setValue("追加編集エラー",__("共有フォルダーの削除に失敗しました。"));
					$this->setValue("画面切り替えフラグ","1");
				}
			}

			if($this->getValue("Enable_Dropbox_Status")){
				$this->setValue("Dropboxサーバ接続",__("設定の変更に失敗しました。"));
				$this->setValue("画面切り替えフラグ","1");
			}

			if($this->getValue("Flets_Status") == ""){
			}else if($this->getValue("Flets_Status") == "0"){
			}else if($this->getValue("Flets_Status") == "3"){
				$this->setValue("フレッツあずけ～る表示",__("フレッツ・あずけ～るサービスの登録が完了していません。"));
				$this->setValue("画面切り替えフラグ","1");
			}else{
				$this->setValue("フレッツあずけ～る表示",__("フレッツ・あずけ～るサービスへの接続に失敗しました。"));
				$this->setValue("画面切り替えフラグ","1");
			}

			if($this->getValue("Enable_Flets_Status")){
				$this->setValue("あずけ～るサーバ接続",__("設定の変更に失敗しました。"));
				$this->setValue("画面切り替えフラグ","1");
			}

			if($this->getValue("RemoteCloudSelect") != "") {
				$csync_error_message = $this->get_csync_error_message($this->getValue("RemoteCloudStatus"));
				if($csync_error_message != "") {
					$this->setValue("RemoteCloud表示",__("Remote Link Cloud Sync 設定エラー")."<br>".$csync_error_message);
					$this->setValue("画面切り替えフラグ","1");
				}
			}
		}

		# 返却値配列化
		$valueArray = array();
# 		$valueArray["lock_timeout"] = "";
		$valueArray["proc_result"] = $completeFlag;
		$valueArray["disp_change"] = $this->getValue("画面切り替えフラグ");
		$valueArray["proc_message"] = $this->getValue("追加編集エラー");
		$valueArray["Enable_Dropbox_Status"] = $this->getValue("Dropboxサーバ接続");
		$valueArray["Flets_Status"] = $this->getValue("フレッツあずけ～る表示");
		$valueArray["Enable_Flets_Status"] = $this->getValue("あずけ～るサーバ接続");
		$valueArray["RemoteLink_Status"] = $this->getValue("RemoteCloud表示");

		# 配列をエンコードして返す
		echo json_encode($valueArray);
	}

	function get_csync_error_message($result) {
		$result_array = explode(',',$result,2);
		$status = "";
		if(count($result_array) >= 1 ){
			$status = $result_array[0];
		}
		$error_code = "";
		if(count($result_array) == 2 && ctype_digit($result_array[1])){
			$error_code = $result_array[1];
		}
		if($status == "OK") {
			return "";
		}
		$error_message = __("エラーが発生しました。");
		if($status == "TouError") {
			$error_message = __("通信に失敗しました。")."<br>".__("しばらく待ってから再度設定を行ってください。");
			if($error_code == "5010000") {
				$error_message = __("インターネットに接続していません。"."<br>".__("ご確認の上、再度設定を行ってください。"));
			} else
			if($error_code == "5020000") {
				$error_message = __("通信に失敗しました。")."<br>".__("しばらく待ってから再度設定を行ってください。");
			} else
			if($error_code == "5020101" || $error_code == "5020201") {
				$error_message = __("PINコードが間違っています。");
			} else
			if($error_code == "5020103" || $error_code == "5020203") {
				$error_message = __("ご利用の環境では接続できません。");
			} else
			if($error_code == "5020204") {
				$error_message = __("同時接続数が最大に達しました。")."<br>".__("しばらく待ってから再度設定を行ってください。");
			} else
			if($error_code == "5000102") {
				$error_message = __("通信に失敗しました。")."<br>".__("しばらく待ってから再度設定を行ってください。");
			} else
			if($error_code == "6000100" || $error_code == "7000100") {
				$error_message = __("通信に失敗しました。");
			} else
			if($error_code == "6000200") {
				$error_message = __("Remote Link 3 機能を利用開始できませんでした。")."<br>".__("しばらく待ってから再度設定を行ってください。");
			} else
			if($error_code == "5030000") {
				$error_message = __("通信に失敗しました。");
			}
		} else
		if($status == "RapsError") {
			$error_message = __("接続に失敗しました。")."".__("接続機器の設定を確認してください。");
			if($error_code == "256") {
				$error_message = __("接続機器上に共有フォルダーが存在しません。");
			} else
			if($error_code == "11") {
				$error_message = __("接続機器への接続数が多すぎます。");
			} else
			if($error_code == "10") {
				$error_message = __("ユーザー名もしくはパスワードが不正です。");
			}
		}
		if($error_code != "") {
			$error_message .= "<br>".__("エラーコード:").$error_code;
		}
		return $error_message;
	}
}
new share_wait();

?>

