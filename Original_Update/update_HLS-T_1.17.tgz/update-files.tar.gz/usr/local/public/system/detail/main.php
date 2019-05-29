<?php
define("FIRMWARE_MAX_INPUT_SIZE",200 * 1024 * 1024);
include_once('../../../c4/c4c.class.php');
include_once('../../../c4/utils.php');
include_once(LD_CLASS_PATH.'/system/detail/service/remotelink3/remotelink3.class.php');
include('/usr/local/c4/class/dropbox.class.php');

class main extends remotelink3_class{
	# ============================================================================== #
	# イベント定義
	# ============================================================================== #

	function onLoad(){
		$this->checkLogon();
	}

	function onInit(){
		$this->startStateFul();


		$this->loadConfig("network");
		if(!$this->getValue("LANDISK名")){
			$this->setValue("LANDISK名","HLS-".substr($this->getMacAddress(),-6));
		}

		# # # # # # # # # # # # # # # # #
		# 各種判定
		# # # # # # # # # # # # # # # # #

		$this->setValue("スマートフォン判定",isSmartPhone());
		$this->setValue("RemoteUI判定",isRemoteUI());
		$this->setValue("オーディオモデル判定",isAudioModel());
		$this->setValue("Twonky除外モデル判定",isWithoutTwonkyModel());
		$this->setValue("LANDISKモデル判定",isLandiskModel());
		$this->setValue("SOLIDモデル判定",isSolidModel());

		# # # # # # # # # # # # # # # # #
		# TCP/IP設定
		# # # # # # # # # # # # # # # # #

		$this->setItemName("TCPIP_DHCPモード","dhcp_mode");
		$this->setItemName("TCPIP_WakeOnLAN","wake_on_lan");

		# # # # # # # # # # # # # # # # #
		# Microsoftネットワーク設定
		# # # # # # # # # # # # # # # # #

		$this->setItemName("MI_参加方法の設定","samba_join_mode");

		# # # # # # # # # # # # # # # # #
		# プロキシ設定
		# # # # # # # # # # # # # # # # #

		$this->setItemName("PROXY_プロキシ設定_利用区分","proxy");

		# # # # # # # # # # # # # # # # #
		# 共有フォルダ設定
		# # # # # # # # # # # # # # # # #

		if($this->getValue("SOLIDモデル判定") == true){
			$this->setItemName("SHARE_サービス","service_ms_only");
		}elseif($this->getValue("Twonky除外モデル判定") == true){
			$this->setItemName("SHARE_サービス","service_without_media");
		}else{
			$this->setItemName("SHARE_サービス","service");
		}

		$this->setItemName("SHARE_読み取り専用区分","read_only");
		$this->setItemName("SHARE_ごみ箱機能","share_trash");
		$this->setIsArray("SHARE_サービス",true);
		$this->setItemName("SHARE_Dropboxアクセストークン無効化フラグ","single:".__("Dropboxへのアクセス権を破棄する"));
		$dropbox = new dropbox_class($this);
		$this->setValue("Dropbox認証用URL",$dropbox->getAuthorizeUrl());
		
		$this->itemList["クラウドストレージ_選択"] = array("0"=>__("Dropbox同期"),"1"=>__("フレッツ・あずけ～る同期"),"2"=>__("Remote Link Cloud Sync 対応機器と同期"));
		$this->setItemName("SHARE_クラウドストレージ選択","クラウドストレージ_選択");
		$this->itemList["フレッツホスト_選択"] = array("east"=>__("NTT東日本"),"west"=>__("NTT西日本"));
		$this->setItemName("SHARE_フレッツホスト","フレッツホスト_選択");
		$this->itemList["詳細アクセス権設定_選択"] = array("mix"=>__("有効"),"all"=>__("無効"));
		$this->setItemName("SHARE_詳細アクセス権設定","詳細アクセス権設定_選択");
		$this->setItemName("SHARE_許可しないユーザー","user");
		$this->setIsArray("SHARE_許可しないユーザー",true);

		$this->setValue("date_sharejs",$this->get_timestamp("../../js/share.js"));

		# # # # # # # # # # # # # # # # #
		# ユーザー設定
		# # # # # # # # # # # # # # # # #

		$this->setIsArray("USER_チェック",true);

		# # # # # # # # # # # # # # # # #
		# メディアサーバー設定
		# # # # # # # # # # # # # # # # #

		if($this->getValue("Twonky除外モデル判定") == false){
			$this->setValue("TWONKY_アドレス",		"http://" . $_SERVER["SERVER_ADDR"] . ":9000");
			$this->setValue("EONKYO_アドレス",		$this->getValue("TWONKY_アドレス") . "/resources/webbrowse/downloader/index.html");
		}

		# # # # # # # # # # # # # # # # #
		# RemoteLink3設定
		# # # # # # # # # # # # # # # # #

		$this->setItemName("RL3_利用区分",		"enable");
		$this->setItemName("RL3_UPNP機能利用",	"use");
		$this->setItemName("RL3_外部ポート区分","doaction");
		$this->setItemName("RL3_リモートUI利用","use");
		$this->setItemName("RL3_PINコード変更",	"single:".__("PINコードを変更する"));
		$this->setItemName("RL3_利用ユーザー",	"qruser");

		$this->setValue("RL3_PINCODE",		$this->getPincode("current"));

		# IOPortal
		$this->setValue("暗号化MAC",		"");
		$this->setValue("暗号化PINコード",	"");
		$this->setValue("暗号化サービスID",	"");
		$status = array();
		exec("sudo cat /boot/.cryptmac.asc",$status,$error);
		if($error == "0"){
			if($status['0']){
				$this->setValue("暗号化MAC",base64_encode($status['0']));
			}
		}
		$this->setValue("暗号化PINコード",	base64_encode($this->getPincode("current")));
		$this->setValue("暗号化サービスID",	base64_encode("005"));
		$this->setValue("エンコードタイプ",	2);

		$this->setValue("date_rl3js",$this->get_timestamp("../../js/remotelink3.js"));

		# # # # # # # # # # # # # # # # #
		# マニュアル
		# # # # # # # # # # # # # # # # #

		$this->setValue("マニュアルURL","");
		exec("/usr/local/bin/getsupporturl.sh",$output,$result);
		if($result == 0){
			$this->setValue("マニュアルURL",$output[0]);
		}

		# # # # # # # # # # # # # # # # #
		# 時刻設定
		# # # # # # # # # # # # # # # # #

		$this->setItemName("TIME_タイムサーバ同期区分",	"time_sync");
		$this->setItemName("TIME_同期タイミング",		"time_sync_timing");
		$this->setItemName("TIME_同期タイミング_時",	"hour");
		$this->setItemName("TIME_同期タイミング_分",	"minute");
		$this->setItemName("TIME_対応タイムゾーン",		"time_zone");
		$this->setItemName("TIME_設定時刻_年",			"year:2014:2037");
		$this->setItemName("TIME_設定時刻_月",			"month");
		$this->setItemName("TIME_設定時刻_日",			"day");
		$this->setItemName("TIME_設定時刻_時",			"hour");
		$this->setItemName("TIME_設定時刻_分",			"minute");

		$this->setIsArray("TIME_同期タイミング",true);

		# # # # # # # # # # # # # # # # #
		# 省電力設定
		# # # # # # # # # # # # # # # # #

		$this->setItemName("SPINDOWN_省電力モード",			"enable");
		$this->setItemName("SPINDOWN_省電力モード切替時間",	"spindown_time");

		# # # # # # # # # # # # # # # # #
		# フォーマット
		# # # # # # # # # # # # # # # # #

		$this->setItemList("フォーマットディスク_モード",array("1"=>__("内蔵ディスク")));
		$this->setItemName("FORMAT_フォーマットディスク","フォーマットディスク_モード");

		# # # # # # # # # # # # # # # # #
		# チェックディスク
		# # # # # # # # # # # # # # # # #

		$this->setItemList("ボリューム選択_モード",array("1"=>__("内蔵ディスク")));
		$this->setItemName("CHECKDISK_ボリューム選択","ボリューム選択_モード");

		# # # # # # # # # # # # # # # # #
		# システム初期化
		# # # # # # # # # # # # # # # # #

		$this->setItemName("INIT_ボリュームクリア判定区分",	"volume_clear");

		# # # # # # # # # # # # # # # # #
		# ファームウェア
		# # # # # # # # # # # # # # # # #

		$this->setIsFile("FIRMWARE_ファームウェアファイル",true);
		
		$this->loadConfig("firmware");
		$this->getLanDiskInfo();
		$this->setValue("現行ファームウェアバージョン",		$this->value["LANDISK製品情報"]["version"]);

		$this->setValue("最新ファーム判定", false);
		if($this->getValue("現行ファームウェアバージョン") < $this->getValue("最新ファームウェアバージョン")){
			$this->setValue("最新ファーム判定", true);
		}

		$this->setItemName("FIRMWARE_ファームウェア自動アップデート機能",	"firmware_shutdown");
		$this->setItemName("FIRMWARE_ファームウェア定期自動アップデート機能",	"firmware_everyday");
		$this->setItemName("FIRMWARE_ファーム確認_時刻指定",	"firmware_time");
		$this->setItemName("FIRMWARE_ファーム確認_時間",	"hour");
		$this->setItemName("FIRMWARE_ファーム確認_分",	"minute");

		$this->setValue("FIRMWARE_ファームウェア機能有効_hid","false");

		# # # # # # # # # # # # # # # # #
		# シャットダウン
		# # # # # # # # # # # # # # # # #

		$this->setItemName("SHUTDOWN_シャットダウンモード","shutdown_mode");

		# # # # # # # # # # # # # # # # #
		# ログ表示
		# # # # # # # # # # # # # # # # #

		# # # # # # # # # # # # # # # # #
		# 管理者パスワード
		# # # # # # # # # # # # # # # # #

		$this->execView("main");
	}

	# ============================================================================== #
	# 内部処理定義
	# ============================================================================== #

	# # # # # # # # # # # # # # # # #
	# TCP/IP設定
	# # # # # # # # # # # # # # # # #

	function setTagIPAddress(){
		$list = array(
				"ipaddress"		=>"IPアドレス",
				"subnetmask"	=>"サブネットマスク",
				"gateway"		=>"ゲートウェイ",
				"dnsserver"		=>"DNSサーバ");

		foreach($list as $id=>$name){
			$this->setTagDefine("TCPIP_".$name."_1",	"text",	array("size"=>"1","maxlength"=>"3","option"=>"id=TCPIP_".$id."_1 disabled style=\"ime-mode:disabled;\" class=\"ip_input\""));
			$this->setTagDefine("TCPIP_".$name."_2",	"text",	array("size"=>"1","maxlength"=>"3","option"=>"id=TCPIP_".$id."_2 disabled style=\"ime-mode:disabled;\" class=\"ip_input\""));
			$this->setTagDefine("TCPIP_".$name."_3",	"text",	array("size"=>"1","maxlength"=>"3","option"=>"id=TCPIP_".$id."_3 disabled style=\"ime-mode:disabled;\" class=\"ip_input\""));
			$this->setTagDefine("TCPIP_".$name."_4",	"text",	array("size"=>"1","maxlength"=>"3","option"=>"id=TCPIP_".$id."_4 disabled style=\"ime-mode:disabled;\" class=\"ip_input\""));

			$this->setInputName("TCPIP_".$name."_1",	"TCPIP_".$id."_1");
			$this->setInputName("TCPIP_".$name."_2",	"TCPIP_".$id."_2");
			$this->setInputName("TCPIP_".$name."_3",	"TCPIP_".$id."_3");
			$this->setInputName("TCPIP_".$name."_4",	"TCPIP_".$id."_4");
		}
		$this->setTagDefine("TCPIP_IPアドレス_1",	"text",	array("size"=>"1","maxlength"=>"3","option"=>"id=\"TCPIP_ipaddress_1\" onBlur='TCPIP_ins_SubNetMask()' disabled style=\"ime-mode:disabled;\" class=\"ip_input\""));
		$this->setInputName("TCPIP_IPアドレス_1",	"TCPIP_ipaddress_1");
	}

	# ============================================================================== #
	# ビュー定義
	# ============================================================================== #

	function html_main(){
		$this->setInputName("フォーム開始",		"form");
# 		$this->setTagDefine("フォーム開始",		"form",		array("stateFul"=>true,"option"=>"id=\"form\""));
		$this->setTagDefine("フォーム開始",		"form",		array("stateFul"=>true,"enctype"=>"multipart/form-data","option"=>"encoding=\"multipart/form-data\" id=\"form\"","maxfilesize"=>FIRMWARE_MAX_INPUT_SIZE));
		$this->setTagDefine("フォーム終了",		"/form");

		# # # # # # # # # # # # # # # # #
		# 共通
		# # # # # # # # # # # # # # # # #

		$this->setTagDefine("リンク終了",					"/link");
		$this->setTagDefine("修正ボタン",					"button",		array("caption"=>"キャンセル","option"=>"class=\"btn btn-default\" data-dismiss=\"modal\""));
		$this->setTagDefine("いいえボタン",					"button",		array("caption"=>"いいえ","option"=>"class=\"btn btn-default\" data-dismiss=\"modal\""));

		# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #

		# # # # # # # # # # # # # # # # #
		# TCP/IP設定
		# # # # # # # # # # # # # # # # #

		$this->setTagDefine("TCPIP_LANDISK名",				"text",		array("option"=>"class=\"form-control\" id=\"TCPIP_name\""));
		$this->setTagDefine("TCPIP_コメント",				"text",		array("option"=>"class=\"form-control\" id=\"TCPIP_coment\""));
		if($this->getValue("TCPIP_DHCPモードtmp") == 1){
			$this->setTagDefine("TCPIP_DHCPモード",			"radio",	array("colSep"=>"<br>","option"=>"onClick='TCPIP_changeDHCP();TCPIP_clearIPaddress();'"));
		}else{
			$this->setTagDefine("TCPIP_DHCPモード",			"radio",	array("colSep"=>"<br>","option"=>"onClick='TCPIP_changeDHCP();'"));
		}
		$this->setTagIPAddress();

		$this->setTagDefine("TCPIP_IPアドレスtmp",			"hidden",		array("option"=>"id=\"TCPIP_ipaddress_hid\""));
		$this->setTagDefine("TCPIP_サブネットマスクtmp",	"hidden",		array("option"=>"id=\"TCPIP_subnetmask_hid\""));
		$this->setTagDefine("TCPIP_DHCPモードtmp",			"hidden",		array("option"=>"id=\"TCPIP_dhcp_hid\""));

		$this->setInputName("TCPIP_IPアドレスtmp",			"TCPIP_ipaddress_hid");
		$this->setInputName("TCPIP_サブネットマスクtmp",	"TCPIP_subnetmask_hid");
		$this->setInputName("TCPIP_DHCPモードtmp",			"TCPIP_dhcp_hid");

		$this->setTagDefine("TCPIP_WakeOnLAN",				"radio",	array("colSep"=>"<br>"));
		$this->setTagDefine("TCPIP_設定ボタン",				"button",	array("caption"=>"設定する","option"=>"id=\"TCPIP_goConfirm\" class=\"btn btn-default btn-warning\" data-toggle=\"modal\" data-target=\"#TCPIP_myModal1\" onClick=\"TCPIP_onConfirm()\""));
		$this->setTagDefine("TCPIP_登録ボタン",				"button",	array("caption"=>"OK","option"=>"class=\"btn btn-primary\" onClick=\"TCPIP_onDone()\""));

		$this->setInputName("TCPIP_DHCPモード",				"TCPIP_dhcp");
		$this->setInputName("TCPIP_LANDISK名",				"TCPIP_name");
		$this->setInputName("TCPIP_コメント",				"TCPIP_coment");
		$this->setInputName("TCPIP_WakeOnLAN",				"TCPIP_wakeonlan");

		# # # # # # # # # # # # # # # # #
		# Microsoftネットワーク設定
		# # # # # # # # # # # # # # # # #

		$this->setTagDefine("MI_参加方法の設定",		"radio");
		$this->setTagDefine("MI_ワークグループ名",		"text",		array("size"=>"24","maxlength"=>"15","option"=>"style=\"width:150px;ime-mode:disabled;\" id=\"MI_workgroup_name\""));
		$this->setTagDefine("MI_WINSサーバーアドレス",	"text",		array("size"=>"24","maxlength"=>"15","option"=>"style=\"width:150px;ime-mode:disabled;\" id=\"MI_wins_server_address\""));

		$this->setTagDefine("MI_設定ボタン",			"button",	array("caption"=>"設定する","option"=>"id=\"MI_goConfirm\" class=\"btn btn-default btn-warning\" data-toggle=\"modal\" data-target=\"#MI_myModal1\" onClick=\"MI_onConfirm()\""));
		$this->setTagDefine("MI_登録ボタン",			"button",	array("caption"=>"OK","option"=>"class=\"btn btn-primary\" onClick=\"MI_onDone()\""));

		$this->setInputName("MI_参加方法の設定",		"MI_join_mode");
		$this->setInputName("MI_ワークグループ名",		"MI_workgroup_name");
		$this->setInputName("MI_WINSサーバーアドレス",	"MI_wins_server_address");

		# # # # # # # # # # # # # # # # #
		# プロキシ設定
		# # # # # # # # # # # # # # # # #

		$this->setTagDefine("PROXY_プロキシ設定_利用区分",		"radio",	array("colSep"=>"<br>","option"=>"onClick='PROXY_chgDisp();'"));
		$this->setTagDefine("PROXY_自動設定スクリプトURL",		"text",		array("maxlength"=>"128","option"=>"style=\"width:150px;\" id=\"PROXY_auto_url\""));
		$this->setTagDefine("PROXY_プロキシ設定_アドレス",		"text",		array("maxlength"=>"128","option"=>"style=\"width:150px;\" id=\"PROXY_proxy_address\""));
		$this->setTagDefine("PROXY_プロキシ設定_ポート番号",	"text",		array("maxlength"=>"5","option"=>"style=\"width:60px;ime-mode:disabled;\" id=\"PROXY_proxy_port\""));

		$this->setTagDefine("PROXY_設定ボタン",					"button",	array("caption"=>"設定する","option"=>"id=\"PROXY_goConfirm\" class=\"btn btn-default btn-warning\" data-toggle=\"modal\" data-target=\"#PROXY_myModal1\" onClick=\"PROXY_onConfirm()\""));
		$this->setTagDefine("PROXY_登録ボタン",					"button",	array("caption"=>"OK","option"=>"class=\"btn btn-primary\" onClick=\"PROXY_onDone()\""));

		$this->setInputName("PROXY_プロキシ設定_利用区分",		"PROXY_join_mode");
		$this->setInputName("PROXY_自動設定スクリプトURL",		"PROXY_auto_url");
		$this->setInputName("PROXY_プロキシ設定_アドレス",		"PROXY_proxy_address");
		$this->setInputName("PROXY_プロキシ設定_ポート番号",	"PROXY_proxy_port");

		# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #

		# # # # # # # # # # # # # # # # #
		# 共有フォルダ設定
		# # # # # # # # # # # # # # # # #

		# 一覧
		$this->setTagDefine("SHARE_新規追加リンク開始",				"link",			array("href"=>"#","option"=>"data-toggle=\"modal\" data-target=\"#SHARE_myModal1\" onClick=\"SHARE_onInitialization()\""));

		# 新規追加・編集
		$this->setTagDefine("SHARE_共有フォルダ名",					"text",			array("maxlength"=>"12","option"=>"class=\"form-control\" id=\"SHARE_name\""));
		$this->setTagDefine("SHARE_共有フォルダコメント",			"text",			array("maxlength"=>"48","option"=>"class=\"form-control\" id=\"SHARE_comment\""));
		$this->setTagDefine("SHARE_サービス",						"checkbox",		array("colSep"=>"<br>","option"=>"onClick=\"SHARE_changeCheck()\""));
		$this->setTagDefine("SHARE_クラウドストレージ選択",			"select",		array("option"=>"id=\"SHARE_cloud\" class=\"form-control\" onchange=\"SHARE_detail_cloud_setting()\""));

		$this->setTagDefine("SHARE_Dropboxアクセストークン無効化フラグ",		"checkbox");
		$this->setTagDefine("SHARE_Dropboxコード入力",			    "text",			array("size"=>"1","option"=>"class=\"form-control\" id=\"SHARE_dbcode\""));
		$this->setTagDefine("SHARE_フレッツID",						"text",			array("size"=>"12","maxlength"=>"60","option"=>"class=\"form-control\" id=\"SHARE_fid\""));
		$this->setTagDefine("SHARE_フレッツパスワード",				"password",		array("size"=>"12","maxlength"=>"10","option"=>"class=\"form-control\" id=\"SHARE_fpass\""));
		$this->setTagDefine("SHARE_フレッツホスト",				"radio",		array("option"=>"onchange='SHARE_change_flets_link()'"));
		$this->setTagDefine("SHARE_詳細アクセス権設定",				"radio",		array("option"=>"onchange='SHARE_detail_setting()'"));

		$this->setTagDefine("SHARE_RL3SYNC_同期先共有名",			"text",			array("size"=>"12","maxlength"=>"64","option"=>"class=\"form-control\" id=\"SHARE_rl3syncfname\""));
		$this->setTagDefine("SHARE_RL3SYNC_PINコード",				"text",			array("size"=>"12","maxlength"=>"32","option"=>"class=\"form-control\" id=\"SHARE_rl3syncpincode\""));
		$this->setTagDefine("SHARE_RL3SYNC_ユーザ名",				"text",			array("size"=>"12","maxlength"=>"20","option"=>"class=\"form-control\" id=\"SHARE_rl3syncusername\""));
		$this->setTagDefine("SHARE_RL3SYNC_パスワード",				"password",		array("size"=>"12","maxlength"=>"20","option"=>"class=\"form-control\" id=\"SHARE_rl3syncpassword\""));

		$this->setTagDefine("SHARE_ごみ箱機能",					"radio");
		$this->setTagDefine("SHARE_読み取り専用区分",				"checkbox");

		$this->setTagDefine("SHARE_フレッツID_hid",					"hidden");
		$this->setTagDefine("SHARE_フレッツパスワード_hid",			"hidden");
		$this->setTagDefine("SHARE_フレッツホスト_hid",				"hidden");
		$this->setTagDefine("SHARE_RL3SYNC_同期先共有名_hid",		"hidden");
		$this->setTagDefine("SHARE_RL3SYNC_PINコード_hid",			"hidden");
		$this->setTagDefine("SHARE_RL3SYNC_ユーザ名_hid",			"hidden");
		$this->setTagDefine("SHARE_RL3SYNC_パスワード_hid",			"hidden");
		$this->setTagDefine("SHARE_クラウドストレージ選択ID_hid",	"hidden");
		$this->setTagDefine("SHARE_サービス_hid",					"hidden");

		$this->setTagDefine("SHARE_全ユーザー欄",					"hidden");
		$this->setTagDefine("SHARE_追加ユーザー欄",					"hidden");
		$this->setTagDefine("SHARE_読取追加ユーザー欄",				"hidden");
		$this->setTagDefine("SHARE_書込追加ユーザー欄",				"hidden");

		$this->setInputName("SHARE_サービス",						"SHARE_service");
		$this->setInputName("SHARE_サービス_hid",					"SHARE_service_hid");
		$this->setInputName("SHARE_クラウドストレージ選択ID_hid",	"SHARE_cloud_sel_hid");
		$this->setInputName("SHARE_Dropboxアクセストークン無効化フラグ",		"SHARE_dbrevokeflag");
		$this->setInputName("SHARE_Dropboxコード入力",			"SHARE_dbcode");
		$this->setInputName("SHARE_フレッツID",						"SHARE_fletsid");
		$this->setInputName("SHARE_フレッツID_hid",					"SHARE_fletsid_hid");
		$this->setInputName("SHARE_フレッツパスワード",				"SHARE_fletspass");
		$this->setInputName("SHARE_フレッツパスワード_hid",			"SHARE_fletspass_hid");
		$this->setInputName("SHARE_フレッツホスト",						"SHARE_fletshost");
		$this->setInputName("SHARE_フレッツホスト_hid",					"SHARE_fletshost_hid");
		$this->setInputName("SHARE_RL3SYNC_同期先共有名",			"SHARE_rl3syncother");
		$this->setInputName("SHARE_RL3SYNC_同期先共有名_hid",		"SHARE_rl3syncother_hid");
		$this->setInputName("SHARE_RL3SYNC_PINコード",				"SHARE_rl3syncpincode");
		$this->setInputName("SHARE_RL3SYNC_PINコード_hid",			"SHARE_rl3syncpincode_hid");
		$this->setInputName("SHARE_RL3SYNC_ユーザ名",				"SHARE_rl3syncuser");
		$this->setInputName("SHARE_RL3SYNC_ユーザ名_hid",			"SHARE_rl3syncuser_hid");
		$this->setInputName("SHARE_RL3SYNC_パスワード",				"SHARE_rl3syncpassword");
		$this->setInputName("SHARE_RL3SYNC_パスワード_hid",			"SHARE_rl3syncpassword_hid");
		$this->setInputName("SHARE_詳細アクセス権設定",				"SHARE_access_setting");
		$this->setInputName("SHARE_全ユーザー欄",					"SHARE_remains_user_list");
		$this->setInputName("SHARE_追加ユーザー欄",					"SHARE_add_user_list");
		$this->setInputName("SHARE_読取追加ユーザー欄",				"SHARE_read_add_user_list");
		$this->setInputName("SHARE_書込追加ユーザー欄",				"SHARE_write_add_user_list");

		$this->setInputName("SHARE_読み取り専用区分",				"SHARE_read_only");
		$this->setInputName("SHARE_ごみ箱機能",					"SHARE_trash");

		$this->setTagDefine("SHARE_フレッツ東日本リンク開始",				"link",			array("href"=>LD_FLETS_AZUKERU_EAST_URL,"target"=>"_blank"));
		$this->setTagDefine("SHARE_フレッツ西日本リンク開始",				"link",			array("href"=>LD_FLETS_AZUKERU_WEST_URL,"target"=>"_blank"));

		$this->setTagDefine("SHARE_読み取り追加ボタン",				"button",		array("caption"=>"読み取りで追加","option"=>"class=\"btn btn-sm btn-success\" data-toggle=\"button\" onClick=\"SHARE_readMoveForm();return false;\""));
		$this->setTagDefine("SHARE_読み書き追加ボタン",				"button",		array("caption"=>"読み書きで追加","option"=>"class=\"btn btn-sm  btn-info\" data-toggle=\"button\" onClick=\"SHARE_writeMoveForm();return false;\""));
		$this->setTagDefine("SHARE_共有フォルダ登録ボタン",			"button",		array("caption"=>"OK","option"=>"class=\"btn btn-primary\" onClick=\"SHARE_onInputDone()\""));

		# # # # # # # # # # # # # # # # #
		# ユーザー設定
		# # # # # # # # # # # # # # # # #

		# 一覧
		$this->setTagDefine("USER_新規追加リンク開始",		"link",			array("href"=>"#","option"=>"data-toggle=\"modal\" data-target=\"#USER_myModal1\" onClick=\"USER_onInitialization()\""));
		$this->setTagDefine("USER_すべてチェックボタン",	"button",		array("caption"=>"すべてチェック","option"=>"class=\"btn btn-success btn-sm\" onClick=\"javascript:checkAll(document.form.userChecked,true);return false;\""));
		$this->setTagDefine("USER_チェック解除ボタン",		"button",		array("caption"=>"チェック解除","option"=>"class=\"btn btn-info btn-sm\" onClick=\"javascript:checkAll(document.form.userChecked,false);return false;\""));
		$this->setTagDefine("USER_一括削除ボタン",			"button",		array("caption"=>"一括削除","option"=>"class=\"btn btn-danger btn-sm\" data-toggle=\"modal\" data-target=\"#USER_myModal2\" onClick=\"USER_get_checkbox();return false;\""));

		# 新規追加・編集
		$this->setTagDefine("USER_ユーザ名",				"text",			array("size"=>"24","maxlength"=>"20","option"=>"class=\"form-control\" id=\"USER_name\""));
		$this->setTagDefine("USER_パスワード",				"password",		array("size"=>"24","maxlength"=>"20","option"=>"class=\"form-control\" id=\"USER_password1\""));
		$this->setTagDefine("USER_確認パスワード",			"password",		array("size"=>"24","maxlength"=>"20","option"=>"class=\"form-control\" id=\"USER_password2\""));
		$this->setTagDefine("USER_ユーザー登録ボタン",		"button",		array("caption"=>"OK","option"=>"class=\"btn btn-primary\" onClick=\"USER_onInputDone()\""));

		# 削除
		$this->setTagDefine("USER_削除モード",				"hidden");
		$this->setTagDefine("USER_ユーザID",				"hidden");
		$this->setTagDefine("USER_全ユーザ",				"hidden");
		$this->setInputName("USER_削除モード",				"USER_deleteMode");
		$this->setInputName("USER_ユーザID",				"USER_userId");
		$this->setInputName("USER_全ユーザ",				"USER_allUser");
		$this->setTagDefine("USER_ユーザー削除ボタン",		"button",		array("caption"=>"はい","option"=>"class=\"btn btn-primary\" onClick=\"USER_onDelete()\""));

	# 	$this->setInputName("チェック","USER_userChecked");

		# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #

		# # # # # # # # # # # # # # # # #
		# メディアサーバー設定
		# # # # # # # # # # # # # # # # #

		if($this->getValue("Twonky除外モデル判定") == false){
			$this->setTagDefine("MEDIA_登録リンク開始",			"link",		array("href"=>"javascript:void(0)","option"=>"data-toggle=\"modal\" data-target=\"#myModal_media_add\" onClick=\"onMediaAdd()\""));
			$this->setTagDefine("MEDIA_詳細設定ボタン",			"button",	array("caption"=>__("メディアサーバー表示設定"),"option"=>"class=\"btn btn-primary\" data-toggle=\"modal\" data-target=\"#myModal_media_setting\" onClick=\"onMediaSettingInit()\""));
			$this->setTagDefine("MEDIA_データベース初期化ボタン",	"button",	array("caption"=>__("データベース初期化"),	"option"=>"class=\"btn btn-info\" data-toggle=\"modal\" data-target=\"#myModal_media_rebuild\""));
	
			# modalで使用
			$this->setTagDefine("MEDIA_一覧に戻るボタン",		"button",	array("caption"=>__("一覧に戻る"),"option"=>"class=\"btn btn-default\" data-dismiss=\"modal\""));
	//		$this->setTagDefine("MEDIA_いいえボタン",			"button",	array("caption"=>__("いいえ"),"option"=>"class=\"btn btn-default\" data-dismiss=\"modal\""));
			$this->setTagDefine("MEDIA_はいボタン"	,			"button",	array("caption"=>__("はい"),"option"=>"class=\"btn btn-primary\""));
			$this->setTagDefine("MEDIA_初期化するボタン",		"button",	array("caption"=>__("はい"),"option"=>"class=\"btn btn-primary\" onClick=\"onMediaRebuild('".C4_HOME_URL."')\""));
	
			# 追加
	//		$this->setItemName("MEDIA_追加_共有フォルダID",		"share_without_media");
	//		$this->setInputName("MEDIA_追加_共有フォルダID",	"media_shareMediaId");
	//		$this->setTagDefine("MEDIA_追加_共有フォルダID",	"select",	array("blank"=>__("共有フォルダー"),"option"=>"class=\"form-control\" id=\"MEDIA_id_shareMediaID\""));
			$this->setTagDefine("MEDIA_追加するボタン",			"button",	array("caption"=>__("設定する"),"option"=>"class=\"btn btn-primary\" onClick=\"onMediaAddSetting('".C4_HOME_URL."')\""));
	
			# 表示設定
			$this->setItemName("MEDIA_カテゴリ表示",			"category_media");
			$this->setInputName("MEDIA_カテゴリ表示",			"media_display_category");
			$this->setTagDefine("MEDIA_カテゴリ表示",			"radio",	array("colSep"=>"<br>"));
			$this->setTagDefine("MEDIA_設定するボタン",			"button",	array("caption"=>__("設定する"),"option"=>"class=\"btn btn-primary\" onClick=\"onMediaSetting('".C4_HOME_URL."')\""));
	
			# TWONKY設定
			#$this->setTagDefine("MEDIA_TWONKY設定ボタン",	"button",	array("caption"=>__("Twonky設定"),	"option"=>"class=\"btn btn-info\" data-toggle=\"modal\" data-target=\"#myModal_media_twonky\""));
			#$this->setTagDefine("MEDIA_TWONKY設定へボタン",			"button",	array("caption"=>__("Twonky設定へ"),"option"=>"class=\"btn btn-default\" onClick=\"window.open('" . $this->getValue("TWONKY_アドレス") . "', '_blank')\" class=\"btn btn-primary\""));
			# e-onkyo設定
			$this->setTagDefine("MEDIA_TWONKY設定ボタン",	"button",	array("caption"=>__("Twonky設定/e-onkyoミュージックダウンローダー設定"),	"option"=>"class=\"btn btn-info\" data-toggle=\"modal\" data-target=\"#myModal_media_twonky\""));
			$this->setTagDefine("MEDIA_TWONKY設定へボタン",			"button",	array("caption"=>__("e-onkyoミュージックダウンローダー設定へ"),"option"=>"class=\"btn btn-default\" onClick=\"window.open('" . $this->getValue("EONKYO_アドレス") . "', '_blank')\" class=\"btn btn-primary\""));
	
			# mDNS設定
			$this->setTagDefine("MEDIA_mDNS設定ボタン",			"button",	array("caption"=>__("mDNS設定"),"option"=>"class=\"btn btn-primary\" data-toggle=\"modal\" data-target=\"#myModal_media_mdns\" onClick=\"onMediaMdnsInit()\""));
			$this->setItemName("MEDIA_mDNS設定",				"mdns_media");
			$this->setInputName("MEDIA_mDNS設定",				"media_mdns");
			$this->setIsArray("MEDIA_mDNS設定",true);
			$this->setTagDefine("MEDIA_mDNS設定",				"checkbox", array("colSep"=>"<br>"));
			$this->setTagDefine("MEDIA_mDNS_一覧に戻るボタン",		"button",	array("caption"=>__("一覧に戻る"),"option"=>"class=\"btn btn-default\" data-dismiss=\"modal\""));
			$this->setTagDefine("MEDIA_mDNS_設定するボタン",			"button",	array("caption"=>__("設定する"),"option"=>"class=\"btn btn-primary\" onClick=\"onMediaMdns('".C4_HOME_URL."')\""));
		}
		
		# # # # # # # # # # # # # # # # #
		# RemoteLink3設定
		# # # # # # # # # # # # # # # # #
		$this->loadConfig("remotelink3");

		$this->setValue("RL3_利用開始ボタン",				"rl3_start");
//		$this->setTagDefine("RL3_利用開始ボタン",			"button",	array("caption"=>__("利用を開始する"),"option"=>"class=\"btn btn-info\" onClick=\"onRl3Start('".C4_HOME_URL."','".$this->getValue("RL3_利用開始ボタン")."')\""));
		$this->setTagDefine("RL3_利用開始ボタン",			"button",	array("caption"=>__("利用を開始する"),"option"=>"class=\"btn btn-info\" onClick=\"onRl3Setting('".C4_HOME_URL."','".$this->getValue("RL3_利用開始ボタン")."')\""));

		$this->setInputName("RL3_利用区分",					"rl3_enabled");
		$this->setTagDefine("RL3_利用区分",					"radio",	array("colSep"=>"<br>","option"=>"onClick=\"rl3_use()\""));

		$this->setTagDefine("RL3_詳細設定ボタン",			"button",	array("caption"=>__("詳細設定"),"option"=>"class=\"btn btn-success\" onClick=\"rl3_detail()\""));

		$this->setInputName("RL3_リモートアクセスポート番号1",	"rl3_remoteAccess_port1");
		$this->setTagDefine("RL3_リモートアクセスポート番号1",	"text",		array("size"=>"60","maxlength"=>"5","option"=>"style=\"width:60px;ime-mode:disabled;\""));
		$this->setInputName("RL3_リモートアクセスポート番号2",	"rl3_remoteAccess_port2");
		$this->setTagDefine("RL3_リモートアクセスポート番号2",	"text",		array("size"=>"60","maxlength"=>"5","option"=>"style=\"width:60px;ime-mode:disabled;\""));

		$this->setInputName("RL3_ポート初期値1",			"rl3_button1");
		$this->setTagDefine("RL3_ポート初期値1",			"button",	array("caption"=>__("初期値"),"option"=>"class=\"btn btn-primary\" onClick=\"document.form.rl3_remoteAccess_port1.value='".$this->getValue("RL3_リモートアクセスポート番号1初期値")."'\""));

		$this->setInputName("RL3_ポート初期値2",			"rl3_button2");
		$this->setTagDefine("RL3_ポート初期値2",			"button",	array("caption"=>__("初期値"),"option"=>"class=\"btn btn-primary\" onClick=\"document.form.rl3_remoteAccess_port2.value='".$this->getValue("RL3_リモートアクセスポート番号2初期値")."'\""));

		$this->setInputName("RL3_UPNP機能利用",				"rl3_useupnp");
		$this->setTagDefine("RL3_UPNP機能利用",				"radio",	array("colSep"=>"<br>"));

		$this->setInputName("RL3_外部ポート区分",			"rl3_externalAccess_enabled");
		$this->setTagDefine("RL3_外部ポート区分",			"radio",	array("colSep"=>"<br>","option"=>"onClick=\"rl3_externalAccess()\""));

		$this->setInputName("RL3_外部ポート番号1",			"rl3_externalAccess_port1");
		$this->setTagDefine("RL3_外部ポート番号1",			"text",		array("size"=>"60","maxlength"=>"5","option"=>"style=\"width:60px;ime-mode:disabled;\""));
		$this->setInputName("RL3_外部ポート番号2",			"rl3_externalAccess_port2");
		$this->setTagDefine("RL3_外部ポート番号2",			"text",		array("size"=>"60","maxlength"=>"5","option"=>"style=\"width:60px;ime-mode:disabled;\""));

		$this->setInputName("RL3_外部ポート初期値1",		"rl3_externalAccess_button1");
		$this->setTagDefine("RL3_外部ポート初期値1",		"button",	array("caption"=>__("初期値"),"option"=>"class=\"btn btn-primary\" onClick=\"document.form.rl3_externalAccess_port1.value='".LD_EXTERNALACCESS_RL3_PORT1."'\""));

		$this->setInputName("RL3_外部ポート初期値2",		"rl3_externalAccess_button2");
		$this->setTagDefine("RL3_外部ポート初期値2",		"button",	array("caption"=>__("初期値"),"option"=>"class=\"btn btn-primary\" onClick=\"document.form.rl3_externalAccess_port2.value='".LD_EXTERNALACCESS_RL3_PORT2."'\""));

		$this->setInputName("RL3_リモートUI利用",			"rl3_use_remoteUi");
		$this->setTagDefine("RL3_リモートUI利用",			"radio",	array("colSep"=>"<br>"));

		$this->setInputName("RL3_PINコード変更",			"rl3_change_pincode");
		$this->setTagDefine("RL3_PINコード変更",			"checkbox");

		$this->setInputName("RL3_利用ユーザー",			"rl3_qrcode_user");
		$this->setTagDefine("RL3_利用ユーザー",			"select",	array("blank"=>"　　　"));

		$this->setTagDefine("RL3_確認するボタン",			"button",	array("caption"=>__("設定する"),"option"=>"class=\"btn btn-warning\" onClick=\"onRl3Confirm();\""));

		# modalで使用
		$this->setTagDefine("RL3_修正するボタン",			"button",	array("caption"=>__("修正する"),"option"=>"class=\"btn btn-default\" data-dismiss=\"modal\""));
		$this->setValue("RL3_設定するボタン",				"rl3_setting");
		$this->setTagDefine("RL3_設定するボタン",			"button",	array("caption"=>__("ＯＫ"),"option"=>"class=\"btn btn-primary\" onclick=\"onRl3Setting('".C4_HOME_URL."','".$this->getValue("RL3_設定するボタン")."')\""));

		# IOBBNET登録ボタン
		$this->setInputName("portalフォーム開始",				"portal_form");
		$this->setTagDefine("portalフォーム開始",				"form",		array("action"=>RL3_REGIST_INDEX_URL,"target"=>"_blank","option"=>"id=\"portal_form\""));
		$this->setTagDefine("RL3_IOPortal登録ボタン",			"button",	array("name"=>"btn_notiry","caption"=>__("本サービスを登録する"),"option"=>"data-disable-with=\"処理中\" class=\"btn btn-info\" data-dismiss=\"modal\" onClick=\"document.getElementById('portal_form').submit();\""));
		$this->setInputName("暗号化MAC",						"mac-address");
		$this->setTagDefine("暗号化MAC",						"hidden");
		$this->setInputName("暗号化PINコード",					"pin-code");
		$this->setTagDefine("暗号化PINコード",					"hidden");
		$this->setInputName("暗号化サービスID",					"service-id");
		$this->setTagDefine("暗号化サービスID",					"hidden");
		$this->setInputName("エンコードタイプ",					"enc-type");
		$this->setTagDefine("エンコードタイプ",					"hidden");
		$this->setInputName("RL3_PINCODE",						"rl3_pincode");
		$this->setTagDefine("RL3_PINCODE",						"text");

		# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #

		# # # # # # # # # # # # # # # # #
		# 時刻設定
		# # # # # # # # # # # # # # # # #

		$this->setTagDefine("TIME_設定時刻_年",				"select",	array("blank"=>__('年'),"option"=>"id=\"TIME_year\" style=\"ime-mode:disabled;\" class=\"form-control select2\""));
		$this->setTagDefine("TIME_設定時刻_月",				"select",	array("blank"=>__('月'),"option"=>"id=\"TIME_month\" style=\"ime-mode:disabled;\" class=\"form-control select2\""));
		$this->setTagDefine("TIME_設定時刻_日",				"select",	array("blank"=>__('日'),"option"=>"id=\"TIME_day\" style=\"ime-mode:disabled;\" class=\"form-control select2\""));
		$this->setTagDefine("TIME_設定時刻_時",				"select",	array("blank"=>"","option"=>"id=\"TIME_hour\" style=\"ime-mode:disabled;\" class=\"form-control select2\""));
		$this->setTagDefine("TIME_設定時刻_分",				"select",	array("blank"=>"","option"=>"id=\"TIME_minute\" style=\"ime-mode:disabled;\" class=\"form-control select2\""));

		$this->setTagDefine("TIME_タイムサーバ同期区分",	"radio",	array("option"=>"onClick='TIME_time()'"));
		$this->setTagDefine("TIME_タイムサーバホスト名",	"text",		array("size"=>"24","maxlength"=>"128","option"=>"disabled style=\"ime-mode:disabled;\" id=\"TIME_time_host\""));
		$this->setTagDefine("TIME_同期タイミング",			"checkbox",	array("colSep"=>"<br>","option"=>"onClick='TIME_timing()' disabled"));
		$this->setTagDefine("TIME_同期タイミング_時",		"select",	array("option"=>"class=\"form-control select2\""));
		$this->setTagDefine("TIME_同期タイミング_分",		"select",	array("option"=>"class=\"form-control select2\""));
		$this->setTagDefine("TIME_対応タイムゾーン",		"select",	array("option"=>"class=\"form-control\""));

		# 設定値取得前に設定実行されるケースへの対策として初期値設定
		$this->setValue("TIME_対応タイムゾーン", "-9");

		$this->setInputName("TIME_設定時刻_年",				"TIME_year");
		$this->setInputName("TIME_設定時刻_月",				"TIME_month");
		$this->setInputName("TIME_設定時刻_日",				"TIME_day");
		$this->setInputName("TIME_設定時刻_時",				"TIME_hour");
		$this->setInputName("TIME_設定時刻_分",				"TIME_minute");
		$this->setInputName("TIME_タイムサーバ同期区分",	"TIME_time_server");
		$this->setInputName("TIME_タイムサーバホスト名",	"TIME_time_host");
		$this->setInputName("TIME_同期タイミング",			"TIME_timing");
		$this->setInputName("TIME_同期タイミング_時",		"TIME_timing_h");
		$this->setInputName("TIME_同期タイミング_分",		"TIME_timing_m");
		$this->setInputName("TIME_対応タイムゾーン",		"TIME_time_zone");

		$this->setTagDefine("TIME_PC時刻設定ボタン",		"button",	array("caption"=>__('pcの時刻を設定'),"option"=>"class=\"btn btn-info\" onClick=\"TIME_set_client_time()\""));
		$this->setTagDefine("TIME_設定ボタン",				"button",	array("caption"=>"設定する","option"=>"id=\"TIME_goConfirm\" class=\"btn btn-warning\" data-dismiss=\"modal\" onClick=\"TIME_onSetting()\""));

		# # # # # # # # # # # # # # # # #
		# 省電力設定
		# # # # # # # # # # # # # # # # #

		$this->setTagDefine("SPINDOWN_省電力モード",			"radio",	array("option"=>"onClick=SPINDOWN_modeChange()"));
		$this->setTagDefine("SPINDOWN_省電力モード切替時間",	"select",	array("blank"=>"","option"=>"class=\"form-control\" disabled"));

		$this->setInputName("SPINDOWN_省電力モード",			"SPINDOWN_spindown_mode");
		$this->setInputName("SPINDOWN_省電力モード切替時間",	"SPINDOWN_spindown_time");

		$this->setTagDefine("SPINDOWN_設定ボタン",				"button",	array("caption"=>"設定する","option"=>"id=\"SPINDOWN_goConfirm\" class=\"btn btn-warning\" data-dismiss=\"modal\" onClick=\"SPINDOWN_onSetting()\""));

		# # # # # # # # # # # # # # # # #
		# フォーマット
		# # # # # # # # # # # # # # # # #

		$this->setTagDefine("FORMAT_フォーマットディスク",	"radio");
		$this->setInputName("FORMAT_フォーマットディスク",	"FORMAT_disk_format");

		$this->setTagDefine("FORMAT_設定ボタン",			"button",	array("caption"=>"確認する","option"=>"id=\"FORMAT_goConfirm\" class=\"btn btn-default btn-warning\" data-toggle=\"modal\" data-target=\"#FORMAT_myModal1\" onClick=\"FORMAT_onConfirm()\""));
		$this->setTagDefine("FORMAT_登録ボタン",			"button",	array("caption"=>"OK","option"=>"class=\"btn btn-primary\" onClick=\"FORMAT_onDone()\""));

		# # # # # # # # # # # # # # # # #
		# チェックディスク
		# # # # # # # # # # # # # # # # #

		$this->setTagDefine("CHECKDISK_ボリューム選択",	"radio");
		$this->setInputName("CHECKDISK_ボリューム選択",	"CHECKDISK_sel_volume");

		$this->setTagDefine("CHECKDISK_設定ボタン",		"button",	array("caption"=>"確認する","option"=>"id=\"CHECKDISK_goConfirm\" class=\"btn btn-default btn-warning\" data-toggle=\"modal\" data-target=\"#CHECKDISK_myModal1\" onClick=\"CHECKDISK_onConfirm()\""));
		$this->setTagDefine("CHECKDISK_登録ボタン",		"button",	array("caption"=>"OK","option"=>"class=\"btn btn-primary\" onClick=\"CHECKDISK_onDone()\""));

		# # # # # # # # # # # # # # # # #
		# システム初期化
		# # # # # # # # # # # # # # # # #

		$this->setTagDefine("INIT_ボリュームクリア判定区分",	"radio",	array("colSep"=>"<br>"));
		$this->setInputName("INIT_ボリュームクリア判定区分",	"INIT_volume_clear");

		$this->setTagDefine("INIT_設定ボタン",					"button",	array("caption"=>"確認する","option"=>"id=\"INIT_goConfirm\" class=\"btn btn-default btn-warning\" data-toggle=\"modal\" data-target=\"#INIT_myModal1\" onClick=\"INIT_onConfirm()\""));
		$this->setTagDefine("INIT_登録ボタン",					"button",	array("caption"=>"消去","option"=>"class=\"btn btn-primary\" onClick=\"INIT_onDone()\""));

		# # # # # # # # # # # # # # # # #
		# ファームウェア
		# # # # # # # # # # # # # # # # #

		$this->setTagDefine("FIRMWARE_ファームウェアファイル",				"file",		array("option"=>"id=\"FIRMWARE_firmware_file\" class=\"font11\""));
		$this->setTagDefine("FIRMWARE_ファームウェア自動アップデート機能",	"checkbox");
		$this->setTagDefine("FIRMWARE_ファームウェア定期自動アップデート機能",	"radio",	array("colSep"=>"<br>","option"=>"id=\"FIRMWARE_firmware_everyday\""));
		$this->setTagDefine("FIRMWARE_ファーム確認_時刻指定",	"checkbox",	array("option"=>"onClick='FIRMWARE_check_time_onclick()'"));
		$this->setTagDefine("FIRMWARE_ファーム確認_時間",		"select",	array("option"=>"class=\"form-control select2\""));
		$this->setTagDefine("FIRMWARE_ファーム確認_分",		"select",	array("option"=>"class=\"form-control select2\""));

		$this->setInputName("FIRMWARE_ファームウェアファイル",				"FIRMWARE_firmware_file");
		$this->setInputName("FIRMWARE_ファームウェア自動アップデート機能",	"FIRMWARE_firmware_shutdown");
		$this->setInputName("FIRMWARE_ファームウェア定期自動アップデート機能",	"FIRMWARE_firmware_everyday");
		$this->setInputName("FIRMWARE_ファーム確認_時刻指定",	"FIRMWARE_firmware_check_time");
		$this->setInputName("FIRMWARE_ファーム確認_時間",		"FIRMWARE_firmware_check_hour");
		$this->setInputName("FIRMWARE_ファーム確認_分",		"FIRMWARE_firmware_check_minute");

		$this->setTagDefine("FIRMWARE_ファームウェア機能有効_hid",			"hidden",	array("option"=>"id=\"FIRMWARE_start\""));
		$this->setInputName("FIRMWARE_ファームウェア機能有効_hid",			"FIRMWARE_start");

		$this->setTagDefine("FIRMWARE_ダウンロードアップデートボタン",		"button",	array("caption"=>"アップデートを開始する","option"=>"id=\"FIRMWARE_goConfirm\" class=\"btn btn-info font11\" data-toggle=\"modal\" data-target=\"#FIRMWARE_myModal1\""));
		$this->setTagDefine("FIRMWARE_指定アップデートボタン",				"button",	array("caption"=>"アップデートを開始する","option"=>"id=\"FIRMWARE_goConfirm\" class=\"btn btn-info font11\" data-toggle=\"modal\" data-target=\"#FIRMWARE_myModal2\" onClick=\"FIRMWARE_select_onConfirm()\""));
		$this->setTagDefine("FIRMWARE_ダウンロード登録ボタン",				"button",	array("caption"=>"OK","option"=>"class=\"btn btn-primary\" onClick=\"FIRMWARE_download_onUpdate()\""));
		$this->setTagDefine("FIRMWARE_指定登録ボタン",						"button",	array("caption"=>"OK","option"=>"class=\"btn btn-primary\" onClick=\"FIRMWARE_select_onUpdate()\""));

		$this->setTagDefine("FIRMWARE_設定ボタン",							"button",	array("caption"=>"設定する","option"=>"class=\"btn btn-default btn-warning\" onClick=\"FIRMWARE_onDone()\""));

		# # # # # # # # # # # # # # # # #
		# シャットダウン
		# # # # # # # # # # # # # # # # #

		$this->setTagDefine("SHUTDOWN_シャットダウンモード",	"radio",	array("colSep"=>"<br>"));
		$this->setInputName("SHUTDOWN_シャットダウンモード",	"SHUTDOWN_shutdown_mode");

		$this->setTagDefine("SHUTDOWN_設定ボタン",				"button",	array("caption"=>"確認する","option"=>"id=\"SHUTDOWN_goConfirm\" class=\"btn btn-default btn-warning\" data-toggle=\"modal\" data-target=\"#SHUTDOWN_myModal1\" onClick=\"SHUTDOWN_onConfirm()\""));
		$this->setTagDefine("SHUTDOWN_登録ボタン",				"button",	array("caption"=>"OK","option"=>"class=\"btn btn-primary\" onClick=\"SHUTDOWN_onDone()\""));

		# # # # # # # # # # # # # # # # #
		# ログ表示
		# # # # # # # # # # # # # # # # #

		$this->setTagDefine("ログ削除ボタン",	"button",	array("caption"=>"ログクリア","option"=>"id=\"LOG_goConfirm\" class=\"btn btn-warning\" data-dismiss=\"modal\" onClick=\"LOG_onSetting()\""));

		# # # # # # # # # # # # # # # # #
		# 管理者パスワード
		# # # # # # # # # # # # # # # # #

# 		$this->setTagDefine("PASSWORD_現パスワード",		"password",	array("maxlength"=>"20","option"=>"class=\"form-control\" id=\"PASSWORD_now_password\""));
# 		$this->setTagDefine("PASSWORD_新パスワード",		"password",	array("maxlength"=>"20","option"=>"class=\"form-control\" id=\"PASSWORD_new_password1\""));
# 		$this->setTagDefine("PASSWORD_確認パスワード",		"password",	array("maxlength"=>"20","option"=>"class=\"form-control\" id=\"PASSWORD_new_password2\""));
		$this->setTagDefine("PASSWORD_現パスワード",		"password",	array("maxlength"=>"20","option"=>"class=\"input1\" id=\"PASSWORD_now_password\""));
		$this->setTagDefine("PASSWORD_新パスワード",		"password",	array("maxlength"=>"20","option"=>"class=\"input1\" id=\"PASSWORD_new_password1\""));
		$this->setTagDefine("PASSWORD_確認パスワード",		"password",	array("maxlength"=>"20","option"=>"class=\"input1\" id=\"PASSWORD_new_password2\""));

		$this->setInputName("PASSWORD_現パスワード",		"PASSWORD_now_password");
		$this->setInputName("PASSWORD_新パスワード",		"PASSWORD_new_password1");
		$this->setInputName("PASSWORD_確認パスワード",		"PASSWORD_new_password2");

		$this->setTagDefine("PASSWORD_設定ボタン",			"button",	array("caption"=>"設定する","option"=>"id=\"PASSWORD_goConfirm\" class=\"btn btn-warning\" data-dismiss=\"modal\" onClick=\"PASSWORD_onSetting()\""));

		# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # #
	}

	# パスワード比較
	function checkPassword($user,$pass){
		exec("sudo cat /etc/shadow|grep -w '".$user."'",$str,$result);
		if($result != 0) return false;

		$spass = explode(":",$str[0]);
		$salt  = explode("$",$spass[1]);
		$cpass = crypt($pass,"$1$".$salt[2]."$");


		if($cpass != $spass[1]){
			return false;
		}
		return true;
	}
}
new main();

?>


