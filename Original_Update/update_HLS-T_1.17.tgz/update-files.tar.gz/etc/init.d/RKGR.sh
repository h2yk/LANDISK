#!/bin/sh

WATCHDOGD=/usr/local/bin/RKGR_watchdogd.sh


function start() {
	if pgrep -f $WATCHDOGD; then
		echo "already running."
		return
	fi
	daemonize $WATCHDOGD
}


function stop() {
	pgrep -f $WATCHDOGD | xargs kill -9 >/dev/null 2>&1
}


function usage() {
	echo "$0 < start | stop | usage >"
}


########################################
# main logic
########################################

case $1 in
	"start" ) 
		start
		;;
	"stop" )
		stop
		;;
	"restart" )
		stop
		start
		;;
	* )
		usage
		;;
esac

#exit 0

# end of file.
