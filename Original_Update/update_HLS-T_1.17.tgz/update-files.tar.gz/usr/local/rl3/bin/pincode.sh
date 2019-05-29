#!/bin/sh
[ -f /etc/beta ] && set -x
OPTION="$1"
PINCODE="$2"

BOOT_PATH='/boot'
PINCODE_INIT_PATH="${BOOT_PATH}/.pincode_init"
REGISTING_INIT_PINCODE_PATH="${BOOT_PATH}/.init_pincode"
REGISTING_CURRENT_PINCODE_PATH="${BOOT_PATH}/.registing_current_pincode"
PINCODE_PATH="${BOOT_PATH}/.pincode"
PINCODE_DELETED_PATH="${BOOT_PATH}/.pincode_deleted"
PINCODE_REGISTING_PATH="${BOOT_PATH}/.pincode_registing"
MAC_PATH="${BOOT_PATH}/.cryptmac.asc"

GETXMLRESULT='/usr/local/rl3/bin/getxmlresult.py'

LOCK_PATH='/var/lock/landisk/pincode.lock'

__try_lock() {
	local COUNT=0
	local TIMEOUT=$1
	[ "${TIMEOUT}" = '' ] && TIMEOUT=30
	while [ ${COUNT} -lt ${TIMEOUT} ]; do
		sudo ln -s "$$" "${LOCK_PATH}" && return 0
		COUNT=$((COUNT+1))
		sleep 1
	done
	return 1
}

__unlock() {
	sudo rm "${LOCK_PATH}" || return 1
	return 0
}

__try_lock 30 || sudo echo "WARNING: $0 lock failed." > /dev/console

__exit() {
	local RESULT=$1
	__unlock
	exit ${RESULT}
}

__localhost_macaddr() {
	local MACADDR=`ifconfig eth0 | grep 'HWaddr' | sed 's/.*HWaddr //' | sed 's/[: ]//g'`
	echo -n "${MACADDR}"
}

__pincode_test() {
	local MACADDR=`__localhost_macaddr`
	echo -n "${MACADDR}00000000000000000000"
}

__pincode_regist_init() {
	local PINCODE="$1"
	local CLEAN_REGISTING_PINCODE="$2"
	local PINCODE_INIT=`__pincode_init`
	[ "${PINCODE}" = '' ] && PINCODE="${PINCODE_INIT}"
	[ "${PINCODE}" = '' ] && return 1
	if [ ! -f "${REGISTING_INIT_PINCODE_PATH}" ]; then
		mount -o remount,rw "${BOOT_PATH}" || return 1
		touch "${REGISTING_INIT_PINCODE_PATH}" || return  1
		if [ -f "${REGISTING_CURRENT_PINCODE_PATH}" ]; then
			rm "${REGISTING_CURRENT_PINCODE_PATH}" || return 128
		fi
		mount -o remount,ro,noatime "${BOOT_PATH}" || return 128
	fi
	__pincode_delete_all || return 128
	if ! __pincode_regist_retry "${PINCODE}" '00'; then
		if [ "${CLEAN_REGISTING_PINCODE}" != '' ]; then
			mount -o remount,rw "${BOOT_PATH}"
			rm -f "${PINCODE_REGISTING_PATH}"
			mount -o remount,ro,noatime "${BOOT_PATH}"
		fi
		return 128
	fi
	mount -o remount,rw "${BOOT_PATH}" || return 128
	if [ "${PINCODE}" != "${PINCODE_INIT}" ]; then
		echo -n "${PINCODE}" > "${PINCODE_INIT_PATH}" || return 128
	fi
	rm "${REGISTING_INIT_PINCODE_PATH}" || return  128
	mount -o remount,ro,noatime "${BOOT_PATH}" || return 1
	return 0
}

__pincode_regist_current() {
	local PINCODE="$1"
	local PINCODE_REGISTING=''
	if [ ! -f "${REGISTING_CURRENT_PINCODE_PATH}" -o "${PINCODE}" != '' ]; then
		mount -o remount,rw "${BOOT_PATH}" || return 1
		echo -n "${PINCODE}" > ${REGISTING_CURRENT_PINCODE_PATH} || return 1
		mount -o remount,ro,noatime "${BOOT_PATH}" || return 128
	fi
	[ -f "${REGISTING_CURRENT_PINCODE_PATH}" ] && PINCODE_REGISTING=`cat "${REGISTING_CURRENT_PINCODE_PATH}"`
	[ "${PINCODE}" = '' ] && PINCODE="${PINCODE_REGISTING}"
	[ "${PINCODE}" = '' ] && return 1
	__pincode_delete_all || return 128
	__pincode_regist_retry "${PINCODE}" '00' || return 128
	mount -o remount,rw "${BOOT_PATH}" || return 128
	rm "${REGISTING_CURRENT_PINCODE_PATH}" || return  128
	mount -o remount,ro,noatime "${BOOT_PATH}" || return 1
	return 0
}

__pincode_init() {
	[ ! -f "${PINCODE_INIT_PATH}" ] && return 1
	cat "${PINCODE_INIT_PATH}" || return 1
	return 0
}

__pincode_current() {
	[ ! -f "${PINCODE_PATH}" ] && return 1
	cat "${PINCODE_PATH}" || return 1
	return 0
}

__pincode_deleted() {
	[ ! -f "${PINCODE_DELETED_PATH}" ] && return 1
	cat "${PINCODE_DELETED_PATH}" || return 1
	return 0
}

__pincode_registing() {
	[ ! -f "${PINCODE_REGISTING_PATH}" ] && return 1
	cat "${PINCODE_REGISTING_PATH}" || return 1
	return 0
}

__pincode_new() {
	local TYPE="$1"
	local OPT='-t'
	[ "${TYPE}" = 'init' ] && OPT='-r'
	local UUID=`uuidgen ${OPT}`
	[ $? -ne 0 ] && return 1
	local PINCODE=`echo -n "${UUID}" | sed 's/\-//g' | tr 'a-z' 'A-Z'`
	[ $? -ne 0 ] && return 1
	echo -n "${PINCODE}"
	return 0
}

__pincode_delete() {
	local PINCODE="$1"
	local EXPECTED_RESULT="$2"
	[ "${PINCODE}" = '' ] && return 1
	[ "${EXPECTED_RESULT}" = '' ] && EXPECTED_RESULT='00'

	local MACADDR=`cat "${MAC_PATH}"`

	#local POSTDATA="enc_type=2&pin_code=${PINCODE}&mac_address=${MACADDR}"
	local POSTDATA="enc_type=2&pin_code=${PINCODE}"
	local URI='http://localhost:15000/cs/InfoDeletion'
	local HTMLRESULT=`wget --timeout=30 --tries=1 --post-data="${POSTDATA}" "${URI}" -O -`
	local RESULT=`echo -n "${HTMLRESULT}" | ${GETXMLRESULT}`
	if [ -f /etc/beta ]; then
		sudo echo "try to delete ${PINCODE}: result=${RESULT}." > /dev/console
	fi
	if [ "${RESULT}" != '00' -a "${RESULT}" != '01' ]; then
		sudo echo "WARNING: result=${RESULT} can not delete ${PINCODE} from server." > /dev/console
 		return 1
	fi
	if [ "${RESULT}" != "${EXPECTED_RESULT}" ]; then
		sudo echo "WARNING: result=${RESULT} deleted ${PINCODE} from server. but not expected result." > /dev/console
	fi
	if [ "${RESULT}" = '01' ]; then
		return 0
	fi
	mount -o remount,rw "${BOOT_PATH}" || return 1
	echo -n "${PINCODE}" > "${PINCODE_DELETED_PATH}" || return 1
	rm -f "${PINCODE_PATH}" || return  1
	mount -o remount,ro,noatime "${BOOT_PATH}" || return 1

	return 0
}

__pincode_delete_retry() {
	local PINCODE="$1"
	local EXPECTED_RESULT="$2"
	local RETRY=1
	local TRY=0
	while true; do
		if __pincode_delete "${PINCODE}" "${EXPECTED_RESULT}"; then
			break
		fi
		sleep 1
		TRY=$((TRY+1))
		[ ${TRY} -ge $((1+RETRY)) ] && return 1
	done
	return 0
}

__pincode_delete_all() {
	local PINCODE_CURRENT=`__pincode_current`
	if [ "${PINCODE_CURRENT}" != '' ]; then
		__pincode_delete_retry "${PINCODE_CURRENT}" "00" || return 1
	fi
	local PINCODE_DELETED=`__pincode_deleted`
	if [ "${PINCODE_DELETED}" != '' ]; then
		__pincode_delete_retry "${PINCODE_DELETED}" "01" || return 1
	fi
	local PINCODE_REGISTING=`__pincode_registing`
	if [ "${PINCODE_REGISTING}" != '' ]; then
		__pincode_delete_retry "${PINCODE_REGISTING}" "01" || return 1
	fi
	return 0
}

__pincode_regist() {
	local PINCODE="$1"
	local EXPECTED_RESULT="$2"
	[ "${PINCODE}" = '' ] && return 1
	[ "${EXPECTED_RESULT}" = '' ] && EXPECTED_RESULT='00'

	#[ -f "${PINCODE_PATH}" ] && return 1
	mount -o remount,rw "${BOOT_PATH}" || return 1
	echo -n "${PINCODE}" > "${PINCODE_REGISTING_PATH}" || return 1
	mount -o remount,ro,noatime "${BOOT_PATH}" || return 1

	#local MACADDR=`__localhost_macaddr`
	local MACADDR=`cat "${MAC_PATH}"`
	local AUTHORITY="0"
	local POSTDATA="enc_type=2&pin_code=${PINCODE}&mac_address=${MACADDR}&authority=${AUTHORITY}"
	local URI='http://localhost:15000/cs/InfoRegistration'
	local HTMLRESULT=`wget --timeout=30 --tries=1 --post-data="${POSTDATA}" "${URI}" -O -`
	local RESULT=`echo -n "${HTMLRESULT}" | ${GETXMLRESULT}`
	if [ -f /etc/beta ]; then
		sudo echo "try to regist ${PINCODE}: result=${RESULT}." > /dev/console
	fi
	if [ "${RESULT}" != '00' ]; then
		sudo echo "WARNING: result=${RESULT} can not regist ${PINCODE} to server." > /dev/console
		return 1
	fi
	if [ "${RESULT}" != "${EXPECTED_RESULT}" ]; then
		sudo echo "WARNING: result=${RESULT} registed ${PINCODE} to server. but not expected result." > /dev/console
	fi
	if [ -f "${PINCODE_PATH}" ]; then
		sudo echo "WARNING: why can regist ${PINCODE} to server?" > /dev/console
	fi
	mount -o remount,rw "${BOOT_PATH}" || return 1
	mv "${PINCODE_REGISTING_PATH}" "${PINCODE_PATH}" || return 1
	mount -o remount,ro,noatime "${BOOT_PATH}" || return 1

	return 0
}

__pincode_regist_retry() {
	local PINCODE="$1"
	local EXPECTED_RESULT="$2"
	local RETRY=1
	local TRY=0
	while true; do
		if __pincode_regist "${PINCODE}" "${EXPECTED_RESULT}"; then
			break
		fi
		sleep 1
		TRY=$((TRY+1))
		[ ${TRY} -ge $((1+RETRY)) ] && return 1
	done
	return 0
}

if [ "${OPTION}" = 'new' ]; then
	__pincode_new || __exit 1
	__exit 0
fi
if [ "${OPTION}" = 'new_init' ]; then
	__pincode_new init || __exit 1
	__exit 0
fi
if [ "${OPTION}" = 'delete' -a "${PINCODE}" != '' ]; then
	__pincode_delete "${PINCODE}" "00" || __exit 1
	__exit 0
fi
if [ "${OPTION}" = 'regist_init' -a "${PINCODE}" != '' ]; then
	__pincode_regist_init "${PINCODE}" 
	__exit $?
fi
if [ "${OPTION}" = 'regist_init_kensa' -a "${PINCODE}" != '' ]; then
	# 他MACアドレスに登録されたPINコードであっても削除できてしまう
	# 検査で2回登録されたりした場合も削除されないよう登録失敗したPINコード
	# は削除されないようにする
	__pincode_regist_init "${PINCODE}" clean_registing_pincode
	__exit $?
fi
if [ -f "${REGISTING_INIT_PINCODE_PATH}" ]; then
	__pincode_regist_init || __exit 128
fi
if [ "${OPTION}" = 'regist' -a "${PINCODE}" != '' ]; then
	__pincode_regist_current "${PINCODE}" 
	__exit $?
fi
if [ -f "${REGISTING_CURRENT_PINCODE_PATH}" ]; then
	__pincode_regist_current || __exit 128
fi

case "${OPTION}" in
	test)
		__pincode_test || __exit 1
		;;
	regist_init)
		__pincode_regist_init "${PINCODE}"
		__exit $?
		;;
	init)
		__pincode_init || __exit 1
		;;
	current)
		__pincode_current || __exit 1
		;;
	new)
		__pincode_new || __exit 1
		;;
	new_init)
		__pincode_new init || __exit 1
		;;
	delete)
		__pincode_delete_all || __exit 1
		;;
	regist)
		__pincode_regist_current "${PINCODE}"
		__exit $?
		;;
	regist_only)
		__pincode_regist "${PINCODE}" "00" || __exit 1
		;;
	*)
		__exit 1
		;;
esac

__exit 0
