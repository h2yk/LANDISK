<?php
class remotelink3_class extends C4CAction{
	# ============================================================================== #
	# 内部処理定義
	# ============================================================================== #
	/**
	 * 入力チェック
	 */
	function checkInputData(){
		$this->checkValue("numeric",		"RL3_利用区分",		0,	1,	true,	__("RemoteLink3機能"));
		if($this->getValue("RL3_利用区分") == 1){
			$this->checkValue("portnum",	"RL3_リモートアクセスポート番号1",		1025,65535,	true,__("ポート番号1"));
			$this->checkValue("portnum",	"RL3_リモートアクセスポート番号2",		1025,65535,	true,__("ポート番号2"));
			
			$this->checkValue("numeric",	"RL3_外部ポート区分",					0,	1,	true,	__("外部ポート設定"));
			if($this->getValue("RL3_外部ポート区分") == 1){
				$this->checkValue("portnum",		"RL3_外部ポート番号1",			0,65535,	true,__("外部ポート番号1"));
				$this->checkValue("portnum",		"RL3_外部ポート番号2",			0,65535,	true,__("外部ポート番号2"));
			}
			# 既に使われているポート番号と比較
			$this->checkSettingPort("remotelink3");
			
			$this->checkValue("numeric",	"RL3_リモートUI利用",		0,	1,	true,	__("リモートUI"));
			$this->checkValue("numeric",	"RL3_PINコード変更",	1,	1,	$this->getValue("RL3_PINコード変更")?true:false,	__("PINコード変更"));
		}
		$this->checkValue("numeric",		"RL3_UPNP機能利用",		0,	1,	true,	__("UPNP機能"));
		
		if($this->getValue("RL3_利用区分tmp") == 1){
			# 現在のPINコード取得
			$this->setValue("RL3_PINコード", $this->getPincode("current"));
		}
		
		if(!$this->getMessage()){
			return true;
		}
		return false;
	}
	
	function getPincode($status) {
		if ($status !== "init" && $status !== "current" && $status !== "new") {
			$this->setValue("RL3_PINコードエラー", __("PINコード取得コマンドのパラメータが不正です。"), VALUE_MESSAGE);
			return null;
		}
		$output = array();
		exec("sudo /usr/local/bin/rl3_pincode.sh ".$status, $output, $result);
		if($result != 0) {
			switch ($result){
				case 128:
					$this->setValue("RL3_PINコードエラー", __("PINコードの登録が完了していません。本製品がインターネットに接続されているか確認してください。"), VALUE_MESSAGE);
					break;
				default:
					$this->setValue("RL3_PINコードエラー", __("PINコード取得に失敗しました。"), VALUE_MESSAGE);
					break;
			}
			return null;
		}
		$pincode = $output[0];
		return $pincode;
	}
	
	function registPincode($status, $pincode="") {
		if ($status !== "regist_init" && $status !== "regist") {
			$this->setValue("RL3_PINコードエラー", __("PINコード登録コマンドのパラメータが不正です。"), VALUE_MESSAGE);
			return false;
		}
		$output = array();
		exec("sudo /usr/local/bin/rl3_pincode.sh ".$status." ".$pincode, $output, $result);
		if($result != 0) {
			switch ($result){
				case 1:
					$this->setValue("RL3_PINコードエラー", __("登録されているPINコードの解除に失敗しました。"), VALUE_MESSAGE);
					break;
				case 2:
					$this->setValue("RL3_PINコードエラー", __("新しいPINコードの登録に失敗しました。"), VALUE_MESSAGE);
					break;
				case 128:
					$this->setValue("RL3_PINコードエラー", __("PINコードの登録が完了していません。本製品がインターネットに接続されているか確認してください。"), VALUE_MESSAGE);
					break;
				default:
					$this->setValue("RL3_PINコードエラー", __("PINコード登録に失敗しました。"), VALUE_MESSAGE);
					break;
			}
			return false;
		}
		$this->logging("rl3:pincode_regist_success");
		return true;
	}
}
?>
