#!/bin/sh

OUTPUT="/mnt/sataraid1/photo_import/log/hotplug_log"
DEBUG=0
if [ $DEBUG -eq 1 ]; then
    echo $$ `date +"%Y/%m/%d %H:%M:%S.%N"`,$1,$ACTION,$INTERFACE,$DEVPATH >> $OUTPUT
fi

subsystem=$1
case $subsystem in
    usb_device)
        case "$ACTION" in
            add)
                if [ $DEBUG -eq 1 ]; then
                    /usr/local/bin/photo_import.py --debug --share_path /mnt/sataraid1/share/photo/ --event add --method date
                else
                    /usr/local/bin/photo_import.py --share_path /mnt/sataraid1/share/photo/ --event add --method date
                fi
            ;;
            remove)
                if [ $DEBUG -eq 1 ]; then
                    echo $$ `date +"%Y/%m/%d %H:%M:%S.%N"`,$1,$ACTION,$INTERFACE,$DEVPATH >> $OUTPUT
                    /usr/local/bin/photo_import.py --debug --event remove
                else
                    /usr/local/bin/photo_import.py --event remove
                fi
            ;;
    esac
esac
