<?php
class dropbox_class {
	var $parent;
	public function __construct($parent) {
		$this->parent = & $parent;
	}
	function getAuthorizeUrl() {
		exec("/mnt/hda5/bin/dropbox_access_token_manager_for_nasdsync --get-authorize-url", $output, $status);
		if ($status != 0) {
			return null;
		}
		return $output[0];
	}
	function _obtainAccessToken($code) {
		$esc_code = escapeshellarg($code);
		exec("/mnt/hda5/bin/dropbox_access_token_manager_for_nasdsync --obtain-access-token $esc_code", $output, $status);
		if ($status != 0) {
			return null;
		}
		return $output[0];
	}
	function _getAccessToken($share) {
		$esc_share = escapeshellarg($share);
		# Needs sudo,
		# because --load-access-token option write to file after convert
		# if need convert v1 to v2.
		exec("sudo /mnt/hda5/bin/dropbox_access_token_manager_for_nasdsync --load-access-token $esc_share", $output, $status);
		if ($status != 0) {
			return null;
		}
		return $output[0];
	}
	function _existAccessToken_v1($share) {
		$path_file = "/mnt/hda5/conf/dropbox/share_config";
		if (!file_exists($path_file)) {
			return false;
		}
		$share_config = file_get_contents($path_file);
		if (!$share_config) {
			return false;
		}
		$result = preg_match("/^" . $share . "\|/", $share_config);
		if ($result != 1) {
			return false;
		}
		return true;
	}
	function _existAccessToken_v2($share) {
		$path_file = "/mnt/hda5/conf/dropbox/access_token.json";
		if (!file_exists($path_file)) {
			return false;
		}
		$json_access_token = file_get_contents($path_file);
		if (!$json_access_token) {
			return false;
		}
		$array_access_token = json_decode($json_access_token, true);
		if (!array_key_exists($share, $array_access_token)) {
			return false;
		}
		return true;
	}
	function existAccessToken($share) {
		# External command in _getAccessToken affects performance.
		# Not use if no need to use.
		if ($this->_existAccessToken_v2($share)) {
			return true;
		}
		if ($this->_existAccessToken_v1($share)) {
			if (!$this->_getAccessToken($share)) {
				return false;
			}
			return true;
		}
		return false;
	}
	function _obtainAccountName($access_token) {
		$esc_access_token = escapeshellarg($access_token);
		exec("/mnt/hda5/bin/dropbox_access_token_manager_for_nasdsync --obtain-account-name $esc_access_token", $output, $status);
		if ($status != 0) {
			return null;
		}
		return $output[0];
	}
	function getAccountName($share) {
		$access_token = $this->_getAccessToken($share);
		if (!$access_token) {
			return null;
		}
		return $this->_obtainAccountName($access_token);
	}
	function enableDropbox($share, $code) {
		$esc_share = escapeshellarg($share);
		if ($code) {
			$access_token = $this->_obtainAccessToken($code);
			if (!$access_token) {
				return false;
			}
			$esc_access_token = escapeshellarg($access_token);
			exec("sudo /mnt/hda5/bin/nasdsync --save-dropbox-setting --share $esc_share --access-token $esc_access_token", $output, $status);
		} else {
			exec("sudo /mnt/hda5/bin/nasdsync --save-dropbox-setting --share $esc_share --enable", $output, $status);
		}
		if ($status != 0) {
			return false;
		}
		return true;
	}
	function disableDropbox($share, $delete) {
		$esc_share = escapeshellarg($share);
		if ($delete) {
			exec("sudo /mnt/hda5/bin/nasdsync --clear-dropbox-setting --share $esc_share", $output, $status);
		} else {
			exec("sudo /mnt/hda5/bin/nasdsync --save-dropbox-setting --share $esc_share --disable", $output, $status);
		}
		if ($status != 0) {
			return false;
		}
		return true;
	}
	function changeDropbox($old_share, $share) {
		$access_token = $this->_getAccessToken($old_share);
		if (!$access_token) {
			return false;
		}
		$esc_old_share = escapeshellarg($old_share);
		exec("sudo /mnt/hda5/bin/nasdsync --clear-dropbox-setting --share $esc_old_share --keep-access-token", $output, $status);
		if ($status != 0) {
			return false;
		}
		$esc_share = escapeshellarg($share);
		$esc_access_token = escapeshellarg($access_token);
		exec("sudo /mnt/hda5/bin/nasdsync --save-dropbox-setting --share $esc_share --access-token $esc_access_token", $output, $status);
		if ($status != 0) {
			return false;
		}
		return true;
	}
}
?>
