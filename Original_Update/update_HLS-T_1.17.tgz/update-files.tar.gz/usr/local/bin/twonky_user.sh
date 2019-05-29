#!/bin/sh
METHOD="$1"
TWONKY_INI='/mnt/sataraid1/twonky/twonkyserver.ini'
TWONKY_INI_TMP="${TWONKY_INI}.twonky_user.tmp"

__get_value() {
	local FILE="$1"
	local KEY="$2"
	VALUE=$(grep "^${KEY}=" < ${FILE} | sed "s/^${KEY}=\(.*\)$/\1/")
	echo -n "${VALUE}" || return 1
	return 0
}

__set_value() {
	local FILE="$1"
	local KEY="$2"
	local VALUE="$3"
	sed -i "s/^${KEY}=.*/${KEY}=${VALUE}/g" ${FILE} || return 1
	return 0
}

USER=$(__get_value "${TWONKY_INI}" 'accessuser')	
if [ "${METHOD}" = 'get' ]; then
	echo -n "${USER}" || exit 1
elif [ "${METHOD}" = 'reset' ]; then
	if [ "${USER}" != '' ]; then
		cp -a "${TWONKY_INI}" "${TWONKY_INI_TMP}" || exit 1
		__set_value "${TWONKY_INI_TMP}" 'accessuser' '' || exit 1
		__set_value "${TWONKY_INI_TMP}" 'accesspwd' '' || exit 1
		mv "${TWONKY_INI_TMP}" "${TWONKY_INI}" || exit 1
		/etc/init.d/twonky.sh restart || exit 1
	fi
fi
exit 0
