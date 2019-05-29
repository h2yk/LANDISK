# -*- mode: landisk-python; coding: utf-8; -*-
from __future__ import with_statement
import os
import shutil
import stat
import sys
import time
import locale
import errno
from subprocess import (Popen)

try:
    import email.utils as emailut
except:
    import email.Utils as emailut

import inspect
import traceback

from dropbox2.client import (DropboxClient,
                             BaseDropboxError,
                             BadInputParameterDropboxError,
                             DisallowedNameDropboxError,
                             MalformedPathDropboxError,
                             InsufficientSpaceDropboxError,
                             NotFoundDropboxError,
                             ConflictDropboxError,
                             UnknownDropboxError)

MOUNT_ROOT = "/mnt"
TMP_DIR = MOUNT_ROOT + "/sataraid1/dropbox_tmp"
READ_BUFFER_SIZE = 65536

IGNORED_FILE_LIST = ("thumbs.db", ".ds_store", "lost+found")

PATH_NAME = "path"
IS_DIR_NAME = "is_dir"
IS_DELETED_NAME = "is_deleted"

DIRS_NAME = "dirs"
CONTENTS_NAME = "contents"
FILES_NAME = "files"
BYTES_NAME = "bytes"
MODIFIED_NAME = "modified"

LSTATE_NAME = "lstate"
DSTATE_NAME = "dstate"
STATE_SAME = "same"
STATE_NEW = "new"
STATE_DEL = "del"

DEFAULT_LOCALE = "UTF8"

HDL_LOG_CMD_ARGS = ('/usr/local/bin/HDL_log',)
HDL_LOG_MSG_OPT = '--message_id='
ERR_DROPBOX_SYNC_SKIP = "ERR_DROPBOX_SYNC_SKIP"
ERR_DROPBOX_FILE_TOO_LARGE = "ERR_DROPBOX_FILE_TOO_LARGE"

STATUS_BAD_INPUT_PARAM = 400
STATUS_ALREADY_EXISTS = 403
STATUS_NOT_FOUND = 404
STATUS_FILE_TOO_LARGE = 413

UPLOAD_LIMIT = 300 * 1024 * 1024

SHARE_CHECK_PERIOD = 60

def log_event(event, args=()):
    """Log a HDL-XR event."""

    cmd_args = list(HDL_LOG_CMD_ARGS)
    cmd_args += [HDL_LOG_MSG_OPT + event]
    cmd_args += args
    proc = Popen(cmd_args)
    proc.wait()

class CreateSyncRootError(Exception):
    """Fail to create synchronized root directory on Dropbox."""

    pass

class ShareNameFileError(Exception):
    """File already exists which name is same as share name."""

    pass

class InternalError(Exception):
    """Internal Error."""

    pass

class DropboxSync:
    "Class for synchronizing local and Dropbox's directory."

    def __get_funcname(self, back = 0):
        if back == 0:
            (filename, lineno, function, code_context, index) = \
                    inspect.getframeinfo(inspect.currentframe().f_back)
        if back == 1:
            (filename, lineno, function, code_context, index) = \
                    inspect.getframeinfo(inspect.currentframe().f_back.f_back)
        return function

    def __debug_output(self, message, back = 0):
        if not self.debug:
            return
        if back == 0:
            (filename, lineno, function, code_context, index) = \
                    inspect.getframeinfo(inspect.currentframe().f_back)
        if back == 1:
            (filename, lineno, function, code_context, index) = \
                    inspect.getframeinfo(inspect.currentframe().f_back.f_back)
        line = self.encoding + ":" + os.path.basename(filename) + ":" + str(lineno)
        debug_output = line + " " + message
        print >> self.output, debug_output

    def __debug_func(self):
        self.__debug_output("FUNC:" + self.__get_funcname(1), 1)

    def __get_mtime_str(self, path=None):
        """Return mtime with the RFC 2822 fomat string. """

        if path:
            mtime = os.path.getmtime(path)
        else:
            mtime = 0
        return emailut.formatdate(mtime).replace("-0000", "+0000")

    def __set_mtime(self, path, mtime_str):
        """Set mtime with the RFC 2822 fomat string. """

        if not mtime_str:
            raise InternalError

        mtime = emailut.mktime_tz(emailut.parsedate_tz(mtime_str))
        os.utime(path, (mtime, mtime))

    def __get_dir_init_info(self, lstate, dstate):
        """ Return the directory initialized information. """

        return {LSTATE_NAME: lstate, DSTATE_NAME: dstate, \
                CONTENTS_NAME: {DIRS_NAME: {}, FILES_NAME: {}}}

    def __get_file_info(self, path, lstate, dstate):
        """Return the file information. """

        return {
            LSTATE_NAME: lstate,
            DSTATE_NAME: dstate,
            BYTES_NAME: str(os.path.getsize(path)),
            MODIFIED_NAME: self.__get_mtime_str(path),
        }

    def __get_path_on_dropbox(self, dirname, name):
        dropbox_sync_root = self.dropbox_path.strip('/')
        dirname = dirname.strip('/')
        name = name.strip('/')

        path = ''
        if dropbox_sync_root: path = '/' + dropbox_sync_root
        if dirname: path = '/'.join([path, dirname])
        if name: path = '/'.join([path, name])
        path = path.decode(self.encoding)
        path = path.encode('UTF-8')
        return path

    def __init_sync_tree(self, path=None, root_tree=None):
        """Initialize the sychronous tree."""

        if not path:
            path = self.local_path

        if not root_tree:
            self.sync_tree = {
                DIRS_NAME: {},
                FILES_NAME: {},
            }
            root_tree = self.sync_tree

        for name in os.listdir(path):
            try:
                if name.lower() in IGNORED_FILE_LIST:
                    continue
            except:
                if self.debug:
                    traceback.print_exc(file=self.output)
                pass

            abspath = os.path.join(path, name)
            if os.path.isdir(abspath):
                root_tree[DIRS_NAME][name] = \
                    self.__get_dir_init_info(STATE_NEW, STATE_SAME)
                self.__init_sync_tree(abspath, \
                                root_tree[DIRS_NAME][name][CONTENTS_NAME])

            else:
                file_info = self.__get_file_info(abspath, STATE_NEW, STATE_SAME)
                root_tree[FILES_NAME][name] = file_info
                root_tree[FILES_NAME][name][BYTES_NAME] = str(0)
                root_tree[FILES_NAME][name][MODIFIED_NAME] = \
                        self.__get_mtime_str()

    def __update_for_local_name(self, path, name, root_tree):
        """Update the sychronous tree for local path/name and
           return True if it is updated.
        """
        is_updated = False

        abspath = os.path.join(path, name)
        if os.path.isdir(abspath):
            if name not in root_tree[DIRS_NAME]:
                is_updated = True
                root_tree[DIRS_NAME][name] = \
                    self.__get_dir_init_info(STATE_NEW, STATE_SAME)
            if (name in root_tree[FILES_NAME] and
                    root_tree[FILES_NAME][name][LSTATE_NAME] != STATE_DEL):

                is_updated = True
                root_tree[FILES_NAME][name][LSTATE_NAME] = STATE_DEL

        else:
            if name in root_tree[FILES_NAME]:
                cur_info = root_tree[FILES_NAME][name]
                new_info = self.__get_file_info(abspath, STATE_NEW,
                                    cur_info[DSTATE_NAME])

                if ((cur_info[BYTES_NAME] != new_info[BYTES_NAME]) or
                    (cur_info[MODIFIED_NAME] != new_info[MODIFIED_NAME])):

                    if cur_info[DSTATE_NAME] == STATE_NEW:
                        cur_mtime = emailut.mktime_tz(
                                emailut.parsedate_tz(cur_info[MODIFIED_NAME]))
                        new_mtime = emailut.mktime_tz(
                                emailut.parsedate_tz(new_info[MODIFIED_NAME]))

                        if cur_mtime >= new_mtime:
                            cur_info[LSTATE_NAME] = STATE_SAME
                        else:
                            is_updated = True
                            new_info[DSTATE_NAME] = STATE_SAME
                            root_tree[FILES_NAME][name] = new_info

                    else:
                        is_updated = True
                        root_tree[FILES_NAME][name] = new_info

            else:
                is_updated = True
                root_tree[FILES_NAME][name] = \
                    self.__get_file_info(abspath, STATE_NEW, STATE_SAME)

            if (name in root_tree[DIRS_NAME] and
                    root_tree[DIRS_NAME][name][LSTATE_NAME] != STATE_DEL):

                is_updated = True
                root_tree[DIRS_NAME][name][LSTATE_NAME] = STATE_DEL

        return is_updated

    def __update_for_local_delete(self, path, root_tree):
        """Update the sychronous tree for the deleted local contents and
           return True if it is updated.
        """
        is_updated = False

        for file in root_tree[FILES_NAME].keys():
            if (not os.path.exists(os.path.join(path, file)) and
                    root_tree[FILES_NAME][file][LSTATE_NAME] != STATE_DEL):

                is_updated = True
                root_tree[FILES_NAME][file][LSTATE_NAME] = STATE_DEL

        for dir in root_tree[DIRS_NAME].keys():
            if (not os.path.exists(os.path.join(path, dir)) and
                    root_tree[DIRS_NAME][dir][LSTATE_NAME] != STATE_DEL):

                is_updated = True
                root_tree[DIRS_NAME][dir][LSTATE_NAME] = STATE_DEL

        return is_updated

    def __update_sync_tree_for_local(self, path, root_tree):
        """Update the sychronous tree for the local directories and
           return True if it is updated.
        """
        is_updated = False

        for name in os.listdir(path):
            try:
                if name.lower() in IGNORED_FILE_LIST:
                    continue
            except:
                if self.debug:
                    traceback.print_exc(file=self.output)
                pass

            if self.__update_for_local_name(path, name, root_tree):
                is_updated = True

        if self.__update_for_local_delete(path, root_tree):
            is_updated = True

        for dir in root_tree[DIRS_NAME].keys():
            if root_tree[DIRS_NAME][dir][LSTATE_NAME] == STATE_DEL:
                continue

            if self.__update_sync_tree_for_local(os.path.join(path, dir),
                        root_tree[DIRS_NAME][dir][CONTENTS_NAME]):

                is_updated = True

        return is_updated

    def __update_for_dropbox_delete(self, path, root_tree, contents):
        """Update the sychronous tree for the deleted contents on dropbox and
           return True if it is updated.
        """
        is_updated = False

        name_list = []
        for content in contents:
            name = os.path.basename((content[PATH_NAME]).encode(self.encoding))
            name_list.append(name)

        for file in root_tree[FILES_NAME].keys():
            if (not file in name_list and
                    root_tree[FILES_NAME][file][DSTATE_NAME] != STATE_DEL):

                is_updated = True
                root_tree[FILES_NAME][file][DSTATE_NAME] = STATE_DEL

        for dir in root_tree[DIRS_NAME].keys():
            if (not dir in name_list and
                    root_tree[DIRS_NAME][dir][DSTATE_NAME] != STATE_DEL):

                is_updated = True
                root_tree[DIRS_NAME][dir][DSTATE_NAME] = STATE_DEL

        return is_updated

    def __update_for_dropbox_name(self, path, name, root_tree, content):
        """Update the sychronous tree for path/name on dropbox and
           return True if it is updated.
        """
        is_updated = False

        if content.get(IS_DIR_NAME, False):
            if name not in root_tree[DIRS_NAME]:
                is_updated = True
                root_tree[DIRS_NAME][name] = \
                    self.__get_dir_init_info(STATE_SAME, STATE_NEW)
            else:
                if root_tree[DIRS_NAME][name][LSTATE_NAME] == STATE_NEW:
                    root_tree[DIRS_NAME][name][LSTATE_NAME] = STATE_SAME
                    root_tree[DIRS_NAME][name][DSTATE_NAME] = STATE_SAME

            if (name in root_tree[FILES_NAME] and
                    root_tree[FILES_NAME][name][DSTATE_NAME] != STATE_DEL):

                is_updated = True
                root_tree[FILES_NAME][name][DSTATE_NAME] = STATE_DEL

        else:
            new_bytes = str(content[BYTES_NAME])
            new_modified = content[MODIFIED_NAME].encode(self.encoding)
            if name in root_tree[FILES_NAME]:
                cur_info = root_tree[FILES_NAME][name]
                cur_bytes = cur_info[BYTES_NAME]
                cur_modified = cur_info[MODIFIED_NAME]

                if (cur_bytes == new_bytes and cur_modified == new_modified):

                    if cur_info[LSTATE_NAME] == STATE_NEW:
                        cur_info[LSTATE_NAME] = STATE_SAME

                elif (cur_bytes != new_bytes or cur_modified != new_modified):

                    if cur_info[LSTATE_NAME] == STATE_NEW:
                        cur_mtime = emailut.mktime_tz(
                                emailut.parsedate_tz(cur_modified))
                        new_mtime = emailut.mktime_tz(
                                emailut.parsedate_tz(new_modified))

                        if cur_mtime >= new_mtime:
                            cur_info[DSTATE_NAME] = STATE_SAME

                        else:
                            is_updated = True
                            cur_info[BYTES_NAME] = new_bytes
                            cur_info[MODIFIED_NAME] = new_modified
                            cur_info[LSTATE_NAME] = STATE_SAME
                            cur_info[DSTATE_NAME] = STATE_NEW

                    else:
                            if cur_info[DSTATE_NAME] != STATE_NEW:
                                is_updated = True
                            cur_info[BYTES_NAME] = new_bytes
                            cur_info[MODIFIED_NAME] = new_modified
                            cur_info[DSTATE_NAME] = STATE_NEW

            else:
                is_updated = True
                root_tree[FILES_NAME][name] = \
                    {LSTATE_NAME: STATE_SAME, DSTATE_NAME: STATE_NEW, \
                        BYTES_NAME: new_bytes, \
                        MODIFIED_NAME: new_modified}

            if (name in root_tree[DIRS_NAME] and
                    root_tree[DIRS_NAME][name][DSTATE_NAME] != STATE_DEL):

                is_updated = True
                root_tree[DIRS_NAME][name][DSTATE_NAME] = STATE_DEL

        return is_updated

    def __update_sync_tree_for_dropbox(self, path, root_tree):
        """Update the sychronous tree for the dropbox directories and
           return True if it is updated.
        """
        is_updated = False

        contents = []
        for f in self.dropbox_client.get_files(path):
            content = {
                PATH_NAME: f.path,
                IS_DIR_NAME: f.is_folder,
                IS_DELETED_NAME: f.is_deleted,
            }
            if f.is_file:
                content[BYTES_NAME] = f.size
                content[MODIFIED_NAME] = f._modified_rfc2822
            contents.append(content)
        for content in contents:
            name = os.path.basename((content[PATH_NAME]).encode(self.encoding))
            if self.__update_for_dropbox_name(path, name, root_tree, content):
                is_updated = True

        if self.__update_for_dropbox_delete(path, root_tree, contents):

            is_updated = True

        for dir in root_tree[DIRS_NAME].keys():
            if root_tree[DIRS_NAME][dir][DSTATE_NAME] == STATE_DEL:
                continue

            if self.__update_sync_tree_for_dropbox(path + "/" + \
                            dir.decode(self.encoding),
                        root_tree[DIRS_NAME][dir][CONTENTS_NAME]):

                is_updated = True

        return is_updated

    def __delete_err_file(self, path):
        ins = sys.exc_info()[1]
        try:
            os.remove(path)
        except:
            if self.debug:
                traceback.print_exc(file=self.output)
            pass
        raise ins

    def __try_file_upload(self, relpath, file, file_info):
        """Upload file from local to Dropbox."""

        if file_info[LSTATE_NAME] != STATE_NEW:
            return

        lpath = self.local_path + "/" + relpath + "/" + file
        dpath = self.__get_path_on_dropbox(relpath, file)
        self.__debug_output("try file upload " + lpath + " to " + dpath.decode("UTF-8").encode(self.encoding))
        try:
            with open(lpath) as f:
                r = self.dropbox_client.put_file(dpath,
                                                 f,
                                                 overwrite=True,
                                                 return_resource_object=True)

            file_info[LSTATE_NAME] = STATE_SAME
            file_info[DSTATE_NAME] = STATE_SAME
            mtime = r._modified_rfc2822
            self.__set_mtime(lpath, mtime)
            file_info[MODIFIED_NAME] = mtime
        except (IOError), err:
            if err.errno == errno.ENOENT:
                file_info[LSTATE_NAME] = STATE_DEL
                return
            raise

    def __try_file_download(self, relpath, file, file_info):
        """Download file from Dropbox to local."""

        if file_info[DSTATE_NAME] != STATE_NEW:
            return

        tmppath = TMP_DIR + '/' + file
        lpath = self.local_path + "/" + relpath + "/" + file
        dpath = self.__get_path_on_dropbox(relpath, file)
        self.__debug_output("try file download " + lpath + " from " + dpath.decode("UTF-8").encode(self.encoding))
        try:
            response = self.dropbox_client.get_file(dpath)
        except NotFoundDropboxError:
            file_info[DSTATE_NAME] = STATE_DEL
            return

        try:
            dest_file = open(tmppath, "wb")
            while True:
                response_data = response.read(READ_BUFFER_SIZE)
                if not response_data:
                    break
                dest_file.write(response_data)
            dest_file.close()
            os.chmod(tmppath, (stat.S_IRUSR | stat.S_IWUSR | stat.S_IRGRP |
                             stat.S_IWGRP | stat.S_IROTH | stat.S_IWOTH))
            self.__set_mtime(tmppath, file_info[MODIFIED_NAME])

            try:
                shutil.move(tmppath, lpath)
            except:
                if self.debug:
                    traceback.print_exc(file=self.output)
                self.__delete_err_file(lpath)

            file_info[LSTATE_NAME] = STATE_SAME
            file_info[DSTATE_NAME] = STATE_SAME

        except:
            if self.debug:
                traceback.print_exc(file=self.output)
            self.__delete_err_file(tmppath)

    def __try_file_delete(self, relpath, file, file_info):
        """Delete file from local or Dropbox."""

        if (file_info[DSTATE_NAME] == STATE_NEW or
                file_info[LSTATE_NAME] == STATE_NEW):

            return False

        is_deleted = False
        if file_info[DSTATE_NAME] == STATE_DEL:
            lpath = self.local_path + "/" + relpath + "/" + file
            self.__debug_output("try file delete " + lpath)
            is_deleted = True
            if file_info[LSTATE_NAME] != STATE_DEL:
                try:
                    os.remove(lpath)
                except (OSError), err:
                    if err.errno != errno.ENOENT:
                        raise

        if file_info[LSTATE_NAME] == STATE_DEL:
            dpath = self.__get_path_on_dropbox(relpath, file)
            self.__debug_output("try file delete " + dpath.decode("UTF-8").encode(self.encoding))
            is_deleted = True
            if file_info[DSTATE_NAME] != STATE_DEL:
                try:
                    self.dropbox_client.remove_file(dpath)
                except NotFoundDropboxError:
                    pass

        return is_deleted

    def __try_dir_create(self, relpath, dir, dir_info):
        """Create directory from local or Dropbox."""

        if dir_info[DSTATE_NAME] == STATE_NEW:
            lpath = self.local_path + "/" + relpath + "/" + dir
            self.__debug_output("try dir create " + lpath)
            try:
                os.mkdir(lpath)
            except (OSError), err:
                if err.errno != errno.EEXIST:
                    raise

            os.chmod(lpath, stat.S_IRWXU | stat.S_IRWXG | stat.S_IRWXO)
            dir_info[LSTATE_NAME] = STATE_SAME
            dir_info[DSTATE_NAME] = STATE_SAME

        if dir_info[LSTATE_NAME] == STATE_NEW:
            dpath = self.__get_path_on_dropbox(relpath, dir)
            self.__debug_output("try dir create " + dpath.decode("UTF-8").encode(self.encoding))
            try:
                self.dropbox_client.create_folder(dpath)
            except ConflictDropboxError:
                pass

            dir_info[LSTATE_NAME] = STATE_SAME
            dir_info[DSTATE_NAME] = STATE_SAME

    def __try_dir_delete(self, relpath, dir, dir_info):
        """Delete directories from local or Dropbox."""

        if (dir_info[DSTATE_NAME] == STATE_NEW or
                dir_info[LSTATE_NAME] == STATE_NEW):

            return False

        is_deleted = False
        if dir_info[DSTATE_NAME] == STATE_DEL:
            lpath = self.local_path + "/" + relpath + "/" + dir
            self.__debug_output("try dir delete " + lpath)
            is_deleted = True
            if dir_info[LSTATE_NAME] != STATE_DEL:
                try:
                    shutil.rmtree(lpath)
                except (OSError), err:
                    if err.errno != errno.ENOENT:
                        raise

        if dir_info[LSTATE_NAME] == STATE_DEL:
            dpath = self.__get_path_on_dropbox(relpath, dir)
            self.__debug_output("try dir delete " + dpath.decode("UTF-8").encode(self.encoding))

            is_deleted = True
            if dir_info[DSTATE_NAME] != STATE_DEL:
                try:
                    self.dropbox_client.remove_file(dpath)
                except NotFoundDropboxError:
                    pass

        return is_deleted

    def __sync(self, relpath, root_tree):
        """Synchronize local and Dropbox's directories."""

        for file in root_tree[FILES_NAME].keys():
            file_info = root_tree[FILES_NAME][file]

            try:
                self.__try_file_upload(relpath, file, file_info)
                self.__try_file_download(relpath, file, file_info)
                if self.__try_file_delete(relpath, file, file_info):
                    del root_tree[FILES_NAME][file]

            except (BadInputParameterDropboxError, NotFoundDropboxError,
                    DisallowedNameDropboxError, MalformedPathDropboxError), e:
                log_event(ERR_DROPBOX_SYNC_SKIP, (file,))

            except IOError, e:
                if e.errno == errno.ENAMETOOLONG:
                    log_event(ERR_DROPBOX_SYNC_SKIP, (file,))
                else:
                    raise

        for dir in root_tree[DIRS_NAME].keys():
            dir_info = root_tree[DIRS_NAME][dir]

            self.__try_dir_create(relpath, dir, dir_info)
            if self.__try_dir_delete(relpath, dir, dir_info):
                del root_tree[DIRS_NAME][dir]
            else:
                self.__sync(relpath + "/" + dir,
                        root_tree[DIRS_NAME][dir][CONTENTS_NAME])

    def __init__(self, share, dropbox_client, debug=False, output=sys.stderr):
        """Constructor."""

        self.debug = debug
        self.output = output
        self.local_path = os.path.abspath(share)
        self.dropbox_client = dropbox_client
        self.encoding = locale.getdefaultlocale()[1]
        if not self.encoding:
            self.encoding = DEFAULT_LOCALE
        self.dropbox_path = ""

        try:
            shutil.rmtree(TMP_DIR)
        except (OSError), err:
            if err.errno != errno.ENOENT:
                raise
        os.mkdir(TMP_DIR)

        self.__init_sync_tree()

    def update_sync_tree_for_local(self):
        """Update the sychronous tree for the local directories and
           return True if it is updated.
        """
        self.__debug_func()

        return self.__update_sync_tree_for_local(self.local_path,
                                                self.sync_tree)

    def update_sync_tree_for_server(self):
        """Update the sychronous tree for the dropbox directories and
           return True if it is updated.
        """
        self.__debug_func()

        return  self.__update_sync_tree_for_dropbox(self.dropbox_path,
                                                self.sync_tree)

    def sync(self):
        """Synchronize local and Dropbox's directory."""
        self.__debug_func()

        self.__sync("", self.sync_tree)

    def try_sync_for_local(self):
        """Check the local update and try to synchronize."""
        self.__debug_func()

        # Wait for completing update.
        time.sleep(SHARE_CHECK_PERIOD)
        while self.update_sync_tree_for_local():
            time.sleep(SHARE_CHECK_PERIOD)

        self.update_sync_tree_for_server()
        self.sync()

    def try_sync_for_server(self):
        """Check the Dropbox update and try to synchronize."""
        self.__debug_func()

        # Wait for completing update.
        while self.update_sync_tree_for_server():
            time.sleep(SHARE_CHECK_PERIOD)

        self.update_sync_tree_for_local()
        self.sync()
