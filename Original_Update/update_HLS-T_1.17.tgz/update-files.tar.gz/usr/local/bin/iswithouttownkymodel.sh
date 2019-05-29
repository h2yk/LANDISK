#!/bin/sh
FIRMWARE=`cat /var/lib/model | grep '^firmware	' | cut -f 2`
[ "${FIRMWARE}" = 'HLS-T' ] && exit 0
[ "${FIRMWARE}" = 'HLS-SLD' ] && exit 0
exit 1
