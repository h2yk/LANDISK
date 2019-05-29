#!/bin/sh
# Workaround for RKGR issue.
# RKGR issue occurs with eth0 not have IP address.
#
# Sometimes 82.75.71.82 is shown by MagicalFinder.
# And anyone can't access to the IP.
# 82.75.71.82 = 52 4B 47 52 = RKGR
# RKGR is part of WORKGROUP.
# HDLFind returns 82.75.71.82, if eth0 not have IP address.
# HDLFind must return 0.0.0.0, if eth0 not have IP address.
# Maybe it is bug of HDLFind.
#
RKGR=0
RKGR_PRE=0
while true; do
	if ethtool eth0 | grep 'Link detected: yes' > /dev/null; then
		RKGR_PRE=${RKGR}
		if ! ifconfig eth0 | grep 'inet addr:' > /dev/null; then
			RKGR=1
		else
			RKGR=0
		fi
		if [ ${RKGR} -ne 0 -a ${RKGR_PRE} -ne 0 ]; then
			echo 'RKGR detected. try to recover.' > /dev/console
			mount -o remount,rw /
			if ! ifdown eth0 > /dev/null; then
				echo 'failed to ifdown.' > /dev/console
			fi
			if ! ifup eth0 > /dev/null; then
				echo 'failed to ifup.' > /dev/console
			fi
			mount -o remount,ro,noatime /
		fi
	fi
	sleep 10
done
