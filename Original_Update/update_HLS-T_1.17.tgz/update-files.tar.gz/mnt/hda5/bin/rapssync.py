#!/usr/bin/env python
# -*- mode: landisk-python; coding: utf-8; -*-
# vim:ts=4 sw=4 sts=4 ai si et sta

"""
Copyright (C) I-O DATA DEVICE, INC.  All rights reserved.
"""
from __future__ import with_statement

import os
import sys
import time
import pwd
import grp
import re
import socket
import errno
import uuid
import subprocess as sp
import traceback as tb

import rapsclient as rc

from ionas.filesync import (LocalFileTree, RAPSFileTree,
                            WRITE_TEMP_FILE_NAME)

from nasdsync_util import (MOUNT_ROOT,
                           EXTERNAL_SHARE_DICT,
                           _init_check,
                           _log_event,
                           get_tmp_path,
                           _clear_announce,
                           get_os_info,
                           get_device_kind_info)

from shareconfigmgr import ShareConfigMgr

from ionas.filesync.sync import (SimpleSyncInfo, SimpleSync,
                                 SyncMgr, RETRY_PERIOD, RECOVER_PERIOD, debug)

XML_ENCODE = "utf-8"
XML_DECLARE = True

IGNORED_LIST = [re.compile("^%s" % re.escape(WRITE_TEMP_FILE_NAME))]

ERR_INFO_FMT_BASE = " (internal error info: %s/%s/%s)"

CLIB_ERR_STUN = 50100
CLIB_ERR_BROKER_GET_ROUTE = 50201
CLIB_ERR_BROKER_CONNECT_P2P = 50202

INVALID_PIN = 1
OFF_LINE = 2
INVALID_CONDITION = 3
EXCEED_SESSION = 4

INVALID_PIN_EV = "ERR_RL3_CLIENT_INVALID_PIN"
INVALID_CONDITION_EV = "ERR_RL3_CLIENT_INVALID_CONDITION"
SELF_OFF_LINE_EV = "ERR_RL3_CLIENT_SELF_OFF_LINE"
TARGET_OFF_LINE_EV = "ERR_RL3_CLIENT_TARGET_OFF_LINE"
EXCEED_SESSION_EV = "ERR_RL3_CLIENT_EXCEED_SESSION"

CONF_DIR = "/mnt/hda5/conf/raps"
LOCK_DIR = "/var/lock/landisk"
CONF_FILE = "share_config"
CLIENTID_FILE = "clientid"
CONF_PATH = os.path.join(CONF_DIR, CONF_FILE)
CLIENTID_PATH = os.path.join(CONF_DIR, CLIENTID_FILE)
LOCK_PATH = os.path.join(LOCK_DIR, CONF_FILE)
DEFAULT_SHARE_ROOT = MOUNT_ROOT + "/sataraid1/share"
FIELD_TUPLE = (("dst_share", str),
               ("login_id", str),
               ("password", str),
               ("pin", str),
               ("ssl", bool), 
               ("host", str),
               ("port", int))
FIELD_SEP = "\t"
SET_OPT = "save_raps_setting"
CLEAR_OPT = "clear_raps_setting"
TEST_OPT = "test_raps_setting"

# clientid generate if not exist
try:
    clientid = open(CLIENTID_PATH).read().strip()
except:
    clientid = str(uuid.uuid1())
    try:
        open(CLIENTID_PATH, 'w').write(clientid)
    except Exception, exc:
        os.remove(CLIENTID_PATH)
        raise exc

def TEST_FUNC(opts):
    client = rc.RapsClient()
    client.set_xmlencode(XML_ENCODE, XML_DECLARE)

    if opts.pin:
        client.set_rl3(opts.pin, opts.ssl)
    else:
        client.set_host(opts.host, opts.port, opts.ssl)

    client.set_auth(opts.login_id, opts.password, clientid)
    client.set_term_info(TERM_INFO)

    timeout_bkup = socket.getdefaulttimeout()
    socket.setdefaulttimeout(30)
    try:
        client.getdir("/" + opts.dst_share, maxnum="1")

    except rc.TouError, exc:
        result_code = None
        err_code = None

        if exc.result_code != rc.INIT_RESULT_CODE:
            result_code = exc.result_code
        if exc.err_code != rc.INIT_ERR_CODE:
            err_code = exc.err_code

        if err_code:
            real_result_code = str(result_code) + "%02d" % err_code
        else:
            real_result_code = str(result_code)

        print exc.__class__.__name__ + "," + real_result_code
        sys.exit(1)

    except rc.TouTimeOutError, exc:
        print exc.__class__.__name__
        sys.exit(1)

    except rc.RapsError, exc:
        print exc.__class__.__name__ + "," + str(exc.status)
        sys.exit(1)

    except Exception, exc:
        print exc.__class__.__name__ + "," + str(exc)
        sys.exit(1)

    finally:
        try:
            try:
                if client.raps_connected:
                    client.raps_disconnect()
            finally:
                if client.rl3_connected:
                    client.rl3_disconnect()
        finally:
            socket.setdefaulttimeout(timeout_bkup)

    print "OK" 
    sys.exit(0)

RAPS_CONF_MGR = ShareConfigMgr(CONF_PATH, LOCK_PATH,
                               FIELD_TUPLE, FIELD_SEP,
                               SET_OPT, CLEAR_OPT, TEST_OPT,
                               TEST_FUNC)

EXCUSE_TIME_FAT = 2

# TermInfoKind, TermInfoOS, TermInfoOther
TERM_INFO = (get_device_kind_info(), get_os_info(), "")

RL3_ROUTE_TABLE = {"DIRECT": "0", "DDNS": "1", "P2P": "2"}


def _raps_log_event(self, event, args={}, err_info="", exc_ins=None):
    if not event: 
        return

    arg = ""
    real_result_code = None

    if isinstance(exc_ins, rc.RapsError):
        real_result_code = exc_ins.status

    elif isinstance(exc_ins, rc.TouError):
        result_code = ""
        err_code = ""

        if exc_ins.result_code != rc.INIT_RESULT_CODE:
            result_code = exc_ins.result_code
        if exc_ins.err_code != rc.INIT_ERR_CODE:
            err_code = exc_ins.err_code

        if err_code:
            real_result_code = str(result_code) + "%02d" % err_code
        else:
            real_result_code = str(result_code)

    arg += ",".join(args.values())

    if real_result_code:
        arg += " (ErrCode:%s)" % real_result_code

    try:
        arg += " [Route:%s]" % RL3_ROUTE_TABLE[exc_ins.rl3_route]
    except:
        try:
            arg += " [Route:%s]" % RL3_ROUTE_TABLE[exc_ins.detail.rl3_route]
        except:
            pass
        
    debug([event, arg, exc_ins.__class__.__name__, str(exc_ins)],
          fmt="LOG_EVENT ev=%s, arg=%s, exc=%s, exc_str=%s\n")

    _log_event(self, event, arg)


def _misc_err_handle(self, exc, do_recover=False):
    event = ""

    if isinstance(exc, rc.TouError):
        if exc.result_code == CLIB_ERR_STUN:
            event = SELF_OFF_LINE_EV

        elif exc.result_code == CLIB_ERR_BROKER_GET_ROUTE:
            if exc.err_code == INVALID_PIN:
                event = INVALID_PIN_EV
            elif exc.err_code == OFF_LINE:
                event = TARGET_OFF_LINE_EV
            elif exc.err_code == INVALID_CONDITION:
                event = INVALID_CONDITION_EV

        elif exc.result_code == CLIB_ERR_BROKER_CONNECT_P2P:
            if exc.err_code == INVALID_PIN:
                event = INVALID_PIN_EV
            elif exc.err_code == OFF_LINE:
                event = TARGET_OFF_LINE_EV
            elif exc.err_code == INVALID_CONDITION:
                event = INVALID_CONDITION_EV
            elif exc.err_code == EXCEED_SESSION:
                event = EXCEED_SESSION_EV

    if event:
        debug(["!!! retry err !!!"])
        sleep_time = RETRY_PERIOD
    else:
        if do_recover:
            debug(["!!! do recover !!!"])
            time.sleep(RECOVER_PERIOD)
            return True
        sleep_time = 0
        event = self.MISC_ERR_LOG

    self._log_event(event, {"name": self.info.share_name}, tb.format_exc(), exc)
    
    time.sleep(sleep_time)   

    raise exc, None, sys.exc_info()[2]


def _set_trees(self):
    info = self.info
    self.local_tree = LocalFileTree(info.share_abs_path,
                                    tmp_path=get_tmp_path(info.share_abs_path),
                                    log_callback=self.log_callback,
                                    ctime=False)
    self.local_tree.EXCUSE_TIME = EXCUSE_TIME_FAT

    self.server_tree = RAPSFileTree(info.client,
                                    info.dst_share,
                                    ignored_list=IGNORED_LIST,
                                    log_callback=self.log_callback)
    self.server_tree.EXCUSE_TIME = 0


def _is_timeout_err(self, err):
    try:
        if isinstance(err, rc.TouTimeOutError):
            return True

        if isinstance(err, socket.gaierror):
            timeout_args_list = (
                socket.EAI_NONAME,
                socket.EAI_NODATA)

            if err.args[0] in timeout_args_list:
                return True

        elif isinstance(err, socket.timeout):
            return True

        elif isinstance(err, socket.sslerror):
            if err.args[0] == "The read operation timed out":
                return True

        elif isinstance(err, socket.error):
            timeout_args_list = (
                errno.ETIMEDOUT,
                errno.EHOSTUNREACH)

            if err.args[0] in timeout_args_list:
                return True

            try:
                if err.args[1].endswith("(Gateway Time-out)"):
                    return True
            except:
                pass

        elif isinstance(err, IOError):
            if isinstance(err.args[1], socket.timeout):
                return True

    except:
        pass

    return False


def _pre_sync(self):
    #self._try_access(self.server_tree.connect)
    pass


def _post_sync(self):
    ### RL3クライアントのmemory leak対策
    if hasattr(self, "sync_count"):
        self.sync_count += 1   
    else:
        self.sync_count = 1  
    ###

    self._try_access(self.server_tree.disconnect)


class RAPSSyncMgr(SyncMgr):

    SERVICE_ERR_LIST = [rc.RapsError, rc.TouError]

    SHARE_CONF_SERVICE_ENABLE_PATH = ""

    ANNOUNCE_CAT = None

    NASCMD_FOR_LOGGING = None

    SYNC_CLASS = SimpleSync

    class RAPSInfo(SimpleSyncInfo):
        def __init__(self, share_name, share_abs_path, uid, gid, client, dst_share):
            super(self.__class__, self).__init__(share_name,
                                                 share_abs_path,
                                                 uid,
                                                 gid)
            self.client = client
            self.dst_share = dst_share

    def _get_info(self, share_name, share_abs_path, client, dst_share):
        nobody_uid = pwd.getpwnam("nobody")[2]
        nogroup_gid = grp.getgrnam("nogroup")[2]

        return self.RAPSInfo(share_name, share_abs_path,
                           nobody_uid, nogroup_gid, client, dst_share)

    def _get_sync_dict(self):
        debug()
        share_config = RAPS_CONF_MGR
        share_config.lock()
        share_config.load()
        share_config.unlock()

        sync_dict = {}
        for share in share_config.get_target_list():
            share_base = os.path.basename(share)
            if share_base in EXTERNAL_SHARE_DICT:
                share_abs_path = EXTERNAL_SHARE_DICT[share_base]
            else:
                share_abs_path = os.path.join(DEFAULT_SHARE_ROOT, share)

            try:
                dst_share, user, password, pin, ssl, host, port =\
                    share_config.get_setting(share_base)
                client = rc.RapsClient()
                client.set_xmlencode(XML_ENCODE, XML_DECLARE)
                client.set_rl3(pin, ssl)
                client.set_auth(user, password, clientid)
                client.set_term_info(TERM_INFO)
                info = self._get_info(share_base, share_abs_path, client, dst_share)
                sync_dict[share_base] = self.SYNC_CLASS(
                    info,
                    service_err_list=self.SERVICE_ERR_LIST,
                    no_root_entry_err_log="ERR_RAPS_CLIENT_SHARE_NOT_EXISTS",
                    no_space_on_local_err_log="ERR_RAPS_CLIENT_NO_SPACE_ON_LOCAL",
                    no_space_on_server_err_log="ERR_RAPS_CLIENT_NO_SPACE_ON_SERVER",
                    invalid_identifier_err_log="ERR_RAPS_CLIENT_INVALID_IDENTIFIER",
                    invalid_password_err_log="",
                    invalid_fs_err_log="ERR_RAPS_CLIENT_INVALID_FILESYSTEM",
                    file_too_large_err_log="ERR_RAPS_CLIENT_FILE_TOO_LARGE",
                    exceed_session_err_log="ERR_RAPS_CLIENT_EXCEED_SESSION",
                    skip_err_log="ERR_RAPS_CLIENT_SYNC_SKIP",
                    timeout_err_log="ERR_RL3_CLIENT_TIMEOUT",
                    misc_err_log="ERR_RAPS_CLIENT_SYNC",
                    nascmd_for_logging=None,
                    _set_trees=_set_trees, _init_check=_init_check,
                    _is_timeout_err=_is_timeout_err, _log_event=_raps_log_event,
                    _clear_announce=_clear_announce,
                    _pre_sync=_pre_sync, _post_sync=_post_sync,
                    _misc_err_handle=_misc_err_handle)

            except:
                pass

        return sync_dict

    def _reset_proxy_info(self):
        pass

    def _log_event(self, event, args={}, err_info="", exc_ins=None):
        _raps_log_event(self, event, args, err_info, exc_ins)

    def _clear_announce(self):
        _clear_announce(self)

    def sync(self):
        ### RL3クライアントのmemory leak対策
        sync_count = 0
        for sync_obj in self.sync_dict.values():
            if hasattr(sync_obj, "sync_count"):
                sync_count += sync_obj.sync_count
        if sync_count:
            debug([sync_count], fmt="!!!SyncCount:%s\n")
        if sync_count > 100:
            debug(["!!! sync count is over than threshold, restart me... !!!"])
            for sync_obj in self.sync_dict.values():
                try:
                    sync_obj.info.client.disconnect()
                    debug([sync_obj.info.share_name], fmt="disconnected:%s\n")
                except:
                    debug([tb.format_exc()])
                    pass
            sp.call(["/mnt/hda5/bin/restart_nasdsync.sh"])
            time.sleep(60)
        ###

        self._sync()
