<?php
include_once('../../../../../c4/c4c.class.php');
include_once(LD_CLASS_PATH.'/system/detail/service/remotelink3/remotelink3.class.php');

class remotelink3_setting extends remotelink3_class{
	# ============================================================================== #
	# イベント定義
	# ============================================================================== #
	function onLoad(){
		$this->standardOutFlag=true;
		
		# ログイン確認
		$this->checkLogon2();
		
		# 非同期通信(POST)
		$this->setValue("ステートフルID",$this->getFormData("StateFulID"));
		
		# ステートフル開始
		$this->startStateFul($this->getValue("ステートフルID"));
		
		# 非同期通信(POST)
		$this->setValue("RL3_設定区分",$this->getFormData("val"));
		
		$valueArray = array();
		
		if($this->getValue("RL3_設定区分") === $this->getValue("RL3_利用開始ボタン") && $this->getPincode("current") === $this->getPincode("init")){
			exec("sudo ".LD_PHP_COMMAND." ".LD_BIN_PATH."/remotelink3.php command=startup",$output,$result);
			if($result != 0){
				$this->setValue("RL3_登録エラー",__("Remote Link 3の設定に失敗しました。"),VALUE_MESSAGE);
			}
			$this->loadConfig("remotelink3");
			$this->loadConfig("remotelink3bin");
			
			$this->setValue("RL3_完了メッセージ",	$this->getValue("BIN_RL3_完了メッセージ"));
			$this->setValue("RL3_PINコードエラー",	$this->getValue("BIN_RL3_PINコードエラー"),VALUE_MESSAGE);
			$this->setValue("RL3_PINコード",	$this->getValue("BIN_RL3_PINコード"));
		}else if($this->getValue("RL3_設定区分") === $this->getValue("RL3_設定するボタン")){
			# 設定値変更の場合
			if($this->checkInputData()){
				$lock = $this->writeMount();
				
				# # # # # # # # # # # # # # # # # # # ## # # # # # 
				# RAPS有効化・無効化(シンボリックリンク)
				# # # # # # # # # # # # # # # # # # # ## # # # # # 
				
				exec("sudo rm -rf /etc/apache2/sites-enabled/001-rl3");
				
				if($this->getValue("RL3_利用区分") == 1){
					if($this->getValue("RL3_リモートUI利用") == 1){
						# リモートUIを「使う」
						exec("sudo ln -s /etc/apache2/sites-available/rl3_raps_remoteui /etc/apache2/sites-enabled/001-rl3");
					}else{
						# リモートUIを「使わない」
						exec("sudo ln -s /etc/apache2/sites-available/rl3_raps /etc/apache2/sites-enabled/001-rl3");
					}
				}
				
				exec("sudo /etc/init.d/apache2 reload");
				
				# # # # # # # # # # # # # # # #  # # # # #  # # # 
				# 多段越えライブラリ有効化(param.conf設定値変更)
				# # # # # # # # # # # # # # # #  # # # # #  # # # 
				
				if($this->getValue("RL3_利用区分") == 1){
					$port1 = $this->getValue("RL3_リモートアクセスポート番号1");
					$port2 = $this->getValue("RL3_リモートアクセスポート番号2");
					$upnp = $this->getValue("RL3_UPNP機能利用");
					if($this->getValue("RL3_外部ポート区分") == 1){
						$extport1 = $this->getValue("RL3_外部ポート番号1");
						$extport2 = $this->getValue("RL3_外部ポート番号2");
						exec("sudo /usr/local/bin/rl3_setting.sh ".$port1." ".$port2." ".$upnp." ".$extport1." ".$extport2,$output,$result);
					}else{
						exec("sudo /usr/local/bin/rl3_setting.sh ".$port1." ".$port2." ".$upnp,$output,$result);
					}
					exec("sudo /etc/init.d/raps.sh start",$output,$result);
					exec("sudo /etc/init.d/rl3.sh start",$output,$result);
				}else{
					exec("sudo /etc/init.d/raps.sh stop",$output,$result);
					exec("sudo /etc/init.d/rl3.sh stop",$output,$result);
				}
				
				# 利用ユーザーの存在確認
				if($this->getValue("RL3_利用ユーザー") != ""){
					$userCheck = "NG";
					$this->loadConfig("user");
					if(isset($this->value["ユーザ情報"])){
						foreach($this->value["ユーザ情報"] as $Uid => $value){
							if($this->value["ユーザ情報"][$Uid]["name"] == $this->getValue("RL3_利用ユーザー")){
								$userCheck = "OK";
							}
						}
					}
					if($userCheck == "NG"){
						$this->setValue("RL3_利用ユーザー","");
					}
				}
				
				$this->saveConfig("remotelink3");
				
				$this->readMount($lock);
				
				if($this->getValue("RL3_利用区分") == 1){
					if($this->getValue("RL3_PINコード変更") == 1){
						# 新しいPINコード生成、取得及び、登録
						$this->registPincode("regist", $this->getPincode("new"));
						$this->setValue("RL3_PINコード",$this->getPincode("current"));
					}else{
						# 現在のPINコード取得
						$this->setValue("RL3_PINコード",$this->getPincode("current"));
					}
				}

				$this->logging("remotelink3:edit");
				
				$valueArray["status"] = "complete";
			}else{
				# 入力エラー
				$valueArray["status"] = "input";
			}
		}else{
			# 登録処理以外
			$valueArray["status"] = "input";
			echo json_encode($valueArray);
			return;
		}
		
		# 返却値配列化
		$message = "";
		if($this->getMessage()){
			foreach($this->getMessage() as $key => $value){
				$message .= $value;
			}
		}
		$valueArray["message"] = $message;
		
		if ($this->getValue("RL3_完了メッセージ") != "") {
			$valueArray["rl3_message_complete"] = $this->getValue("RL3_完了メッセージ");
		}
		
		$valueArray["lock_timeout"] = "";
		
		# 配列をエンコードして返却
		echo json_encode($valueArray);
	}
}
new remotelink3_setting();
?>
