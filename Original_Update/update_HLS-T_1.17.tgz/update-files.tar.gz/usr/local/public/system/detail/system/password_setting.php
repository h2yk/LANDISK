<?php
include_once('../../../../c4/c4c.class.php');
include_once('../../../../c4/utils.php');
include_once(LD_CLASS_PATH.'/system/detail/share/user/user.class.php');

class password_setting extends user_class{
	# 非同期処理 登録処理
	function onLoad(){
		$this->standardOutFlag=true;
		
		# ログイン確認
		$this->checkLogon2();
		
		# 初期値
		$this->setValue("現パスワード",		"");
		$this->setValue("新パスワード",		"");
		$this->setValue("確認パスワード",	"");
		
		# 非同期通信(POST)
		$this->setValue("ステートフルID",$this->getFormData("StateFulID"));
		if(!$this->getValue("ステートフルID")){
			$this->setValue("ERROR:STID",__("エラーが発生しました。"),VALUE_MESSAGE);
		}
		
		# ステートフル開始
		$this->startStateFul($this->getValue("ステートフルID"));
		
		# 非同期通信(POST)
		$this->setValue("現パスワード",		$this->getFormData("now_password"));
		$this->setValue("新パスワード",		$this->getFormData("new_password1"));
		$this->setValue("確認パスワード",	$this->getFormData("new_password2"));
		
		# 入力チェック
		if($this->checkInputData()){
			$lock = $this->writeMount();
			$this->changePassword("admin",$this->getValue("新パスワード"));
			$this->logging("password:edit");
			$this->readMount($lock);
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
		
		# 配列をエンコードして返す
		echo json_encode($valueArray);
	}
	
	# ============================================================================== #
	# 内部処理定義
	# ============================================================================== #
	
	function checkInputData(){
		if(!$this->checkPassword("admin",$this->getValue("現パスワード"))){
			$this->setValue("不正パスワード",__("「現在のパスワード」が正しくありません。<BR>"),VALUE_MESSAGE);
			return false;
		}else{
			$this->checkValue("name","現パスワード",null,20,false,__("現在のパスワード"));
			if(isSolidModel()){
				$this->checkValue("name","新パスワード",null,20,false,__("新しいパスワード"));
			}else{
				$this->checkValue("name","新パスワード",1,20,true,__("新しいパスワード"));
			}
			
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
	}
}
new password_setting();

?>
