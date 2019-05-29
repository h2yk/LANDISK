<?php
include_once ('../../../../../c4/c4c.class.php');
include_once (LD_CLASS_PATH . '/system/samba.class.php');
include_once (LD_CLASS_PATH . '/system/detail/share/share/share.class.php');

class get_dropbox_access_token extends share_class{
	function onLoad(){
		$this->standardOutFlag = true;
		$this->restartStateFul();
		$valueArray = array();
		$valueArray["accesstokenflag"] = $this->getValue("Dropboxアクセストークンフラグ");
		echo json_encode($valueArray);
	}
}
new get_dropbox_access_token();
?>
