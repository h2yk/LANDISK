#!/bin/sh
NAME=raps
APICGI="/usr/local/raps/www/api.cgi"
APIPY="/usr/local/raps/www/api.py"
LINKDELPY="/usr/local/raps/bin/link_del.py"
UPDATETHUMBSPY="/usr/local/raps/bin/update_thumbs.py"

FIXLOG="/mnt/sataraid1/raps/fix.log"

fix_tmpdir() {
    local FOLDER="$1"
    if [ -e "${FOLDER}" ]; then
        local PERM=$(stat -c %a "${FOLDER}")
	if [ "${PERM}" != "777" ]; then
            echo "$(LANG=C date): not correct perm: ${FOLDER}" >> "${FIXLOG}"
            chmod 777 "${FOLDER}" || return 1
            echo "$(LANG=C date): fixed: ${FOLDER}" >> "${FIXLOG}"
	fi
	return 0
    fi
    echo "$(LANG=C date): not exist: ${FOLDER}" >> "${FIXLOG}"
    mkdir "${FOLDER}" || return 1
    chmod 777 "${FOLDER}" || return 1
    echo "$(LANG=C date): fixed: ${FOLDER}" >> "${FIXLOG}"
    return 0
}

raps_clear() {
    #セッション削除 
    rm -rf /mnt/sataraid1/raps/session/*
    fix_tmpdir /mnt/sataraid1/raps/session
    
    #リスト削除 
    rm -rf /mnt/sataraid1/raps/list/*
    fix_tmpdir /mnt/sataraid1/raps/list

    #upload一時ファイル削除 
    rm -rf /mnt/sataraid1/raps/upload_tmp/*
    fix_tmpdir /mnt/sataraid1/raps/upload_tmp

    #image一時ファイル削除 
    rm -rf /mnt/sataraid1/raps/image_tmp/*
    fix_tmpdir /mnt/sataraid1/raps/image_tmp
}

raps_kill() {
    local API="$1"

    # api.cgiの親（apache）をkill
    ps alx | grep $API | grep -v grep | awk '{print $4}'| xargs kill -9 >/dev/null 2>&1 

    # api.cgiの子をkill
    local CHILD=`ps alx | grep $API | grep -v grep | awk '{print $3}'`
    for C_PID in $CHILD; do 
        echo $C_PID 
        ps --ppid $C_PID --no-headers -o pid | xargs kill -9 >/dev/null 2>&1 
        kill -9 $C_PID
    done
}

raps_start() {
    if pgrep -f $LINKDELPY; then
        echo "already running."
	return
    fi
    daemonize $LINKDELPY

    raps_clear

    daemonize su 'www-data' -c "$UPDATETHUMBSPY"
}

raps_stop() { 
    raps_kill $APICGI
    raps_kill $APIPY

    pgrep -f $UPDATETHUMBSPY | xargs kill -9 >/dev/null 2>&1

    raps_clear

    pgrep -f $LINKDELPY | xargs kill -9 >/dev/null 2>&1
}

case "$1" in
  start)
        echo -n "Starting $NAME: "
	raps_start
        ;;
  stop)
        echo -n "Stopping $NAME: "
        raps_stop
        ;;
  *)
        echo "Usage: $NAME {start|stop}" >&2
        exit 1
        ;;
esac

exit 0
