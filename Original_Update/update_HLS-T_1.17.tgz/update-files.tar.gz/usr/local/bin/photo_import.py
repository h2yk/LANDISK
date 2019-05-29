#!/usr/bin/env python
# -*- coding: utf-8 -*-

from __future__ import with_statement

import os
import sys
import shutil
import subprocess as sp
import datetime
import time
import commands
import glob
import pwd
import grp
import csv
import re
import traceback as tb
import inspect
from optparse import OptionParser
from PIL import Image
from PIL.ExifTags import TAGS
import logging
import fcntl
import math
import signal
import gphoto2
gp2 = gphoto2.gphoto2()

VERSION = "1.0.2"
MOUNT_DIR = "/mnt/usb1"
BOOT_DONE_FLAG = "/var/lock/lanicn/.boot-done"
DEV_LED_PATH = "/dev/led"
LED_CONT_PATH = "/usr/local/bin/ledcont"
HDL_LOG_PATH = "/usr/local/bin/HDL_log"
RAPS_UPDATE_THUMBS_PATH = "/usr/local/bin/raps_update_thumbs.sh"
USB_PORT_PATH = "/sys/devices/platform/ehci_marvell.70059/usb1/1-1"

TYPE_MSC = 1
TYPE_MTP = 2

ERR_SUCCESS = 0
ERR_FAILED = 1
ERR_CHECK_LIST_FAILED = 2
ERR_IMPORT_CANCELED = 99

GPHOTO_IO_ERR = "-7"
GPHOTO_DIR_ERR = "-107"
GPHOTO_PTP_ERR = "-6"

VID_APPLE = "05ac"

class FileLocker:
    path = None
    lockFile = None

    def __init__(self,path=None):
        self.path = path

    def __del__(self):
        self.unlock()

    def lock(self,wait):
        if self.lockFile != None:
            return
        
        self.lockFile = open(self.path,"w")
        if wait == 0:
            fcntl.flock(self.lockFile,fcntl.LOCK_EX | fcntl.LOCK_NB)
        elif wait == 1:
            while True:
                try:
                    fcntl.flock(self.lockFile,fcntl.LOCK_EX)
                except:
                    continue
                break
    
    def unlock(self):
        if self.lockFile == None:
            return 

        fcntl.flock(self.lockFile,fcntl.LOCK_UN)
        self.lockFile.close()
        self.lockFile = None

class Option:
    def __init__(self,argv=sys.argv[1:]):

        optparser = OptionParser(version="%prog : Ver. " + VERSION,
                usage="Usage: %prog [options] ...")
    
        optparser.add_option(
            "-s","--share_path",
            dest="share_path",
            default="/mnt/sataraid1/share/photo/",
            help="Path of shared destination folder.")

        optparser.add_option(
            "-f","--folder",
            dest="dest_dir",
            default="",
            help="Relative path of save destination folder from share path.")
    
        optparser.add_option(
            "-e","--event",
            dest="event",
            default="remove",
            help="Select script action. \"add\" or \"remove\" or \"delete\".")
        
        optparser.add_option(
            "-m","--method",
            dest="method",
            default="date",
            help="Select import method. \"root\" or \"date\".")

        optparser.add_option(
            "-d","--debug",
            action="store_true",
            dest="debug",
            default=False,
            help="Output debug information.")

        optparser.add_option(
            "","--system_folder_path",
            dest="system",
            default="/mnt/sataraid1/photo_import",
            help="Setting system folder path for photo import.")

        optparser.add_option(
            "-w","--overwrite",
            action="store_true",
            dest="overwrite",
            default = False,
            help="If there is a different file with the same name, overwrite it.")
    
        optparser.add_option(
            "","--threshold",
            dest="threshold",
            default=10000,
            type="int",
            help="Threshold of file size to be downloaded first.Specified in KB.")

        (self.options,self.args) = optparser.parse_args(argv)

class database:

    database_path = None

    def __init__(self, database_path):
        database_dir = os.path.dirname(database_path)
        if not os.path.isdir(database_dir):
            os.makedirs(database_dir)
        self.database_path = database_path

    def get_unique_name(self, product, serial):
        if not os.path.exists(self.database_path):
            return None
        with open(self.database_path, "r") as db:
            reader = csv.reader(db)
            for row in reader:
                if row[0] == product and row[1] == serial:
                    return row[2]
        return None

    def get_unique_name_list(self):
        unique_name_list = []
        if not os.path.exists(self.database_path):
            return unique_name_list
        with open(self.database_path, "r") as db:
            reader = csv.reader(db)
            for row in reader:
                unique_name_list.append(row[2])
        return unique_name_list

    def add(self, product, serial, unique_name):
        if self.get_unique_name(product, serial):
            return -1
        if unique_name in self.get_unique_name_list():
            return -1
        with open(self.database_path, "a") as db:
            writer = csv.writer(db)
            writer.writerow([product, serial, unique_name])
        return 0

    def delete(self, product, serial):
        found = False
        deleted_list = []
        with open(self.database_path, "r") as db:
            reader = csv.reader(db)
            for row in reader:
                if row[0] == product and row[1] == serial:
                    found = True
                    continue
                deleted_list.append(row)
        if not found:
            return 1
        with open(self.database_path, "w") as db:
            writer = csv.writer(db)
            for row in deleted_list:
                writer.writerow(row)
        return 0

class PhotoImport:

    device_info = {
                   'usb': {
                           'class': '',
                           'vid': '',
                           'pid': '',
                           'product': '',
                           'serial': '',
                          },
                   'volume': {
                              'uuid': '',
                              'fstype': '',
                             },
                   'connect_type': '',
                   'product': '',
                   'serial': '',
                  }

    def __init__(self,share_path,dest_path,event,method,system,threshold,debug):

        self.share_path = share_path
        self.dest_path = dest_path
        self.event = event
        self.method = method
        self.sdlist = [] # sdb,sdc,...
        self.sdpartlist = [] # sdb1,sdb2,...
        self.database_dir = os.path.join(system,"database")
        self.tmp_import_dir = os.path.join(system,"tmp")
        self.history_dir = os.path.join(system,"history")
        self.debug = debug
        self.led_lock = FileLocker(os.path.join(system,"lock/led_lock"))
        self.kill_lock = FileLocker(os.path.join(system,"lock/kill_lock"))
        self.unique_name = ''
        self.error_count = 0
        self.fail_flag = 0
        self.threshold_size = threshold

    def get_value_from_file(self, path):
        f = open(path, "r")
        value = f.read().strip()
        f.close()
        return value

    def update_device_info_from_usb_port(self):
        func_name = self.get_current_func_name()
        self.dbglog(func_name)

        self.dbglog(func_name + ": current:" + str(self.device_info))

        usb_if_class_path = os.path.join(USB_PORT_PATH, "1-1:1.0/bInterfaceClass")
        usb_vendor_id_path = os.path.join(USB_PORT_PATH, "idVendor")
        usb_product_id_path = os.path.join(USB_PORT_PATH, "idProduct")
        usb_product_path = os.path.join(USB_PORT_PATH, "product")
        usb_serial_path = os.path.join(USB_PORT_PATH, "serial")

        if_class = None
        vendor_id = None
        product_id = None
        product = None
        serial = None
        while not cancel:
            if not os.path.isdir(USB_PORT_PATH):
                break

            if os.path.isfile(usb_if_class_path):
                if_class = self.get_value_from_file(usb_if_class_path)
            if os.path.isfile(usb_vendor_id_path):
                vendor_id = self.get_value_from_file(usb_vendor_id_path)
            if os.path.isfile(usb_product_id_path):
                product_id = self.get_value_from_file(usb_product_id_path)
            if os.path.isfile(usb_product_path):
                product = self.get_value_from_file(usb_product_path)
            if os.path.isfile(usb_serial_path):
                serial = self.get_value_from_file(usb_serial_path)

            if if_class and vendor_id and product_id and product and serial:
                break

            time.sleep(0.1)

        if if_class:
            self.device_info['usb']['class'] = if_class
        if vendor_id:
            self.device_info['usb']['vid'] = vendor_id
        if product_id:
            self.device_info['usb']['pid'] = product_id
        if product:
            self.device_info['usb']['product'] = product
        if serial:
            self.device_info['usb']['serial'] = serial

        if if_class == "08" or if_class == "8":     
            self.device_info['connect_type'] = TYPE_MSC
        elif if_class == "06" or if_class == "6" or if_class == "ff" or if_class == "255":
            self.device_info['connect_type'] = TYPE_MTP
        self.device_info['product'] = product
        self.device_info['serial'] = serial

        self.dbglog(func_name + ": updated:" + str(self.device_info))

    def import_device(self):
        if self.event == "add":
            if self.device_info['connect_type'] == TYPE_MSC:
                self.addStorage()
            elif self.device_info['connect_type'] == TYPE_MTP:
                self.addMtp()
        elif self.event == "remove":
            self.removeDevice()
        elif self.event == "delete":
            self.deleteImportHistory()
        
    def addMtp(self):
        self.dbglog("addMtp")

        gp2.session_open()

        # デバイス情報取得
        if self.update_device_info_from_gphoto2_summary() != ERR_SUCCESS:
            if not cancel:
                self.dbglog("update_device_info_from_gphoto2_summary failed")
                self.writeSystemLog("error_end",
                                    "VID:" + self.device_info['usb']['vid'] + 
                                    ",PID:" + self.device_info['usb']['pid'])
                self.ledControl("err")
            return
	
        # ストレージが認識できるまで(アクセス許可されるまで)待機
        if not self.get_basedir_with_waiting():
            self.dbglog("get_basedir_with_waiting failed")
            return 

        # iOSデバイスの場合、許可されてから開きなおさないと適切な動作をしない？
        if self.device_info['usb']['vid'] == VID_APPLE:
            self.dbglog("reopen session for iOS devices")
            gp2.session_close()
            gp2.session_open()
            if not self.get_basedir():
                self.dbglog("get_basedir failed")
                return 

        # シリアル-ID関連付けデータベース読み書き
        self.model_dir = self.determine_model_dir()
        if not os.path.isdir(self.model_dir):
            if self.mkdir_with_perm(self.model_dir) != ERR_SUCCESS:
                self.writeSystemLog("error_end",self.unique_name)
                self.ledControl("err")
                return
        
        # インポート処理
        self.ledControl("clear_err")
        self.ledControl("external_progress")
        self.writeSystemLog("start",self.unique_name)
        result = self.importMtp()
        self.dbglog("importMtp result:" + str(result))
        self.ledControl("clear_external_progress")
        if result != ERR_SUCCESS or self.fail_flag == 1:
            self.ledControl("err")
            self.writeSystemLog("error_end",self.unique_name)
        else:
            self.writeSystemLog("end",self.unique_name)

        # サムネイルの更新
        self.raps_update_thumbs()

    def addStorage(self):
        self.dbglog("addStorage")

        while not cancel:
            while not cancel:
                self.getSdList()
                ret = self.checkMediaInsert()
                if ret == 0:    
                    break
                elif ret == 1:
                    self.dbglog("before import:usb removed")
                    return
                else:
                    time.sleep(1)

            # マウント処理
            mount_result = -1
            for sd in self.sdpartlist:
                mount_result = self.mountStorageDevice(sd)
                self.dbglog("mount_result_main:" + str(mount_result))
                if mount_result == 0:
                    break
            if mount_result != 0:
                self.dbglog("error:any partition couldn't mount")
                self.ledControl("err")
                self.writeSystemLog("error_end","エラー:8")
                return 

            # 接続されているデバイスがSDカードか確認
            self.update_device_info_if_in_sdcard_slot()

            # シリアル-ID関連付けデータベース読み書き
            self.model_dir = self.determine_model_dir()
            if not os.path.isdir(self.model_dir):
                if self.mkdir_with_perm(self.model_dir) != ERR_SUCCESS:
                    self.writeSystemLog("error_end",self.unique_name)
                    self.ledControl("err")
                    return 

            # インポート処理
            self.ledControl("clear_err")
            self.ledControl("external_progress")
            self.writeSystemLog("start",self.unique_name)
            result = self.importStorage()
            self.dbglog("importStorage result:" + str(result))
            self.ledControl("clear_external_progress")
            if result != ERR_SUCCESS or self.fail_flag == 1:
                self.ledControl("err")
                self.writeSystemLog("error_end",self.unique_name)
            else:
                self.writeSystemLog("end",self.unique_name)

            # サムネイルの更新
            self.raps_update_thumbs()
        
            # アンマウント処理
            self.umountDevice()
        
            # メディアの存在確認
            while not cancel:
                ret = self.checkMediaInsert()
                if ret == 0:
                    #self.dbglog("device ready.media still inserting")
                    pass
                elif ret == 1:
                    self.dbglog("after import:usb removed")
                    return 
                else:
                    #self.dbglog("device not ready")
                    break
                time.sleep(1)

    def deleteImportHistory(self):
        if os.path.isdir(self.history_dir):
            shutil.rmtree(self.history_dir)
            os.mkdir(self.history_dir)
        self.writeSystemLog("init","")

    def update_device_info_if_in_sdcard_slot(self):
        func_name = self.get_current_func_name()
        self.dbglog(func_name)

        self.dbglog(func_name + ": current:" + str(self.device_info))

        with open(DEV_LED_PATH,"r") as fp:
            line = fp.readline()
            while line:
                if "btn_sdcard" in line:
                    tmp = line.split()
                    if tmp[1] == "on":
                        self.device_info['product'] = u"SDカード".encode('utf-8')
                        self.device_info['serial'] = self.device_info['volume']['uuid']
                        break
                line = fp.readline()
            if not self.device_info['product']:
                device_info['product'] = "MassStorage"
            #if not self.device_info['serial']:
            #    self.writeSystemLog("error_end",self.unique_name)
            #    self.ledControl("err")

        self.dbglog(func_name + ": updated:" + str(self.device_info))

    def removeDevice(self):
        #self.dbglog("removeDevice")
        self.umountDevice()

    def importMtp(self):
        self.dbglog("importMtp")

        file_pattern = re.compile("^#")

        folders_list = []
        if gp2.list_folders("/", folders_list, 0) != 0:
            return ERR_FAILED

        for current_basedir in folders_list:
            current_basedir = "/" + current_basedir.strip("/")
            self.dbglog("current basedir:" + current_basedir)
            current_storage_label = self.get_current_storage_label(current_basedir)
            if not current_storage_label:
                return ERR_FAILED

            # DCIM以下のファイルリスト取得
            dcim_path = os.path.join("/", current_basedir, "DCIM")
            files_list = []
            if gp2.list_files(dcim_path, files_list, 1, 0) != 0:
                self.dbglog("DCIM not found...")
                continue

            for srcpath in files_list: 
                if cancel or self.error_count >= 3:
                    self.dbglog("error end(cancel or error_count is over)")
                    return ERR_FAILED

                logpath = re.sub("/store_.+?/", current_storage_label + "/", srcpath)
                filename = os.path.basename(srcpath)
                tmppath = os.path.join(self.tmp_import_dir, filename)

                # 隠しファイルはスキップ
                if self.check_hidden_file(srcpath):
                    self.dbglog("skip(hidden file):" + logpath)
                    continue

                # インポート履歴を参照し登録済みの場合はスキップ
                if self.searchImportHistory(logpath):
                    self.dbglog("skip(already imported):" + logpath)
                    continue

                info_data = []
                if gp2.show_info(srcpath, info_data) != 0:
                    self.dbglog("failed to show info")
                    self.errorHandling(logpath)
                    continue

                (srcsize,srctime) = self.get_size_and_time_from_showinfo(info_data)
                if srcsize is None or srctime is None:
                    self.dbglog("failed to get size or time from showinfo")
                    self.errorHandling(logpath)
                    continue

                datetime_original = None
                exif_data = []
                if gp2.show_exif(srcpath, exif_data) == 0:
                    datetime_original = self.get_datetime_original_from_showexif(exif_data)

                if datetime_original:
                    srctime = datetime_original
                else:
                    # Exif情報がなくUnixTimeとして0より小さい値が設定されている場合はmetadataのDateCreatedタグを利用する
                    # 主にiPhoneでExif情報が取得できないファイルのタイムスタンプが-1に設定されている場合がある問題の対策
                    if self.get_unixtime(srctime) < 0:
                        metadata_data = []
                        if gp2.get_metadata(srcpath, metadata_data) == 0:
                            datecreated = self.get_datecreated_from_metadata(metadata_data)
                            if datecreated:
                                srctime = datecreated

                # 最終的な値がUnixTimeとして1より小さい場合は強制的にUnixTime1に設定する
                # 仮に意図的に設定されていたとしてもsambaから適切な時間として見えないなどの問題があるため
                if self.get_unixtime(srctime) < 1:
                    srctime = datetime.datetime.fromtimestamp(1)

                # インポート先オリジナルパス生成
                dstpath_original = self.create_dstpath_original(srctime,filename)

                # インポート先オリジナルパスが別名を含み同じファイルが存在すればスキップ
                # 存在しない場合は別名含みインポートするべきパスを返す
                dstpath = self.get_path_of_no_duplicates_if_not_exist_identical_file(srcsize,srctime,dstpath_original)
                if not dstpath:
                    self.dbglog("skip(already exist identical file):" + logpath)
                    self.error_count = 0
                    # インポート履歴に登録されていなければ登録
                    if not self.searchImportHistory(logpath):
                        self.recordImportHistory(logpath)
                    continue

                if gp2.get_file(srcpath, tmppath) != 0:
                    self.dbglog("failed to get file")
                    self.errorHandling(logpath)
                    continue

                # show_exifでExif情報が取れない場合も、ファイルからは直接取れる場合がある。
                if not datetime_original:
                    datetime_original = self.get_datetime_original_from_file(tmppath)
                    if datetime_original:
                        self.dbglog("got datetime_original from file")
                        srctime = datetime_original

                        # UnixTimeとして1より小さい場合は強制的にUnixTime1に設定する
                        # 仮に意図的に設定されていたとしてもsambaから適切な時間として見えないなどの問題があるため
                        if self.get_unixtime(srctime) < 1:
                            srctime = datetime.datetime.fromtimestamp(1)

                        # インポート先オリジナルパス生成
                        dstpath_original = self.create_dstpath_original(srctime,filename)

                        # インポート先オリジナルパスが別名を含み同じファイルが存在すればスキップ
                        # 存在しない場合は別名含みインポートするべきパスを返す
                        dstpath = self.get_path_of_no_duplicates_if_not_exist_identical_file(srcsize,srctime,dstpath_original)
                        if not dstpath:
                            self.dbglog("skip(already exist identical file):" + logpath)
                            self.error_count = 0
                            # インポート履歴に登録されていなければ登録
                            if not self.searchImportHistory(logpath):
                                self.recordImportHistory(logpath)
                            self.remove_file(tmppath)
                            continue

                # 属性とタイムスタンプを変更
                if self.set_perm(tmppath) != ERR_SUCCESS:
                    self.dbglog("failed to set perm")
                    self.errorHandling(logpath)
                    self.remove_file(tmppath)
                    continue

                if self.set_filetime(tmppath,srctime) != ERR_SUCCESS:
                    self.dbglog("failed to set filetime")
                    self.errorHandling(logpath)
                    self.remove_file(tmppath)
                    continue

                # インポート先へ移動
                if self.move_file(tmppath,dstpath) != ERR_SUCCESS:
                    self.dbglog("failed to move file")
                    self.errorHandling(logpath)
                    self.remove_file(tmppath)
                    continue

                self.error_count = 0
                    
                # インポート履歴に登録
                self.recordImportHistory(logpath)

        return ERR_SUCCESS

    def get_size_and_time_from_showinfo(self, showinfo):
        try:
            filesize = None
            filetime = None
            for line in showinfo:
                #self.dbglog(line)
                if "容量" in line and not filesize:
                    filesize_str = line.split("容量:")[1].strip()
                    filesize = int(filesize_str.split(" ")[0].strip())
                if "Size:" in line and not filesize:
                    filesize_str = line.split("Size:")[1].strip()
                    filesize = int(filesize_str.split(" ")[0].strip())
                if "時間" in line and not filetime:
                    filetime_str = line.split("時間:")[1].strip()
                    filetime = datetime.datetime.strptime(filetime_str, "%a %b %d %H:%M:%S %Y")
                if "Time:" in line and not filetime:
                    filetime_str = line.split("Time:")[1].strip()
                    filetime = datetime.datetime.strptime(filetime_str, "%a %b %d %H:%M:%S %Y")
                if filesize and filetime:
                    break
            return (filesize, filetime)
        except:
            self.dbglog(tb.format_exc())
            return (None, None)

    def get_matched_datetime_original(self, line):
        try:
            match = re.search(r"([0-9]+:[0-9]+:[0-9]+ [0-9]+:[0-9]+:[0-9]+)", line)
            if not match:
                raise Exception("not match format:" + line)
            return datetime.datetime.strptime(match.group(1), "%Y:%m:%d %H:%M:%S")
        except:
            self.dbglog(tb.format_exc())
            return None

    def get_datetime_original_from_showexif(self, showexif):
        try:
            for line in showexif:
                #self.dbglog(line)
                if "DateTimeOriginal" in line:
                    datetime_original = line.split("|")[1]
                    return self.get_matched_datetime_original(datetime_original)
            return None
        except:
            self.dbglog(tb.format_exc())
            return None

    def get_datecreated_from_metadata(self, metadata):
        try:
            pattern = re.compile(r"<DateCreated>([0-9]+T[0-9]+).*</DateCreated>")
            for line in metadata():
                #self.dbglog(line)
                match = pattern.search(line)
                if match:
                    return datetime.datetime.strptime(match.group(1), "%Y%m%dT%H%M%S")
            return None
        except:
            self.dbglog(tb.format_exc())
            return None

    def get_basedir_from_storageinfo(self, storageinfo_data):
        try:
            for line in storageinfo_data:
                #self.dbglog(line)
                if "basedir=" in line:
                    basedir = line.split("=")[1]
                    return basedir.rstrip("/") + "/"
            return None
        except:
            self.dbglog(tb.format_exc())
            return None

    def get_basedir(self):
        try:
            storageinfo_data = []
            if gp2.storage_info(storageinfo_data) != 0:
                return None
            return self.get_basedir_from_storageinfo(storageinfo_data)
        except:
            self.dbglog(tb.format_exc())
            return None

    def importStorage(self):
        self.dbglog("importStorage")
        if self.method == "date":
            # 日付でフォルダ分け
            self.dbglog("copyDate")

            # DCIMフォルダパス取得
            dcim_path = os.path.join(MOUNT_DIR,"DCIM")
            if not os.path.isdir(dcim_path):
                self.dbglog("DCIM folder not found.")
                return ERR_SUCCESS
            return self.copyDate(dcim_path,self.model_dir)

    def ledControl(self,led_type):
        self.dbglog("ledControl:" + led_type)
        self.kill_lock.lock(1)
        sp.call([LED_CONT_PATH,led_type])
        self.kill_lock.unlock()

    def writeSystemLog(self,log_type,msg):
        self.dbglog("writeSystemLog:" + log_type + "," + msg)

        self.kill_lock.lock(1)
        if log_type == "start":
            sp.call([HDL_LOG_PATH,"--message_id=MSG_PHOTO_IMPORT_START",msg])
        elif log_type == "locked":
            sp.call([HDL_LOG_PATH,"--message_id=MSG_PHOTO_IMPORT_LOCKED",msg])
        elif log_type == "unlocked":
            sp.call([HDL_LOG_PATH,"--message_id=MSG_PHOTO_IMPORT_UNLOCKED",msg])
        elif log_type == "end":
            sp.call([HDL_LOG_PATH,"--message_id=MSG_PHOTO_IMPORT_END",msg])
        elif log_type == "error_end":
            sp.call([HDL_LOG_PATH,"--message_id=ERR_PHOTO_IMPORT_END",msg])
        elif log_type == "error":
            sp.call([HDL_LOG_PATH,"--message_id=ERR_PHOTO_IMPORT",msg])
        elif log_type == "init":
            sp.call([HDL_LOG_PATH,"--message_id=MSG_PHOTO_IMPORT_INIT"])
        self.kill_lock.unlock()

    def format_stack(self):
        stack = "Stack\n"
        for line in tb.format_stack()[:-1]:
            stack += line
        return stack

    def errorHandling(self,logpath):
        self.dbglog(self.format_stack())
        self.writeSystemLog("error",logpath)
        self.error_count = self.error_count + 1
        self.fail_flag = 1

    def raps_update_thumbs(self):
        self.kill_lock.lock(1)
        sp.call(RAPS_UPDATE_THUMBS_PATH)
        self.kill_lock.unlock()

    def isfile_ignore_case(self,srcpath):
        #self.dbglog("isfile_ignore_case:" + srcpath)
        splitpath = os.path.split(srcpath)
        dirname = splitpath[0]
        if not os.path.isdir(dirname):
            return ""
        filename_original = splitpath[1]
        file_list = os.listdir(dirname)
        for filename in file_list:
            if filename.upper() == filename_original.upper():
                samename = os.path.join(dirname,filename)
                return samename
        return ""

    def get_path_of_no_duplicates_if_not_exist_identical_file(self,srcsize,srctime,dstpath_original):
        dstpath_samename = self.isfile_ignore_case(dstpath_original)
        if dstpath_samename:
            if not self.diff_check(srctime,srcsize,dstpath_samename):
                self.dbglog("exist identical file:" + dstpath_original)
                return None
            return self.create_newname(srcsize,srctime,dstpath_original)
        self.dbglog("same path not exist:" + dstpath_original)
        return dstpath_original
                    
    def create_newname(self,srcsize,srctime,filepath):
        #self.dbglog("create_newname")
        i = 0
        newname = ""
        while True:
            i = i + 1
            (root,ext) = os.path.splitext(filepath)
            root = root + "_%d" % i
            newname = root + ext
            newname_samename = self.isfile_ignore_case(newname)
            if newname_samename:
                if self.diff_check(srctime,srcsize,newname_samename):
                    continue
                else:
                    self.dbglog("samefile exist:" + newname)
                    newname = ""
                    break
            else:
                break

        return newname

    def remove_file(self,path):
        self.dbglog("remove_file,path:" + path)
        try:
            os.remove(path)
            return ERR_SUCCESS
        except:
            self.dbglog(tb.format_exc())
            return ERR_FAILED

    def move_file(self,srcpath,dstpath):
        self.dbglog("move_file,src:" + srcpath + ",dst:" + dstpath)
        try:
            monthpath = os.path.dirname(dstpath)
            yearpath = os.path.dirname(monthpath)
            if not os.path.isdir(yearpath):
                self.mkdir_with_perm(yearpath)
            if not os.path.isdir(monthpath):
                self.mkdir_with_perm(monthpath)
            shutil.move(srcpath,dstpath)
            return ERR_SUCCESS
        except:
            self.dbglog(tb.format_exc())
            return ERR_FAILED

    def recordImportHistory(self,logpath):
        #self.dbglog("recordImportHistory:" + logpath)
        if not os.path.isdir(self.history_dir):
            os.mkdir(self.history_dir)
        history_file = os.path.join(self.history_dir,self.unique_name)
        fp = open(history_file,"a+")
        fp.write(logpath)
        fp.write("\n")
        fp.close()

    def searchImportHistory(self,logpath):
        #self.dbglog("searchImportHistory:" + logpath)
        if not os.path.isdir(self.history_dir):
            os.mkdir(self.history_dir)
        history_file = os.path.join(self.history_dir,self.unique_name)
        fp = open(history_file,"a+")
        for line in fp:
            line = line.strip()
            #self.dbglog("line:" + line)
            if line == logpath:
                return True
        fp.close()
        return False

    def update_device_info_from_blkid(self, sd):
        func_name = self.get_current_func_name()
        self.dbglog(func_name)

        self.dbglog(func_name + ": current:" + str(self.device_info))

        p = sp.Popen(["env", "LANG=C", "/usr/local/bin/blkid-2.18", "-p", "-s",
                      "UUID", "-s", "TYPE", sd],
                     stdout=sp.PIPE, stderr=sp.PIPE)
        blkid_out, blkid_err = p.communicate()
        if p.returncode != 0:
            return ERR_FAILED

        uuid = None
        fstype = None
        pattern_uuid = re.compile("UUID=\".+?\"")
        pattern_fstype = re.compile("TYPE=\".+?\"")
        matched_uuid = pattern_uuid.search(blkid_out)
        if matched_uuid:
            uuid_tmp = matched_uuid.group().split("\"")
            uuid = uuid_tmp[1]
        matched_fstype = pattern_fstype.search(blkid_out)
        if matched_fstype:
            fstype_tmp = matched_fstype.group().split("\"")
            fstype = fstype_tmp[1]

        if uuid:
            self.device_info['volume']['uuid'] = uuid
        if fstype:
            self.device_info['volume']['fstype'] = fstype

        self.dbglog(func_name + ": updated:" + str(self.device_info))

        return ERR_SUCCESS

    def update_device_info_from_exfatinfo(self, sd):
        func_name = self.get_current_func_name()
        self.dbglog(func_name)

        self.dbglog(func_name + ": current:" + str(self.device_info))

        p = sp.Popen(["env", "LANG=C", "/usr/local/bin/exfatinfo", sd],
                     stdout=sp.PIPE, stderr=sp.PIPE)
        exfatinfo_out, exfatinfo_err = p.communicate()
        if p.returncode != 0:
            return ERR_FAILED

        uuid = None
        fstype = "exfat"
        lines = exfatinfo_out.splitlines()
        for line in lines:
            if "Volume serial number" in line:
                tmp = line.split(": ")
                uuid = tmp[1]
                break

        if uuid:
            self.device_info['volume']['uuid'] = uuid
        if fstype:
            self.device_info['volume']['fstype'] = fstype

        self.dbglog(func_name + ": updated:" + str(self.device_info))

        return ERR_SUCCESS

    def mountStorageDevice(self,sd):
        func_name = self.get_current_func_name()
        self.dbglog(func_name)

        if self.update_device_info_from_blkid(sd) != ERR_SUCCESS:
            if self.update_device_info_from_exfatinfo(sd) != ERR_SUCCESS:
                return -1

        if self.device_info['volume']['fstype'] == "vfat":
            p = sp.Popen(["mount", "-t", "vfat", "-o",
                          "shortname=winnt,utf8,quiet", sd, MOUNT_DIR],
                         stdout=sp.PIPE, stderr=sp.PIPE)
            mount_out,mount_err = p.communicate()
            return p.returncode
        else:
            p = sp.Popen(["mount", sd, MOUNT_DIR],
                          stdout=sp.PIPE, stderr=sp.PIPE)
            mount_out,mount_err = p.communicate()
            return  p.returncode

    def update_device_info_from_gphoto2_summary(self):
        func_name = self.get_current_func_name()
        self.dbglog(func_name)

        self.dbglog(func_name + ": current:" + str(self.device_info))

        summary_data = []
        if gp2.summary(summary_data) != 0:
            return ERR_FAILED

        product = None
        serial = None
        for line in summary_data:
            if not product and "Model: " in line:
                tmp = line.split(": ")
                product = tmp[1]
            if not serial and "Serial Number: " in line:
                tmp = line.split(": ")
                serial = tmp[1]
            if product and serial:
                break

        if product:
            self.device_info['product'] = product
        if serial:
            self.device_info['serial'] = serial

        self.dbglog(func_name + ": updated:" + str(self.device_info))

        return ERR_SUCCESS
    
    def get_basedir_with_waiting(self):
        self.dbglog("get_basedir_with_waiting")
        while not cancel:
            storageinfo_data = []
            if gp2.storage_info(storageinfo_data) != 0:
                self.dbglog("failed to storage_info")
                return None
            basedir = self.get_basedir_from_storageinfo(storageinfo_data)
            if not basedir:
                self.dbglog("could not get basedir from storageinfo")
                time.sleep(3)
                continue
            self.dbglog("gotten basedir:" + basedir)
            if "store_feedface" in basedir:
                self.dbglog("gotten basedir is including store_feedface")
                time.sleep(3)
                continue
            return basedir
        return None

    def get_current_storage_label(self, current_basedir):
        self.dbglog("get_current_storage_label")

        storageinfo_data = []
        if gp2.storage_info(storageinfo_data) != 0:
            return None

        storage_pattern = re.compile("\[Storage [0-9]+\]")
        label = ""
        description = ""
        basedir = ""
        for line in storageinfo_data:
            #self.dbglog(line)
            match = storage_pattern.match(line)
            if match:
                label = ""
                description = ""
                basedir = ""
            if "label=" in line:
                label = line.split("label=")[1]
                self.dbglog("found label:" +  label)
            if "description=" in line:
                description = line.split("description=")[1]
                self.dbglog("found description:" +  description)
            if "basedir=" in line:
                basedir = line.split("basedir=")[1]
                self.dbglog("found basedir:" +  basedir)
            if current_basedir.strip("/") == basedir.strip("/"):
                current_storage_label = label
                if not current_storage_label:
                    current_storage_label = description
                return current_storage_label

        return None

    def get_current_func_name(self):
        currentframe = inspect.currentframe()
        return inspect.getframeinfo(currentframe.f_back)[2]

    def get_winok_name(self, name):
        return re.sub(r'[\\/:*?"<>|]', '_', name)

    def determine_unique_name(self, product, unique_name_list):
        number = 1
        while number < 1000:
            unique_name = "%s_%03d" % (product, number)
            if not unique_name in unique_name_list:
                return unique_name
            number += 1
        # FIXME!!
        return None

    def determine_model_dir(self):
        func_name = self.get_current_func_name()
        self.dbglog(func_name)

        product = self.device_info['product']
        serial = self.device_info['serial']

        database_path = os.path.join(self.database_dir,"serial_id")

        db = database(database_path)
        unique_name = db.get_unique_name(product, serial)
        if not unique_name:
            unique_name_list = db.get_unique_name_list()
            unique_name = self.determine_unique_name(product, unique_name_list)
            if not unique_name:
                # FIXME!!
                raise
            db.add(product, serial, unique_name)

        model_dir = os.path.join(self.dest_path,
                                 self.get_winok_name(unique_name))

        # fix nowinok folder created
        model_dir_nowinok = os.path.join(self.dest_path, unique_name)
        if model_dir_nowinok != model_dir:
            if os.path.isdir(model_dir_nowinok):
                if not os.path.exists(model_dir):
                    os.rename(model_dir_nowinok, model_dir)
                    self.dbglog(func_name + ": renamed:" + model_dir_nowinok
                                                + " to " + model_dir)

        # fix mtp device folder created as usb device
        if self.device_info['connect_type'] == TYPE_MTP:
            usb_product = self.device_info['usb']['product']
            usb_serial = self.device_info['usb']['serial']
            usb_unique_name = db.get_unique_name(usb_product, usb_serial)
            if usb_unique_name and (usb_unique_name != unique_name):
                model_dir_old = os.path.join(self.dest_path, usb_unique_name)
                if os.path.isdir(model_dir_old):
                    if not os.path.exists(model_dir):
                        os.rename(model_dir_old, model_dir)
                        self.dbglog(func_name + ": renamed:" + model_dir_old
                                                    + " to " + model_dir)
                    history_file_old = os.path.join(self.history_dir,
                                                    usb_unique_name)
                    if os.path.exists(history_file_old):
                        os.remove(history_file_old)
                        self.dbglog(func_name + ": removed:" + history_file_old)
                db.delete(usb_product, usb_serial)

        # FIXME!! it used by history function
        self.unique_name = unique_name

        return model_dir

    def copyDate(self,src,dst): 
        file_list = os.listdir(src)
        for filename in file_list:
            if cancel or self.error_count >= 3:
                return ERR_FAILED

            # インポート元パス、ログ出力用パス、テンポラリパスを生成
            srcpath = os.path.join(src,filename)
            logpath = srcpath.lstrip(MOUNT_DIR)
            tmppath = os.path.join(self.tmp_import_dir,filename)

            if os.path.isdir(srcpath):
                self.copyDate(srcpath,dst)
            else:
                # 隠しファイルはスキップ
                if self.check_hidden_file(srcpath):
                    continue

                # ファイルサイズとタイムスタンプ取得
                (srcsize,srctime) = self.get_size_and_time_from_file(srcpath)
                if srcsize is None or srctime is None:
                    self.dbglog("failed to get size or time from file")
                    self.errorHandling(logpath)
                    continue

                # exif情報が存在するなら撮影日(DateTimeOriginal)を取得
                datetime_original = self.get_datetime_original_from_file(srcpath)
                if datetime_original:
                    srctime = datetime_original

                # 最終的な値がUnixTimeとして1より小さい場合は強制的にUnixTime1に設定する
                # 仮に意図的に設定されていたとしてもsambaから適切な時間として見えないなどの問題があるため
                if self.get_unixtime(srctime) < 1:
                    srctime = datetime.datetime.fromtimestamp(1)

                # インポート先オリジナルパス生成
                dstpath_original = self.create_dstpath_original(srctime,filename)

                # インポート先オリジナルパスが別名を含み同じファイルが存在すればスキップ
                # 存在しない場合は別名含みインポートするべきパスを返す
                dstpath = self.get_path_of_no_duplicates_if_not_exist_identical_file(srcsize,srctime,dstpath_original)
                if not dstpath:
                    self.error_count = 0
                    continue
                self.dbglog("dstpath:" + dstpath)

                # テンポラリフォルダにダウンロード
                try:
                    shutil.copy2(srcpath, tmppath)
                except:
                    self.dbglog(tb.format_exc())
                    self.dbglog("failed to download file")
                    self.errorHandling(logpath)
                    continue
                
                # 属性とタイムスタンプを変更
                if self.set_perm(tmppath) != ERR_SUCCESS:
                    self.dbglog("failed to set perm")
                    self.errorHandling(logpath)
                    self.remove_file(tmppath)
                    continue

                if self.set_filetime(tmppath,srctime) != ERR_SUCCESS:
                    self.dbglog("failed to set filetime")
                    self.errorHandling(logpath)
                    self.remove_file(tmppath)
                    continue

                # インポート先へ移動
                if self.move_file(tmppath,dstpath) != ERR_SUCCESS:
                    self.dbglog("failed to move file")
                    self.errorHandling(logpath)
                    self.remove_file(tmppath)
                    continue

                self.error_count = 0

        return ERR_SUCCESS

    def check_hidden_file(self,path):
        hidden_pattern = re.compile(".*/\..*")
        if hidden_pattern.match(path):
            return True
        return False

    def diff_check(self,srctime,srcsize,dstpath):
        #self.dbglog("diff_check")

        dsttime = datetime.datetime.fromtimestamp(os.stat(dstpath).st_mtime)
        dstsize = os.stat(dstpath).st_size  

        if srctime != dsttime or srcsize != dstsize:
            return True
        else:
            return False

    def getSdList(self):
        self.dbglog("getSdList")

        self.sdlist = []
        tmp2 = "/sys/devices/platform/ehci_marvell.70059/usb1/1-1/1-1:1.0/host*/target*/*"
        while not cancel:
            if os.path.isdir(USB_PORT_PATH):
                find = glob.glob(tmp2)
                self.dbglog(find)
                if len(find) != 0:
                    pattern = re.compile("([0-9]+\:){3}[0-9]")
                    tmp_list = []
                    for i in find:      
                        file = os.path.basename(i)
                        if pattern.match(file):
                            tmp_list.append(i)
                    for i in tmp_list:
                        search_path = i + "/block:*"
                        tmp = glob.glob(search_path)
                        if len(tmp) != 0:
                            self.dbglog(tmp)
                            sd = os.path.basename(os.readlink(tmp[0]))
                            self.sdlist.append(sd)
                    break
                else:
                    time.sleep(0.1)
            else:
                break

    def checkMediaInsert(self):
        #self.dbglog("checkMediaInsert")

        self.sdpartlist = []
        if os.path.isdir(USB_PORT_PATH):
            for sd in self.sdlist:
                p = sp.Popen(["sg_turs","/dev/" + sd],stdout=sp.PIPE,stderr=sp.PIPE)
                sg_out,sg_err = p.communicate()
                #self.dbglog("sg_out:" + sg_out)
                #self.dbglog("sg_err:" + sg_err)
                #self.dbglog("sg_turs_returncode:" + sd + ":" + str(p.returncode))
        
                tmp_list = []
                if p.returncode == 0: # device ready
                    # メディアが挿入されているのが確認できたらマウントするパーティションリストを取得する
                    #self.dbglog("media found")
                    tmp_list = glob.glob("/sys/block/" + sd + "/" + sd + "*")
                    if len(tmp_list) > 0:
                        for part in tmp_list:
                            part_num = os.path.basename(part)
                            self.sdpartlist.append(os.path.join("/dev/",part_num))
                        self.sdpartlist.sort()
                    else:
                        # スーパーフロッピー形式?
                        self.sdpartlist.append(os.path.join("/dev/",sd))
                    return 0
                else:
                    self.dbglog("device not ready:" + sd)
            return 2
        else:
            return 1

    def umountDevice(self):
        #self.dbglog("umountDevice")
        self.kill_lock.lock(1)
        p = sp.Popen("mount",stdout=sp.PIPE)
        mount_out,mount_err = p.communicate()
        if p.returncode == 0:
            mount_list = mount_out.splitlines()
            for line in mount_list:
                if MOUNT_DIR in line:
                    sp.call(["umount","-f",MOUNT_DIR])
        self.kill_lock.unlock()

    def get_exif_from_file(self, path, field):
        try:
            img = Image.open(path)
            exif = img._getexif()
            for id, value in exif.items():
                if TAGS.get(id) == field:
                    return value
            return None
        except:
            self.dbglog(tb.format_exc())
            return None

    def get_datetime_original_from_file(self, path):
        try:
            datetime_original = self.get_exif_from_file(path, "DateTimeOriginal")
            return self.get_matched_datetime_original(datetime_original)
        except:
            self.dbglog(tb.format_exc())
            return None
        
    def get_size_and_time_from_file(self, path):
        try:
            filesize = os.stat(path).st_size
            filetime = datetime.datetime.fromtimestamp(os.stat(path).st_mtime)
            return (filesize, filetime)
        except:
            self.dbglog(tb.format_exc())
            return (None, None)
        
    def set_perm(self,path):
        self.dbglog("set_perm:" + str(path))
        try:
            os.chmod(path,0777)
            os.chown(path,pwd.getpwnam("nobody").pw_uid,grp.getgrnam("nobody").gr_gid)
        except:
            self.dbglog(tb.format_exc())
            return ERR_FAILED
        return ERR_SUCCESS

    def get_unixtime(self, filetime):
        self.dbglog("get_unixtime:" + str(filetime))
        try:
            unixtime = time.mktime(filetime.timetuple())
        except Exception, exc:
            if filetime == datetime.datetime.fromtimestamp(-1.0):
                self.dbglog("mktime can not make -1.0 from 1970/01/01 08:59:59")
                unixtime = -1.0
            else:
                raise exc
        return unixtime

    def set_filetime(self,path,filetime):
        self.dbglog("set_filetime:" + str(path) + ',' + str(filetime))
        try:
            atime = mtime = self.get_unixtime(filetime)
            os.utime(path, (atime,mtime))
        except:
            self.dbglog(tb.format_exc())
            return ERR_FAILED
        return ERR_SUCCESS

    def mkdir_with_perm(self,srcpath):
        self.dbglog("mkdir_with_perm:" + srcpath)
        result = ERR_SUCCESS
        try:
            umask = os.umask(0)
            os.mkdir(srcpath)
            if self.set_perm(srcpath) != ERR_SUCCESS:
                result = ERR_FAILED
            os.umask(umask)
        except:
            self.dbglog(tb.format_exc())
            return ERR_FAILED
        return result

    def create_dstpath_original(self,srctime,filename):
        #self.dbglog("create_dstpath_original")
        timestamp_dir_year = os.path.join(self.model_dir,srctime.strftime("%Y") + u"年".encode('utf-8'))
        timestamp_dir_month = os.path.join(timestamp_dir_year,srctime.strftime("%m") + u"月".encode('utf-8'))
        dstpath_original = os.path.join(timestamp_dir_month,filename)
        return dstpath_original
        
    def dbglog(self,strMsg):
        try:
            if self.debug:
                logging.debug(strMsg)
        except:
            pass
        return

    def get_lineno_from_tb(self,tb_out):
        lineno = ""
        lineno_pattern = re.compile("line ([0-9]+),")
        for line in tb_out.splitlines():
            line = line.strip()
            self.dbglog(line)
            match = lineno_pattern.search(line)
            if match:
                lineno = match.group(1)
        return lineno
                
def throw_signal(process_name,mypid):
    p = sp.Popen(["pgrep","-o","-f",process_name],stdout=sp.PIPE)
    pgrep_out,pgrep_err = p.communicate()
    if p.returncode == ERR_SUCCESS:
        pid = pgrep_out.strip()
        logging.debug("find pid:" + pid)
        if mypid != pid:
            logging.debug("kill:" + pid)
            try:
                os.kill(int(pid),signal.SIGUSR1)
                time.sleep(0.5)
            except:
                logging.debug(tb.format_exc())

def func(signo,frame):
    #logging.debug("func:signal received")
    global cancel
    cancel = True

signal.signal(signal.SIGUSR1,func)
cancel = False

# main
if __name__ == '__main__':

    # オプション解析
    opt = Option()

    if opt.options.debug:
        log_dir = os.path.join(opt.options.system,"log")
        if not os.path.isdir(log_dir):
            os.mkdir(log_dir)

        log_file = os.path.join(log_dir,"photo_import.log")
        logging.basicConfig(format=' %(process)d - %(asctime)s.%(msecs)d - %(message)s',datefmt='%Y/%m/%d %I:%M:%S',level=logging.DEBUG, filename=log_file)
        logging.debug(sys.argv)

        log_file_gp2 = os.path.join(log_dir, "libgphoto2.log")
        gp2.enable_debug(log_file_gp2)

    event = opt.options.event
    lock_dir = os.path.join(opt.options.system,"lock")
    if not os.path.isdir(lock_dir):
        os.mkdir(lock_dir)

    led_lock_file = os.path.join(lock_dir,"led_lock")
    led_lock = FileLocker(led_lock_file)
    # LED操作(add)
    if event == "add":
        led_lock.lock(1)
        sp.call([LED_CONT_PATH,"external_connected"])
        sp.call([LED_CONT_PATH,"clear_err"])
        led_lock.unlock()
        
    kill_lock_file = os.path.join(lock_dir,"kill_lock")
    kill_lock = FileLocker(kill_lock_file)
    # 起動完了チェック
    if not os.path.isfile(BOOT_DONE_FLAG):
        if opt.options.debug:
            logging.debug("error:During startup...")
        led_lock.lock(1)
        sp.call([LED_CONT_PATH,"err"])      
        led_lock.unlock()
        
        kill_lock.lock(1)
        sp.call(["/usr/local/bin/HDL_log","--message_id=ERR_PHOTO_IMPORT","エラー:1"])
        kill_lock.unlock()
        sys.exit()

    share_path=opt.options.share_path
    
    if event == "add":
        if not os.path.isdir(share_path):
            led_lock.lock(1)
            sp.call([LED_CONT_PATH,"err"])
            led_lock.unlock()
            if opt.options.debug:
                logging.debug("error:photo folder not found")
            kill_lock.lock(1)
            sp.call(["/usr/local/bin/HDL_log","--message_id=ERR_PHOTO_IMPORT","エラー:2"])
            kill_lock.unlock()
            sys.exit()

    # 自分より前に実行されているphoto_importプロセスを終了させる
    mypid = str(os.getpid())
    cancel = False
    kill_lock.lock(1)
    if not cancel:
        throw_signal(sys.argv[0],mypid)
    kill_lock.unlock()

    # photo_import.pyの排他処理
    import_lock_file = os.path.join(lock_dir,"hotplug_lock")

    if not cancel:
        import_lock = FileLocker(import_lock_file)
        import_lock.lock(1)

        dest_path = os.path.join(share_path,opt.options.dest_dir.lstrip("/"))
        if dest_path != share_path:
            if not os.path.isdir(dest_path):
                umask = os.umask(0)
                os.mkdir(dest_path)
                os.umask(umask)

        photo = PhotoImport(share_path,dest_path,event,opt.options.method,opt.options.system,opt.options.threshold,opt.options.debug)
        tmp_import_dir = os.path.join(opt.options.system,"tmp")

        if event == "add":
            if not os.path.isdir(tmp_import_dir):
                os.mkdir(tmp_import_dir)
            else:
                shutil.rmtree(tmp_import_dir)
                os.mkdir(tmp_import_dir)
            photo.update_device_info_from_usb_port()

        try:
            photo.import_device()
        except:
            lineno = photo.get_lineno_from_tb(tb.format_exc())
            photo.writeSystemLog("error_end",lineno)
            photo.ledControl("err")

        import_lock.unlock()
    
    # LED操作(remove)
    if event == "remove":
        photo.ledControl("external_disconnected")
    
    photo.dbglog("end script")
    sys.exit()

