<?php

function isSmartPhone(){
	$ua=$_SERVER['HTTP_USER_AGENT'];
	if((strpos($ua,'iPhone')!==false) || (strpos($ua,'iPod')!==false) || (strpos($ua,'iPad')!==false) || (strpos($ua,'Android')!==false)) {
		return false;
	}
	return true; 
}

function isRemoteUI(){
	$ra=$_SERVER['REMOTE_ADDR'];
	if($ra == '127.0.0.1') {
		return true;
	}
	return false;
}

function isAudioModel(){
	exec("/usr/local/bin/isaudiomodel.sh",$output,$result);
	if ($result === 0) {
		return true;
	}
	return false;
}

function isWithoutTwonkyModel(){
	exec("/usr/local/bin/iswithouttownkymodel.sh",$output,$result);
	if ($result === 0) {
		return true;
	}
	return false;
}

function isLandiskModel(){
	exec("/usr/local/bin/islandiskmodel.sh",$output,$result);
	if ($result === 0) {
		return true;
	}
	return false;
}

function isSolidModel(){
	exec("/usr/local/bin/issolidmodel.sh",$output,$result);
	if ($result === 0) {
		return true;
	}
	return false;
}

function getHostPrefix(){
	if (isLandiskModel()) {
		return "LANDISK";
	}
	return "HLS";
}

?>
