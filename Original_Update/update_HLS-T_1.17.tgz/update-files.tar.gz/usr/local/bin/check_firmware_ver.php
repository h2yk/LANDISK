#!/usr/bin/php
<?

$filePath = get_included_files();
chdir(preg_replace('/(.+)[\\\|\/].*$/i',"\\1",$filePath[0]));

define("C4_SESSION_DEFAULT_ENABLED",false);

include_once('../c4/c4c.class.php');

class action extends C4CAction{
	# ============================================================================== #
	# コンストラクタ定義
	# ============================================================================== #

	function action($argv){

		$seconds = 1;
		$this->__cron = false;

		if(isset($argv[1])){
			$seconds = $argv[1];
		}

		if(isset($argv[2])){
			if($argv[2] == "cron"){
				$this->__cron = true;
			}
		}

		sleep($seconds);

		C4EAction::C4EAction();
	}

	# ============================================================================== #
	# イベント定義
	# ============================================================================== #

	function onLoad(){
		$do_update = false;

		$this->loadConfig("firmware");
		$this->getLanDiskInfo();
		$this->setValue("国内販売判定",$this->checkSalesArea());
		# クーロンに追加
		if($this->getValue("国内販売判定") && $this->getValue("ファーム確認_時刻指定") == "1"){
			$lock = $this->writeMount();
			$now_ver = $this->value["LANDISK製品情報"]["version"];

			# ここで、ファームウェアshを実行する
			# 実行結果のファームウェアバージョンは$new_verに格納する事
			exec(LD_BIN_PATH."/getnewfwver.sh ",$output,$result);
			$new_ver = '';
			if($result == 0) {
				$new_ver = $output[0];
			}

			if($new_ver!='' && ($now_ver < $new_ver)){
				$this->setValue("最新ファームウェアバージョン",$new_ver);
				$this->saveConfig("firmware");
				if($this->getValue("ファームウェア通知機能") == "1") {
					$this->ledcont("notify");
				} else if ($this->getValue("ファームウェア通知機能") == "0") {
					$this->ledcont("clear_notify");
				}
				if($this->getValue("ファームウェア定期自動アップデート機能") == "1") {
					# 後述のshutdown.php内でもwriteMountしているのでここではフラグセットのみする
					$do_update = true;
				}
			}
			$this->readMount($lock);
		}
		$this->standardOutFlag = true;

		if($do_update == true && $this->__cron == true) {
			exec(LD_PHP_COMMAND." ".LD_BIN_PATH."/shutdown.php 3",$output,$result);
		} else {
			# アップデートしない場合、スピンアップついでにサムネイルの生成を行う
			exec(LD_BIN_PATH."/raps_update_thumbs.sh",$output,$result);
		}
	}

}

new action($argv);

exit(0);

?>


