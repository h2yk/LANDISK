#!/bin/sh -x
#
# landisk-update.sh
#
# $Id$
#

PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

ROOTPATH='/mnt/hda5/.update'
UPDATELOG="${ROOTPATH}/update.log"

firmware=$(cat /var/lib/model | grep '^firmware' | cut -f 2)
currentversion=$(cat /var/lib/model | grep '^version' | cut -f 2)
currentbeta=$(cat /var/lib/model | grep '^beta' | cut -f 2)
currentfirm="${firmware} ${currentversion}"
if [ "${currentbeta}" != '' ]; then
	currentfirm="${firmware} ${currentversion}(${currentbeta})"
fi
echo "current firmware:${currentfirm}"

VERFILE="${ROOTPATH}/for_${firmware}_series"
BETAFILE="${ROOTPATH}/beta"
UPDATEFILE="${ROOTPATH}/update-files.tar.gz"
LOCKDIR='/var/c4/tmp/lock'

version=$(echo -n $(cat "${VERFILE}"))
updatefirm="${firmware} ${version}"
beta=''
if [ -f "${BETAFILE}" ]; then
	beta=$(echo -n $(cat ${BETAFILE}))
	updatefirm="${firmware} ${version}(${beta})"
fi
echo "update firmware:${updatefirm}"

__start_led() {
	/usr/local/bin/ledcont progress || return 1
	return 0
}

__exit_select() {
	echo "### FIXME!!: landisk-update.sh: ロックした人が削除すべきでは? ###"
	rm -rf "${LOCKDIR}"
	exit "$1"
}

__update_param_conf_value() {
	local PARAM_CONF="$1"
	local PARAM_CONF_DEFAULT="$2"
	local KEY="$3"
	local VALUE=$(cat "${PARAM_CONF_DEFAULT}" | grep "^${KEY}=" | sed "s/${KEY}=//g")
	[ "${VALUE}" = '' ] && return 1
	if grep "${KEY}" "${PARAM_CONF}"; then
		sed -i "s/^${KEY}=.*/${KEY}=${VALUE}/" "${PARAM_CONF}" || return 1
	else
		echo "${KEY}=${VALUE}" >> "${PARAM_CONF}" || return 1
	fi
	return 0
}

__update_param_conf() {
	local PARAM_CONF='/mnt/hda5/conf/rl3/param.conf'
	local PARAM_CONF_DEFAULT='/mnt/hda5/conf/rl3/param.conf.default'

	if [ ! -e "${PARAM_CONF}" ]; then
		cp -a "${PARAM_CONF_DEFAULT}" "${PARAM_CONF}" || return 1
	fi

	__update_param_conf_value "${PARAM_CONF}" "${PARAM_CONF_DEFAULT}" 'BANDLE_MAX' || return 1
	__update_param_conf_value "${PARAM_CONF}" "${PARAM_CONF_DEFAULT}" 'CS_PUSH_TIMER' || return 1
	__update_param_conf_value "${PARAM_CONF}" "${PARAM_CONF_DEFAULT}" 'SEND_THRESHOLD_MIN' || return 1
	__update_param_conf_value "${PARAM_CONF}" "${PARAM_CONF_DEFAULT}" 'TCP_TARGET_ADDR_CS' || return 1
	__update_param_conf_value "${PARAM_CONF}" "${PARAM_CONF_DEFAULT}" 'UDP_SERVER' || return 1

	return 0
}

########################
# start
__start_led || __exit_select 1

# サービスの停止の布石
echo 'remounting root file system...'
mount -o remount,rw,noatime / || __exit_select 1
mount -o remount,rw,noatime /boot || __exit_select 1
echo 'done.'

# ファイルのアップデート
echo 'extracting update files...'
tar xpzf ${UPDATEFILE} -C / || __exit_select 1
echo 'done.'

echo 'updating param.conf...'
__update_param_conf || __exit_select 1
echo 'done.'

# アップデート後ldconfig
touch /boot/.landisk/do_ldconfig || __exit_select 1

# アップデート終了処理
echo "success: ${currentfirm} -> ${updatefirm}" >> "${UPDATELOG}"

echo 'update done.'

exit 0
