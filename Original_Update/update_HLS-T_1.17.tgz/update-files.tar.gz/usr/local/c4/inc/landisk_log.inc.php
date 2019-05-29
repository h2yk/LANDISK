<?
# ============================================================================== #
# ログ出力内容返却
# ============================================================================== #
function GET_LOG_STRING($ident,$message=null){
	$log_string = array(
				# パスワード認証
				"auth" => array(
							"logon" 		=> __("ログオン：<MESSAGE>"),
							"logoff" 		=> __("ログオフ：<MESSAGE>"),
							"error" 		=> __("ログオン失敗：<MESSAGE>")
						),
				# 管理者パスワード変更
				"password" => array(
							"edit" 			=> __("管理者パスワード：変更")
						),
				# はじめて設定
				"begin" => array(
							"edit" 			=> __("はじめて設定：変更")
						),
				# ネットワーク設定
				"network" => array(
							"edit" 			=> __("ネットワーク設定：変更")
						),
				# ユーザー設定
				"user" => array(
							"add" 			=> __("ユーザー：登録：<MESSAGE>"),
							"add_error" 		=> __("ユーザー：登録エラー：<MESSAGE>"),
							"edit" 			=> __("ユーザー：パスワード変更：<MESSAGE>"),
							"edit_error" 		=> __("ユーザー：パスワード変更エラー：<MESSAGE>"),
							"delete" 		=> __("ユーザー：削除：<MESSAGE>"),
							"delete_error" 		=> __("ユーザー：削除エラー：<MESSAGE>")
						),
				# グループ設定
				"group" => array(
							"add" 			=> __("グループ：登録：<MESSAGE>"),
							"add_error" 		=> __("グループ：登録エラー：<MESSAGE>"),
							"edit" 			=> __("グループ：変更：<MESSAGE>"),
							"edit_error" 		=> __("グループ：変更エラー：<MESSAGE>"),
							"delete" 		=> __("グループ：削除：<MESSAGE>"),
							"delete_error" 		=> __("グループ：削除エラー：<MESSAGE>")
						),
				# 共有フォルダー設定
				"share" => array(
							"add" 			=> __("共有フォルダー：作成：<MESSAGE>"),
							"add_error" 		=> __("共有フォルダー：作成エラー：<MESSAGE>"),
							"edit" 			=> __("共有フォルダー：変更：<MESSAGE>"),
							"edit_error" 		=> __("共有フォルダー：変更エラー：<MESSAGE>"),
							"delete" 		=> __("共有フォルダー：削除：<MESSAGE>"),
							"delete_error" 		=> __("共有フォルダー：削除エラー：<MESSAGE>")
							# "notfound"		=> "共有フォルダー：検出エラー：<MESSAGE>"
						),
				# DLNA設定
				"dlna" => array(
							"add" 			=> __("DLNA：公開：<MESSAGE>"),
							"delete" 		=> __("DLNA：公開終了：<MESSAGE>"),
							"access_edit" 		=> __("DLNA：アクセス許可設定：変更"),
							"detail"		=> __("DLNA：DLNA表示設定：変更")
						),
# 2014/06/17 ADD START by seto
				# MEDIA SERVER設定
				"media" => array(
							"add"			=> __("メディアサーバー：公開：<MESSAGE>"),
							"delete"		=> __("メディアサーバー：公開終了：<MESSAGE>"),
							"rebuild"		=> __("メディアサーバー：データベース初期化：開始"),
							"rebuild_end"		=> __("メディアサーバー：データベース初期化：終了"),
							"rebuild_error"		=> __("メディアサーバー：データベース初期化：失敗"),
							"detail"		=> __("メディアサーバー：メディアサーバー表示設定：変更"),
							"mdns"			=> __("メディアサーバー：mDNS設定：変更")
						),
# 2014/06/17 ADD END by seto
				# iTunes設定
				"itunes" => array(
							"add" 			=> __("iTunes：公開：<MESSAGE>"),
							"delete" 		=> __("iTunes：公開終了：<MESSAGE>"),
							"update_start" 		=> __("iTunes：データベース更新：開始"),
							"update_end" 		=> __("iTunes：データベース更新：終了"),
							"edit"			=> __("iTunes更新設定：変更")
						),
#				# デジカメコピー設定
#				"copy" => array(
#							"edit"			=> "デジカメコピー設定：変更"
#						),
				# ＵＳＢポートモード
				"usb_port" => array(
							"degicame" 		=> __("USBポートモード：設定：デジカメコピーモード"),
							"quick"			=> __("USBポートモード：設定：クイックコピーモード"),
							"share"			=> __("USBポートモード：設定：共有モード"),
							"server"		=> __("USBポートモード：設定：net.USBモード")
						),
				# デジカメコピー処理
				"digicam_copy" => array(
							"start"			=> __("デジカメコピー：開始"),
							"end"			=> __("デジカメコピー：終了"),
							"edit"			=> __("デジカメコピー：設定変更"),
							"error"			=> __("デジカメコピー：異常終了")
						),
				# クイックコピー処理
				"datetime_copy" => array(
							"start"			=> __("クイックコピー：開始"),
							"end"			=> __("クイックコピー：終了"),
							"edit"			=> __("クイックコピー：設定変更"),
							"error"			=> __("クイックコピー：異常終了")
						),
# 				# バックアップディスク登録
# 				"backup_disk" => array(
# 							"add"			=> "バックアップディスク：登録",
# 							"error"			=> "バックアップディスク：登録失敗"
# 						),
# 				# デジカメバックアップ処理
# 				"digicam_backup" => array(
# 							"start"			=> "バックアップ：開始",
# 							"end"			=> "バックアップ：終了",
# 							"error"			=> "バックアップ：異常終了"
# 						),
				# USBフォーマット処理
				"usb_format" => array(
							"start" 		=> __("USBフォーマット：開始"),
							"end" 			=> __("USBフォーマット：終了"),
							"error" 		=> __("USBフォーマット：異常終了")
						),
# 				# リストア処理
# 				"digicam_restore" => array(
# 							"start"			=> "リストア：開始",
# 							"end"			=> "リストア：終了",
# 							"error"			=> "リストア：異常終了"
# 						),
				# 時刻設定
				"time" => array(
							"edit" 			=> __("時刻設定：変更"),
							"sync" 			=> __("タイムサーバー：同期：<MESSAGE>"),
							"error" 		=> __("タイムサーバー：同期失敗：<MESSAGE>")
						),
				# 省電力設定
				"spindown" => array(
							"edit" 			=> __("省電力設定：変更")
						),
				# チェックディスク処理
				"checkdisk" => array(
							"start" 		=> __("チェックディスク：開始"),
							"ok" 			=> __("チェックディスク：異常なし"),
							"ng" 			=> __("チェックディスク：エラー発見"),
							"error" 		=> __("チェックディスク：異常終了")
						),
				# ファームウェア更新処理
				"firmware" => array(
							"start" 		=> __("ファームウェア：更新：<MESSAGE>"),
							"edit" 			=> __("ファームウェア設定：変更"),
							"error"			=> __("ファームウェア：更新失敗：<MESSAGE>")
						),
				# メール基本設定
				"mail" => array(
							"edit" 			=> __("メール基本設定：変更")
						),
				# メールイベント設定
				"event_mail" => array(
							"edit" 			=> __("メールイベント設定：変更")
						),
# 				# フォトアルバム処理（アルバム）
# 				"album" => array(
# 							"add" 			=> "アルバム：作成：<MESSAGE>",
# 							"add_error" 		=> "アルバム：作成失敗：<MESSAGE>",
# 							"edit" 			=> "アルバム：変更：<MESSAGE>",
# 							"edit_error" 		=> "アルバム：変更失敗：<MESSAGE>",
# 							"delete" 		=> "アルバム：削除：<MESSAGE>",
# 							"delete_error" 		=> "アルバム：削除失敗：<MESSAGE>"
# 						),
# 				# フォトアルバム処理（画像）
# 				"photo" => array(
# 							"add" 			=> "フォト：追加：<MESSAGE>",
# 							"add_error" 		=> "フォト：追加失敗：<MESSAGE>",
# 							"delete" 		=> "フォト：削除：<MESSAGE>",
# 							"delete_error" 		=> "フォト：削除失敗：<MESSAGE>"
# 						),
				# USB処理
				"usb" => array(
							"start" 		=> __("USBデバイス：接続：<MESSAGE>"),
							"end" 			=> __("USBデバイス：切断：<MESSAGE>"),
							"timeout" 		=> __("USBデバイス：タイムアウト：<MESSAGE>"),
							"error" 		=> __("USBデバイス：マウント失敗：<MESSAGE>")
						),
				# リセット処理
				"reset" => array(
							"start" 		=> __("リセット：実行")
						),
				# システム起動
				"system" => array(
							"start" 		=> __("システム：起動"),
							"end" 			=> __("システム：終了"),
							"error" 		=> __("システム：マウント失敗"),
							"apache"		=> __("システム：エラー：<MESSAGE>"),
							# "notntfs"		=> "システム：エラー：<MESSAGE>",
							"stopfan"		=> __("システム：FAN停止"),
							"temperr"		=> __("システム：温度異常")
						),
				# テストメール
				"test_mail" => array(
							"success" 		=> __("テストメール：送信：<MESSAGE>"),
							"failure" 		=> __("テストメール：送信失敗：<MESSAGE>")
						),
				# ログメール
				"log_mail" => array(
							"success" 		=> __("ログメール：送信：<MESSAGE>"),
							"failure" 		=> __("ログメール：送信失敗：<MESSAGE>")
						),
				# お知らせメール
				"error_mail" => array(
							"success" 		=> __("お知らせメール：送信：<MESSAGE>"),
							"failure" 		=> __("お知らせメール：送信失敗：<MESSAGE>")
						),
				# DHCP自動取得
				"dhcp" => array(
							"success" 		=> __("DHCP自動取得：成功"),
							"failure" 		=> __("DHCP自動取得：失敗")
						),
				# アクティブリペア設定
				"activerepair" => array(
							"edit" 			=> __("アクティブリペアー設定：変更"),
							"start" 		=> __("アクティブリペアー：開始"),
							"end" 			=> __("アクティブリペアー：終了"),
							"stop" 			=> __("アクティブリペアー：中断"),
							"repair_error"		=> __("アクティブリペアー：異常あり"),
							"skip"			=> __("アクティブリペアー：スキップ：<MESSAGE>"),
							"start_error"		=> __("アクティブリペアー：実行失敗")
						),
				# フォーマット処理
				"format" => array(
							"start" 		=> __("本体フォーマット：開始"),
							"end" 			=> __("本体フォーマット：終了"),
							"error" 		=> __("本体フォーマット：異常終了")
						),
				# RAID 0 フォーマット
				"raid0format" => array(
							"start" 		=> __("RAID 0 フォーマット：開始"),
							"end" 			=> __("RAID 0 フォーマット：終了"),
							"error" 		=> __("RAID 0 フォーマット：異常終了")
						),
				# RAID 1 フォーマット
				"raid1format" => array(
							"start" 		=> __("RAID 1 フォーマット：開始"),
							"end" 			=> __("RAID 1 フォーマット：終了"),
							"error" 		=> __("RAID 1 フォーマット：異常終了")
						),
				# UPS設定
				"ups" => array(
							"edit" 			=> __("UPS設定：変更"),
							"start" 		=> __("UPS監視：開始"),
							"stop" 			=> __("UPS監視：停止"),
							"error" 		=> __("UPS監視：UPS状態を確認してください"),
							"onbattery"		=> __("UPS監視：バッテリーでの運用を開始"),
							"mainsback"		=> __("UPS監視：商用電源での運用に復旧"),
							"timeout"		=> __("UPS監視：停電後、指定した経過時間を超えました"),
							"loadlimit"		=> __("UPS監視：UPS のバッテリーローを検出しました")
						),
				# バックアップ
				"backup"=> array(
							"edit"			=> __("バックアップ設定：変更"),
							"start"			=> __("バックアップ：開始"),
							"end"			=> __("バックアップ：終了"),
							"error"			=> __("バックアップ：失敗")
						),
				# ネットバックアップ
				"netbackup"=> array(
							"edit"			=> __("ネットワークバックアップ設定：変更"),
							"start"			=> __("ネットワークバックアップ：開始"),
							"end"			=> __("ネットワークバックアップ：終了"),
							"end_error"		=> __("ネットワークバックアップ：異常終了"),
							"error"			=> __("ネットワークバックアップ：失敗：<MESSAGE>")
						),
				# RAIDイベント処理
				"mdadm_handler" => array(
							"RebuildStarted"	=> __("RAID監視：再構築：開始"),
							"Rebuild20"		=> __("RAID監視：再構築：20%完了"),
							"Rebuild40"		=> __("RAID監視：再構築：40%完了"),
							"Rebuild60"		=> __("RAID監視：再構築：60%完了"),
							"Rebuild80"		=> __("RAID監視：再構築：80%完了"),
							"RebuildFinished"	=> __("RAID監視：再構築：終了"),
							"CrashFinished"		=> __("RAID監視：再構築：異常終了"),
							"Fail"			=> __("RAID監視：ディスクエラー：<MESSAGE>"),
							"FailSpare"		=> __("RAID監視：回復不能エラー：<MESSAGE>")
						),
				# RAID起動処理
				"raid_init" => array(
							"degrade"		=> __("RAID監視：起動時ディスクエラー：<MESSAGE>"),
							"crash"			=> __("RAID監視：崩壊"),
							"errorcount"		=> __("RAID監視：<MESSAGE>：エラーが多発しています。データをバックアップしてディスクを交換してください"),
							"smart_error"		=> __("RAID監視：<MESSAGE>：ディスクに故障があります。データをバックアップしてディスクを交換してください")
						),
# 				# メディア書き出し
# 				"media" => array(
# 							"write"			=> "メディア：書き出し",
# 							"write_error"	=> "メディア：書き出し失敗",
# 							"delete"		=> "メディア：消去",
# 							"delete_error"	=> "メディア：消去失敗"
# 						),
				# DDNS設定
				"ddns" => array(
							"edit"			=> __("Remote Link2設定：変更"),
							"add"			=> __("Remote Link2設定：登録"),
							"failure" 		=> __("Remote Link2設定：失敗：<MESSAGE>"),
							"disable"		=> __("Remote Link2設定：無効")
						),
				# DDNS更新通知
				"ddns_update" => array(
							"success" 		=> __("Remote Link2更新：成功"),
							"failure" 		=> __("Remote Link2更新：失敗：<MESSAGE>")
						),
# 2014/04/21 ADD START RemoteLink3対応 by seto 
				# RemoteLink3設定
				"remotelink3" => array(
							"edit"			=> __("Remote Link 3設定：変更"),
							"add"			=> __("Remote Link 3設定：登録"),
							"failure" 		=> __("Remote Link 3設定：失敗：<MESSAGE>"),
							"disable"		=> __("Remote Link 3設定：無効")
						),
# 2014/04/21 ADD END RemoteLink3対応 by seto 
# 				# リモートリンク設定
# 				"remotelink" => array(
# 							"edit"			=> "リモートリンク設定：変更"
# 						),
# 				# マイウェブサーバー設定
# 				"mywebserver" => array(
# 							"edit"			=> "マイウェブサーバー設定：変更"
# 						),
				# UPnPポート通知
				"port_forward" => array(
							"success" 		=> __("ポート通知：成功"),
							"failure" 		=> __("ポート通知：失敗：<MESSAGE>")
						),
				# UPnPポートオープン
				"port_open" => array(
							"failure"		=> __("UPnPポートオープン：失敗")
						),
				# EasySetup
				"easysetup" => array(
							"success" 		=> __("EasySetupOnUSB：成功"),
							"failure" 		=> __("EasySetupOnUSB：失敗"),
							"writeerror"		=> __("EasySetupOnUSB：書込失敗"),
							"readerror"		=> __("EasySetupOnUSB：読込失敗")
						),
				# Bittorrent設定
				"bittorrent" => array(
							"edit" 			=> __("BitTorrent設定：変更")
						),
				# TimeMachine設定
				"timemachine" => array(
							"edit" 			=> __("Time Machine設定：変更"),
							"error" 		=> __("Time Machine設定：失敗")
						),
				# FTP設定
				"ftp" => array(
							"edit" 			=> __("FTP設定：変更"),
							"error" 		=> __("FTP設定：失敗")
						),
				# TimeMachine設定
				"others" => array(
							"edit" 			=> __("その他：変更"),
							"error" 		=> __("その他：設定失敗")
						),
				# microsoftネットワーク設定
				"microsoft" => array(
							"edit" 			=> __("Microsoftネットワーク設定：変更"),
							"error" 		=> __("Microsoftネットワーク設定：失敗")
						),
				# テストメール 2011/05/12 hamataka
				"remote_mail" => array(
							"success"		 => __("Remote Link2：メール共有：送信：<MESSAGE>"),
							"failure"		 => __("Remote Link2：メール共有：送信失敗：<MESSAGE>"),
							"error"			 => __("Remote Link2：メール共有：検出エラー：<MESSAGE>")
						),
				# リモートリンクログイン 2011/05/12 hamataka
				"remote"	 => array(
							"login"			 => __("Remote Link2：ログオン：<MESSAGE>"),
							"logout"		 => __("Remote Link2：ログオフ：<MESSAGE>")
							),
				# Dropbox 2012/07/11
				"dropbox"	 => array(
							"fail"			 => __("Dropbox：同期失敗：<MESSAGE>"),
							"serverfull"		 => __("Dropbox：サーバー容量不足"),
							"diskfull"		 => __("Dropbox：共有フォルダー容量不足：<MESSAGE>"),
							"skip"			 => __("Dropbox：スキップ：<MESSAGE>"),
							"over"			 => __("Dropbox：スキップ：アップロードサイズ超過：<MESSAGE>")
							),
				# フレッツ・あずけ～る 2013/10/25
				"azukeru"	=> array(
							"fail"			=> __("あずけ～る：同期失敗：<MESSAGE>"),
							"serverfull"		=> __("あずけ～る：サーバー容量不足"),
							"diskfull"		=> __("あずけ～る：共有フォルダー容量不足：<MESSAGE>"),
							"skip"			=> __("あずけ～る：スキップ：<MESSAGE>"),
							"over"			=> __("あずけ～る：スキップ：アップロードサイズ超過：<MESSAGE>")
							),
				# クラウドストレージ 2013/10/25
				"nasdsync"	=> array(
							"fail"			=> __("クラウドストレージ：同期失敗：<MESSAGE>")
							),
				# NarSuS対応 2013/05/16
				"narsus"	=> array(
							"notice"		 => __("NarSuS：定期通知"),
							"failure"		 => __("NarSuS：接続失敗"),
							"error"			 => __("NarSuS：利用コードが不正"),
							"edit"			 => __("NarSuS設定：変更"),
							"fail"			 => __("NarSuS設定：失敗")
						),
				"proxy"		=> array(
							"edit"			 => __("プロキシ設定：変更")
						),
				# ユーザー設定
# 				"rapsclient" => array(
# 							"fail" 					=> __("Remote Link3同期：同期失敗：<MESSAGE>"),
# 							"serverfull" 			=> __("Remote Link3同期：対象機器容量不足：<MESSAGE>"),
# 							"diskfull" 				=> __("Remote Link3同期：共有フォルダー容量不足：<MESSAGE>"),
# 							"skip" 					=> __("Remote Link3同期：スキップ：<MESSAGE>"),
# 							"over" 					=> __("Remote Link3同期：スキップ：アップロードサイズ超過：<MESSAGE>"),
# 							"sharenotexists" 		=> __("Remote Link3同期：対象機器共有フォルダー不在：<MESSAGE>"),
# 							"invalidfilessystem" 	=> __("Remote Link3同期：共有フォルダーファイルシステム不正：<MESSAGE>"),
# 							"exceedsession" 		=> __("Remote Link3同期：最大接続数超過：<MESSAGE>"),
# 							"invalididentifier" 	=> __("Remote Link3同期：認証失敗：<MESSAGE>"),
# 							"invalidpin" 			=> __("Remote Link3同期：PINコード不正：<MESSAGE>"),
# 							"offline" 				=> __("Remote Link3同期：対象機器オフライン：<MESSAGE>"),
# 							"invalidcondition" 		=> __("Remote Link3同期：対象機器環境条件不正：<MESSAGE>"),
# 							"timeout" 				=> __("Remote Link3同期：接続タイムアウト：<MESSAGE>")
# 						)
				# RemoteLink3
				"rapsclient"	=> array(
							"fail"			 => __("Remote Link Cloud Sync：同期失敗：<MESSAGE>"),
							"serverfull"		 => __("Remote Link Cloud Sync：接続機器容量不足：<MESSAGE>"),
							"diskfull"		 => __("Remote Link Cloud Sync：共有フォルダー容量不足：<MESSAGE>"),
							"skip"			 => __("Remote Link Cloud Sync：スキップ：<MESSAGE>"),
							"over"			 => __("Remote Link Cloud Sync：アップロードサイズ超過：<MESSAGE>"),
							"sharenotexists"	 => __("Remote Link Cloud Sync：接続機器共有フォルダー不在：<MESSAGE>"),
							"invalidfilesystem"	 => __("Remote Link Cloud Sync：共有フォルダーファイルシステム不正：<MESSAGE>"),
							"exceedsession"		 => __("Remote Link Cloud Sync：最大接続数超過：<MESSAGE>"),
							"invalididentifier"	 => __("Remote Link Cloud Sync：認証失敗：<MESSAGE>")
						),
				"rl3client"	=> array(
							"selfoffline"		 => __("Remote Link Cloud Sync：インターネット不通：<MESSAGE>"),
							"invalidpin"		 => __("Remote Link Cloud Sync：PINコード不正：<MESSAGE>"),
							"targetoffline"		 => __("Remote Link Cloud Sync：接続機器インターネット不通：<MESSAGE>"),
							"invalidcondition"	 => __("Remote Link Cloud Sync：通信不可環境：<MESSAGE>"),
							"exceedsession"		 => __("Remote Link Cloud Sync：最大接続数超過：<MESSAGE>"),
							"timeout"			 => __("Remote Link Cloud Sync：接続タイムアウト：<MESSAGE>")
						),
				"raps"	=> array(
							"request_login"				 => __("Remote Link 3：ログオン要求：<MESSAGE>"),
							"request_logout"		 	 => __("Remote Link 3：ログオフ要求：<MESSAGE>")
						),
				"rl3"	=> array(
							"port_forward_success"		 => __("Remote Link 3：ポート通知：成功"),
							"port_forward_failure"		 => __("Remote Link 3：ポート通知：失敗：<MESSAGE>"),
							"pincode_regist_success"	 => __("Remote Link 3：PINコード変更：成功"),
							"pincode_regist_failure"	 => __("Remote Link 3：PINコード変更：失敗：<MESSAGE>")
						),
				"photo_import"	=> array(
							"edit"			=> __("フォトインポート設定：変更"),
							"start"			=> __("フォトインポート：開始：<MESSAGE>"),
							"locked"			=> __("フォトインポート：中断：端末ロック：<MESSAGE>"),
							"unlocked"			=> __("フォトインポート：再開：端末ロック解除：<MESSAGE>"),
							"end"			=> __("フォトインポート：終了：<MESSAGE>"),
							"end_error"		=> __("フォトインポート：異常終了：<MESSAGE>"),
							"error"			=> __("フォトインポート：失敗：<MESSAGE>"),
							"init"			=> __("フォトインポート：データベース初期化")
							)
			);
	list($class,$item) = explode(":",$ident);
	return str_replace("<MESSAGE>",$message,$log_string[$class][$item]);
}
# ============================================================================== #
# お知らせメール内容返却
# ============================================================================== #
function GET_ERROR_STRING($ident,$message=null){
	$error_string = array(
				"init" => array(
							"mount_error"	=> __("データパーティションのマウントに失敗しました")
						),
				"checkdisk" => array(
							"mount_error"	=> __("データパーティションのマウントに失敗しました"),
							"umount_error"	=> __("データパーティションのアンマウントに失敗しました")
						),
				"format" => array(
							"format_error"	=> __("データパーティションのフォーマットに失敗しました"),
							"mount_error"	=> __("データパーティションのマウントに失敗しました"),
							"umount_error"	=> __("データパーティションのアンマウントに失敗しました"),
							"raid_error"	=> __("データパーティションのRAID再構成に失敗しました")
						),
# 				"backup" => array(
# 							"end"		=> __("デジカメバックアップが完了しました"),
# 							"error"		=> __("デジカメバックアップに失敗しました")
# 						),
# 				"restore" => array(
# 							"end"		=> __("バックアップディスクからのリストアが完了しました"),
# 							"error"		=> __("バックアップディスクからのリストアに失敗しました")
# 						),
				"usb_backup" => array(
							"end"		=> __("バックアップが完了しました"),
							"error"		=> __("バックアップに失敗しました"),
							"start_error"	=> __("バックアップ 実行失敗")
				),
				"netbackup" => array(
							"end"		=> __("ネットワークバックアップが完了しました"),
							"error"		=> __("ネットワークバックアップに失敗しました"),
							"start_error"	=> __("ネットワークバックアップ 実行失敗")
				),
				"raid" => array(
							"crash"		=> __("RAIDが構成できませんでした"),
							"degrade"	=> __("起動時にディスクエラーが発生しました"),
							"Fail"		=> __("<MESSAGE>にディスクエラーが発生しました"),
							"FailSpare"	=> __("<MESSAGE>に回復不能エラーが発生しました"),
							"errorcount"	=> __("RAID監視：<MESSAGE>：エラーが多発しています。データをバックアップしてディスクを交換してください"),
							"smart_error"	=> __("RAID監視：<MESSAGE>：ディスクに故障があります。データをバックアップしてディスクを交換してください")
						),
				"activerepair" => array(
							"start"		=> __("アクティブリペアー開始"),
							"end"		=> __("アクティブリペアー終了 異常なし"),
							"repair_error"	=> __("アクティブリペアー終了 異常あり ログを確認してください"),
							"start_error"	=> __("アクティブリペアー 実行失敗"),
							"stop"		=> __("アクティブリペアー中断")
						),
				"ddns" => array(
							"disable"	=> __("Remote Link2設定が無効になりました")
						),
# 				"share" => array(
# 							"notfound"	=> __("共有フォルダー「<MESSAGE>」が見つかりませんでした")
# 						),
				"system" => array(
							"stopfan"	=> __("FANが停止しました"),
							"temperr"	=> __("温度異常を検知しました")
						),
				"firmware" => array(
							"update"	=> __("新しいファームウェアが公開されています。")
					)
			);
	
	list($class,$item) = explode(":",$ident);
	return str_replace("<MESSAGE>",$message,$error_string[$class][$item]);
}
?>
