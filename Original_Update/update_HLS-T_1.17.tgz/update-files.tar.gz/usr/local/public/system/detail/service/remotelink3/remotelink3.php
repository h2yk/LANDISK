<?php
include_once('../../../../../c4/c4c.class.php');
include_once(LD_CLASS_PATH.'/system/detail/service/remotelink3/remotelink3.class.php');

class remotelink3 extends remotelink3_class{
	# ============================================================================== #
	# イベント定義
	# ============================================================================== #
	function onLoad(){
		$this->standardOutFlag=true;
		
		# ログイン確認
		$this->checkLogon2();
		
		$this->startStateFul();
		
		# 中間設定ファイル読み込み
		$this->loadConfig("remotelink3");
		$this->loadConfig("share");
		$this->loadConfig("spindown");
		
		# 現在のPINコードが初期PINコードか確認
		if ($this->getPincode("current") === $this->getPincode("init")) {
			# 「現在のPINコード == 初期PINコード」の場合
			$this->setValue("PINコード確認", 0);
		} else {
			$this->setValue("PINコード確認", 1);
			
			# 「現在のPINコード != 初期PINコード」の場合
			$this->initRemotelink3Values();
			# サービス利用状況をテンポラリに保存
			$this->setTemporaryData();
		}
		
		$this->setItemName("RL3_利用区分",		"enable");
		$this->setItemName("RL3_UPNP機能利用",	"use");
		$this->setItemName("RL3_外部ポート区分","doaction");
		$this->setItemName("RL3_リモートUI利用","use");
		$this->setItemName("RL3_PINコード変更",	"single:".__("PINコードを変更する"));
		$this->setItemName("RL3_利用ユーザー",	"qruser");
		
		# iobbネットメッセージ設定
		$url_link = '<a href='.LD_REMOTELINK3_GATE_URL.' target="_blank">'.LD_REMOTELINK3_GATE_URL.'</a>';
		$this->setValue("RL3_パソコンからのアクセス",str_replace("[%1]",$url_link,__("パソコンからは[%1]へアクセスし、「PINコード」をクライアントに入力して接続してください。")));
		
		# 返却値配列化
		$valueArray = array();
		$valueArray["lock_timeout"] = "";
		$valueArray["rl3_pincode_check"] = $this->getValue("PINコード確認");
		$valueArray["rl3_use_tmp"] = $this->getValue("RL3_利用区分tmp");
		$valueArray["rl3_enabled"] = $this->getValue("RL3_利用区分");
		$valueArray["rl3_remoteAccess_port1"] = $this->getValue("RL3_リモートアクセスポート番号1");
		$valueArray["rl3_remoteAccess_port2"] = $this->getValue("RL3_リモートアクセスポート番号2");
		$valueArray["rl3_button1"] = $this->getValue("RL3_リモートアクセスポート番号1初期値");
		$valueArray["rl3_button2"] = $this->getValue("RL3_リモートアクセスポート番号2初期値");
		$valueArray["rl3_useupnp"] = $this->getValue("RL3_UPNP機能利用");
		$valueArray["rl3_externalAccess_enabled"] = $this->getValue("RL3_外部ポート区分");
		$valueArray["rl3_externalAccess_port1"] = $this->getValue("RL3_外部ポート番号1");
		$valueArray["rl3_externalAccess_port2"] = $this->getValue("RL3_外部ポート番号2");
		$valueArray["rl3_use_remoteUi"] = $this->getValue("RL3_リモートUI利用");
		$valueArray["rl3_pincode"] = $this->getValue("RL3_PINコード");
		$valueArray["rl3_qrcode_user"] = $this->getValue("RL3_利用ユーザー");
		$valueArray["rl3_message_acccess"] = $this->getValue("RL3_パソコンからのアクセス");
		
		$message = "";
		if($this->getMessage()){
			foreach($this->getMessage() as $key => $value){
				$message .= $value;
			}
		}
		$valueArray["message"] = $message;
		
		# 配列をエンコードして返す
		echo json_encode($valueArray);
	}
	# ============================================================================== #
	# 内部処理定義
	# ============================================================================== #
	function initRemotelink3Values(){
		if($this->getValue("RL3_利用区分") == ""){
			$this->setValue("RL3_利用区分", 1);
		}
		if($this->getValue("RL3_リモートアクセスポート番号1") == ""){
			$this->setValue("RL3_リモートアクセスポート番号1", $this->getValue("RL3_リモートアクセスポート番号1初期値"));
		}
		if($this->getValue("RL3_リモートアクセスポート番号2") == ""){
			$this->setValue("RL3_リモートアクセスポート番号2", $this->getValue("RL3_リモートアクセスポート番号2初期値"));
		}
		
		if($this->getValue("RL3_UPNP機能利用") == ""){
			$this->setValue("RL3_UPNP機能利用", 1);
		}
		
		if($this->getValue("RL3_外部ポート区分") == ""){
			$this->setValue("RL3_外部ポート区分", 0);
		}
		if($this->getValue("RL3_外部ポート番号1") == ""){
			$this->setValue("RL3_外部ポート番号1", LD_EXTERNALACCESS_RL3_PORT1);
		}
		if($this->getValue("RL3_外部ポート番号2") == ""){
			$this->setValue("RL3_外部ポート番号2", LD_EXTERNALACCESS_RL3_PORT2);
		}
		
		if($this->getValue("RL3_リモートUI利用") == ""){
			$this->setValue("RL3_リモートUI利用", 1);
		}
		
		$this->setValue("RL3_PINコード変更", 0);
		
		if($this->getValue("RL3_利用区分") == 1){
			$this->setValue("RL3_PINコード", $this->getPincode("current"));
		}
		
		$this->unlinkValueSet("RL3_完了メッセージ",VALUE_SESSION);
		$this->setValue("RL3_完了メッセージ","");
	}
	function setTemporaryData(){
		$this->setValue("RL3_利用区分tmp",				$this->getValue("RL3_利用区分"));
	}
}
new remotelink3();
?>
