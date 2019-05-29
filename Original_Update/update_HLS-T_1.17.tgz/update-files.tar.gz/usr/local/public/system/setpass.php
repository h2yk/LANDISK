<?php
include_once('../../c4/c4c.class.php');
include_once(LD_CLASS_PATH.'/system/detail/share/user/user.class.php');

class setpass extends user_class{
	# ============================================================================== #
	# イベント定義
	# ============================================================================== #

	function onLoad(){
		$this->setItemName("言語選択","language");

		# 初期値セット
		$this->setLangValue();

		# 2013/06/01 ファームチェック
		$this->check_firm_notice();
	}

	function onInit(){
		$this->loadConfig("hdd");
		if($this->isLdRaidType()){
			$this->checkRaidError();
		}else{
			if($this->getValue("HDDステータス") == "1"){
				$this->setValue("RAIDエラー",__("データパーティションにアクセスできません。<br>フォーマットが必要です。<br>"),VALUE_MESSAGE);
			}
		}

		$this->setInputName("言語選択","lang");
		$this->getFormValue("言語選択");

		if($this->getValue("言語選択") == ""){
			# 初期値セット
			$this->setLangValue();
		}
		$this->execView("setpass/setpass");
	}
	function lock_onClick(){
		$this->setValue("エラーメッセージ",__("現在、別のユーザーがログオン中です。"),VALUE_MESSAGE);
		$this->execView("setpass/setpass");
	}
	function setpass_onClick(){
		if(!$this->checkProcLock()){
			$lock = $this->writeMount();

			if(file_exists(LD_LOGON_CHECK_FILE)){
				$para = array();
				$para = explode("\t",file_get_contents(LD_LOGON_CHECK_FILE));
				if(isset($para[0])) $ip = $para[0];
				if(isset($para[1])) $url = $para[1];
				if(isset($para[2])) $queryString = $para[2];
				if(isset($para[3])) $actionEvent = $para[3];
				if(isset($para[4])) $sessionId = $para[4];
				if(isset($para[5])) $stateFulID = $para[5];
			}else{
				$ip = '';
				$url = '';
				$queryString = '';
				$actionEvent = '';
				$sessionId = '';
				$stateFulID = '';
			}


			if($ip){
				if($ip != $_SERVER["REMOTE_ADDR"] && filemtime(LD_LOGON_CHECK_FILE) > (time() - (LD_TIMEOUT))){
					if(EX_MODE){
						$this->setValue("ERROR",__("現在、別のユーザーがログオン中です。"),VALUE_MESSAGE);
					}
				}
			}

			$this->setValue("新パスワード",		$this->getFormData("new_password1"));
			$this->setValue("確認パスワード",	$this->getFormData("new_password2"));

			if($this->checkInputData()){
				$this->changePassword("admin",$this->getValue("新パスワード"));
				$this->logging("password:edit");
			}

			if($this->getMessage()){
				$this->logging("auth:error",$_SERVER["REMOTE_ADDR"]);
				$this->execView("setpass/setpass");
			}else{
				# ブラウザ判定用COOKIEをセット
				$this->setAgentCookie();

				# ログオンファイル生成
				$this->makeLogonFile();

				$this->logging("auth:logon",$_SERVER["REMOTE_ADDR"]);

				$this->redirect("./detail/main.php");
			}

			$this->readMount($lock);
		}
		else{
			$this->setValue("ERROR",__("設定処理中です。"),VALUE_MESSAGE);
			$this->execView("setpass/setpass");
		}
	}
	function logoff_onClick(){
		$this->redirect("./logoff.php");
	}

	function sel_lang_onClick(){
		$this->setInputName("言語選択","lang");
		$this->getFormValue("言語選択");

		# 言語設定
		$info = SET_LANGUAGE($this->getValue("言語選択"));
		$lang = $this->loadLangConf();

		$this->setValue("timezone",	$lang["timezone"]);
		$this->setValue("lang",		$info["lang"]);
		$this->setValue("client",	$lang["client"]);
		$lock = $this->writeMount();

		$this->makeConfig("/mnt/hda5/etc/lang");

		# 画像のリンク
		$this->setImageLink($info["lang"]);

		$this->readMount($lock);

		$this->redirect("./setpass.php","",array("言語選択"));
	}
	# ============================================================================== #
	# 内部処理定義
	# ============================================================================== #
	function setAgentCookie(){
		$agent = $_SERVER['HTTP_USER_AGENT'];
		if(!preg_match("/(playstation|nintendo\swii)/i",$agent)){
			setcookie(LD_USER_AGENT_COOKIE,$agent);
		}
	}
	function checkRaidError(){
		$this->setDefineLdHddDevice();
		foreach($this->LD_HDD_DEVICE as $hdd => $dev){
			# デグレード(HDD未使用状態)
			if($this->value["HDD情報"][$hdd]["状態"] == "degrade"){
				$degrade[] = $hdd;
			}
			# 崩壊状態
			else if($this->value["HDD情報"][$hdd]["状態"] == "crash"){
				$crash = $hdd;
				break;
			}
		}

		# エラー1台時
		if(count($degrade) == 1){
			$this->setItemName("HDD名","hdd");
			$this->setValue("HDD名",$degrade[0]);
			$this->setValue("RAIDエラー",str_replace("/* degrade */",$this->getItem("HDD名"),__("/* degrade */に異常が発生しています。<br>データをバックアップしてディスクを交換してください。")));
		}
		# エラー2台時
		else if(count($degrade) == 2){
			$this->setValue("RAIDエラー",__("HDD1,2でエラーが発生しています。<br>画面で見るマニュアルを参照してください。"));
		}
		# RAID崩壊時
		else if($crash != ""){
			$this->setValue("RAIDエラー",__("RAID 構成が崩壊しています。<br>画面で見るマニュアルを参照してください。"));
		}
		# マウントエラー時
		if($this->getValue("RAIDエラー") == "" && $this->getValue("HDDステータス") == "1"){
			$this->setValue("RAIDエラー",__("データパーティションにアクセスできません。<br>フォーマットが必要です。"));
		}
	}

	# ファーム通知 2013/06/01
	function check_firm_notice(){
		$this->loadConfig("firmware");
		$this->getLanDiskInfo();

		# クーロンに追加
		if($this->checkSalesArea()){
			$now_ver = $this->value["LANDISK製品情報"]["version"];

			$new_ver = $this->getNewFirmwareVersion();

			if($new_ver!='' && ($now_ver < $new_ver)){
				$this->setValue("最新ファームウェアバージョン",$new_ver);
				$this->saveConfig("firmware");
				if($this->getValue("ファームウェア通知機能") == "1") {
					$this->ledcont("notify");
				} else if ($this->getValue("ファームウェア通知機能") == "0") {
					$this->ledcont("clear_notify");
				}
			}
		}
	}

	function getNewFirmwareVersion(){

		$ver = null;

		# 最新バージョン取得
		exec("sudo sh ".LD_BIN_PATH."/getnewfwver.sh",$output,$result);
		if($result == 0){
			$ver = $output[0];
		}

		return $ver;
	}

	function checkInputData(){
		$this->checkValue("name","新パスワード",1,20,true,__("新しいパスワード"));
		
		if($this->getMessage()){
			return false;
		}
		if($this->getValue("新パスワード") !== $this->getValue("確認パスワード")){
			$this->setValue("パスワード不一致",__("「新しいパスワード」と「新しいパスワード（確認）」が一致しません。<BR>"),VALUE_MESSAGE);
			return false;
		}
		if(!$this->getMessage()){
			return true;
		}
	}

        function checkPassword($user,$pass){
                exec("sudo cat /etc/shadow|grep -w '".$user."'",$str,$result);
                if($result != 0) return false;

                $spass = explode(":",$str[0]);
                $salt  = explode("$",$spass[1]);
                $cpass = crypt($pass,"$1$".$salt[2]."$");


                if($cpass != $spass[1]){
                        return false;
                }
                return true;
        }


	# ============================================================================== #
	# ビュー定義
	# ============================================================================== #
	function html_setpass(){
		$this->setValueDefine("フォーム開始","sid",true);

		$this->setInputName("フォーム開始",		"form");
		$this->setTagDefine("フォーム開始",		"form");
		$this->setTagDefine("フォーム終了",		"/form");

		$this->setInputName("デフォルトイベント",	"default_event");
		$this->setTagDefine("デフォルトイベント",	"hidden");
		$this->setValue("デフォルトイベント",		"setpass");


		$this->setTagDefine("新パスワード",		"password",	array("maxlength"=>"20","option"=>"class=\"input1\" id=\"new_password1\""));
		$this->setTagDefine("確認パスワード",		"password",	array("maxlength"=>"20","option"=>"class=\"input1\" id=\"new_password2\""));

		$this->setInputName("新パスワード",		"new_password1");
		$this->setInputName("確認パスワード",		"new_password2");


		$this->setTagDefine("ログオンリンク開始",	"link",		array("href"=>"javascript:onSubmit('setpass')","option"=>"class=\"btn btn-default btn-saku\""));
		$this->setTagDefine("リンク終了",		"/link");

	}

}

new setpass();

?>
