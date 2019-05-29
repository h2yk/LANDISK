#!/bin/sh
pgrep -f update_thumbs.py | xargs -n 1 kill -USR1
