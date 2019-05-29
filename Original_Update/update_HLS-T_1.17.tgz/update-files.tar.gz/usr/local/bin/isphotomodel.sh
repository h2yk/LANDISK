#!/bin/sh
FIRMWARE=$(cat /var/lib/model | grep '^firmware	' | cut -f 2)
[ "${FIRMWARE}" = 'HLS-PG' ] && exit 0
exit 1
