<?
# サービス
class item_service_ms_only {
	# アイテムリスト取得
	function getItem(){
		$item = array(
				"1"=>__("Microsoftネットワーク共有"));
#				"8"=>__("マルチメディア共有"),
# 				"2"=>__("AppleShareネットワーク共有"),
# 				"6"=>__("FTP共有"),
# 				"3"=>__("DLNA共有"),
# 				"4"=>__("iTunes共有"),
#				"5"=>__("リモートアクセス共有"),
#				"7"=>__("クラウドストレージ同期"));
		return $item;
	}
}
?>
