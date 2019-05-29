#!/usr/bin/php
<?
$filePath = get_included_files();
chdir(preg_replace('/(.+)[\\\|\/].*$/i',"\\1",$filePath[0]));

include_once('../c4/c4c.class.php');

class action extends C4CAction{
	# ============================================================================== #
	# イベント定義
	# ============================================================================== #
	
	function onLoad(){
		$lock = $this->writeMount(false);
		
		$this->logging("reset:start");
		
		# 管理パスワード初期化
		exec(LD_PHP_COMMAND." password.php mount=no log=no name=admin pass=");
		
		# DHCP有効化
		exec(LD_PHP_COMMAND." network.php mount=no log=no dhcp_mode=1");
		
		# RL3初期化
		$enable = "1";
		exec(LD_BIN_PATH."/issolidmodel.sh",$output,$result);
		if($result == 0) {
			$enable = "0";
		}
		exec(LD_PHP_COMMAND." remotelink3.php command=init enable=".$enable." mount=no");

		# メディアサーバ初期化
		exec(LD_BIN_PATH."/twonky_user.sh reset >/dev/null 2>&1");
		$retry = 3;
		for($retry_count = 0; $retry_count <= $retry; $retry_count++) {
			$output = null;
			exec(LD_BIN_PATH."/twonky_controller rebuild",$output,$result);
			if($result === 0) {
				break;
			}
			sleep(1);
		}

		# フォトインポート用データベース初期化
		exec(LD_BIN_PATH."/isphotomodel.sh",$output,$result);
		if($result == 0) {
			exec("/usr/local/bin/photo_import.py --event delete");
		}
		
		$this->readMount($lock);
		
		$this->standardOutFlag = true;
	}
}
new action();
exit(0);

?>
