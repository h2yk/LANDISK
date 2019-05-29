# -*- coding: utf-8 -*-
import httplib, urllib, socket
import mimetools
import os

from xml.etree import ElementTree

header_content_type = "Content-Type"
header_content_disposition = "Content-Disposition"

header_company_id = "X-FBS-Company-id"
header_service_id = "X-FBS-Service-id"
header_session_id = "X-FBS-Session-id"
header_error_code = "X-FBS-Error-Code"
header_error_string = "X-FBS-Error-String"
header_result_count = "X-FBS-Result-Count"
header_file_offset = "X-FBS-File-Offset"
header_file_size = "X-FBS-File-Size"

body_login_id = "login_id"
body_password = "password"
body_folder_name = "folder_name"
body_file_id = "file_id"

EP_TABLE = {"east": ("www.azukeru.ntt-east.net",
                     "fbs-0008",
                     "pwugivugjj3tgulnacnxNbsji1aHgpc0"),
            "west": ("www.azukeru.ntt-west.net",
                     "faw-al-01",
                     "u9i3rnshtgfkri4h3yepwumzalo273jr")}

DEFAULT_EP = EP_TABLE["east"]

EP_IDX = 0
COMPANY_IDX = 1
SERVICE_IDX = 2

STATUS_OK = 200
STATUS_CREATED = 201
STATUS_BAD_REQUEST = 400
STATUS_UNAUTHORIZED = 401
STATUS_NOT_FOUND = 404
STATUS_METHOD_NOT = 405
STATUS_CONFLICT = 409

STATUS_SERVICE_UNAVAILABLE = 503

ERROR_CODE_INVALID_COMPANY_CODE = 1
ERROR_CODE_INVALID_SERVICE_CODE = 2
ERROR_CODE_INVALID_USER_ACL = 3
#ERROR_CODE_INVALID_URI = 4
ERROR_CODE_CONFLICT_STATE = 5
ERROR_CODE_MISTAKEN_TERM = 6
ERROR_CODE_NOT_REGISTRATION = 7
ERROR_CODE_MOVEMENT_IMPOSSIBLE = 8
ERROR_CODE_PROHIBITION = 9
ERROR_CODE_QUOTA_LIMIT_OVER = 13
#ERROR_CODE_VIRUS_INFECTION = 14
ERROR_CODE_TAG_LIMIT_OVER = 15
ERROR_CODE_PROHIBITION_FONT_LIMIT_OVER = 16
ERROR_CODE_FONT_NUMBER_LIMIT_OVER = 17
ERROR_CODE_CHANGE_IS_IMPOSSIBLE = 18
ERROR_CODE_EXISTENCE_IS_IMPOSSIBLE = 19
ERROR_CODE_RESOURCE_EXIST = 21
ERROR_CODE_CREATE_FAILED = 22 # FAILD?
ERROR_CODE_RESOURCE_SIZE_OVER = 23
#ERROR_CODE_NOT_MATCH_CONTENT_LENGTH = 24
ERROR_CODE_NOT_MATCH_HASH = 25
ERROR_CODE_UNINPUTTED = 27
ERROR_CODE_LIMIT_OVER = 28
ERROR_CODE_NON_AUTHORITY = 29
ERROR_CODE_STOP_DESTINATION_USER = 30
ERROR_CODE_STOP_ORIGIN_USER = 31 # ORIGN?
ERROR_CODE_STOP_MEMBERS = 32
ERROR_CODE_STOP_ORIGIN_SERVICE = 33
ERROR_CODE_PROHIBITION_FILE = 35
ERROR_CODE_UNATTESTED = 36
ERROR_CODE_PROHIBITION_ON_A_SHARE = 37
ERROR_CODE_SERVER_ERROR1 = 10000
ERROR_CODE_SERVER_ERROR2 = 20000
ERROR_CODE_UNEXPECTED_ERROR = 40000

def _debug(message):
    """debug out message."""
    f = open("/dev/console", "w")
    f.write(message)
    f.close()

class AzukeruSession:
    def __init__(self):
        self.login_id = None
        self.password = None
        self.session_id = None
        self.ep = DEFAULT_EP[EP_IDX]
        self.company_id = DEFAULT_EP[COMPANY_IDX]
        self.service_id = DEFAULT_EP[SERVICE_IDX]
        self.base_header = {header_company_id: self.company_id,
                            header_service_id: self.service_id}

        # http responseの"connection"ヘッダーの値が
        # 東日本は"close"であり西日本は"keep-alive"であり、
        # "keep-alive"の場合は、HTTPResponse.read()を
        # HTTPConnection.close()よりも先に実行しないと、
        # HTTPConnection.close()内部にて
        # HTTPResponse.close()が実行されてしまうことで
        # HTTPResponse.read()の結果が空になってしまう。
        # その対処として、HTTPConnection実体を
        # メンバ変数に保持し、次回request時に
        # 前回のself.connをclose()する。
        self.conn = None

    _get_proxy = None
    def _set_get_proxy(self, get_proxy):
        self._get_proxy = get_proxy
    def _get_get_proxy(self):
        if self._get_proxy is None:
            self._get_proxy = lambda url: None
        return self._get_proxy
    get_proxy = property(_get_get_proxy, _set_get_proxy)

    #### Internal Methods ####
    def __connect(self, uri):
        if self.conn:
            try:
                self.conn.close()
            except:
                pass

        try:
            proxy = self.get_proxy('https://%s%s' % (self.ep, uri))
            if proxy:
                proxy = proxy.split(':')
                host = proxy[0]
                port = int(proxy[1]) if len(proxy) > 1 else None
                self.conn = httplib.HTTPSConnection(host, port=port)
                self.conn.set_tunnel(self.ep, 443)
        except:
            pass
        if not proxy:
            self.conn = httplib.HTTPSConnection(self.ep, 443)
    
    def __request(self, method, uri, body, header):
        retry_remain = 2
        while True: 
            try:
                self.__connect(uri)
                self.conn.request(method, uri, body, header)
                response = self.conn.getresponse()
            except socket.error, err:
                _debug(str(err) + "\n")
                if retry_remain > 0:
                    _debug("retry_remain = " + str(retry_remain) + "\n")
                    retry_remain -= 1
                    continue
                raise err
            if response.status != STATUS_SERVICE_UNAVAILABLE:
                break
            _debug(str(response.status) + " " +  str(response.reason) + "\n")
            if retry_remain > 0:
                _debug("retry_remain = " + str(retry_remain) + "\n")
                retry_remain -= 1
                continue
            break

        return response
    
    def request_login(self, login_id, password):
        body = urllib.urlencode({ body_login_id : login_id,
                                  body_password : password })
        header = self.base_header.copy()
        header.update({header_content_type : "application/x-www-form-urlencoded"})
        uri = "/service/fbs/login"
        response = self.__request("POST", uri, body, header)
        return response
    
    def request_logincheck(self):
        body = urllib.urlencode({})
        header = self.base_header.copy()
        header.update({header_content_type: "application/x-www-form-urlencoded",
                       header_session_id: self.session_id})
        uri = "/service/fbs/login"
        response = self.__request("GET", uri, body, header)
        return response
    
    def request_logout(self):
        body = urllib.urlencode({})
        header = self.base_header.copy()
        header.update({header_content_type: "application/x-www-form-urlencoded",
                       header_session_id: self.session_id})
        uri = "/service/fbs/login"
        response = self.__request("DELETE", uri, body, header)
        return response
    
    def request_getdir(self, file_id = None, query = None):
        body = urllib.urlencode({})
        header = self.base_header.copy()
        header.update({header_session_id: self.session_id})
        if query == None:
            query = "?limit=0"
        uri = "/service/fbs/folder/list" + query
        if file_id != None:
            uri = "/service/fbs/folder/" + file_id + "/list" + query
        response = self.__request("GET", uri, body, header)
        return response
    
    def request_mkdir(self, file_id, folder_name):
        body = urllib.urlencode({ body_folder_name : folder_name })
        header = self.base_header.copy()
        header.update({header_content_type: "application/x-www-form-urlencoded",
                       header_session_id: self.session_id})
        uri = "/service/fbs/folder/" + file_id
        response = self.__request("POST", uri, body, header)
        return response
    
    def request_delete(self, file_id):
        body = urllib.urlencode({ body_file_id : file_id })
        header = self.base_header.copy()
        header.update({header_content_type: "application/x-www-form-urlencoded",
                       header_session_id: self.session_id})
        uri = "/service/fbs/file/delete"
        response = self.__request("POST", uri, body, header)
        return response
    
    def request_upload(self, file_id, file_name, file_data):
        boundary = mimetools.choose_boundary()
        body_prefix_list = [ "--" + boundary,
                              header_content_disposition + ': form-data; name="upload_file"; filename="' + file_name + '"',
                             '',
                             '' ]
        body_prefix = '\r\n'.join(body_prefix_list)
        body_suffix_list = [ '', "--" + boundary + "--" ]
        body_suffix = '\r\n'.join(body_suffix_list)
        header = self.base_header.copy()
        header.update({header_content_type: "multipart/form-data; boundary=" + boundary,
                       header_session_id: self.session_id})
        uri = "/service/fbs/folder/" + file_id + "/upload"
    
        if not hasattr(file_data, 'read'):
            body = body_prefix + \
                   file_data + \
                   body_suffix
            response = self.__request("POST", uri, body, header)
        else:
            file_size = os.path.getsize(file_data.name)
            clen = len(body_prefix) + file_size + len(body_suffix)
            header['Content-Length'] = clen
            self.__connect(uri)
            self.conn.request("POST", uri, "", header)
            self.conn.send(body_prefix)
            buffer_size = 65536
            buffer = file_data.read(buffer_size)
            while(buffer):
                self.conn.send(buffer)
                buffer = file_data.read(buffer_size)
            self.conn.send(body_suffix)
            response = self.conn.getresponse()
    
        return response
    
    def request_download(self, file_id):
        body = urllib.urlencode({})
        header = self.base_header.copy()
        header.update({header_session_id: self.session_id})
        uri = "/service/fbs/file/" + file_id + "/download"
        response = self.__request("GET", uri, body, header)
        return response
    
    def request_getfileinfo(self, file_id):
        body = urllib.urlencode({})
        header = self.base_header.copy()
        header.update({header_session_id: self.session_id})
        uri = "/service/fbs/file/" + file_id
        response = self.__request("GET", uri, body, header)
        return response

    #### External Methods ####

    def set_auth(self, login_id, password):
        self.login_id = login_id
        self.password = password

    def set_endpoint(self, ep="east"):
        if not ep:
           ep = "east"
        self.ep, self.company_id, self.service_id = EP_TABLE[ep]
        self.base_header = {header_company_id: self.company_id,
                            header_service_id: self.service_id}

    def login(self, login_id = None, password = None):
        if login_id == None:
            login_id = self.login_id
            password = self.password
        response = self.request_login(login_id, password)
        if response.status == STATUS_OK:
            self.session_id = response.getheader(header_session_id)
        return response

    def logincheck(self):
        return self.request_logincheck()

    def logout(self):
        response = self.request_logout()
        if response.status == STATUS_OK:
            self.session_id = None
        return response

    def getdir(self, file_id = None, query = None):
        return self.request_getdir(file_id, query)

    def mkdir(self, file_id, folder_name):
        return self.request_mkdir(file_id, folder_name)
     
    def delete(self, file_id):
        return self.request_delete(file_id)

    def upload(self, file_id, file_name, file_data):
        return self.request_upload(file_id, file_name, file_data)

    def download(self, file_id):
        return self.request_download(file_id)

    def getfileinfo(self, file_id):
        return self.request_getfileinfo(file_id)


class ErrorResponse(Exception):

    def __init__(self, status, reason, error_code = None, error_string = None):
        self.status = status
        self.reason = reason
        self.error_code = error_code
        self.error_string = error_string

class LoginError(Exception):
    """Login Error."""

    pass

class AzukeruClient:
    sess = None

    def __init__(self, session):
        self.sess = session

    def __get_error_response(self, response):
        status = response.status
        reason = response.reason
        error_code = None
        try:
            error_code = response.getheader(header_error_code)
            error_code = int(error_code)
        except:
            pass
        error_string = None
        try:
            error_string = response.getheader(header_error_string)
        except:
            pass
        return ErrorResponse(status, reason, error_code, error_string)

    def login(self, login_id = None, password = None):
        response = self.sess.login(login_id, password)
        if response.status != STATUS_OK:
            raise self.__get_error_response(response)
        response_data = response.read()
        element = ElementTree.fromstring(response_data)
        registration = element.findtext("registration")
        if registration != '1':
            raise LoginError

    def logincheck(self):
        response = self.sess.logincheck()
        if response.status != STATUS_OK:
            raise self.__get_error_response(response)

    def logout(self):
        response = self.sess.logout()
        if response.status != STATUS_OK:
            raise self.__get_error_response(response)
        
    def getdir(self, file_id = None, query = None):
        response = self.sess.getdir(file_id, query)
        if response.status != STATUS_OK:
            raise self.__get_error_response(response)
        return response

    def mkdir(self, file_id, folder_name):
        response = self.sess.mkdir(file_id, folder_name)
        if response.status != STATUS_OK:
            raise self.__get_error_response(response)
     
    def delete(self, file_id):
        response = self.sess.delete(file_id)
        if response.status != STATUS_OK:
            raise self.__get_error_response(response)

    def upload(self, file_id, file_name, file_data):
        response = self.sess.upload(file_id, file_name, file_data)
        if response.status != STATUS_OK:
            raise self.__get_error_response(response)

    def download(self, file_id):
        response = self.sess.download(file_id)
        if response.status != STATUS_OK:
            raise self.__get_error_response(response)
        return response

    def getfileinfo(self, file_id):
        response = self.sess.getfileinfo(file_id)
        if response.status != STATUS_OK:
            raise self.__get_error_response(response)
        return response

