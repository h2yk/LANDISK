<?php
include_once('../../../../../c4/c4c.class.php');
include_once(LD_CLASS_PATH.'/system/samba.class.php');
include_once(LD_CLASS_PATH.'/system/detail/share/share/share.class.php');

class share_input extends share_class{
	# 非同期処理 入力欄初期化
	function onLoad(){
		$this->standardOutFlag=true;
		
		# ログイン確認
		$this->checkLogon2();
		
		# ステートフル再開
		$this->restartStateFul();
		
		$this->value["proc_result"] = null;
		
		$this->loadConfig("share");
		$this->loadConfig("dropbox");
		
		# 非同期通信(POST)
		$this->setValue("共有フォルダ名",			$this->getFormData("name"));
		$this->setValue("共有フォルダコメント",		$this->getFormData("comment"));
		$this->setValue("サービス",					$this->getFormData("service"));
		$this->setValue("クラウドストレージ選択",	$this->getFormData("cloud"));
		#$this->setValue("Dropboxアカウント設定",	$this->getFormData("dbcheck"));
		$this->setValue("Dropboxアクセストークン削除",	$this->getFormData("dbrevokeflag"));
		$this->setValue("Dropboxコード",		$this->getFormData("dbcode"));
		$this->setValue("フレッツID",				$this->getFormData("fid"));
		$this->setValue("フレッツパスワード",		$this->getFormData("fpass"));
		$this->setValue("フレッツホスト",		$this->getFormData("fhost"));
		$this->setValue("同期先共有名",				$this->getFormData("rl3syncfname"));
		$this->setValue("PINコード",				$this->getFormData("rl3syncpincode"));
		$this->setValue("RL3ユーザ名",				$this->getFormData("rl3syncusername"));
		$this->setValue("RL3パスワード",			$this->getFormData("rl3syncpassword"));
		$this->setValue("ごみ箱機能",		$this->getFormData("trash"));
		$this->setValue("読み取り専用区分",		$this->getFormData("read_only"));
		$this->setValue("詳細アクセス権設定",		$this->getFormData("access_setting"));
		$this->setValue("全ユーザー欄",				$this->getFormData("remains_user_list"));
		$this->setValue("追加ユーザー欄",			$this->getFormData("add_user_list"));
		$this->setValue("読取追加ユーザー欄",		$this->getFormData("read_add_user_list"));
		$this->setValue("書込追加ユーザー欄",		$this->getFormData("write_add_user_list"));
		
		if($this->getValue("詳細アクセス権設定") != 'mix'){
			$this->setValue("全ユーザー欄",			"");
			$this->setValue("追加ユーザー欄",		"");
			$this->setValue("読取追加ユーザー欄",	"");
			$this->setValue("書込追加ユーザー欄",	"");
		}
		
		# 入力値加工
		$this->getInputData();
		
		# 入力値チェック
		if(!$this->getValue("共有フォルダID")){
			$this->checkInputData("add");
		}else{
			$this->checkInputData("edit");
		}
		
		# 最大共有フォルダ数チェック
		$this->checkShareCountValue();
		
		if(!$this->getMessage()){
			if($this->getValue("共有フォルダID")){
				# 編集
				$this->setValue("動作モード","edit");
			}else{
				# 新規追加
				$this->setValue("動作モード","add");
			}
			# 新規追加・編集
			$this->shareDataSetting();
		}
		
		# エラーメッセージ(1文字化)
		$message = "";
		if($this->getMessage()){
			foreach($this->getMessage() as $key => $value){
				$message .= $value;
			}
		}
		
		# 返却値配列化
		$valueArray = array();
		$valueArray["lock_timeout"] = "";
		$valueArray["message"] = $message;
		$valueArray["folder_fault_area"] = $this->getValue("共有フォルダ数超過フラグ");		# 警告
		
		# 配列をエンコードして返す
		echo json_encode($valueArray);
	}
}
new share_input();

?>
