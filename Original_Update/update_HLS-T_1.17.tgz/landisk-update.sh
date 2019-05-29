#!/bin/sh -x
#
# landisk-update.sh
#
# $Id$
#

PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

ROOTPATH='/mnt/hda5/.update'
UPDATELOG="${ROOTPATH}/update.log"

firmware=`cat /var/lib/model | grep '^firmware' | cut -f 2`
currentversion=`cat /var/lib/model | grep '^version' | cut -f 2`
currentbeta=`cat /var/lib/model | grep '^beta' | cut -f 2`
currentfirm="${firmware} ${currentversion}"
if [ "${currentbeta}" != '' ]; then
	currentfirm="${firmware} ${currentversion}(${currentbeta})"
fi
echo "current firmware:${currentfirm}"

BOARDMODEL="$(echo -n `cat /proc/boardmodel`)"

VERFILE="${ROOTPATH}/for_${firmware}_series"
BETAFILE="${ROOTPATH}/beta"
UPDATEFILE="${ROOTPATH}/update-files.tar.gz"
SETTINGFILE="${ROOTPATH}/setting-files.tar.gz"
BOOTFILE="${ROOTPATH}/flash_hlsc.uboot"
ENVFILE="${ROOTPATH}/flash_hlsc.env"
RMLIST="${ROOTPATH}/rmlist"
BOOTDEV='/dev/mtdblock0'
ENVDEV='/dev/mtdblock1'
LOCKDIR='/var/c4/tmp/lock'

version=`echo -n $(cat ${VERFILE})`
updatefirm="${firmware} ${version}"
beta=''
if [ -f "${BETAFILE}" ]; then
	beta=`echo -n $(cat ${BETAFILE})`
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
	exit $1
}

###############################################################################
# ブートローダ環境変数からmodel名抽出
###############################################################################
__get_model() {
	for RECORD in `/usr/local/bin/fw_printenv bootargs`; do
		local FIELD=`/bin/echo $RECORD | /usr/bin/cut -d "=" -f 1`
	
		if [ x"${FIELD}" = x"model" ]; then
			local VALUE=`/bin/echo $RECORD | /usr/bin/cut -d "=" -f 2`
			/bin/echo $VALUE
			return 0
		fi
	done
	return 1
}


###############################################################################
# ブートローダ環境変数のアップデート
###############################################################################
__update_env() {
	local modelname="$1"
	local root="/dev/sda2"
	local bootargs="console=ttyS0,115200 mtdparts=spi_flash:448k(u-boot),64k@448k(env) root=${root} initrd=0x2000040,16M rw model=${modelname}"
	fw_setenv bootargs "${bootargs}" || return 1
	fw_printenv bootargs || return 1

	local ethaddr=`LANG=C ifconfig eth0 | sed -n 's/^.*HWaddr \([0-9A-F:]*\)[ ]*$/\1/p' | tr A-Z a-z`
	fw_setenv ethaddr= || return 1
	fw_setenv ethaddr "${ethaddr}" || return 1
	fw_printenv ethaddr || return 1
}

###############################################################################
# ブートローダの比較
###############################################################################
__cmp_flash() {
	local file="$1"
	local dev="$2"
	local tmp="/tmp/tmp.bin"

	dd if="${dev}" of="${tmp}" bs=64k
	cmp -s ${tmp} ${file}
	local status=$?
	rm -f ${tmp}
	return ${status}
}

###############################################################################
# ブートローダのアップデート
###############################################################################
__update_flash() {
	local file="$1"
	local dev="$2"

	[ ! -f "${file}" ] && return 1

	echo -n '--- checking boot loader...'

	if __cmp_flash "${file}" "${dev}"; then
		echo 'same version. not update.'
		return 0
	fi

	echo 'different version.'

	if [ "${dev}" = "${ENVDEV}" ]; then
		echo -n '--- backup model name...'
		modelname_bkup=`__get_model` || return 1
	fi

	echo -n '--- updating boot loader...'

	fw_unlock "${dev}" || return 1
	dd if="${file}" of="${dev}" bs=64k || return 1
	blockdev --flushbufs "${dev}" || return 1

	echo 'done.'

	echo -n '--- comparing...'

	if ! __cmp_flash "${file}" "${dev}"; then
		echo 'different version!'
		echo 'update boot loader failed.'
		return 1
	fi

	if [ "${dev}" = "${ENVDEV}" ]; then
		echo -n '--- updating env...'
		if ! __update_env $modelname_bkup; then
			echo 'update boot loader env failed.'
			return 1
		fi
	fi

	echo 'done.'

	echo 'update boot loader success.'

	return 0
}

__update_param_conf_value() {
	local PARAM_CONF="$1"
	local PARAM_CONF_DEFAULT="$2"
	local KEY="$3"
	local VALUE=$(cat "${PARAM_CONF_DEFAULT}" | grep "^${KEY}=" | sed "s/${KEY}=//g")
	[ "${VALUE}" = '' ] && return 1
	if grep "${KEY}" ${PARAM_CONF}; then
		sed -i "s/^${KEY}=.*/${KEY}=${VALUE}/" "${PARAM_CONF}" || return 1
	else
		echo "${KEY}=${VALUE}" >> ${PARAM_CONF} || return 1
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

__update_twonkyserver_default_ini() {
	local DEFAULT_INI='/mnt/hda5/twonky/twonkyserver-default.ini'
	local DEFAULT_INI_DEFAULT='/mnt/hda5/twonky/twonkyserver-default.ini.default'

	if [ ! -e "${DEFAULT_INI}" ]; then
		cp -a "${DEFAULT_INI_DEFAULT}" "${DEFAULT_INI}" || return 1
	fi

	local IGNOREDIR=`cat "${DEFAULT_INI_DEFAULT}" | grep "^ignoredir=" | sed "s/ignoredir=//g"`
	[ "${IGNOREDIR}" = '' ] && return 1
	if ! grep "^ignoredir" "${DEFAULT_INI}" > /dev/null; then
		echo "ignoredir=${IGNOREDIR}" >> "${DEFAULT_INI}" || return 1
	fi

	return 0
}

__user_setting_have_issue() {
	local USER_SETTING=0
	if ! grep '"remote"' '/mnt/hda5/.c4/data/setting/user' > /dev/null; then
		USER_SETTING=1
	fi

	local PASSWD=0
	if ! grep '^remote:' '/etc/passwd' > /dev/null; then
		PASSWD=1
	fi

	if [ ${USER_SETTING} -eq ${PASSWD} ]; then
		# no have issue
		return 1
	fi

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

# 1.06以降でのサーバインストール対応での、インストール済みHDDフラグ
touch /boot/.install_done

# サービスの停止
echo 'stopping services...'
for service in apache2 raps.sh rl3.sh samba slpd netatalk mt-daapd twonky.sh proftpd avahi-daemon HDLFind.sh HDLFind_port65.sh cron networking nasdsync snmpd
do
	/etc/init.d/${service} stop
done
echo 'done.'

# ファイルのアップデート
echo 'removing rmlist files...'
if [ -f "${RMLIST}" ]; then
	cat "${RMLIST}" | xargs -n 1 -i{} rm -rvf "/{}"
fi
echo 'done.'

echo 'extracting update files...'
tar xpzf ${UPDATEFILE} -C / || __exit_select 1
echo 'done.'

echo 'extracting(keep old file) setting files...'
tar xpzfk ${SETTINGFILE} -C / || echo 'error but ignore.'
echo 'done.'

echo 'updating param.conf...'
__update_param_conf || __exit_select 1
echo 'done.'

if [ "${firmware}" = "HLS-C" ]; then
	echo 'updating twonkyserver-default.ini...'
	__update_twonkyserver_default_ini || __exit_select 1
	echo 'done.'
fi

echo 'updating uboot...'
if [ -f "${BOOTFILE}" ]; then
	__update_flash "${BOOTFILE}" "${BOOTDEV}" || __exit_select 1
fi
if [ -f "${ENVFILE}" ]; then
	__update_flash "${ENVFILE}" "${ENVDEV}" || __exit_select 1
fi
echo 'done.'

# Ver.1.08 -> 1.09での利用開始前状態の変更に伴う調整フラグ
if [ ${currentversion/./} -le 108 ]; then
	touch /boot/.rl3_setting_update || __exit_select 1
fi

if __user_setting_have_issue; then
	touch /boot/.fix_user_setting || __exit_select 1
fi


# アップデート後ldconfig
touch /boot/.landisk/do_ldconfig || __exit_select 1

# アップデート終了処理
echo "success: ${currentfirm} -> ${updatefirm}" >> "${UPDATELOG}"

echo 'update done.'

exit 0
