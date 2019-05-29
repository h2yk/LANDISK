#!/usr/bin/php
<?
$filePath = get_included_files();
chdir(preg_replace('/(.+)[\\\|\/].*$/i', "\\1", $filePath[0]));

define("C4_SESSION_DEFAULT_ENABLED", false);

include_once ('/usr/local/c4/c4c.class.php');
include_once (LD_CLASS_PATH . '/system/detail/share/share/share.class.php');
include_once (LD_CLASS_PATH . '/dropbox.class.php');
include_once (LD_CLASS_PATH . '/azukeru.class.php');
include_once (LD_CLASS_PATH . '/remotelinksync.class.php');

$_COOKIE[C4_SESSION_ID_NAME] = $argv[1];

class action extends share_class {
	# ============================================================================== #
	# コンストラクタ定義
	# ============================================================================== #
	
	function action($stateFulID) {
		$this->setValue("ステートフルID", $stateFulID);
		C4EAction::C4EAction();
	}
	# ============================================================================== #
	# イベント定義
	# ============================================================================== #
	
	function onLoad() {
		$this->standardOutFlag = true;

		$this->startSession();
		$this->startStateFul($this->getValue("ステートフルID"));

		$sessionId = $this->getSession()->getSessionId();

		$this->endSession();

		$lock = $this->writeMount(false);

		$this->setDefineLdHddDevice();
		# ロックファイルのリフレッシュ起動
		exec(LD_PHP_COMMAND . " " . LD_BIN_PATH . "/refresh_proc_lock.php " . $sessionId . " " . $this->stateFulID . " >& /dev/null &");

		$this->logging("format:start");

		$this->restartConfig("media_stop", "share_stop");
		# RemoteLink3無効化
		$this->loadConfig("remotelink3");
		if ($this->getValue("RL3_利用区分") == 1) {
			exec("sudo rm -rf /etc/apache2/sites-enabled/001-rl3");
			if ($this->getValue("RL3_リモートUI利用") == 1) {
				exec("sudo ln -s /etc/apache2/sites-available/rl3_remoteui /etc/apache2/sites-enabled/001-rl3");
			} else {
				exec("sudo ln -s /etc/apache2/sites-available/rl3_only /etc/apache2/sites-enabled/001-rl3");
			}
			exec("sudo /etc/init.d/apache2 reload");
			exec("sudo /etc/init.d/raps.sh stop");
		}

		$result = $this->formatDisk("single");

		if ($result == 0) {
			#$this->ledcont("serious_ok");
			#$this->ledcont("progress");
			# 共有ディレクトリルートを作成
			exec("sudo mkdir " . LD_SHARE_ROOT_DIR);
			# クラウドストレージ同期無効化
			$this->loadConfig("share");
			$dropbox = new dropbox_class($this);
			$azukeru = new azukeru_class($this);
			$remoteLinkSync = new remotelinksync_class($this);
			foreach ($this->value["共有フォルダ情報"] as $share_info) {
				$dropbox->disableDropbox($share_info["name"], 1);
				$azukeru->disableAzukeru($share_info["name"]);
				$remoteLinkSync->disableRetemoLinkSync($share_info["name"]);
			}
			exec("sudo rm -f /mnt/hda5/conf/dropbox/access_token.json");
			exec("sudo rm -f /mnt/hda5/conf/dropbox/share_config_v2");
			exec("sudo rm -f /mnt/hda5/conf/dropbox/share_config");
			$this->setValue("フレッツ共有", array());
			$this->saveConfig("azukeru");
			exec("sudo rm -f /mnt/hda5/conf/azukeru/share_config");
			$this->setValue("RemoteLink3Sync共有", array());
			$this->saveConfig("remotelink3sync");
			exec("sudo rm -f /mnt/hda5/conf/raps/share_config");
			# 中間設定ファイル初期化
			$this->restoreConfig("share");
			# 中間設定ファイル取得
			$this->loadConfig("microsoft");
			$this->loadConfig("network");
			$this->loadConfig("share");
			$this->loadConfig("user");
			$this->loadConfig("others");
			# samba.conf設定
			$this->makeConfig("/etc/samba/smb_conf");

			foreach ($this->value["共有フォルダ情報"] as $share_info) {
				$this->setValue("共有フォルダ名", $share_info["name"]);
				$this->setValue("共有フォルダコメント", $share_info["comment"]);
				$this->setValue("サービス", $share_info["service"]);

				$share_dir = LD_SHARE_ROOT_DIR . "/" . $this->getValue("共有フォルダ名");
				if (!file_exists($share_dir)) {
					# ゲスト共有（アクセス権の選択が「全てのユーザを許可」）
					# $chmod = "775";
					$chmod = "777";
					$chown = LD_GUEST_USER;
					$chgrp = LD_GUEST_GROUP;
					# 共有フォルダ作成
					exec("sudo mkdir $share_dir");
					# パーミッション変更
					exec("sudo chmod $chmod $share_dir");
					# オーナー変更
					exec("sudo chown $chown:$chgrp $share_dir");
				}
			}
			# ファイル解凍
			exec("sudo cp -pr " . LD_FORMAT_EXPANSION_PATH . "/* " . LD_DATA_MOUNT_PATH);
			# twonkyServer設定(DLNAは同時に使えない)
			$this->updateMediaSetting();
		}
		# RemoteLink3有効化
		#$this->loadConfig("remotelink3");
		if ($this->getValue("RL3_利用区分") == 1) {
			if ($this->getPincode("current") === $this->getPincode("init")) {
				exec("sudo rm -rf /etc/apache2/sites-enabled/001-rl3");
				exec("sudo ln -s /etc/apache2/sites-available/rl3_only /etc/apache2/sites-enabled/001-rl3");
				exec("sudo /etc/init.d/apache2 reload");
				exec("sudo /etc/init.d/raps.sh stop");
			} else {
				exec("sudo rm -rf /etc/apache2/sites-enabled/001-rl3");
				if ($this->getValue("RL3_リモートUI利用") == 1) {
					exec("sudo ln -s /etc/apache2/sites-available/rl3_raps_remoteui /etc/apache2/sites-enabled/001-rl3");
				} else {
					exec("sudo ln -s /etc/apache2/sites-available/rl3_raps /etc/apache2/sites-enabled/001-rl3");
				}
				exec("sudo /etc/init.d/apache2 reload");
				exec("sudo /etc/init.d/raps.sh start");
			}
		}

		if ($result < 90) {
			$this->restartConfig("media_start", "share_start");
			$this->restartConfig("avahi-daemon");
		}

		if ($result == 0) {
			$this->logging("format:end");
		} else {
			$this->ledcont("err");
			$this->logging("format:error");
		}

		$this->readMount($lock);

		$this->startSession();
		$this->startStateFul($this->getValue("ステートフルID"));
		$this->setValue("proc_result", $result);
		$this->endSession();
	}
	# RAIDフォーマット
	function formatDisk($raid_mode) {
		$this->setItemName("HDD名", "hdd");

		$this->loadConfig("hdd");

		$result = 0;

		exec("sudo mount -l|grep " . LD_DATA_MOUNT_PATH, $mount_info);
		if ($mount_info[0]) {
			exec("sudo umount " . LD_DATA_MOUNT_PATH . " >& /dev/null", $output, $result_umount);
			if ($result_umount != 0) {
				$result = 1;
			}
			exec("sudo /etc/init.d/snmpd restart > /dev/console 2>&1");
		}

		if ($result == 0) {
			$datapart = "/dev/sda6";
			exec("sudo /sbin/mkfs.xfs -f " . $datapart, $output, $result_format);
			if ($result_format != 0) {
				$result = 99;
			}
		}

		if ($result == 0) {
			exec("sudo mount " . $datapart . " " . LD_DATA_MOUNT_PATH . " >& /dev/null", $output, $result_mount);
			if ($result_mount != 0) {
				$result = 97;
				foreach ($this->LD_HDD_DEVICE as $hdd => $dev) {
					$this->setValue("HDD名", $hdd);
					$this->value["HDD情報"][$hdd]["状態"] = "crash";
				}
			}
			exec("sudo /etc/init.d/snmpd restart > /dev/console 2>&1");
		}

		if ($result == 0) {
			$err_result = 0;
			foreach ($this->LD_HDD_DEVICE as $hdd => $dev) {
				if (!$dev && $this->value["HDD情報"][$hdd]["状態"] == "degrade") {
					$err_result = 1;
					continue;
				} else if (!$dev && $this->value["HDD情報"][$hdd]["状態"] == "crash") {
					$err_result = 1;
					continue;
				}

				$this->setValue("HDD名", $hdd);
				$this->value["HDD情報"][$hdd]["状態"] = "normal";
			}
			if ($err_result != 0) {
				foreach ($this->LD_HDD_DEVICE as $hdd => $dev) {
					$this->setValue("HDD名", $hdd);
					$this->value["HDD情報"][$hdd]["状態"] = "crash";
				}
			}
		}

		if ($result < 90) {
			$this->setValue("HDDステータス", "0");
		} else {
			$this->setValue("HDDステータス", "1");
		}

		$this->saveConfig("hdd");

		return $result;
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
new action($argv[2]);
exit(0);
?>
