#!/bin/sh
LEDDEV="/dev/led"
BOOT_PATH='/boot'
BUTTON_CHECKED_PATH="${BOOT_PATH}/.button_checked"

__led() {
	echo "led_$1 $2" > "${LEDDEV}"
}

__is_led() {
	cat "${LEDDEV}" | grep "led_$1\s*$2" > /dev/null
}

__is_btn() {
	cat "${LEDDEV}" | grep "btn_$1\s*$2" > /dev/null
}

[ -f "${BUTTON_CHECKED_PATH}" ] && exit 0

PCB=$(cat /var/lib/model | grep '^pcb	' | sed 's/^pcb	\(.*\)/\1/')

__led top off
if [ "${PCB}" = 'HLS-PG' ]; then
	echo /dev/null > /proc/sys/kernel/hotplug
	__led top on
fi
__led status on
__led update on
while ! __is_led status off || ! __is_led update off || ! __is_led top off; do
	__is_btn power on && __led status off
	__is_btn reset on && __led update off
	if __is_led top on; then
		__is_btn sdcard on && __led top blink
	fi
	if __is_led top blink; then
		__is_btn sdcard off && __led top off
	fi
done

mount -o remount,rw "${BOOT_PATH}"
touch "${BUTTON_CHECKED_PATH}"
mount -o remount,ro,noatime "${BOOT_PATH}"

exit 0
