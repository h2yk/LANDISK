#!/usr/bin/php
<?
$filePath = get_included_files();
chdir(preg_replace('/(.+)[\\\|\/].*$/i', "\\1", $filePath[0]));
include_once ('/usr/local/c4/c4c.class.php');
include_once ('/usr/local/c4/utils.php');
include_once (LD_CLASS_PATH . '/system/detail/share/share/share.class.php');
class action extends share_class {
	# ============================================================================== #
	# コンストラクタ定義
	# ============================================================================== #
	function action() {
		C4EAction::C4EAction();
	}
	# ============================================================================== #
	# イベント定義
	# ============================================================================== #
	function onLoad() {
		$lock = $this->writeMount(false);
		# LANDISK製品情報取得
		$this->getLanDiskInfo();
		# HLS-C
		if ($this->value["LANDISK製品情報"]['pcb'] == LD_SINGLE_TYPE_NAME) {
			# マウント判定
			# ■HDDデバイス取得
			# ■HDAデータパーティション取得
			# ■HDBデータパーティション取得
			$this->setDefineLdHddDevice();
			# MBR1チェック
			exec(LD_BIN_PATH . "/isvalidmbr1.sh " . LD_DATA_DISK . " >& /dev/null", $status, $error_mbr);
			exec("sudo mount " . "/dev/sda6" . " " . LD_DATA_MOUNT_PATH . " >& /dev/null", $output, $result_mount);
			exec("sudo /etc/init.d/snmpd restart > /dev/console 2>&1");
			if (isset($result_mount) && $result_mount == '0') {
				$this->setValue("HDDステータス", "0");
				$this->outputLog("share service start");
				foreach ($this->LD_HDD_DEVICE as $hdd => $dev) {
					if ($hdd) {
						$this->value["HDD情報"][$hdd]["状態"] = "normal";
					}
				}
				$this->share_start = true;
			} else {
				foreach ($this->LD_HDD_DEVICE as $hdd => $dev) {
					if ($hdd) {
						$this->value["HDD情報"][$hdd]["状態"] = "crash";
					}
				}
				$this->setValue("HDDステータス", "1");
				$this->share_start = false;
			}
			$this->saveConfig("hdd");
		}
		$this->loadConfig("first");
		# 初回起動時のみ
		if (!$this->getValue("初回設定区分")) {
			$this->setValue("国内販売判定", $this->checkSalesArea());
			# タイムゾーンとクライアント言語
			$this->loadConfig("time");
			$this->loadConfig("others");
			if ($this->getValue("国内販売判定") == true) {
				$this->setValue("対応タイムゾーン", LD_LOCAL_TIME_ZONE);
				$this->setValue("対応クライアント言語", "CP932");
				$this->setValue("対応言語", "japanese");
			} else {
				# 海外
				$this->setValue("対応タイムゾーン", LD_FOREIGN_COUNTRIES_TIME_ZONE);
				$this->setValue("対応クライアント言語", "CP437");
				$this->setValue("対応言語", "english");
			}
			# タイムゾーン
			$this->makeConfig("/etc/timezone");
			$command = "sudo ln -sf /usr/share/zoneinfo/Etc/GMT" . $this->getValue("対応タイムゾーン") . " /etc/localtime";
			exec($command);
			# 画像のリンク
			$this->setImageLink($this->getValue("対応言語"));
			# 時間設定
			$this->setValue("タイムサーバ同期区分", 1);
			$this->setValue("タイムサーバホスト名", LD_DEFAULT_TIME_SERVER);
			$this->setValue("同期タイミング", array("1", "2"));
			list($yy, $mm, $dd, $hh, $ii, $ss) = c4_explode('-', date("Y-m-d-H-i-s", time()));
			$this->setValue("同期タイミング_時", date("G", mktime($hh, $ii + 5, $ss, $mm, $dd, $yy)));
			$this->setValue("同期タイミング_分", date("i", mktime($hh, $ii + 5, $ss, $mm, $dd, $yy)));
			$this->saveConfig("time");
			#オートパワーオン
			$this->setValue("オートパワーオン", 1);
			$this->saveConfig("others");
			$this->makeConfig("/mnt/hda5/etc/lang");
			# Crontab生成
			$this->makeCrontab();
			# LANDISK名初期化
			$hostprefix = getHostPrefix();
			$macaddress = $this->getMacAddress();
			$macaddress = substr($macaddress, -6);
			exec(LD_PHP_COMMAND . " network.php mount=no log=no name=" . $hostprefix . "-" . $macaddress . " wake_on_lan=1", $output, $result);
			# 中間設定ファイル初期化
			$this->restoreConfig("firmware");
			$this->loadConfig("firmware");
			$this->setValue("最新ファームウェアバージョン", $this->value["LANDISK製品情報"]["version"]);
			$this->saveConfig("firmware");
			# 言語設定
			$this->setValue("timezone", $this->getValue("対応タイムゾーン"));
			$this->setValue("lang", $this->getValue("対応言語"));
			$this->setValue("client", $this->getValue("対応クライアント言語"));
			$this->makeConfig("/mnt/hda5/etc/lang");
			# TwonkyServerデフォルト設定ファイル更新
			$this->loadConfig("media");
			$this->setValue("カテゴリ表示", "en");
			exec("/usr/local/bin/isaudiomodel.sh", $output, $result);
			if ($result === 0) {
				$this->setValue("カテゴリ表示", "jp");
			}
			$this->saveConfig("media");
			# 共有設定
			$this->shareSetting();
			# デフォルトユーザ作成
			$this->defaultUserSetting();
			# remotelink3初期値設定
			$enable = "1";
			exec("/usr/local/bin/issolidmodel.sh", $output, $result);
			if ($result == 0) {
				$enable = "0";
			}
			exec("remotelink3.php command=first enable=" . $enable . " mount=no");
			$this->setValue("初回設定区分", 1);
			$this->saveConfig("first");
		}
		# アップデート時ユーザ設定を修復する
		if (file_exists("/boot/.fix_user_setting")) {
			$this->fixUserSetting();
			exec("mount -o remount,rw /boot");
			unlink("/boot/.fix_user_setting");
			exec("mount -o remount,ro,noatime /boot");
		}
		# DHCP取得結果の設定
		$this->checkDhcp();
		# ntpサーバ同期(タイムサーバ)
		exec(LD_PHP_COMMAND . " ntpdate.php mount=no mode=init");
		# SSL証明書更新(有効期限が切れていた場合のみ再生成)
		exec("/etc/init.d/makesslkey start");
		$this->logging("system:start");
		if ($this->getValue("HDDステータス") == "1") {
			$this->logging("system:error");
		}
		# 1.02β1動作確認-24
		# 言語設定
		$lang = $this->loadLangConf();
		$this->setImageLink($lang["lang"]);
		# favicon設定
		$this->setIconLink($this->value["LANDISK製品情報"]['pcb']);
		if ($this->share_start) {
			$this->outputLog("share service start");
			$this->restartConfig("share_start");
		} else {
			$this->restartConfig("samba");
		}
		# ネットワーク設定
		$this->setDomainConfig();
		# スピンダウン
		$this->spindownSetting();
		# remotelink3設定
		$this->loadConfig("remotelink3");
		# Ver.1.08 -> 1.09での利用開始前状態の変更に伴う調整
		if (file_exists("/boot/.rl3_setting_update")) {
			$output = null;
			exec("/usr/local/bin/rl3_pincode.sh current", $output, $result);
			if ($result == 0) {
				$pincode_current = $output[0];
				$output = null;
				exec("/usr/local/bin/rl3_pincode.sh init", $output, $result);
				$pincode_init = $output[0];
				if ($pincode_current === $pincode_init) {
					# 初期設定PINコードのため、初期状態
					$this->setValue("RL3_利用区分", "1");
				}
			}
			if ($result == 128) {
				# PINコードの初期化に失敗した状態のため、初期状態
				$this->setValue("RL3_利用区分", "1");
			}
			$this->saveConfig("remotelink3");
			exec("mount -o remount,rw /boot");
			unlink("/boot/.rl3_setting_update");
			exec("mount -o remount,ro,noatime /boot");
		}
		# 中間設定と実設定ファイルの整合性を保つ
		$port1 = $this->getValue("RL3_リモートアクセスポート番号1");
		$port2 = $this->getValue("RL3_リモートアクセスポート番号2");
		$upnp = $this->getValue("RL3_UPNP機能利用");
		$extport1 = $port1;
		$extport2 = $port2;
		if ($this->getValue("RL3_外部ポート区分") == 1) {
			$extport1 = $this->getValue("RL3_外部ポート番号1");
			$extport2 = $this->getValue("RL3_外部ポート番号2");
		}
		exec("/usr/local/bin/rl3_setting.sh " . $port1 . " " . $port2 . " " . $upnp . " " . $extport1 . " " . $extport2, $output, $result);
		$result_apache = 1;
		exec("sudo rm -f /etc/apache2/sites-enabled/001-rl3");
		if ($this->getValue("RL3_利用区分") == 1) {
			exec("sudo /etc/init.d/rl3.sh start");
			$pincode_current = $this->getPincode("current");
			$pincode_init = $this->getPincode("init");
			if ($pincode_current === $pincode_init) {
				exec("sudo /etc/init.d/raps.sh stop");
				exec("sudo ln -s /etc/apache2/sites-available/rl3_only /etc/apache2/sites-enabled/001-rl3");
			} else {
				exec("sudo /etc/init.d/raps.sh start");
				if ($this->getValue("RL3_リモートUI利用") == 1) {
					exec("sudo ln -s /etc/apache2/sites-available/rl3_raps_remoteui /etc/apache2/sites-enabled/001-rl3");
				} else {
					exec("sudo ln -s /etc/apache2/sites-available/rl3_raps /etc/apache2/sites-enabled/001-rl3");
				}
			}
			exec("sudo /etc/init.d/apache2 reload", $output, $result_apache);
		} else {
			exec("sudo /etc/init.d/rl3.sh stop");
			exec("sudo /etc/init.d/raps.sh stop");
			exec("sudo /etc/init.d/apache2 reload", $output, $result_apache);
		}
		if ($result_apache != 0) {
			$this->logging("system:error");
		}
		$this->readMount($lock);
		# ファームウェア最新バージョン情報更新
		exec("sudo " . LD_PHP_COMMAND . " " . LD_BIN_PATH . "/check_firmware_ver.php 300 > /dev/null &");
		$this->standardOutFlag = true;
	}
	# ============================================================================== #
	# 内部処理定義
	# ============================================================================== #
	function month2num($month) {
		$num = array("Jan" => 1, "Feb" => 2, "Mar" => 3, "Apr" => 4, "May" => 5, "Jun" => 6, "Jul" => 7, "Aug" => 8, "Sep" => 9, "Oct" => 10, "Nov" => 11, "Dec" => 12);
		return $num[$month];
	}
	function formatDate($dateString) {
		$date = preg_split('/\s+/', $dateString);
		$date[1] = $this->month2num($date[1]);
		$timestamp = $date[4] . $date[1] . $date[2] . str_replace(":", "", $date[3]);
		return $timestamp;
	}
	function outputLog($log) {
		print "*** RAID Initialization : $log\n";
	}
	# DHCPの取得成功/失敗を判定する
	function checkDhcp($wait_flag = false) {
		$this->loadConfig("network");
		if ($this->getValue("DHCPモード") == "1") {
			# EasySetupが実行された場合、DHCP結果が取得できるよう数秒待つ
			$wait_flag ? sleep(LD_DHCP_WAIT_SECONDS) : "";
			exec(LD_BIN_PATH . "/issuccessdhcp.sh " . LD_NETWORK_INTERFACE . " >& /dev/null", $status, $error_dhcp);
			$this->logging("dhcp:" . (($error_dhcp == "0") ? "success" : "failure"));
		}
	}
	# # # # # # # # # # # # # # # #  # # # # #  # # #
	# ユーザ設定(一部オーバーライド)
	# # # # # # # # # # # # # # # #  # # # # #  # # #
	# デフォルトユーザ作成
	function defaultUserSetting() {
		# 中間設定ファイル初期化
		$this->restoreConfig("user");
		$this->loadConfig("user");
		$this->setValue("ユーザID", key($this->value["ユーザ情報"]));
		if ($this->value["ユーザ情報"][$this->getValue("ユーザID") ]["name"] == LD_DEFAULT_USER_NAME) {
			$this->addUser($this->value["ユーザ情報"][$this->getValue("ユーザID") ]["name"], "");
		}
	}
	function fixUserSetting() {
		$this->loadConfig("user");
		foreach ($this->value["ユーザ情報"] as $user_id => $user_setting) {
			unset($output);
			exec("grep '^" . $user_setting["name"] . ":' /etc/passwd", $output, $return_var);
			if ($return_var != 0) {
				$this->addUser($user_setting["name"], "");
			}
		}
	}
	# 共有ユーザ追加
	function addUser($user, $pass) {
		# ユーザの追加
		exec("sudo /usr/sbin/adduser --force-badname --home /dev/null -shell /bin/false --no-create-home --gecos '" . $user . ",,,' --disabled-password '" . $user . "'", $status, $error);
		if ($error != 0) return false;
		$cpass = crypt($pass, "$1$" . $this->getUrandStr(12) . "$");
		# ユーザパスワードの設定
		exec("sudo /usr/sbin/usermod -p '" . $cpass . "' '" . $user . "'", $status, $error);
		if ($error != 0) {
			$this->deleteUser($user);
			return false;
		}
		# smbへ設定
		exec("(echo '" . $pass . "';echo '" . $pass . "')|sudo smbpasswd -sa '" . $user . "'", $status, $error);
		if ($error != 0) {
			$this->deleteUser($user);
			return false;
		}
		return true;
	}
	# 共有ユーザ削除
	function deleteUser($user) {
		# smbへ設定
		exec("sudo smbpasswd -x '" . $user . "'", $status, $error);
		if ($error != 0) return false;
		# ユーザ削除
		exec("sudo /usr/sbin/userdel '" . $user . "'", $status, $error);
		if ($error != 0) return false;
		return true;
	}
	# 乱数抽出
	function getUrandStr($length) {
		$handle = fopen("/dev/urandom", "rb");
		$rand_pool = fread($handle, 4094);
		fclose($handle);
		$nchars = ord('~') - ord('!') + 1;
		$charmap_array = array();
		for ($i = ord('0');$i <= ord('9');$i++) {
			$charmap_array[] = chr($i);
		}
		for ($i = ord('A');$i <= ord('Z');$i++) {
			$charmap_array[] = chr($i);
		}
		for ($i = ord('a');$i <= ord('z');$i++) {
			$charmap_array[] = chr($i);
		}
		$charmap_array[] = '.';
		$charmap_array[] = '/';
		$random_string = '';
		for ($pointa = 0, $counta = 0;$counta < $length;) {
			$n = (ord($rand_pool[$pointa]) % $nchars) + ord('!');
			if (array_search(chr($n), $charmap_array) !== false) {
				$random_string.= chr($n);
				$counta++;
			}
			if ($counta >= $length) {
				return $random_string;
			}
			$pointa++;
		}
	}
	function getPincode($status) {
		if ($status !== "init" && $status !== "current" && $status !== "new") {
			$this->setValue("PINコード取得エラー", "");
			return null;
		}
		$output = array();
		exec("sudo /usr/local/bin/rl3_pincode.sh " . $status, $output, $result);
		if ($result != 0) {
			$this->setValue("PINコード取得エラー", $result);
			return null;
		}
		$pincode = $output[0];
		return $pincode;
	}
}
new action();
exit(0);
?>
