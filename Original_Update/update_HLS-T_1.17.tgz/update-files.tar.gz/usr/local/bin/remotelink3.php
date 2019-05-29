#!/usr/bin/php
<?
$filePath = get_included_files();
chdir(preg_replace('/(.+)[\\\|\/].*$/i',"\\1",$filePath[0]));

include_once('../c4/c4c.class.php');
include_once(LD_CLASS_PATH.'/system/detail/service/remotelink3/remotelink3.class.php');

class action extends remotelink3_class{
	# ============================================================================== #
	# コンストラクタ定義
	# ============================================================================== #
	
	function action(){
		# 引数振り分け
		#$args = func_get_args();
		$args = func_get_arg(0);

		$this->mountStatus = "rw";
		$this->command = null;
		$this->enable = "1";

		$myname = array_shift($args);
		foreach($args as $arg) {
			$keyValue = explode("=",$arg);
			if (!isset($keyValue[1])) {
				exit(1);
			}
			$key = $keyValue[0];
			$value = $keyValue[1];
			if ($key === "mount") {
				$mountStatus = null;
				if ($value === "no" || $value === "ro" || $value === "rw"){
					$this->mountStatus = $value;
				}
			}
			if ($key === "command") {
				if ($value === "startup" || $value === "init" || $value === "first"){
					$this->command = $value;
				}
			}
			if ($key === "enable") {
				if ($value === "0" || $value === "1"){
					$this->enable = $value;
				}
			}
			if ($key === "type") {
				if ($value === "1") {
					$this->command = "startup";
				}
				if ($value === "2") {
					$this->command = "init";
				}
			}
		}
		if(!$this->mountStatus) {
			exit(1);
		}
		if(!$this->command) {
			exit(1);
		}
		
		C4EAction::C4EAction();
	}
	
	# ============================================================================== #
	# イベント定義
	# ============================================================================== #
	
	function onLoad(){
		$this->standardOutFlag = true;
		
		if($this->mountStatus != "no" && $this->mountStatus != "ro"){
			$lock = $this->writeMount(false);
		}
		
		# 設定値
		$output=array();
		exec("sudo /usr/local/bin/rl3_portgen.sh 1",$output,$result);
		if($result != 0){
			$this->endExit(1,$lock);
		}
		$port1 = $output[0];
		
		$output=array();
		exec("sudo /usr/local/bin/rl3_portgen.sh 2",$output,$result);
		if($result != 0){
			$this->endExit(1,$lock);
		}
		$port2 = $output[0];
		
		$this->loadConfig("remotelink3");

		if($this->command === "startup"){
			$this->setValue("RL3_利用区分","1");
			$this->setValue("RL3_PINコード変更","1");
		}else if($this->command === "init" || $this->command === "first"){
			$this->setValue("RL3_利用区分",$this->enable);
			$this->setValue("RL3_PINコード変更","");
		}else{
			$this->endExit(1,$lock);
		}
		$this->setValue("RL3_UPNP機能利用",		"1");
		$this->setValue("RL3_リモートアクセスポート番号1初期値",$port1);
		$this->setValue("RL3_リモートアクセスポート番号2初期値",$port2);
		$this->setValue("RL3_リモートアクセスポート番号1",$port1);
		$this->setValue("RL3_リモートアクセスポート番号2",$port2);
		$this->setValue("RL3_外部ポート区分",	"0");
		$this->setValue("RL3_外部ポート番号1",	LD_EXTERNALACCESS_RL3_PORT1);
		$this->setValue("RL3_外部ポート番号2",	LD_EXTERNALACCESS_RL3_PORT2);
		$this->setValue("RL3_リモートUI利用",	"1");
		$this->setValue("RL3_利用ユーザー",		"remote");
		
		# 利用ユーザーの存在確認
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
		
		$this->saveConfig("remotelink3");

		if ($this->command === "first") {
			$this->endExit(0,$lock);
		}
		
		# # # # # # # # # # # # # # # # # # # ## # # # # # 
		# RAPS有効化・無効化(シンボリックリンク)
		# # # # # # # # # # # # # # # # # # # ## # # # # # 
		
		exec("sudo rm -rf /etc/apache2/sites-enabled/001-rl3");
		if($this->command == "startup"){
			exec("sudo ln -s /etc/apache2/sites-available/rl3_raps_remoteui /etc/apache2/sites-enabled/001-rl3",$output,$result);
			if($result != 0){
				$this->endExit(1,$lock);
			}
		}else if($this->command === "init" && $this->enable === "1"){
			exec("sudo ln -s /etc/apache2/sites-available/rl3_only /etc/apache2/sites-enabled/001-rl3",$output,$result);
			if($result != 0){
				$this->endExit(1,$lock);
			}
		}
		
		exec("sudo /etc/init.d/apache2 reload",$output,$result);
		if($result != 0) {
			$this->logging("system:apache",__("システムエラーが発生しました。"));
			$this->endExit(2,$lock);
		}
		
		# # # # # # # # # # # # # # # #  # # # # #  # # # 
		# 多段越えライブラリ有効化(param.conf設定値変更)
		# # # # # # # # # # # # # # # #  # # # # #  # # # 
		
		$port1 = $this->getValue("RL3_リモートアクセスポート番号1");
		$port2 = $this->getValue("RL3_リモートアクセスポート番号2");
		$upnp = $this->getValue("RL3_UPNP機能利用");
		exec("sudo /usr/local/bin/rl3_setting.sh ".$port1." ".$port2." ".$upnp,$output,$result);
		if($result != 0){
			$this->endExit(1,$lock);
		}
		
		if($this->command === "startup"){
			exec("sudo /etc/init.d/raps.sh start",$output,$result);
			if($result != 0){
				$this->endExit(1,$lock);
			}
			exec("sudo /etc/init.d/rl3.sh start",$output,$result);
			if($result != 0){
				$this->endExit(1,$lock);
			}
		}else if($this->command === "init"){
			exec("sudo /etc/init.d/raps.sh stop",$output,$result);
			if($result != 0){
				$this->endExit(1,$lock);
			}
			exec("sudo rm -f /usr/local/rl3/bin/conf/param.conf.override");
			if($this->enable === "1") {
				exec("sudo /etc/init.d/rl3.sh start",$output,$result);
			}else{
				exec("sudo /etc/init.d/rl3.sh stop",$output,$result);
			}
			if($result != 0){
				$this->endExit(1,$lock);
			}
		}

		if($this->command == "startup"){
			$this->registPincode("regist",$this->getPincode("new"));
		}else if($this->command === "init"){
			$this->registPincode("regist_init");
		}else{
			$this->endExit(1,$lock);
		}
		$this->setValue("RL3_PINコード",$this->getPincode("current"));
		
		$this->setValue("BIN_RL3_PINコードエラー",	$this->getValue("RL3_PINコードエラー"));
		$this->setValue("BIN_RL3_PINコード",		$this->getValue("RL3_PINコード"));
		$this->saveConfig("remotelink3bin");

		$this->endExit(0,$lock);		
	}
	
	# ============================================================================== #
	# 内部処理定義
	# ============================================================================== #
	
	function endExit($result,$lock){
		if($this->mountStatus != "no" && $this->mountStatus != "ro"){
			$this->readMount($lock);
		}
		exit($result);
	}
}
new action($argv);
exit(0);

?>
