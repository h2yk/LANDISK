<?php
include_once('../../../../../c4/c4c.class.php');
include_once(LD_CLASS_PATH.'/system/detail/service/remotelink3/remotelink3.class.php');

class qrcode extends remotelink3_class{
	# ============================================================================== #
	# イベント定義
	# ============================================================================== #
	function onLoad(){
		# ログイン確認
		$this->checkLogon();
	}
	function onInit(){
	
		# 現在のPINコード取得
		$inputData = $this->getPincode("current");
		# 中間設定ファイル読み込み
		$this->loadConfig("remotelink3");
		# ユーザー存在チェック
		$iList = $this->getItemList("qruser");
		if(!isset($iList[$this->getValue("RL3_利用ユーザー")])){
			$this->setValue("RL3_利用ユーザー","");
		}
		
		$user = "";
		$pass = "";
		if($this->getValue("RL3_利用ユーザー") != ""){
			$user = "'".$this->getValue("RL3_利用ユーザー")."'";
			# ユーザのパスワードチェック
			exec("sudo cat /etc/shadow|grep -w '".$user."'",$str,$result);
			if($result == 0){
				$spass = explode(":",$str[0]);
				$salt  = explode("$",$spass[1]);
				$cpass = crypt("","$1$".$salt[2]."$");
				if($cpass == $spass[1]){
					$pass = " ''";
				}
			}
		}else{
			$user = "''";
		}
		
		$image = null;
		
		if ($inputData != false) {
			# QRコード用フォーマット生成
			ob_start();
			passthru("sudo /usr/local/bin/rl3_qrcodefmt.sh ".$inputData." ".$user.$pass , $return_var);
			$qrcodefmt = ob_get_contents();
			ob_end_clean();
			
			if($return_var != 0) {
				// フォーマット生成エラーの場合
				;
			} else {
				# QRコードを生成
				ob_start();
				passthru("sudo /usr/bin/qrencode -s 2 -o - '$qrcodefmt'", $return_var);
				$image = ob_get_contents();
				ob_end_clean();
				
				if($return_var != 0) {
					// コマンド実行エラーの場合
					;
				} else {
					if (strlen($image) > 0) {
						mb_http_output("pass");
						header("Content-type: image/png");
						header("Content-Disposition: inline; filename=qrcode.png");
					} else {
						$image = null;
					}
				}
				
			}
		}
		print $image;
		
		$this->standardOutFlag = true;
	}
}
new qrcode();
?>
