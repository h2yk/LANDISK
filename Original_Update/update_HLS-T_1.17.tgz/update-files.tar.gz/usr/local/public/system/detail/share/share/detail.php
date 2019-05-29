<?php
include_once('../../../../../c4/c4c.class.php');
include_once('../../../../../c4/utils.php');
include_once(LD_CLASS_PATH.'/system/samba.class.php');
include_once(LD_CLASS_PATH.'/system/detail/share/share/share.class.php');

class share_detail extends share_class{
	# 非同期処理 詳細情報取得
	function onLoad(){
		$this->standardOutFlag=true;
		
		# ログイン確認
		$this->checkLogon2();
		
		# アイテム
		$this->setItemName("読み取り専用区分","read_only");
		if(isSolidModel() == true){
			$this->setItemName("サービス名取得用","share_service_ms_only");
		}elseif(isWithoutTwonkyModel() == true){
			$this->setItemName("サービス名取得用","share_service_without_media");
		}else{
			$this->setItemName("サービス名取得用","share_service");
		}
		$this->itemList["クラウドストレージ_選択"] = array("0"=>__("Dropbox同期"),"1"=>__("フレッツ・あずけ～る同期"),"2"=>__("Remote Link Cloud Sync 対応機器と同期"));
		$this->setItemName("クラウドストレージ選択", "クラウドストレージ_選択");
		$this->itemList["フレッツホスト_選択"] = array("east"=>__("NTT東日本"),"west"=>__("NTT西日本"));
		$this->setItemName("フレッツホスト","フレッツホスト_選択");
		$this->setItemName("ごみ箱機能","share_trash");
		$this->itemList["詳細アクセス権設定_選択"] = array("mix"=>__("有効"),"all"=>__("無効"));
		$this->setItemName("詳細アクセス権設定","詳細アクセス権設定_選択");
		$this->setItemName("ユーザー名取得用","user");
		
		# 非同期通信(POST)
		$this->setValue("共有フォルダID",$this->getFormData("share_id"));
		$message = "";
		if(!$this->getRecord()){
			# 空配列をエンコードして返す
			echo json_encode(array());
		}else{
			# 返却値配列化
			$valueArray = array();
			$valueArray["lock_timeout"] = "";
			$valueArray["message"] = $message;
			$valueArray["disp_share_name"] = $this->getValue("共有フォルダ名");
			$valueArray["disp_share_comment"] = $this->getValue("共有フォルダコメント");
			
			if(($valueArray["disp_read_only"] = $this->getItem("読み取り専用区分")) === null){
				$valueArray["disp_read_only"] = "-";
			}

			$valueArray["disp_service"] = "";
			$valueArray["value_cloud"] = "";
			$valueArray["disp_cloud"] = "";
			$cloudFlag = "";
			if($this->getValue("サービス")){
				$this->setValue("サービス名取得用","");
				foreach($this->getValue("サービス") as $key){
					if($key == "7"){
						$cloudFlag = 1;
					}
					$this->setValue("サービス名取得用",$key);
					$valueArray["disp_service"] .= $this->getItem("サービス名取得用");
				}
				
				if($cloudFlag == 1){
					if(is_array($this->getValue("サービス"))){
						$valueArray["value_cloud"] = $this->getValue("クラウドストレージ選択");
						$valueArray["disp_cloud"] = $this->getItem("クラウドストレージ選択");
						if($this->getValue("クラウドストレージ選択") == "1"){
							mb_regex_encoding(C4_INNER_CHARSET);
							$valueArray["disp_flets_host"] = $this->getItem("フレッツホスト");
							$valueArray["disp_flets_id"] = mb_ereg_replace('[\x20-\x7F]|[０-９Ａ-Ｚａ-ｚ]','\0<wbr>',$this->getValue("フレッツID"));
						}else if($this->getValue("クラウドストレージ選択") == "2"){
							$valueArray["disp_rl3syncfname"] = $this->getValue("同期先共有名");
							$valueArray["disp_rl3syncpincode"] = $this->getValue("PINコード");
							$valueArray["disp_rl3syncusername"] = $this->getValue("RL3ユーザ名");
						}
					}
				}
			}
			
			$valueArray["disp_trash"] = $this->getItem("ごみ箱機能");

			$this->setShareAccessDetail();
			
			$valueArray["value_access"] = "";
			$valueArray["disp_access"] = $this->getItem("詳細アクセス権設定");
			$valueArray["disp_read_user"] = "";
			$valueArray["disp_write_user"] = "";
			if($this->getValue("詳細アクセス権設定") == "mix"){
				$valueArray["value_access"] = "mix";
				
				if($this->getValue("読み取りユーザー")){
					$this->setValue("ユーザー名取得用","");
					foreach($this->getValue("読み取りユーザー") as $key){
						if($key){
							$this->setValue("ユーザー名取得用",$key);
							$valueArray["disp_read_user"] .= $this->getItem("ユーザー名取得用")."<br>";
						}
					}
				}
				
				if($this->getValue("書き込みユーザー")){
					$this->setValue("ユーザー名取得用","");
					foreach($this->getValue("書き込みユーザー") as $key){
						if($key){
							$this->setValue("ユーザー名取得用",$key);
							$valueArray["disp_write_user"] .= $this->getItem("ユーザー名取得用")."<br>";
						}
					}
				}
			}
			
# 			$valueArray["detail_link"] = "<a href=\"#\" data-toggle=\"modal\" data-target=\"#myModal4\" onClick=\"onDetail('".$this->getValue("共有フォルダID")."')\">".$this->getValue("共有フォルダ名")."</a>";
			$valueArray["edit_link"] = "<a href=\"#\" data-toggle=\"modal\" data-target=\"#SHARE_myModal1\" onClick=\"SHARE_onInitialization('".$this->getValue("共有フォルダID")."')\"><img src=\"".C4_HOME_URL."/img/common/z1.gif\" width=\"24\" height=\"40\" alt=\"編集\"/></a>";
			$valueArray["delete_link"] = "<a href=\"#\" data-toggle=\"modal\" data-target=\"#SHARE_myModal2\" onClick=\"SHARE_onDeleteConfirm('".$this->getValue("共有フォルダID")."')\"><img src=\"".C4_HOME_URL."/img/common/z2.gif\" width=\"24\" height=\"40\" alt=\"削除\"/></a>";
			
			# 配列をエンコードして返す
			echo json_encode($valueArray);
		}
	}
}
new share_detail();

?>
