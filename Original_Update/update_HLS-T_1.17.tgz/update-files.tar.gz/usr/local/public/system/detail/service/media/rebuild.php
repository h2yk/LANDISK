<?php
include_once('../../../../../c4/c4c.class.php');

class media_rebuild extends C4CAction{
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
		
		$valueArray = array();
		$valueArray["lock_timeout"] = "";
		
		$lock = $this->writeMount();

		$this->logging("media:rebuild");
		$output = null;
 		exec("sudo ".LD_BIN_PATH."/twonky_user.sh reset >/dev/null 2>&1",$output,$result);
		$retry = 3;
		for($retry_count = 0; $retry_count <= $retry; $retry_count++) {
			$output = null;
			exec("sudo ".LD_BIN_PATH."/twonky_controller rebuild",$output,$result);
			if($result === 0) {
				break;
			}
			sleep(1);
		}
		if($result != 0){
			$this->logging("media:rebuild_error");
			$valueArray["message_complete"] = __("初期化に失敗しました。");
		} else {
			$this->logging("media:rebuild_end");
			$valueArray["message_complete"] = __("初期化しました。");
		}

		$this->readMount($lock);
		
		# 配列をエンコードして返却
		echo json_encode($valueArray);
	}
}
new media_rebuild();
?>
