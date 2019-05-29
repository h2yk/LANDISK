#!/usr/bin/env python
# -*- mode: landisk-python; coding: utf-8; -*-
# vim:ts=4 sw=4 sts=4 ai si et sta

import re
import os

from ionas.request import Request, RequestData, Entry
from ionas.exception import InvalidFileSystemError
#import ionas.resultcode as resultcode
OK = "0"
from iobase.cmdexec import CmdExec, NULL

QUERY_TYPE = "query"
FAL_PROXY_REQ_BASE_XML = {QUERY_TYPE: "<proxy><url/></proxy>"}
FAL_PROXY_PATH = "/mnt/hda5/bin/fal_proxy"

URL_NAME = "url"
SERVERLIST_NAME = "serverlist"
SERVER_NAME = "server"
NAME_PATH = SERVER_NAME + "/host"
PORT_PATH = SERVER_NAME + "/port"
HOST_NAME = "host"
PORT_NAME = "port"
PROXY_DIRECT = "DIRECT"
PROC_MOUNTS_DIR = "/proc/mounts"
VALID_FS_LIST = ("ext2", "ext3", "ext4", "xfs")
MOUNTS_OPT_SPLIT = ","
RW_OPT = "rw"
MOUNT_ROOT = "/mnt"
EXTERNAL_SHARE_DICT = {"usb1": MOUNT_ROOT + "/usb1",
                        "usb2": MOUNT_ROOT + "/usb2"}

NASDSYNC_DOWNLOAD_TMP_DIR = MOUNT_ROOT + "/sataraid1/nasdsync_download_tmp"

LOG_CMD = "/usr/local/bin/HDL_log"
MSG_ID_FMT = "--message_id=%s"


def __is_rw_opt_set(options):
    """Check if rw option is set."""

    for opt in options.split(MOUNTS_OPT_SPLIT):
        if opt == RW_OPT:
            return True
    return False


def __is_mount_point_available(mount_point):
    """Check if mount_point is available."""

    mount_re = re.compile("[^ ]+ " + mount_point + " ([^ ]+) ([^ ]+).*")
    for line in open(PROC_MOUNTS_DIR):
        match = mount_re.match(line)
        if not match:
            continue

        if not match.group(1) in VALID_FS_LIST:
            raise InvalidFileSystemError

        return __is_rw_opt_set(match.group(2))

    return False


def get_proxy(url):
    """Try to get proxy and return it."""

    entry = Entry(QUERY_TYPE,
                  FAL_PROXY_REQ_BASE_XML[QUERY_TYPE])

    entry.get().get_node(URL_NAME).set_text(url)
    request_data = RequestData(entry)
    target = Request(FAL_PROXY_PATH)
    result_entry = target.request(request_data).get_entrylist().get_entry()
    if result_entry.get_result() != OK:
        return ()

    serverlist = result_entry.get().get_node(SERVERLIST_NAME)
    for proxy in serverlist.get_node_list(SERVER_NAME):
        host = proxy.get_node(HOST_NAME).get_text()
        port = proxy.get_node(PORT_NAME).get_text()

        if not host or host == PROXY_DIRECT:
            continue

        proxy_server = host
        if port:
            proxy_server += ':' + port
        return proxy_server

    return ""


def is_share_available(share, share_abs_path):
    """Check if local share folder is available."""

    if not os.path.isdir(share_abs_path):
        return False

    if not share in EXTERNAL_SHARE_DICT:
        return True

    return __is_mount_point_available(share_abs_path)


def get_tmp_path(share_abs_path):
    if share_abs_path in EXTERNAL_SHARE_DICT.values():
        return share_abs_path
    return NASDSYNC_DOWNLOAD_TMP_DIR


def send_request(cmd, type, xml=""):
    response = Request(cmd).request(Entry(type, xml))
    entry = response.get_entrylist().get_entry()
    if int(entry.get_result()):
        return False
    return entry.get()


def save_event_log(event, arg):
    cmd = [LOG_CMD]
    cmd.append(MSG_ID_FMT % event)
    if arg:
        cmd.append(arg)
    CmdExec(cmd).execute(stdout=NULL, stderr=NULL)


def get_os_info():
    os_ = ""
    try:
        if os.path.exists("/etc/debian_version"):
            ver = open("/etc/debian_version").read().strip()
            os_ = "Debian " + ver
    except:
        pass
    return os_


def get_device_kind_info():
    device_kind = ""
    try:
        if os.path.exists("/var/lib/model"):
            file_ = open("/var/lib/model")
            for line in file_.readlines():
                if line.startswith("product_full"):
                    device_kind = line.split("\t")[1].strip()
    except:
        pass
    return device_kind


### 同期クラスの内部処理において大抵同様の処理のメソッド群 ###

def _init_check(self):
    info = self.info
    is_share_available(info.share_name, info.share_abs_path)


def _log_event(self, event, arg):
    if not event:
        return
    save_event_log(event, arg)


def _clear_announce(self):
    pass


def reset_proxy_info(self, host, port):
    self.server_tree.reset_proxy_info(host, port)


