<?
include_once('c4e.class.php');
include_once(CHROOT_PATH."/usr/share/php/php-gettext/gettext.inc");

class C4CAction extends C4EAction{

	# ============================================================================== #
	# 機器情報の取得
	# ============================================================================== #
	function getLanDiskInfo(){
		exec("sudo cat ".LD_MODEL_FILE,$result,$status);
		$info = array();
		foreach($result as $key => $str){
			$tmp = array();	
		#	if($key == "submodel"){
		#		$info["dubmodel"] = "";
		#	} else {
				$tmp = explode("\t",$str);
				if(!empty($tmp[1])){
					list($title,$value) = $tmp;
					$info[$title] = $value;
				}
		#	}
		}
		$this->value["LANDISK製品情報"] = $info;

	}
	function checkSalesArea(){
		# 国内販売製品か否か(Wだったら海外)
		$this->getLanDiskInfo();
		if(isset($this->value["LANDISK製品情報"]["submodel"])){
			if($this->value["LANDISK製品情報"]["submodel"] == "W") return false;
		}
		return true;
	}
	function isLdRaidType(){
		$this->getLanDiskInfo();
	# 	if($this->value["LANDISK製品情報"]["pcb"]==LD_RAID_TYPE_NAME) return true;
		return false;
	}
	function isLdSingleType(){
		$this->getLanDiskInfo();
		if($this->value["LANDISK製品情報"]["pcb"]==LD_SINGLE_TYPE_NAME) return true;
		return false;
	}
	function isTVBrowser(){
		$agent = $_SERVER['HTTP_USER_AGENT'];
		if (preg_match("/InettvBrowser/",$agent) or preg_match("/PLAYSTATION/i", $agent)) {
			return true;
		}
		return false;
	}
	# ============================================================================== #
	# sysfs対応
	# ============================================================================== #
	# ■HDDデバイス
	# c4_define("LD_HDD_DEVICE",			"/dev/sda:/dev/sdb");
	# ■HDAデータパーティション
	# c4_define('LD_HDA_DATA_PARTITION',	'/dev/sda6');
	# ■HDBデータパーティション
	# c4_define('LD_HDB_DATA_PARTITION',	'/dev/sdb6');
	# 代用
	function setDefineLdHddDevice(){
		exec("sudo ls /sys/block|grep 'sd[a-z]'|sort",$device,$error);
		$hdd1 = "";
		$hdd2 = "";
		$this->LD_HDA_DATA_PARTITION = "";
		$this->LD_HDB_DATA_PARTITION = "";
		$this->LD_HDD_DEVICE = array();
		
		if(is_array($device)){
			foreach($device as $sd){
				if($sd){
					$linkArray = array();
					exec("sudo readlink /sys/block/".$sd."/device",$linkArray,$error);
					if($error == '0'){
						$tmp = array();
						foreach($linkArray as $link){
							$tmp = explode("/",$link);
							$hdd = $tmp[4]."/".$tmp[5]."/".$tmp[6];
							
							if(LD_HDD1_DEVICE == $hdd){
								$hdd1 = "/dev/".$sd;
								$this->LD_HDA_DATA_PARTITION = $hdd1."6";
							}
						}
					}
				}
			}
		}
		
		$this->LD_HDD_DEVICE["hdd1"] = $hdd1;
	}
	
	# ============================================================================== #
	# 各種設定処理
	# ============================================================================== #
	# 中間設定ファイル読み込み
	function loadConfig($middleConfName){
		$confValue = array();
		if(file_exists(LD_MIDDLE_CONF_SETTING_DIR."/".$middleConfName)){
			$confValue = unserialize(file_get_contents(LD_MIDDLE_CONF_SETTING_DIR."/".$middleConfName));
			if(is_array($confValue)){
				foreach($confValue as $key=>$value){
					$this->setValue($key,$confValue[$key]);
				}
			}
		}
	}
	# 中間設定ファイル保存
	function saveConfig($middleConfName){
		$confValue  = array();
		$valueNames = GET_MIDDLE_CONF_COLUMN($middleConfName);
		if(is_array($valueNames)){
			foreach($valueNames as $name){
				if(isset($this->value[$name])) $confValue[$name] = $this->value[$name];
			}
			$fp = fopen(LD_MIDDLE_CONF_SETTING_DIR."/".$middleConfName,"w");
			fwrite($fp,serialize($confValue));
			fclose($fp);
		}
	}
	# 中間設定ファイル初期化
	function restoreConfig($middleConfName){
		copy(LD_MIDDLE_CONF_DEFAULT_DIR."/".$middleConfName,LD_MIDDLE_CONF_SETTING_DIR."/".$middleConfName);
	}
	# 本設定ファイル反映
	function makeConfig($confName,$perm=644){
		# 設定ファイルをtmpへ出力
		$confPath = GET_CONF_FILE_PATH($confName);
		$this->outputCharset = LD_CONF_CHARSET;
		$this->execViewFile("/conf".$confName,LD_CONF_PATH.$confPath);
		$this->outputCharset = C4_OUTPUT_CHARSET;
		# 実設定パスへ上書き
		exec("sudo mv -f ".LD_CONF_PATH.$confPath." ".CHROOT_PATH.$confPath.";");
		exec("sudo chmod $perm ".CHROOT_PATH.$confPath.";");
		exec("sudo chown root:root ".CHROOT_PATH.$confPath.";");
	}
	# サービス再起動
	function restartConfig(){
		$this->loadConfig("hdd");
		$serviceList = func_get_args();
		$share_service = array(
					"samba"		=>0,
					"share_start"	=>1,
					"nasdsync"	=>1);
		$clash = false;
		if($this->getValue("HDDステータス") == "1"){
			$clash = true;
		}
		
		$this->setDefineLdHddDevice();
		foreach($this->LD_HDD_DEVICE as $hdd => $dev){
			if(isset($this->value["HDD情報"][$hdd]["状態"])){
				if($this->value["HDD情報"][$hdd]["状態"] == "crash"){
					$clash = true;
					break;
				}
			}
		}
		if(is_array($serviceList)){
			foreach($serviceList as $service){
				if(isset($share_service[$service])){
					if($clash && $share_service[$service]){
						continue;
					}
				}else if($clash){
					continue;
				}
				if($command = GET_SERVICE_COMMAND($service)){
					if(!DEBUG_MODE){
						exec($command);
					}
				}
			}
		}
	}
	# オンラインマニュアルURL
	function get_landisk_support_url(){
		$lang = $this->getLangLanguage();
		exec("/usr/local/bin/getsupporturl.sh ".$lang,$output,$result);
		if ($result === 0) {
			return $output[0];
		}
		return "#";
	}
	
	function get_website_url(){
		return ($this->checkSalesArea() == true) ? LD_JAPAN_URL : LD_FOREIGN_URL;
	}
	# ============================================================================== #
	# 乱数取得
	# ============================================================================== #
	function getUrandStr($length){
		$handle = fopen("/dev/urandom","rb");
		$rand_pool = fread($handle,4094);
		fclose($handle);
		
		$nchars = ord('~') - ord('!') + 1;
		$charmap_array = array();
		for($i=ord('0'); $i<=ord('9'); $i++){
			$charmap_array[] = chr( $i );
		}
		for($i=ord('A'); $i<=ord('Z'); $i++){
			$charmap_array[] = chr( $i );
		}
		for($i=ord('a'); $i<=ord('z'); $i++){
			$charmap_array[] = chr( $i );
		}
		$charmap_array[] = '.';
		$charmap_array[] = '/';
		
		$random_string = '';
		for($pointa=0,$counta=0; $counta<$length;){
			$n = (ord($rand_pool[$pointa]) % $nchars) + ord('!');
			if(array_search(chr($n),$charmap_array) !== false){
				$random_string .= chr($n);
				$counta++;
			}
			if($counta >= $length){
				return $random_string;
			}
			$pointa++;
		}
	}
	function getUniqid(){
		return md5(uniqid(rand(), true));
	}
	# 2012/07/09 \usr\local\public\system\detail\service\bittorrent\bt_ninshou.phpから移動
	function makeRand(){
		$charList = "ABCDEF0123456789";
		$rand = "";
		for($i = 0; $i < 8; $i++) {
			$rand .= $charList{mt_rand(0, strlen($charList) - 1)};
		}
		return $rand;
	}
	# ============================================================================== #
	# 製品情報取得
	# ============================================================================== #
	
	# MACアドレス返却
	function getMacAddress($format=""){
		$this->loadConfig("first");
		exec("sudo /sbin/ifconfig|grep 'HWaddr'",$str,$result);
		if(($this->getValue("初回設定区分") == 0) && ($result != "0")){
			return false ;
		}
		if($format == "colon") return preg_replace("/.*(\w{2}):(\w{2}):(\w{2}):(\w{2}):(\w{2}):(\w{2}).*/i","\\1:\\2:\\3:\\4:\\5:\\6",$str[0]);
		return preg_replace("/.*(\w{2}):(\w{2}):(\w{2}):(\w{2}):(\w{2}):(\w{2}).*/i","\\1\\2\\3\\4\\5\\6",$str[0]);
	}
	# LANDISK名取得
	function getHostName(){
		exec("hostname --short",$hostName,$error);
		if($error) return null;
		return $hostName[0];
	}
	# DNSサーバ名取得
	function getDnsServer(){
		$dns = "";
		exec("sudo cat /etc/resolv.conf|grep 'nameserver'",$resolv);
		if($resolv[0]){
			if(preg_match('/^nameserver\s+(\S+)/',$resolv[0])){
				$dns = preg_replace('/^nameserver\s+(\S+)/','\1',$resolv[0]);
			}
		}
		return $dns;
	}
	# ファームウェアバージョン取得
	function getFirmVersion(){
		$this->getLanDiskInfo();
		return $this->value["LANDISK製品情報"]["version"];
	}
	# ローカルIPアドレス取得
	function getIpAddress(){
		$ipAddr = "";
		exec("sudo /sbin/ifconfig | grep 'inet addr:' | grep -v '127\.0\.0\.1'",$ifconfig,$error);
		if($error == "0"){
			$ipAddr = preg_replace('/^.*addr:([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}).*$/','\1',$ifconfig[0]);
		}
		return $ipAddr;
	}
	
	# グローバルIPアドレス取得(RL3)
	function getGlobalIpAddressRl3ver(){
		$ipAddr = "";
		# ルータに問合せ
		if($this->getValue("RL3_UPNP機能利用") == 1){
			exec(LD_BIN_PATH."/upnp_getextip.pl",$status,$error);
			if($error == "0" && $status[0] != "error0"){
				if(preg_match("/^([^\t]*)\t\((\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\)$/",$status[0],$match)){
					$model = $match[1];
					$ipAddr = $match[2];
				}
			}
		}
		if($ipAddr == "0.0.0.0"){
			$ipAddr = "";
		}
		
		return $ipAddr;
	}
	# ============================================================================== #
	# リモートリンク関連
	# ============================================================================== #
	# ポート設定(RL3)
	function settingPortRl3ver($type="notice",$open_list=array(),$close_list=array()){
		$this->loadConfig("remotelink3");
		$this->setValue("RL3_オープンエラーポート",null);
		$this->saveConfig("remotelink3");
		
		# UPNP一覧取得
		
		if($this->getValue("RL3_UPNP機能利用") == 1){
# 			$upnp_list = $this->getUpnpListRl3ver();
		}
		
		# ポートクローズ
		if($type == "close" || ($type == "open" && count($close_list) > 0)){
# 			$this->controlPortRl3ver("close",$close_list,$upnp_list);
		}
		
		$this->saveConfig("remotelink3");
	}
	# ポート設定エラーメッセージ取得(RL3)
	function getSettingPortRl3verMessage(){
		$this->loadConfig("remotelink3");
		$message = "";
# 		if($this->getValue("RL3_ポート通知フラグ") == 1){
			if($this->getValue("RL3_オープンエラーポート") != ""){
				$message = __("UPnP機能によるルーターの設定に失敗しました。ルーターの設定を手動で行ってください。(ポート番号：").$this->getValue("RL3_オープンエラーポート").__(")");
			}
# 		}else{
# 			$message = __("iobbとの通信に失敗しました。詳細はログを確認して下さい。");
# 		}
		return $message;
	}
	# ポートオープン
	function openPort($wanip,$port,$export=""){
		$macaddress = substr($this->getMacAddress(),-6);
		$port = str_pad($port,5,"0",STR_PAD_LEFT);
		$name = "HLS-".$macaddress."-".$port;
		$protocol = LD_UPNP_PROTOCOL;
		$lanip = $this->getIpAddress();
		if($export == "")$export = $port;
		
		if(!DEBUG_MODE){
			$port = ltrim($port,"0");
			$export = ltrim($export,"0");
			# exec(LD_BIN_PATH."/upnp_tool.pl add $name $protocol $wanip $port $lanip $port",$result,$error);
			exec(LD_BIN_PATH."/upnp_tool.pl add $name $protocol $wanip $export $lanip $port",$result,$error);
			
			if($error == '0'){
				return $result[0];
			}
		}else{
			return "OK";
		}
	}
	# ポートクローズ
	function closePort($wanip,$port){
		$protocol = LD_UPNP_PROTOCOL;
		# if($export == "")$export = $port;
		
		if(!DEBUG_MODE){
			$port = ltrim($port,"0");
			exec(LD_BIN_PATH."/upnp_tool.pl delete $protocol $wanip $port",$result,$error);
			# exec(LD_BIN_PATH."/upnp_tool.pl delete $protocol $wanip $export",$result,$error);
			
			if($error == '0'){
				return $result[0];
			}
		}else{
			return "OK";
		}
	}
	# ポートオープン、クローズ(RL3)
	function controlPortRl3ver($type,$port_list=array(),$upnp_list=false){
		$this->loadConfig("remotelink3");
		$error = array();
		if($this->getValue("RL3_UPNP機能利用") == 1 || ($this->getValue("RL3_UPNP機能利用") != $this->getValue("RL3_UPNP機能利用tmp"))){
# 			$wanip = $this->getGlobalIpAddressRl3ver();
			$lanip = $this->getIpAddress();
			
			if(count($port_list) < 1){
				$port_list[] = $this->getValue("RL3_リモートアクセスポート番号1");
				$port_list[] = $this->getValue("RL3_リモートアクセスポート番号2");
				# $this->debug(serialize($port_list));
			}
			
			# UPNP一覧取得
			# まだ取得していない場合は取得する
			if(!$upnp_list){
# 				$upnp_list = $this->getUpnpListRl3ver($wanip);
			}
			$macaddress = substr($this->getMacAddress(),-6);
			
			# 現在開いているポートのリストと、自分が開いたポートのリストを取得
			$ownopenlist = array();
			$nowopenlist = array();
			if(is_array($upnp_list) && count($upnp_list) > 0){
				foreach($upnp_list as $upnp_name=>$value){
					$loc1 = $upnp_list[$upnp_name]["NewInternalPort"];
					$exp1 = $upnp_list[$upnp_name]["NewExternalPort"];
					array_push($nowopenlist,$loc1);
					if($loc1 != $exp1) array_push($nowopenlist,$exp1);
					
					if(preg_match("/HLS\-$macaddress\-(\d{2,5})/",$upnp_name,$matches)){
						$portnum = (int)$matches[1];
						$ownopenlist[$portnum] = $upnp_list[$upnp_name]["NewInternalClient"];
						if($loc1 != $exp1){
							$ownopenlist[$exp1] = $upnp_list[$upnp_name]["NewInternalClient"];
						}
					}
				}
			}
			if(is_array($port_list)){
				# LANDISKが開けたポート
				foreach($ownopenlist as $pnum=>$client){
# 					$res = $this->closePort($wanip,$pnum);
					if(($type == "close") && ($res != "OK")){
						$error[$pnum] = $res;
					}
				}
				$res = "";
				foreach($port_list as $port){
					if($type == "open"){
						# 現在開いているポート
# 						if(in_array($port,$nowopenlist)){
# 							# LANDISKが開けたポート
# 							if(array_key_exists($port,$ownopenlist)){
# 								# IPアドレスがポートオープン時と異なる
# 								if($ownopenlist[$port] != $lanip){
# 									$this->closePort($wanip,$port,$this->getExternalPort($port));
# 								}
# 							}
# 						}
						if($this->getValue("RL3_UPNP機能利用") == 1){
# 							$res = $this->openPort($wanip,$port,$this->getExternalPortRl3ver($port));
						}
					}else if($type == "close"){
						# 現在開いているポート
# 						if(in_array($port,$nowopenlist)){
# 							# LANDISKが開けたポート
# 							if(array_key_exists($port,$ownopenlist)){
# 								$res = $this->closePort($wanip,$port,$this->getExternalPort($port));
# 							}
# 						}
					}
					if($res != "OK"){
						$error[$port] = $res;
					}
				}
			}
		}
		return $error;
	}
# 	# 内部ポートと対になる外部ポートを取得(RL2)
# 	function getExternalPort($locport){
# 		$this->loadConfig("ddns");
# 		$export = "";
# 		if($this->getValue("DDNS_外部ポート区分") == 1){
# 			if($locport == $this->getValue("DDNS_リモートアクセスポート番号1")){
# 				$export = $this->getValue("DDNS_外部ポート番号1");
# 			}
# 			if($locport == $this->getValue("DDNS_リモートアクセスポート番号2")){
# 				$export = $this->getValue("DDNS_外部ポート番号2");
# 			}
# 		}
# 		return $export;
# 	}
	# 内部ポートと対になる外部ポートを取得(RL3)
	function getExternalPortRl3ver($locport){
		$this->loadConfig("remotelink3");
		$export = "";
		if($this->getValue("RL3_外部ポート区分") == 1){
			if($locport == $this->getValue("RL3_リモートアクセスポート番号1")){
				$export = $this->getValue("RL3_外部ポート番号1");
			}
			if($locport == $this->getValue("RL3_リモートアクセスポート番号2")){
				$export = $this->getValue("RL3_外部ポート番号2");
			}
		}
		return $export;
	}
	# 暗号化したMACアドレス取得
	function iobb_ENCRYPTMAC_e(){
		exec("sudo /usr/local/bin/iobb_ENCRYPTMAC /e",$status,$error);
		if($error == "0"){
			if($status['0']){
				$encodeMacAddress = explode("\t",$status['0']);
				
				return $encodeMacAddress['2'];
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
	# 暗号化したMACアドレスを複合化
	function iobb_ENCRYPTMAC_d($encodeMacAddress){
		if($encodeMacAddress){
			$command = "sudo /usr/local/bin/iobb_ENCRYPTMAC /d ".$encodeMacAddress;
			exec($command,$status,$error);
			if($error == "0"){
				if($status['0']){
					$decodeMacAddress = explode("\t",$status['0']);
					
					return $decodeMacAddress['2'];
				}else{
					return false;
				}
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
	# 暗号化した物と複合化した物が同じか？
	function check_ENCRYPTMAC($encodeMacAddress,$decodeMacAddress){
		if(!$encodeMacAddress OR !$decodeMacAddress){
			return false;
		}
		
		if($decodeMacAddress == $this->iobb_ENCRYPTMAC_d($encodeMacAddress)){
			return true;
		}else{
			return false;
		}
	}
	# 製品再起動
	function reboot($background=false,$sleep=null){
		$this->logging("system:end");
		$command = LD_PHP_COMMAND." ".LD_BIN_PATH."/shutdown.php 1";
		if(is_numeric($sleep)){
			$command .= " $sleep";
		}
		if($background == true){
			$command .=" > /dev/null &";
		}
		exec($command);
	}
	
	# UPNP一覧取得(RL3)
	function getUpnpListRl3ver($wanip=""){
		if($wanip == ""){
# 			$wanip = $this->getGlobalIpAddressRl3ver();
		}
		exec(LD_BIN_PATH."/upnp_tool.pl list $wanip",$result,$error);
		$upnp_list = true;
		if($error == '0'){
			if(is_array($result)){
				array_shift($result);
				if(count($result) > 0){
					$upnp_list = array();
					foreach($result as $param){
						if(preg_match("/\[[0-9]+\]\s*\:\s([^\s]+)/",$param,$matches)){
							$description = $matches[1];
						}else{
							list($key,$value) = explode("=",$param);
							$key = trim($key);
							$value = trim($value);
							$upnp_list[$description][$key] = $value;
						}
					}
				}
			}
		}
		return $upnp_list;
	}
	
	function getWaitSec(){
		# MACアドレス下3ケタ取得
		$str = substr($this->getMacAddress(),9,3);
		return hexdec($str) % 500 +90;
	}
	# ============================================================================== #
	# DNSエラー回避
	# ============================================================================== #
	function resolvHostname($hostname){
		if (!preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $hostname)){
			exec("host $hostname",$result,$status);
			if($status == 0){
				foreach($result as $i => $value){
					if(preg_match("/".preg_quote($hostname)." has address (\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/",$value,$matches)){
						$ip = $matches[1];
						break;
					}
				}
			}
		} else {
			# 既にIPのようなのでそのまま返す
			$ip = $hostname;
		}
		return $ip;
	}
	# ============================================================================== #
	# スピンダウン処理
	# ============================================================================== #
	function spindownSetting(){
		$this->loadConfig("spindown");
# 		$this->loadConfig("usb");
		
		$this->getLanDiskInfo();
		$this->setDefineLdHddDevice();
		
		# HDD1(sata)
		$count = "1";
		$this->setValue("HDD1sata名","");
		if($this->getValue("省電力モード") == 1){
			$this->setValue("HDD1sata名","sata".$count);
		}
		
# 		# HDD2(sata)
		$this->setValue("HDD2sata名","");
# 		if(($this->getValue("省電力モード") == 1) && $this->isLdRaidType()){
# 			$count++;
# 			$this->setValue("HDD2sata名","sata".$count);
# 		}
		
		
		# HDD1SD名 HDD1SG名
		$hdd1name = str_replace("/dev/","",$this->LD_HDD_DEVICE["hdd1"]);
		$this->setValue("HDD1SD名","/dev/".$hdd1name);
		$linkArray = array();
		exec("sudo readlink /sys/block/".$hdd1name."/device",$linkArray,$error);
		$link = str_replace("../../","/sys/",$linkArray[0]);
		$command = "ls ".$link." | grep scsi_generic:sg* | grep -o sg[0-9]";
		$result_sg = array();
		exec($command,$result_sg,$error);
		$this->setValue("HDD1SG名","/dev/".$result_sg['0']);
		
# 		# HDD2SD名 HDD2SG名
# 		if($this->LD_HDD_DEVICE["hdd2"]){
# 			$hdd2name = str_replace("/dev/","",$this->LD_HDD_DEVICE["hdd2"]);
# 			$this->setValue("HDD2SD名","/dev/".$hdd2name);
# 			$linkArray = array();
# 			exec("sudo readlink /sys/block/".$hdd2name."/device",$linkArray,$error);
# 			$link = str_replace("../../","/sys/",$linkArray[0]);
# 			$command = "ls ".$link." | grep scsi_generic:sg* | grep -o sg[0-9]";
# 			$result_sg = array();
# 			exec($command,$result_sg,$error);
# 			$this->setValue("HDD2SG名","/dev/".$result_sg['0']);
# 		}
		
# 		# USB1SD名 USB1SG名
# 		$this->setValue("USB1SD名","/dev/".$this->value['接続機器管理情報1']['service']['usb1']['mnt']['sd']);
# 		$this->setValue("USB1SG名","/dev/".$this->value['接続機器管理情報1']['service']['usb1']['mnt']['sg']);
		
# 		# USB2SD名 USB2SG名
# 		$this->setValue("USB2SD名","/dev/".$this->value['接続機器管理情報2']['service']['usb2']['mnt']['sd']);
# 		$this->setValue("USB2SG名","/dev/".$this->value['接続機器管理情報2']['service']['usb2']['mnt']['sg']);
		
		# 省電力モード切替時間
		$this->setValue("省電力モード切替時間_秒",$this->getValue("省電力モード切替時間"));
		$this->setItemName("省電力モード切替時間_秒","spindown_second");
		
		# セット
		$this->makeConfig("/tmp/spindownd_conf");
		# シグナル送信
		if(!DEBUG_MODE){
			exec("sudo killall -HUP usbhdmng");
		}
	}
	# ============================================================================== #
	# ファームウェア関連
	# ============================================================================== #
	function checkvar($crypt_file){
		if(!DEBUG_MODE){
			# UPLoadファイルパス
			$this->getLanDiskInfo();
			$model = $this->value["LANDISK製品情報"]["product"];
			exec("sudo ".CHROOT_PATH."/usr/local/bin/getfwver.sh ".$crypt_file." ".$model,$output_fwver,$return_fwver);
			if($return_fwver != "0"){
				return false;
			}
			else{
				if($output_fwver[0] <= $this->value["LANDISK製品情報"]["version"]) return false;
			}
		}
		return $output_fwver[0];
		#return true;
	}
	# ============================================================================== #
	# ロギング処理
	# ============================================================================== #
	function logging($ident,$message=null){
		include_once(C4_PROJECT_PATH."/inc/landisk_log.inc.php");
		
		$log_string = date("YmdHis")."\t".GET_LOG_STRING($ident,$message)."\n";
		
		# NarSuS対応 2013/05/09
		if($this->isEnableNarSuS()){
			include_once(C4_PROJECT_PATH.'/inc/narsus_event.inc.php');
			if($narsus_event) $this->narsus_event = $narsus_event;
			$this->sendNarsusEventLog($ident);
		}
		
		$this->eventLogWrite("view",$log_string);
# 		$this->eventLogWrite("mail",$log_string);

	}
	function isEnableNarSuS(){
#		$this->loadConfig("issnotify");
#		if($this->isSupportedNarsus() && $this->getValue("narsus_利用区分")==1){
#			return true;
#		}
		return false;
	}
	function isSupportedNarsus(){
#		exec("sudo ".LD_BIN_PATH."/issupportednarsus.sh",$output,$result);
#		if ($result === 0){
#			return true;
#		}
		return false;
	}
	function sendNarsusEventLog($ident){
		if(!$this->narsus_event)
			return false;
		if(!array_key_exists($ident,$this->narsus_event))
			return false;
		$event = $this->narsus_event[$ident];
		$command = 'sudo /mnt/hda5/bin/issnotify --event';
		$command.= ' event@category='.$event['category'];
		$command.= ' event@type='.$event['type'];
		$command.= ' code='.$event['code'];
		//print $command."\n";
		exec($command,$output,$result);
		if($result != 0)
			return false;
		return true;
	}

	function Lock($name){
		$lock = fopen("/var/c4/lock/".$name,"r+");
		flock($lock,LOCK_EX);
		return $lock;
	}
	function Unlock($lock){
		flock($lock,LOCK_UN);
		fclose($lock);
	}

	function eventLogWrite($mode,$log_string){
		$lock = $this->Lock("eventLogWrite");
# 		if($mode == "view"){
			$file = LD_EVENT_VIEW_LOG_FILE;
# 		}else if($mode == "mail"){
# 			$file = LD_EVENT_MAIL_LOG_FILE;
# 		}
		$logs = "";
		$size = "";
		if(file_exists($file)){
			$logs = file($file);
			$size = filesize($file);
		}else{
			touch($file);
			chmod($file,0666);
			if(!DEBUG_MODE){
				chown($file,LD_APACHE_USER);
				chgrp($file,LD_APACHE_GROUP);
			}
		}
		
		if($size >= LD_EVENT_LOG_MAX_FILE_SIZE){
# 			if($mode == "mail"){
# 				$this->loadConfig("mail");
# 				if($this->getValue("メール通知区分")){
# 					$this->loadConfig("mail_event");
# 					$this->getValue("ログ通知メールアドレス1") ? $to_address[] = $this->getValue("ログ通知メールアドレス1") : "";
# 					$this->getValue("ログ通知メールアドレス2") ? $to_address[] = $this->getValue("ログ通知メールアドレス2") : "";
# 					$this->getValue("ログ通知メールアドレス3") ? $to_address[] = $this->getValue("ログ通知メールアドレス3") : "";
# 				}
# 				if(is_array($to_address)){
# 					$this->logs = $logs;
# 					$cnt = 1;
# 					foreach($to_address as $to){
# 						if($this->sendMail($to,"/mail/log")){
# 							$result[$cnt] = "success";
# 						}else{
# 							$result[$cnt] = "failure";
# 						}
# 						$cnt++;
# 					}
# 					
# 					# 一度ログを削除(永久ループ回避のため)
# 					unlink($file);
# 					reset($to_address);
# 					$cnt = 1;
# 					
# 					foreach($to_address as $to){
# 						$this->logging("log_mail:".$result[$cnt]."",$to);
# 						$cnt++;
# 					}
# 					# ファイルを削除する場合はここで終了
# 					return;
# 				}
# 			}
			array_pop($logs);
		}
		
		if(is_array($logs)){
			array_unshift($logs,$log_string);
		}else{
			$logs[] = $log_string;
		}
		
		$fp = fopen($file,"w");
		flock($fp,LOCK_EX);
		foreach($logs as $log){
			fwrite($fp,$log);
		}
		flock($fp,LOCK_UN);
		fclose($fp);
		$this->Unlock($lock);
	}
# 	function eventMailLogListLoop(){
# 		if(!current($this->logs)){
# 			reset($this->logs);
# 			return false;
# 		}
# 		
# 		$key = key($this->logs);
# 		
# 		list($timestamp,$message) = explode("\t",$this->logs[$key]);
# 		
# 		$month = (int)substr($timestamp,4,2);
# 		$day = (int)substr($timestamp,6,2);
# 		$date = $month."月".$day."日";
# 		
# 		$hour = (int)substr($timestamp,8,2);
# 		$min = substr($timestamp,10,2);
# 		$sec = substr($timestamp,12,2);
# 		$time = $hour.":".$min.":".$sec;
# 		
# 		$this->setValue("日付",		$date);
# 		$this->setValue("時間",		$time);
# 		$this->setValue("メッセージ",	$message);
# 		
# 		next($this->logs);
# 		return true;
# 	}
	function debug($log_string){
		if(!file_exists(LD_DEBUG_LOG_FILE)){
			touch(LD_DEBUG_LOG_FILE);
			chmod(LD_DEBUG_LOG_FILE,0666);
		}
		$fp = fopen(LD_DEBUG_LOG_FILE,"r+");
		flock($fp,LOCK_EX);
		
		$log_string = date("Y-m-d H:i:s")."\t".$log_string."\n";
		clearstatcache();
		$logs = file(LD_DEBUG_LOG_FILE);
		$size = filesize(LD_DEBUG_LOG_FILE);
		
		if($size >= LD_DEBUG_LOG_MAX_FILE_SIZE){
			array_pop($logs);
		}
		
		if(is_array($logs)){
			array_unshift($logs,$log_string);
		}
		else{
			$logs[] = $log_string;
		}
		
		ftruncate($fp,0);
		rewind($fp);
		foreach($logs as $log){
			fwrite($fp,$log);
		}
		flock($fp,LOCK_UN);
		fclose($fp);
	}
	# ============================================================================== #
	# ログオン処理
	# ============================================================================== #
	# ロック除外イベント取得
	function getDenyLockEvent(){
		return array(
				"done_onClick",
				"close_onClick",
				"ok_onClick",
				"ng_onClick",
				"error_onClick",
				"format_error_onClick");
	}
	# ログオンチェック
	function checkLogon($checkProcLock=true){
		if(!$this->isTVBrowser()){
			if(file_exists(LD_LOGON_CHECK_FILE)){
				if(filemtime(LD_LOGON_CHECK_FILE) > (time() - (LD_TIMEOUT))){
					if(EX_MODE){
						list($ip) = explode("\t",file_get_contents(LD_LOGON_CHECK_FILE),2);
						if($ip == $_SERVER["REMOTE_ADDR"]){
							$this->touchLogonFile2();
						}
						else{
							$this->redirect(C4_HOME_URL."/system/logon.php","lock");
							$this->errorDie("");
						}
					}else{
						$this->touchLogonFile2();
					}
				}
				else{
					$this->redirect(C4_HOME_URL."/system/logon.php","timeout");
					$this->errorDie("");
				}
			}
			else{
				$this->redirect(C4_HOME_URL."/system/logon.php","timeout");
				$this->errorDie("");
			}
		}
		# 処理ロック確認
		# 追加：完了イベントを除外する
# 		if(!in_array(trim($this->actionEvent),$this->getDenyLockEvent())){
# 			if($checkProcLock){
				if($this->checkProcLock()){
					$current_path = preg_replace('/^(\/.*?\/.*?)\/.*$/','\1',C4_CURRENT_PATH);
					if($current_path != "/system"){

						# 下記execViewは存在しないtemplateを参照しており問題。
						# 修正の影響を最小限にするためここでlogon.phpへredirectし
						# ログオン画面にて"設定処理中です"と表示されるよう形で修正。
						#$this->execView(preg_replace('/^(\/.*?\/.*?)\/.*$/','\1',C4_CURRENT_PATH)."/lock");
						$this->redirect(C4_HOME_URL."/system/logon.php","logon");

						$this->errorDie("");
					}
				}
# 			}
# 		}
	}
	# ログオンチェック2
	function checkLogon2(){
		$valueArray = array();
		if(!$this->isTVBrowser()){
			if(file_exists(LD_LOGON_CHECK_FILE)){
				if(filemtime(LD_LOGON_CHECK_FILE) > (time() - (LD_TIMEOUT))){
					if(EX_MODE){
						list($ip) = explode("\t",file_get_contents(LD_LOGON_CHECK_FILE),2);
						if($ip == $_SERVER["REMOTE_ADDR"]){
							$this->touchLogonFile2();
						}
						else{
							$valueArray = array();
							$valueArray["lock_timeout"] = C4_HOME_URL."/system/logon.php?submit_lock=1";
							
							$this->errorDie(json_encode($valueArray));
						}
					}else{
						$this->touchLogonFile2();
					}
				}
				else{
					$valueArray = array();
					$valueArray["lock_timeout"] = C4_HOME_URL."/system/logon.php?submit_timeout=1";
					
					$this->errorDie(json_encode($valueArray));
				}
			}
			else{
				$valueArray = array();
				$valueArray["lock_timeout"] = C4_HOME_URL."/system/logon.php?submit_timeout=1";
				
				$this->errorDie(json_encode($valueArray));
			}
		}
		# 処理ロック確認
		# 追加：完了イベントを除外する
		if($this->checkProcLock()){
			$current_path = preg_replace('/^(\/.*?\/.*?)\/.*$/','\1',C4_CURRENT_PATH);
			if($current_path != "/system"){
				$valueArray = array();
				
				$valueArray["lock_timeout"] = C4_HOME_URL."/system/detail/close.php?submit_processing=1";
				
				$this->errorDie(json_encode($valueArray));
			}
		}
	}
	# ログオンファイル生成
	function makeLogonFile(){
		$fp = fopen(LD_LOGON_CHECK_FILE,"w");
		fwrite($fp,$_SERVER["REMOTE_ADDR"]);
		fclose($fp);
	}
	# ログオンファイル削除
	function deleteLogonFile(){
		if(file_exists(LD_LOGON_CHECK_FILE)){
			unlink(LD_LOGON_CHECK_FILE);
		}
	}
	# ログオンファイル更新
	function touchLogonFile(){
		$deny_list = array(
					"/system/photoalbum/image.php" => true,
# 					"/system/detail/service/bittorrent/manager.php" => true,
					"/system/narsus/qrcode.php" => true
				);
		
		if(!isset($deny_list[C4_CURRENT_PATH."/".C4_SCRIPT_NAME.".php"])){
			if($this->actionEvent != "onInit"){
				$actionEvent = preg_replace('/(.*)_onClick$/','\1',$this->actionEvent);
			}else{
				$actionEvent = "";
			}
			$session = &$this->getSession();
			if(is_object($session)){
				$sessionId = $session->getSessionId();
			}else{
				$sessionId = "";
			}
			$logon_txt = array($_SERVER["REMOTE_ADDR"],$_SERVER["PHP_SELF"],$_SERVER["QUERY_STRING"],$actionEvent,$sessionId,$this->stateFulID);
			$fp = fopen(LD_LOGON_CHECK_FILE,"w");
			fwrite($fp,implode("\t",$logon_txt));
			fclose($fp);
		}
	}
	# ログオンファイル更新2
	function touchLogonFile2(){
# 		$deny_list = array(
# 					"/system/photoalbum/image.php" => true,
# # 					"/system/detail/service/bittorrent/manager.php" => true,
# 					"/system/narsus/qrcode.php" => true
# 				);
# 		
# 		if(!isset($deny_list[C4_CURRENT_PATH."/".C4_SCRIPT_NAME.".php"])){
# 			if($this->actionEvent != "onInit"){
# 				$actionEvent = preg_replace('/(.*)_onClick$/','\1',$this->actionEvent);
# 			}
			$session = &$this->getSession();
			if(is_object($session)){
				$sessionId = $session->getSessionId();
			}
			$logon_txt = array($_SERVER["REMOTE_ADDR"],"/system/detail/main.php","","",$sessionId,$this->stateFulID);
			$fp = fopen(LD_LOGON_CHECK_FILE,"w");
			fwrite($fp,implode("\t",$logon_txt));
			fclose($fp);
# 		}
	}
	# ============================================================================== #
	# 処理中ロック確認処理
	# ============================================================================== #
	function checkProcLock(){
		if(file_exists(LD_LOCK_PATH)){
			if(filemtime(LD_LOCK_PATH) > (time() - (LD_TIMEOUT))){
				return true;
			}
			else{
				rmdir(LD_LOCK_PATH);
			}
		}

		return false;
	}
	# ============================================================================== #
	# LED/ブザー処理
	# ============================================================================== #
	# LED処理
	function ledcont($command){
		$error = "0";
		
		if(!DEBUG_MODE){
			if($command == "ok"){
				# 正常
				exec("sudo ".LD_BIN_PATH."/ledcont clear_progress");
			}
			else if($command == "progress"){
				# システム処理中
				exec("sudo ".LD_BIN_PATH."/ledcont progress");
			}
			else if($command == "serious_err"){
				# 重大エラー発生
				exec("sudo ".LD_BIN_PATH."/ledcont err");
			}
			else if($command == "serious_ok"){
				# 重大エラー解除
				exec("sudo ".LD_BIN_PATH."/ledcont clear_err");
			}
			else if($command == "err"){
				# 軽微エラー発生
				exec("sudo ".LD_BIN_PATH."/ledcont err");
			}
			else if($command == "notify"){
				# 現FWよりも新しいFWを検出
				exec("sudo ".LD_BIN_PATH."/ledcont notify");
			}
			else if ($command == "clear_notify") {
				# FWお知らせLED通知を行わない
				exec("sudo ".LD_BIN_PATH."/ledcont clear_notify");
			}
		}
		return $error;
	}
	# ブザー処理
	function buzcont($command){

	}
	# ============================================================================== #
	# マウント処理
	# ============================================================================== #
	function writeMount($ui = true){
		#$lock = $this->Lock("writeMount");
		$lock = null;
		$cnt = 0;
		while(true){
			if(@mkdir(LD_LOCK_PATH)){
				break;
			}
			if($ui) {
				$valueArray = array();
				$valueArray["lock_timeout"] = C4_HOME_URL."/system/detail/close.php?submit_processing=1";
				$this->errorDie(json_encode($valueArray));
			}
			if($cnt >= 30){
				break;
			}
			$cnt++;
			sleep(3);
		}
		
		exec("sudo mount -o remount,rw / >&/dev/null");
		#$this->ledcont("progress");
		return $lock;
	}
	function readMount($lock = null){
		if(is_dir(LD_LOCK_PATH)){
			rmdir(LD_LOCK_PATH);
		}
		
		exec("sudo mount -o remount,ro,noatime / >&/dev/null");
		#$this->ledcont("ok");
		#$this->Unlock($lock);
	}
# 	# ============================================================================== #
# 	# メール送信処理
# 	# ============================================================================== #
# 	# このメソッドをカスタマイズする時は
# 	# \usr\local\public\system\login\mail.phpのオーバーライドを考慮すること
# 	function sendMail($to,$template_name){
# 		$this->setItemName("文字コード区分","char_code");
# 		$this->loadConfig("mail");
# 		$this->loadConfig("network");
# 		if($this->getValue("メール通知区分")){
# 			$this->setValue("文字コード",$this->getItem("文字コード区分"));
# 			$returnPath = $this->getValue('差出人メールアドレス');
# 			$from =  "LANDISK <".$this->getValue('差出人メールアドレス').">";
# 			$ip =  $this->resolvHostname($this->getValue("SMTPサーバ"));
# #			$sendStr = "socket:host=".$this->getValue("SMTPサーバ")." port=25";
# 			if($this->getValue("SMTP認証") != "none"){
# 				$sendStr = "socket:host=".$ip." port=".$this->getValue("ポート番号")." auth=true"." authtype=".$this->getValue("SMTP認証")." username=".$this->getValue("ユーザー名")." password=".$this->getValue("パスワード");
# 			}else{
# 				$sendStr = "socket:host=".$ip." port=".$this->getValue("ポート番号");
# 			}
# 			
# 			$this->setValue("メッセージID","<".date("YmdHis")."@".md5($this->getMacAddress()).".".$this->getValue("SMTPサーバ").">");
# 			$this->setValue("送信日時",date("d M Y H:i:s")." +0900");
# 			$success = $this->sendViewMessage($template_name,$to,$from,null,array(),$returnPath,null,$sendStr);
# 		}
# 		return $success;
# 	}
# 	function sendErrorMail($ident,$message=null){
# 		include_once(C4_PROJECT_PATH."/inc/landisk_log.inc.php");
# 		$this->loadConfig("mail");
# 		if($this->getValue("メール通知区分")){
# 			$this->setValue("本文",GET_ERROR_STRING($ident,$message));
# 			$this->loadConfig("mail_event");
# 			$this->getValue("エラー通知メールアドレス1") ? $to_address[] = $this->getValue("エラー通知メールアドレス1") : "";
# 			$this->getValue("エラー通知メールアドレス2") ? $to_address[] = $this->getValue("エラー通知メールアドレス2") : "";
# 			$this->getValue("エラー通知メールアドレス3") ? $to_address[] = $this->getValue("エラー通知メールアドレス3") : "";
# 			if(is_array($to_address)){
# 				foreach($to_address as $to){
# 					if($this->sendMail($to,"/mail/error")){
# 						$this->logging("error_mail:success",$to);
# 					}else{
# 						$this->logging("error_mail:failure",$to);
# 					}
# 				}
# 			}
# 		}
# 	}
	# ============================================================================== #
	# Macアドレス依存パラメータ取得処理
	# ============================================================================== #
	function getMacAddrDependParam(){
		$result=array(0,0);
		exec("ifconfig |grep HWaddr",$op);
		if(is_array($op)){
			$macAddr = str_replace(":","",preg_replace("/.*HWaddr /","",$op[0]));
			$base = hexdec(substr($macAddr, -3));
			$result=array(($base % 3) + 2,$base % 60);
		}
		return $result;
	}

	# ============================================================================== #
	# cron設定処理
	# ============================================================================== #
	function makeCrontab(){
		$this->loadConfig("time");
# 		$this->loadConfig("ddns");
# 		$this->loadConfig("backup");
# 		$this->loadConfig("netbackup");
# 		$this->loadConfig("activerepair");
# 		$this->loadConfig("itunes");
		$this->loadConfig("firmware");
		if($this->getValue("タイムサーバ同期区分") == 1){
			if(is_array($this->getValue("同期タイミング")) and in_array(2,$this->getValue("同期タイミング"))){
				$this->setValue("定期同期フラグ",true);
			}
		}
		$this->getLanDiskInfo();
		$this->setValue("国内販売判定",$this->checkSalesArea());
		# クーロンに追加
# 		$this->setValueDefine("バックアップ_スケジュール曜日指定","colSep",",");
# 		$this->setValueDefine("ネットバックアップ_スケジュール曜日指定","colSep",",");
# 		$this->setValueDefine("アクティブリペア_スケジュール曜日指定","colSep",",");
# 		$this->setValueDefine("iTunes更新_スケジュール曜日指定","colSep",",");
		$this->makeConfig("/etc/crontab");
	}
	# ============================================================================== #
	# アクティブリペア制御処理
	# ============================================================================== #
# 	function stopActiverepair(){
# 		$error == 0;
# 		if(!DEBUG_MODE){
# 			$fd = fopen("/tmp/repair_cancel","w+");
# 			fclose($fd);
# 			exec("sudo chmod 777 /tmp/repair_cancel",$op,$error);
# 		}
# 		
# 		if($error == 0){
# 			while(file_exists(LD_ACTIVEREPAIR_LOCK_PATH)){
# 				sleep(1);
# 			}
# 			return true;
# 		}
# 		return false;
# 	}
	
	# ============================================================================== #
	# HDD状態取得
	# ============================================================================== #
	function setHddStatus(){
		$this->loadConfig("hdd");
		$this->setDefineLdHddDevice();
		foreach($this->LD_HDD_DEVICE as $hdd => $dev){
			if(!DEBUG_MODE){
				$this->hdd[$hdd]["status"] = "0";
				
				if($dev){
					# 接続のチェック
					$output = array();
					exec("sudo fdisk -l $dev 2> /dev/null;",$output,$return_var);
					if(count($output) != 0){
						# 容量チェック
						if(isset($this->value["HDD情報"][$hdd]["容量不足"])){
							if($this->value["HDD情報"][$hdd]["容量不足"] == 1){
								if($this->hdd[$hdd]["status"] < 2){
									$this->hdd[$hdd]["status"] = 2;	# 容量不足
								}
							}
						}
					}else{
						$this->hdd[$hdd]["status"] = 1;	# 未接続
					}
					
					# 故障(SMARTエラー)チェック
					$output = array();
					exec("sudo smartctl -d marvell -H $dev 2> /dev/null;",$output,$return_var);
					if(substr(sprintf("%04d",decbin($return_var)),-4,1)){
						$this->hdd[$hdd]["status"] = 3;	# 故障（SMARTエラー）
					}
					
					# エラーカウントのチェック
					$output = array();
					exec("sudo dd if=$dev bs=1 skip=17472 count=4 2> /dev/null;",$output,$return_var);
					$error_count = trim($output[0]);
					
					if($error_count >= LD_ERROR_COUNT_LIMIT){
						if($this->hdd[$hdd]["status"] < 4){
							$this->hdd[$hdd]["status"] = 4;	# エラー（エラーカウントオーバー）
						}
					}
				}else{
					if($hdd == "hdd1"){
						$this->hdd[$hdd]["status"] = 1;	# 未接続
					}
# 					else if(($hdd == "hdd2") && $this->isLdRaidType()){
# 						$this->hdd[$hdd]["status"] = 1;	# 未接続
# 					}
				}
			}else{
				$this->hdd["hdd1"]["status"] = "0";
				$this->hdd["hdd2"]["status"] = "0";
			}
		}
	}
	function getHddStatus(){
		foreach($this->hdd as $hdd){
			if($hdd["status"] != "0"){
				return false;
			}
		}
		return true;
	}
	function setRaidStatus(){
		if(!DEBUG_MODE){
			$mode = "";
			$status = 0;
			$rebuild = "";
			
			$this->loadConfig("hdd");
			$this->setDefineLdHddDevice();
			foreach($this->LD_HDD_DEVICE as $hdd => $dev){
				if(isset($this->value["HDD情報"][$hdd]["状態"])){
					if($this->value["HDD情報"][$hdd]["状態"] == "crash"){
						$status = 3;	// 崩壊
					}
				}
			}
			
			foreach(explode(":",LD_RAID_DEVICE) as $dev){
				$output = array();
				exec("sudo mdadm --detail $dev 2> /dev/null;",$output,$return_var);
				
				if($return_var == 0){
					
					$status_buf = 0;
					
					foreach($output as $buf){
						if(preg_match('/Raid Level : (.*)$/',$buf,$matches)){
							if($dev == LD_DATA_PARTITION){
								$mode = $matches[1];
							}
						}
						else if(preg_match('/State : (.*)$/',$buf,$matches)){
							if(preg_match('/clean|active/',$matches[1])){
								if($status_buf < 0){
									$status_buf = 0;	// 正常動作
								}
							}
# 							if(preg_match('/recovering|resyncing/',$matches[1])){
# 								if($status_buf < 1){
# 									if(!file_exists(LD_ACTIVEREPAIR_LOCK_PATH)){
# 										$status_buf = 1;	// 再構築
# 									}
# 								}
# 							}
							if(preg_match('/degrade/',$matches[1])){
								if($status_buf < 2){
									$status_buf = 2;	// ディスクエラー
								}
							}
						}
						else if(preg_match('/Rebuild Status : (.*)\s/',$buf,$matches)){
							$rebuild = $matches[1];
						}
						else if(preg_match('/Working Devices : (.*)$/',$buf,$matches)){
							$working_devices = $matches[1];
						}
					}
					
					if($status_buf == 2 && $working_devices == 2){
						$status_buf = 1;	// 再構築待ち
					}
					
					if($status < $status_buf){
						$status = $status_buf;
					}
				}
				else{
					if($status < 3){
						$status = 3;	// 崩壊
					}
				}
			}
			
			# md6からRAIDモードを取得できなかった場合はhda6,hdb6からRAIDモード取得
			if($mode == ""){
				foreach(array($this->LD_HDA_DATA_PARTITION,$this->LD_HDB_DATA_PARTITION) as $hdd){
					$output = array();
					exec("sudo mdadm --examine $hdd 2> /dev/null;",$output,$return_var);
					
					if($return_var == 0){
						foreach($output as $buf){
							if(preg_match('/Raid Level : (.*)$/',$buf,$matches)){
								$mode = $matches[1];
								break 2;
							}
						}
					}
				}
			}
			
			$this->raid["mode"] = $mode;
			$this->raid["status"] = $status;
			$this->raid["rebuild"] = $rebuild;
		}
		else{
			$this->raid["mode"] = "raid0";
			$this->raid["status"] = 0;
			
			$this->loadConfig("hdd");
			$this->setDefineLdHddDevice();
			foreach($this->LD_HDD_DEVICE as $hdd => $dev){
				if(isset($this->value["HDD情報"][$hdd]["状態"])){
					if($this->value["HDD情報"][$hdd]["状態"] == "crash"){
						$this->raid["status"] = 3;
					}
				}
			}
		}
	}
	# ============================================================================== #
	# HDD崩壊状態チェック
	# ============================================================================== #
	function checkCrash($function="detail"){
		$this->loadConfig("hdd");
		$this->setDefineLdHddDevice();
		foreach($this->LD_HDD_DEVICE as $hdd => $dev){
			if(isset($this->value["HDD情報"][$hdd]["状態"])){
				if($this->value["HDD情報"][$hdd]["状態"] == "crash"){
					$this->viewError($function,true);
					$this->errorDie("");
				}
			}
		}
		
		if($this->getValue("HDDステータス") == "1"){
			$this->viewError($function,false);
			$this->errorDie("");
		}
	}
	function checkMountError($function="detail"){
		$this->loadConfig("hdd");
		$this->setDefineLdHddDevice();
		foreach($this->LD_HDD_DEVICE as $hdd => $dev){
			if(isset($this->value["HDD情報"][$hdd]["状態"])){
				if($this->value["HDD情報"][$hdd]["状態"] == "crash"){
					$this->viewError($function,true);
					$this->errorDie("");
				}
			}
		}
	}
	function viewError($function,$israid){
# 		if($this->isLdRaidType() && $israid){
# 			$this->execView("/system/$function/crash");
# 		}else{
			$this->execView("/system/$function/crashpart");
# 		}
	}
	# ============================================================================== #
	# HDD状態チェック
	# ============================================================================== #
//	function checkCrash($function="detail"){
//		$this->loadConfig("hdd");
//		
//		if($this->getValue("HDDステータス") == "1"){
//			$this->execView("/system/$function/crash");
//			$this->errorDie("");
//		}
//	}
	# ============================================================================== #
	# ステートフルIDをGETパラメータで引き渡せるようにする
	# ============================================================================== #
	function setStateFulInputName(){
		$this->setValue("ステートフルID",		$this->stateFulID);
		$this->setInputName("ステートフルID",		STATEFULID_INPUTNAME);
		
		$this->setValue("セッションID",			$this->getSession()->getSessionId());
		$this->setInputName("セッションID",		C4_SESSION_ID_NAME);
	}
	# ============================================================================== #
	# 多言語設定
	# ============================================================================== #
# 	function loadLangList(){
# 		$fp = fopen(LANGLIST_FILE_PATH,'r');
# 		$text = fread($fp,filesize(LANGLIST_FILE_PATH));
# 		fclose($fp);
# 		
# 		$loadList = explode("\n",$text);
# 		foreach($loadList as $value){
# 			if(preg_match("/^\[.*\]$/",$value)){
# 				$key = substr($value,1,-1);
# 			}else if($key!='' && $value!=''){
# 				$langList[$key][] = $value;
# 			}
# 		}
# 		return $langList;
# 	}
	function loadLangConf(){
		if(!file_exists(LANGCONF_FILE_PATH)){
			return false;
		}
		$fp = fopen(LANGCONF_FILE_PATH,'r');
		$text = fread($fp,filesize(LANGCONF_FILE_PATH));
		fclose($fp);
		
		$loadList = explode("\n",$text);
		$lang = array();
		foreach($loadList as $value){
			if(preg_match("/^([a-zA-z0-9]+)\t([a-zA-z0-9\+\-]+)$/",$value,$matches)){
				$lang[$matches[1]] = $matches[2];
			}
		}
		return $lang;
	}

	function getLangLanguage(){
		$lang = $this->loadLangConf();
		$lanFlg = "";
		if(isset($lang["lang"])) {
			$lanFlg = $lang["lang"];
		}
		return $lanFlg;
	}
	function getLangTimeZone(){
		$lang = $this->loadLangConf();
		$timeZone = "";
		if(isset($lang["timezone"])) {
			$timeZone = $lang["timezone"];
		}
		return $timeZone;
	}
	function getLangClient(){
		$lang = $this->loadLangConf();
		$cli = "";
		if(isset($lang["client"])) {
			$cli = $lang["client"];
		}
		return $cli;
	}
	# gettext用_変換設定
	function loadLangageSetting($lang=null){
		if(!$lang){
			$lang = $this->getLangLanguage();
		}
		switch($lang){
			case "japanese":
				# Locale Language
				_setlocale(LC_ALL, 'ja_JP');
				# putenv('LC_ALL=ja_JP');
				# mo's Filepath
				_bindtextdomain(GETTEXT_JP_FILE, C4_BASE_PATH."/library");
				_bind_textdomain_codeset(GETTEXT_JP_FILE, "UTF-8");
				_textdomain(GETTEXT_JP_FILE);
			break;
			case "english":
				# Locale Language
				_setlocale(LC_ALL, 'en_US');
				# putenv('LC_ALL=en_US');
				# mo's Filepath
				_bindtextdomain(GETTEXT_US_FILE, C4_BASE_PATH."/library");
				_bind_textdomain_codeset(GETTEXT_US_FILE, "UTF-8");
				_textdomain(GETTEXT_US_FILE);
			break;
			case "traditional_chinese":
				# Locale Language
				_setlocale(LC_ALL, 'zh_TW');
				# putenv('LC_ALL=zh_TW');
				# mo's Filepath
				_bindtextdomain(GETTEXT_CHt_FILE, C4_BASE_PATH."/library");
				_bind_textdomain_codeset(GETTEXT_CHt_FILE, "UTF-8");
				_textdomain(GETTEXT_CHt_FILE);
			break;
			case "simplified_chinese":
				# Locale Language
				_setlocale(LC_ALL, 'zh_CN');
				# putenv('LC_ALL=zh_CN');
				# mo's Filepath
				_bindtextdomain(lang_china_simple, C4_BASE_PATH."/library");
				_bind_textdomain_codeset(lang_china_simple, "UTF-8");
				_textdomain(lang_china_simple);
			break;
			default:
			break;
		}
	}
	function setLangValue(){
		# 初期値セット
		$lang = $this->loadLangConf();
		$val = "";
		if(isset($lang["lang"])) {
			$val = $lang["lang"];
		}
		$lang_num = GET_LANGUAGE($val);
		$this->setValue("言語選択",				$lang_num);
# 		$this->setValue("BitTorrent_言語選択",	$lang_num);
	}
	function isEnglishDateType(){
		if($this->getLangLanguage() == "english"){
			return true;
		}
		return false;
	}
	
	function setImageLink($lang="japanese"){
		exec("sudo rm -rf /usr/local/public/img");
		exec("sudo ln -s /usr/local/public/img_".$lang." /usr/local/public/img");
	}

	function setIconLink($pcb){
		exec("sudo rm -rf /usr/local/public/favicon.ico");
		exec("sudo ln -s /usr/local/public/favicon_".$pcb.".ico /usr/local/public/favicon.ico");
	}

	# ============================================================================== #
	# USB設定
	# ============================================================================== #
# 	# マウントチェック
# 	function checkUsbMount($num=null){
# 		$this->loadConfig("usb");
# 		if(($this->value["接続機器管理情報".$num]['service']['usb'.$num]['mnt']['fs'] != "") && ($this->value["USBポートモード".$num] == 3)){
# 			return true;
# 		}
# 		return false;
# 	}
# 	# ファイルシステムチェック
# 	function checkIsEXT3($num=null){
# 		$this->loadConfig("usb");
# 		if($this->value["接続機器管理情報".$num]['service']['usb'.$num]['mnt']['fs'] == "ext3") return true;
# 		return false;
# 	}
# 	# DLNA リンクの作成
# 	function createDlnaLink($port_num){
# 		$mnt_path = ($port_num == 1) ? LD_TMP_MOUNT_DIR : LD_TMP_MOUNT_DIR2;
# 		$link_path = ($port_num == 1) ? LD_USB1_LINK : LD_USB2_LINK;
# 		if(!file_exists(LD_DLNA_ALIAS_PATH."usb$port_num-".$link_path)){
# 			$command = "sudo ln -s ".$mnt_path." ".LD_DLNA_ALIAS_PATH."usb$port_num-".$link_path. " && ";
# 			$command .= "sudo ln -s /mnt/hda5/dms/usb$port_num.didl ".LD_DLNA_ALIAS_PATH."usb$port_num-".$link_path.".didl";
# 			exec($command,$result,$error);
# 		}
# 	}
# 	# DLNA リンクの削除
# 	function deleteDlnaLink($port_num){
# 		if($port_num == "1" || $port_num == "2"){
# 			$link_path = ($port_num == 1) ? LD_USB1_LINK : LD_USB2_LINK;
# 			$command = "sudo rm -f ".LD_DLNA_ALIAS_PATH."usb$port_num-".$link_path."; ";
# 			$command .= "sudo rm -f ".LD_DLNA_ALIAS_PATH."usb$port_num-".$link_path.".didl";
# 			exec($command,$result,$error);
# 		}
# 	}
# 	# testUnitReadyを殺す
# 	function killTestUnitReady($port){
# 		if($port == "1" || $port == "2"){
# 			$command = 'sudo ps axu | grep "root" | grep "testUnitReady.php '.$port.'"'." | awk '{print $2}'";
# 			exec($command,$result,$error);
# 			if($result){
# 				foreach($result as $key => $no){
# 					$command = "sudo kill ".$no;
# 					exec($command);
# 				}
# 			}
# 		}
# 	}
	# ============================================================================== #
	# ネットワーク設定
	# ============================================================================== #
	function setDomainConfig(){
		if(!($this->getValue("DHCPモード") == 1 && $this->getValue("MI_参加方法の設定") != "domain_nt" && $this->getValue("MI_参加方法の設定") != "domain_ad")){
			$this->setValue("IPアドレス",		$this->getIpAddress());
			$this->makeConfig("/etc/hosts");
			$this->makeConfig("/etc/resolv_conf");
		}
# 		if($this->getValue("MI_参加方法の設定") == "domain_nt"){
# 			$this->restartConfig("domain_stop");
# 			exec("sudo net rpc oldjoin",$output,$error1);
# 			if($error1 != 0) exec("sudo net rpc testjoin",$output,$error2);
# 			$this->restartConfig("domain_start");
# 			if($error2 != 0) return false;
# 		
# 		}else if($this->getValue("MI_参加方法の設定") == "domain_ad"){
# 			$this->restartConfig("domain_stop");
# 			exec("sudo net ads join -U '".$this->getValue("MI_AD_管理者ユーザー名")."%".$this->getValue("MI_AD_管理者パスワード")."' >&/dev/null; ",$output,$error);
# 			$this->restartConfig("domain_start");
# 			if($error != 0) return false;
# 		
# 		}
		return true;
	}
	# ============================================================================== #
	# 共有フォルダ関係
	# ============================================================================== #
# 	function netatalkConfListLoop(){
# 		if(!current($this->value["共有フォルダ情報"])){
# 			return false;
# 		}
# 		$this->setValue("共有フォルダID",		key($this->value["共有フォルダ情報"]));
# 		
# 		if(is_array($this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["service"])){
# 			$service = array_flip($this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["service"]);
# 		}
# 		
# # 		$name = $this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["name"];
# # 		if(isset($service[2]) && (($name != LD_USB_SHARE_NAME && $name != LD_USB_SHARE_NAME2) || ($name == LD_USB_SHARE_NAME && $this->checkUsbMount("1")) || ($name == LD_USB_SHARE_NAME2 && $this->checkUsbMount("2")))){
# 		if(isset($service[2])){
# 		$this->setValue("AppleDBパス",LD_DATA_MOUNT_PATH."/AppleDB/".$name);
# 	# 	if(isset($service[2]) && ($this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["name"] != LD_USB_SHARE_NAME || $this->checkUsbMount())){
# 			$this->setValue("共有フォルダ名",$this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["name"]);
# 			
# # 			if($this->getValue("共有フォルダ名") != LD_USB_SHARE_NAME && $this->getValue("共有フォルダ名") != LD_USB_SHARE_NAME2){
# 				$this->setValue("netatalk_conf_path",LD_SHARE_ROOT_DIR."/".$this->getValue("共有フォルダ名"));
# # 			}else{
# # 			# 	$this->setValue("netatalk_conf_path",  LD_TMP_MOUNT_DIR);
# # 				if($this->getValue("共有フォルダ名") == LD_USB_SHARE_NAME) $this->setValue("netatalk_conf_path",LD_TMP_MOUNT_DIR);
# # 				if($this->getValue("共有フォルダ名") == LD_USB_SHARE_NAME2) $this->setValue("netatalk_conf_path",LD_TMP_MOUNT_DIR2);
# # 			}
# 			
# # 			if($this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["access_allow_setting"] == 0){
# # 				# ゲスト共有の場合
# # 				$this->setValue("netatalk_conf_user",	LD_GUEST_USER);
# # 			}
# # 			
# # 			if($this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["read_only"] == 0){
# 				# リードオンリーではないの場合	
# 				$this->setValue("netatalk_conf_read_only",	"no");
# # 			}
# # 			else{
# # 				# リードオンリーの場合
# # 				$this->setValue("netatalk_conf_read_only",	"yes");
# # 			}
# 			$this->setValue("詳細アクセス判定",$this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["detail_access_setting"]);
# 			if($this->getValue("詳細アクセス判定") == 'mix'){
# # 				if($this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["read_group"]!='' && $this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["read_group"]!='@'){
# # 					$readGroup = c4_explode(",",$this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["read_group"]);
# # 					foreach($readGroup as $key => $grp){
# # 						$grp = preg_replace("/^@/","",$grp);
# # 						if($grp!=''){
# # 							$readGroup[$key] = '@'.$grp;
# # 						}
# # 					}
# # 					$readGroup = implode($readGroup,',');
# # 				}
# 				if($this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["read_user"]!=''){
# 					$readUser = c4_explode(",",$readUser = $this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["read_user"]);
# 					foreach($readUser as $key => $user){
# 						if(strlen($user)>=1){
# 							$readUser[$key] = $user;
# 						}
# 					}
# 					$readUser = implode($readUser,',');
# 				}
# # 				$this->setValue("netatalk_conf_read_list",		$readUser.$readGroup);
# 				$this->setValue("netatalk_conf_read_list",		$readUser);
# 				
# # 				if($this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["write_group"]!='' && $this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["write_group"]!='@'){
# # 					$writeGroup = c4_explode(",",$this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["write_group"]);
# # 					foreach($writeGroup as $key => $grp){
# # 						$grp = preg_replace("/^@/","",$grp);
# # 						if($grp!=''){
# # 							$writeGroup[$key] = '@'.$grp;
# # 						}
# # 					}
# # 					$writeGroup = implode($writeGroup,',');
# # 				}
# 				if($this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["write_user"]!=''){
# 					$write_user = c4_explode(",",$this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["write_user"]);
# 					foreach($write_user as $key => $user){
# 						if(strlen($user)>=1){
# 							$write_user[$key] = $user;
# 						}
# 					}
# 					$write_user = implode($write_user,',');
# 				}
# # 				$this->setValue("netatalk_conf_write_list",	$write_user.$writeGroup);
# 				$this->setValue("netatalk_conf_write_list",	$write_user);
# 				
# 				if($this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["valid_user"]!=''){
# 					$valid_user = c4_explode(",",$this->value["共有フォルダ情報"][$this->value["共有フォルダID"]]["valid_user"]);
# 					foreach($valid_user as $key => $user){
# 						$user = preg_replace("/^@/","",$user);
# 						if($user!=''){
# # 							if(in_array('@'.$user,explode(",",$readGroup))||in_array('@'.$user,explode(",",$writeGroup))){
# # 								$valid_user[$key] = '@'.$user;
# # 							}else{
# 								$valid_user[$key] = $user;
# # 							}
# 						}
# 					}
# 					$valid_user = implode($valid_user,',');
# 				}
# 				$this->setValue("netatalk_conf_valid_users",$valid_user);
# 			}else{
# 				$this->setValue("netatalk_conf_read_list",null);
# 				$this->setValue("netatalk_conf_write_list",null);
# 				$this->setValue("netatalk_conf_valid_users",null);
# 			}
# 
# 		}
# 		else{
# 			$this->setValue("共有フォルダID","");
# 		}
# 		
# 		next($this->value["共有フォルダ情報"]);
# 		return true;
# 	}
	# ============================================================================== #
	# 時刻設定
	# ============================================================================== #
	function set_local_time(){
		# 変数に値を退避
		# $tmp = $this->getValue("対応タイムゾーン");
		
		# $this->loadConfig("time");
		# # タイムゾーンを環境変数に設定
		# putenv("TZ=GMT".$this->getValue("対応タイムゾーン"));
		# タイムゾーンをPHPに設定
		exec("ls -l /etc/localtime | grep -o 'GMT[\+\-]\?[0-9]' | grep -o '[\+\-]\?[0-9]'",$output,$result);
		if($result == 0){
			ini_set("date.timezone", "Etc/GMT".$output[0]);
		}
		
		# if($tmp!='') $this->setValue("対応タイムゾーン",$tmp);
	}
	
	# ============================================================================== #
	# RemoteLink2設定 RemoteLink3設定 BitTorrent設定 FTP設定
	# ============================================================================== #
	# 既に使われているポート番号と比較
	function checkSettingPort($service=null){
		$port_list = array();
		
# 		$rl_type = $this->checkRlType();
# 		if($rl_type == 2){
# 			# DDNS(RemoteLink2)
# 			if($service!="ddns"){
# 				$this->loadConfig("ddns");
# 			}else if($this->getValue("DDNS_リモートアクセスポート番号1") == $this->getValue("DDNS_リモートアクセスポート番号2") || $this->getValue("DDNS_外部ポート番号1") == $this->getValue("DDNS_外部ポート番号2")){
# 				$this->setValue("ポート重複エラー",__("ポート番号が重複しています。"),VALUE_MESSAGE);
# 				return;
# 			}
# 			if($this->getValue("DDNS_利用区分") == 1){
# 				$port_list[] = (int)$this->getValue("DDNS_リモートアクセスポート番号1");
# 				$port_list[] = (int)$this->getValue("DDNS_リモートアクセスポート番号2");
# 				if($this->getValue("DDNS_外部ポート区分") == 1){
# 					$port_list[] = (int)$this->getValue("DDNS_外部ポート番号1");
# 					$port_list[] = (int)$this->getValue("DDNS_外部ポート番号2");
# 				}
# 				array_merge($port_list);
# 			}
# 		}else if($rl_type == 3){
			# RemoteLink3
			if($service!="remotelink3"){
				$this->loadConfig("remotelink3");
			}else if($this->getValue("RL3_リモートアクセスポート番号1") == $this->getValue("RL3_リモートアクセスポート番号2") || $this->getValue("RL3_外部ポート番号1") == $this->getValue("RL3_外部ポート番号2")){
				$this->setValue("ポート重複エラー",__("ポート番号が重複しています。"),VALUE_MESSAGE);
				return;
			}
			if($this->getValue("RL3_利用区分") == 1){
				$port_list[] = (int)$this->getValue("RL3_リモートアクセスポート番号1");
				$port_list[] = (int)$this->getValue("RL3_リモートアクセスポート番号2");
				if($this->getValue("RL3_外部ポート区分") == 1){
					$port_list[] = (int)$this->getValue("RL3_外部ポート番号1");
					$port_list[] = (int)$this->getValue("RL3_外部ポート番号2");
				}
				array_merge($port_list);
			}
# 		}
		
# 		# BitTorrent
# 		if($service!="bittorrent"){
# 			$this->loadConfig("bittorrent");
# 		}
# 		if($this->getValue("BitTorrent_利用区分")==1){
# 			$port_list[] = (int)$this->getValue("BitTorrent_ポート番号");
# 		}
		
# 		# ftp
# 		if($service!="ftp"){
# 			$this->loadConfig("ftp");
# 		}
# 		$port_list[] = (int)$this->getValue("ポート番号");
		
		# check開始
		foreach(array_count_values($port_list) as $value => $count){
			if($count > 1 && $value !== ""){
				$this->setValue("ポート重複エラー",__("ポート番号が重複しています。"),VALUE_MESSAGE);
				break;
			}
		}
	}
	function killRemoteLinkProcess(){
		# 自分以外のapacheをkill
		$mypid = getmypid();
		exec("pgrep -f 'apache2'",$output,$result);
		if(is_array($output) && count($output) > 0){
			$kpid = implode(" ",$output);
			$kpid = str_replace($mypid,"",$kpid);
			exec("kill $kpid",$output,$result);
		}
	}
	
	# ============================================================================== #
	# RemoteLink3 利用可否チェック
	# ============================================================================== #
# 	function checkRlType(){
# 		$this->loadConfig("rl3check");
# 		
# 		if($this->getValue("RL3_利用可否") === 0){
# 			# RemoteLink3
# 			return 3;
# 		}else{
# 			# RemoteLink2
# 			return 2;
# 		}
# 	}
	
	# ============================================================================== #
	# TimeMachine関係
	# ============================================================================== #
# 	function isTMdir($dir){
# 		if($this->getValue("タイムマシン機能_利用区分") != 1) return false;
# 		if($this->getValue("保存先共有フォルダー") == $dir){
# 			return true;
# 		}
# 		return false;
# 	}
	# ============================================================================== #
	# C4オーバーライド
	# ============================================================================== #
	# アクションイベントメソッド名取得
	function setActionEvent(){
		# 言語変換の設定読み込み
		$this->loadLangageSetting();
		# 時刻設定
		$this->set_local_time();
		# GETデータからイベント取得
		if(!$this->getEventName($_GET)){
			# POSTデータからイベント取得
			if(!$this->getEventName($_POST)){
				# GETデータから標準イベント取得
				if(!$this->getDefaultEventName($_GET)){
					# POSTデータから標準イベント取得
					if(!$this->getDefaultEventName($_POST)){
						# 初期イベント取得
						$this->actionEvent = "onInit";
					}
				}
			}
		}
		# イベントメソッド存在チェック
		if(!method_exists($this,$this->actionEvent)){
			$this->redirect(C4_HOME_URL."/system/main.php");
			$this->errorDie('');
		}
	}
	# フォームデータ単一取得
	function getFormValue($name){
		if($this->getIsFile($name)==true){
			$value = $this->getFileData($name);
			if(!is_null($value)){
				$this->setValue($name,$value,false,true);
			}
			return true;
		}
		$iName = $this->getInputName($name);
		$value = $this->getFormData($iName);
		if(!is_null($value)){
			$this->setValue($name,$value,false,true);
		}
	}
	# POST/GETデータ取得
	function getFormData($iName){
		if(isset($_GET[$iName])){
			$value = $_GET[$iName];
		}
		elseif(isset($_POST[$iName])){
			$value = $_POST[$iName];
		}
		else{
			$value = "";
		}
		if (get_magic_quotes_gpc()) {// magic quotes gpcの設定を判断、￥を削除
			return $this->arrayMethod('stripslashesValue',$value);
		}
		
		if(isset($_GET["return"]) && !isset($_GET[$iName])){
			return null;
		}
		
		return $value;
	}
	# StateFul開始処理
	function startStateFul($stateFulID=null,$loadOnly=false){
		if(!is_object($this->getSession())){
			$this->startSession();
		}
		$session = &$this->getSession();
		$this->stateFulSet = &$session->sessionLink(STATEFUL_KEY);
		if(!is_array($this->stateFulSet)){
			# stateFulSetを初期化
			$this->stateFulSet=array();
		}
		# Load Onlyのエラー判定
		if($loadOnly==true){
			if(!isset($this->stateFulSet[$stateFulID]) || is_numeric($this->stateFulSet[$stateFulID])){
				if(C4_PROTOCOL != "file"){
					$this->redirect(C4_HOME_URL."/system/main.php");
					$this->errorDie('');
				}
			}
# 			if(!isset($this->stateFulSet[$stateFulID])){
# 				$this->errorDie('指定されたStateFul領域が見つかりません。StateFulID「'.$stateFulID.'」');
# 			}
# 			elseif(is_numeric($this->stateFulSet[$stateFulID])){
# 				$status = $this->stateFulSet[$stateFulID];
# 				if($status==0){
# 					$this->errorDie('指定されたStateFul領域は既に無効です。StateFulID「'.$stateFulID.'」');
# 				}
# 				elseif($status==1){
# 					$this->errorDie('指定されたStateFul領域は最大保持件数を超えた為破棄されました。StateFulID「'.$stateFulID.'」');
# 				}
# 				else{
# 					$this->errorDie('指定されたStateFul領域で致命的なエラーが発生しました。StateFulID「'.$stateFulID.'」');
# 				}
# 			}
		}

		$st_id_count=0;
		$st_enabled_count=0;
		foreach(array_reverse(array_keys($this->stateFulSet)) as $st_id){
			$st_id_count++;
			if($st_id_count>=MAX_STATEFUL_ID){
				# 全ステートフルIDの最大保持件数超過分を削除
				unset($this->stateFulSet[$st_id]);
			}
			if(isset($this->stateFulSet[$st_id])){
				if(is_array($this->stateFulSet[$st_id])){
					$st_enabled_count++;
					if($st_enabled_count>=MAX_STATEFUL_ENABLED){
						# 有効ステートフルの最大保持件数超過分を破棄
						$this->stateFulSet[$st_id] = 1;
					}
				}
			}
		}

		# StateFulIDが未指定の場合、StateFulIDを生成
		if(is_null($stateFulID)) $stateFulID=md5(uniqid(rand(),1));
		$this->stateFulID = $stateFulID;
		# StateFulIDでstateFulデータを取得
		$stateFul = &$this->stateFulSet[$stateFulID];
		if(!is_array($stateFul)) $stateFul = array();
		# StateFulValue接続
		$this->valueSet[VALUE_STATEFUL] = &$stateFul[VALUE_STATEFUL];
		if(!is_array($this->valueSet[VALUE_STATEFUL])){
			# 新規StateFulを初期化
			$this->valueSet[VALUE_STATEFUL]=array();
		}
		else{
			# 再開StateFulをvalueに反映
			foreach(array_keys($this->valueSet[VALUE_STATEFUL]) as $key){
				$this->value[$key] = &$this->valueSet[VALUE_STATEFUL][$key];
			}
		}
		# StateFulで維持するメンバ変数を接続
		foreach(explode('/',STATEFUL_MEMBER) as $member){
			$this->{$member} = &$stateFul[$member];
		}
		$this->defaultValueMode = VALUE_STATEFUL;
		return $stateFulID;
	}
	# テンプレート生成
	function createTemplate($viewType,$templatePath,$templateName,$templatePHPPath,$templatePHPName){
		$templateFile = $templatePath.$templateName;
		$templatePHPFile = $templatePHPPath.$templatePHPName;
		$functionName = preg_replace('|\\.'.$viewType.'$|','',$templateName);
		$functionName = '__TEMPLATE'.str_replace('/','__',$functionName);
		if(function_exists($functionName)){
			return $functionName;
		}
		if(file_exists($templateFile)){
			if(!(file_exists($templatePHPFile) and filemtime($templatePHPFile)>filemtime($templateFile)) or C4_TEMPLATE_DEBUG){
				# テンプレート変換処理
				$tmpText = file_get_contents($templateFile);
				# 変換テンプレート格納先ディレクトリ作成
				$dirList = explode('/',$templatePHPName);
				array_pop($dirList);
				$dirPath = $templatePHPPath;
				foreach($dirList as $dir){
					$dirPath .= $dir."/";
					if(!is_dir($dirPath)){
						mkdir($dirPath);
						chmod($dirPath,C4_DIR_MOD);
						# オーナー・グループ変更追記 ------------#
						if(!DEBUG_MODE){
							chown($dirPath,LD_APACHE_USER);
							chgrp($dirPath,LD_APACHE_GROUP);
						}
						#----------------------------------------#
					}
				}
				$tmpPHP = str_replace("'","\\'",str_replace("\\","\\\\",$tmpText));
				$tmpPHP = preg_replace_callback('|<\\!\\-\\-#(.*?)#\\-\\->|s','templateReplace',$tmpPHP);
				$tmpPHP = preg_replace_callback('|/\\*#(.*?)#\\*/|s','templateReplace',$tmpPHP);
				$tmpPHP = preg_replace_callback('|(@\\([^\\)]*\\)?)|s','templateReplaceOmission',$tmpPHP);
				$tmpPHP = preg_replace_callback('|@\\{([^\\}]*)\\}?|s','templateReplaceText',$tmpPHP);

				$tmpPHP = "<?\n"
					.'function '.$functionName.'(&$view,$viewType="'.$viewType.'"){'
					.'if(!$view->outputHandler(\''.$tmpPHP.'\')) return false;return true;}'
					."\n?>";

				# 変換テンプレート生成
				if(!($tmpFile = @fopen($templatePHPFile,"w"))){
					$this->errorDie(__("変換テンプレートの書き込みに失敗しました「").$templatePHPFile.__("」"));
				}
				
				fwrite($tmpFile,$tmpPHP);
				fclose($tmpFile);
				chmod($templatePHPFile,C4_FILE_MOD);
				# オーナー・グループ変更追記 ------------#
				if(!DEBUG_MODE){
					chown($templatePHPFile,LD_APACHE_USER);
					chgrp($templatePHPFile,LD_APACHE_GROUP);
				}
				#----------------------------------------#
			}
			include_once($templatePHPFile);
			return $functionName;
		}
		else{
			$this->errorDie(__('テンプレートファイル[').$templateFile.__(']が見つかりません'));
		}
	}
	#------------------------------ 他言語変換用_オーバーライド開始 ------------------------------------------#
	# StateFul終了処理
	function endStateFul($stateFulID=null){
		if(is_null($stateFulID)) $stateFulID = $this->stateFulID;
		if(!is_object($this->getSession())){
			$this->errorDie(__('セッション未確立時にendStateFulが実行されました'));
		}
		$session = &$this->getSession();
		$this->stateFulSet = &$session->sessionLink(STATEFUL_KEY);
		if(!is_array($this->stateFulSet)){
			$this->errorDie(__('endStateFulの実行時にStateFul領域が見つかりませんでした。'));
		}
		if(!isset($this->stateFulSet[$stateFulID])){
			$this->errorDie(__('endStateFulで指定されたStateFul領域が見つかりません。StateFulID「').$stateFulID.__('」'));
		}
		elseif(is_numeric($this->stateFulSet[$stateFulID])){
			$status = $this->stateFulSet[$stateFulID];
			if($status==0){
				$this->errorDie(__('endStateFulで指定されたStateFul領域は既に無効です。StateFulID「').$stateFulID.__('」'));
			}
			elseif($status==1){
				$this->errorDie(__('endStateFulで指定されたStateFul領域は最大保持件数を超えた為破棄済みです。StateFulID「').$stateFulID.'」');
			}
			else{
				$this->errorDie(__('endStateFulで指定されたStateFul領域で致命的なエラーが発生しました。StateFulID「').$stateFulID.__('」'));
			}
		}
		if(is_array($this->stateFulSet[$stateFulID])){
			# 指定ステートフル領域を無効化
			$this->stateFulSet[$stateFulID] = 0;
		}
		$this->stateFulID = null;
		unset($this->valueSet[VALUE_STATEFUL]);
		$this->defaultValueMode = DEFAULT_VALUEMODE;
		return $stateFulID;
	}
	# Detail追加
	function addDetails($name,$detailValues=array(),$id=null){
		$details = $this->getDetails($name);
		$detailKey = array_shift($details);
		$detailKeyValue = &$this->getValue($detailKey,true);
		# DetailKeyを初期化
		if(!is_null($id) and isset($detailKeyValue[$id])){
			$this->errorDie(__('追加DetailIDが重複しています。DetailID「').$id.__('」'));
		}
		# 新規行作成
		$value = array_shift($detailValues);
		if(is_null($id)){
			# DetailID自動発行
			$detailKeyValue[] = $value;
			$detailPointer = $detailKeyValue;
			end($detailPointer);
			$id = key($detailPointer);
		}
		else{
			# 指定DetailID使用
			$detailKeyValue[$id] = $value;
		}
		# 追加DetailIDにポインタセット
		$this->setValue($name,$id);
		# DetailValuesを各Valueに格納
		foreach($details as $dName){
			$value = array_shift($detailValues);
			$this->setValue($dName,$value);
		}
		return $id;
	}
	# 出力ハンドラ起動
	function outputHandler($data){
		$outputHandler = $this->getOutputHandler();
		if(!method_exists($this,$outputHandler)){
			$this->errorDie(__('指定された出力ハンドラが未定義です。ハンドラ名「').$outputHandler.__('」'));
		}
		$this->callMethod($outputHandler,$data);
		if($this->getTemplateDie()==true) return false;
		return true;
	}
	# ログ出力
	function writeLog($logPath,$ext,$logData){
		$fileName = $logPath.'/'.date("Ymd",time()).$ext.".log";
		if(!($f = @fopen($fileName,"a"))) $this->errorDie(__("ログファイルの書込みに失敗しました。「").$fileName.__("」"));
		$writedata = date("Y-m-d H-i-s",time()) ."\t".preg_replace('|[\\r\\n]+|',"\n\t",$logData)."\n";
		$writedata=preg_replace('|[\\t\\n]+$|',"\n",$writedata);
		fwrite($f,$writedata);
		fclose($f);
		@chmod($fileName,C4_FILE_MOD);
	}
	# クラスファイルインクルード
	function includeClass($fileName,$className=null){
			if(!is_null($className) and class_exists($className)) return $className;
			if(!file_exists($fileName)){
				$this->errorDie(__('指定されたクラスファイルが見つかりません。ファイル名「').$fileName.__('」'));
			}
			include_once($fileName);
			return $className;
	}
	# 関数ファイルインクルード
	function includeFunction($fileName,$functionName=null){
			if(!is_null($functionName) and function_exists($functionName)) return true;
			if(!file_exists($fileName)){
				$this->errorDie(__('指定された関数ファイルが見つかりません。ファイル名「').$fileName.__('」'));
			}
			include_once($fileName);
	}
# 	# タグ取得
# 	function &getTag($name,$tagName=null){
# 		# タグ名取得
# 		if(is_null($tagName)) $tagName = $this->getTagDefine($name);
# 		if(is_null($tagName)) $this->errorDie(__("指定されたデータのタグ名が未定義です。データ名「").$name.__("」"));
# 		$tagClass = $tagName;
# 		if(substr($tagName,0,1)=="/") $tagClass = substr($tagName,1);
# 		$tag = &$this->createPlugin('tag',$tagClass);
# 		# タグ専用の定義情報格納
# 		$tagDefine = c4_array_value($this->valueDefine,$name);
# 		$tagDefine["valueName"] = c4_array_value($tagDefine,"name");
# 		$tagDefine["name"] = $this->getOutputName($name);
#  		$tagDefine["value"] = $this->getValue($name);
# 		if($this->getItemName($name)!=""){
# 			$tagDefine["item"] = &$this->getItemList($this->getItemName($name));
# 		}
# 		# タグ生成
# 		if($tagName == $tagClass){
# 			# 開始タグ
# 			$tagText = call_user_func(array(&$tag,'openTag'),$tagDefine);
# 		}
# 		else{
# 			# 終了タグ
# 			$tagText = call_user_func(array(&$tag,'closeTag'),$tagDefine);
# 		}
# 		if($tagName=='form' and (c4_array_value($tagDefine,'sid')==true or $this->getAutoSidUrl()==true)){
# 			# Session対応Formタグ
# 			$session = &$this->getSession();
# 			if(is_object($session)){
# 				$tagText .= '<input type="hidden" name="'.$session->getSessionIdName().'" value="'.htmlspecialchars($session->getSessionId()).'">';
# 			}
# 		}
# 		return $tagText;
# 	}
	# View処理実行（汎用）
	function execViewGeneral($viewType,$viewName,$outputHandler='standardOut',$viewPath=C4_VIEW_HTML_PATH,$tempPath=C4_TEMPLATE_HTML_PATH,$tempPHPPath=C4_TEMPLATE_HTML_CACHE_PATH){
		# viewClass/viewFile取得
		if(substr($viewName,0,1)=='/'){
			# 絶対指定
			if(preg_match('|([^/]+)$|',$viewName,$matches)!=1){
				$this->errorDie(__('指定されたview名が不正です。「').$viewName.__('」'));
			}
			$viewClass = $matches[1];
			$viewFile = $viewPath.$viewName.'.'.$viewType.'.php';
			$tempName = $viewName.'.'.$viewType;
			$tempPHPName = $viewName.'.'.$viewType.'.php';
		}
		else{
			# 相対指定
			$viewClass = $viewName;
			$viewFile = $viewPath.C4_CURRENT_PATH."/".$viewClass.'.'.$viewType.'.php';
			$tempName = "/".$viewClass.'.'.$viewType;
			if(C4_CURRENT_PATH != '/') $tempName = C4_CURRENT_PATH.$tempName;
			$tempPHPName = C4_CURRENT_PATH."/".$viewClass.'.'.$viewType.'.php';
		}
		# ビューオブジェクト生成
		$viewClass = preg_replace('|^.*/|','',$viewClass);
		# テンプレートフラグ追加
		$this->pushTemplateDie();
		$this->createViewObject($viewType,$viewClass,$viewFile);
		# 出力ハンドルの前処理
		$this->callMethod($outputHandler.'_Before');
		# テンプレート生成(Function化)
		$functionName = $this->createTemplate($viewType,$tempPath,$tempName,$tempPHPPath,$tempPHPName);
		# 出力ハンドラPush
		$this->pushOutputHandler($outputHandler);
		# テンプレート実行
		$result = $this->call_func($functionName,$this->view[$viewType.'_'.$viewClass]);
		# 出力ハンドラPop
		$this->popOutputHandler();
		# 出力ハンドルの後処理
		$this->callMethod($outputHandler.'_After');
		# テンプレートフラグ削除
		$this->popTemplateDie();
		return $result;
	}
	function openDB($connectStr=null){
		if(is_null($connectStr)) $connectStr=$this->defaultConnectStr;
		list($name,$className,$charset,$conStr) = c4_explode(':',$connectStr,4);
		if(isset($this->pluginObjects['db'][$name])){
			$this->errorDie(__('既にDBオブジェクトが生成済みです。接続名「').$name.__('」'));
		}
		$this->createPlugin('db',$className,$name,$connectStr);
		if($this->pluginObjects['db'][$name]->errorMsg=="") return true;
		$this->lastError['C4Action::openDB'] = $this->pluginObjects['db'][$name]->errorMsg;
		if(C4_DB_LOG_LEVEL>=1) $this->writeLog(C4_DB_LOG_PATH,"-error",$this->getLastError());
		return false;
	}
	#------------------------------ 他言語変換用_オーバーライド終了 ------------------------------------------#

	# キャッシュ回避のためファイルの更新日時をクエリに追加
	function get_timestamp($filepath){
		if(file_exists($filepath)){
			return date('YmdHis',filemtime($filepath));
		} else {
			return 'file not found';
		}
	}

}
	# テンプレート汎用解析
	function templateReplaceText($matches){
		$matches[1]="=__('".addcslashes($matches[1],"'")."')";
		return templateReplace($matches);
	}

	# 多次元配列の値取得用(デフォルト値付き)
        function getArrayValue($targetArray, $keyArray, $default=""){
                $value = $default;
                foreach ($keyArray as $key){
                        if (! is_array($targetArray)) {
                                return $default;
                        } elseif (array_key_exists($key, $targetArray)) {
                                $value = $targetArray[$key];
                                $targetArray = $value;
                        } else {
                                return $default;
                        }
                }
                return $value;
        }
?>
