#!/usr/bin/env python
import sys
import os
import time
import traceback
import signal

import raps.rapscomm as rapscomm
import raps.rapsmminfo as rapsmminfo

comm = rapscomm.rapscomm()
comm.readConfig()
thumb = rapsmminfo.Thumbnail(None, comm)

share_path = comm.CONFIG.SHARE_PATH
ext_share_list = comm.CONFIG.EXT_SHARE_LIST

support_ext_list = rapscomm.suffix_picture

def create_thumbs(path):
    global cancel
    for file in os.listdir(path):
        if cancel:
            break
        path_file = path + '/' + file
        if os.path.isdir(path_file):
            create_thumbs(path_file)
        elif os.path.isfile(path_file):
            (root, ext) = os.path.splitext(path_file)
            if ext.lower() in support_ext_list:
                comm.logout("creating thumb cache: " + path_file)
                thumb.create(path_file)

def create_thumb_exclude_ext_share():
    global cancel
    for share in os.listdir(share_path):
        if cancel:
            break
        if share in ext_share_list:
            continue
        create_thumbs(share_path + "/" + share)

def remove_thumb_less_than_time(time):
    global cancel
    for file in os.listdir(comm.CONFIG.CACHE_PATH):
        if cancel:
            break
        file_path = comm.CONFIG.CACHE_PATH + "/" + file
        stat = os.stat(file_path)
        if stat.st_mtime < time:
            #comm.logout("removing thumb cache: " + file_path)
            os.remove(file_path)

def func(signo, frame):
    global cancel
    cancel = True
    pass

signal.signal(signal.SIGUSR1, func)

cancel = False
while True:
    cancel = False
    try:
        start_time = time.time()
        comm.logout("creating thumbs...")
        create_thumb_exclude_ext_share()
        if cancel:
            comm.logout("create thumb cancel")
            continue
        comm.logout("removing thumbs...")
        remove_thumb_less_than_time(start_time)
        if cancel:
            comm.logout("remove thumb cancel")
            continue
        comm.logout("update_thumbs.py sleeping...")
        time.sleep(24 * 60 * 60)
        comm.logout("update_thumbs.py waked")
    except:
        traceback.print_exc(file=open(c_comm.CONFIG.LOG,"w"))
        pass

exit()
