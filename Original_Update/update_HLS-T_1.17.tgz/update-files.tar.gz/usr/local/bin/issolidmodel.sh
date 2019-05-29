#!/bin/sh
SUB_SUB_MODEL=`cat /var/lib/model | grep '^subsubmodel	' | cut -f 2`
[ "${SUB_SUB_MODEL}" = 'SLD' ] && exit 0
exit 1
