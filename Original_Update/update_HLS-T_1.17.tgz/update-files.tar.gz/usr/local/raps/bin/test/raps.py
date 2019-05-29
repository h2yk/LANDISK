
import httplib, urllib
import mimetools
import os
import codecs
import StringIO
import time

from xml.etree import ElementTree

header_content_type = "Content-Type"
header_content_length = "Content-Length"

separator = codecs.BOM_BE + "\r\n"

STATUS_OK = 200

defaultencode = 'UTF-8'
defaultdeclare = True

debug = True

# separate method
def get_xml(xmlwithdata):
    xml = ''
    rawbuf = StringIO.StringIO(xmlwithdata)
    while True:
        line = rawbuf.readline()
        if not line:
            break
        if line.endswith(separator):
            xml += line.replace(separator,"")
            break
        xml += line
    return xml

def get_data(xmlwithdata):
    data = ''
    rawbuf = StringIO.StringIO(xmlwithdata)
    while True:
        line = rawbuf.readline()
        if not line:
            break
        if line.endswith(separator):
            break
    data = rawbuf.read()
    return data

def find_first_element_by_tag(element,tag):
    if element.tag == tag:
        return element
    for subelement in list(element):
        found = find_first_element_by_tag(subelement,tag)
        if found != None:
            return found
    return None

def get_attrib(element,tag,attrib):
    value = None
    element = find_first_element_by_tag(element,tag)
    if element != None:
        value = element.get(attrib,default=None)
    if value == None:
        value = ''
    return value

def get_text(xml,tag):
    value = None
    xml = get_xml(xml)
    element = ElementTree.fromstring(xml)
    element = find_first_element_by_tag(element,tag)
    if element != None:
        value = element.text
    if value == None:
        value = ''
    return value

def __request_xmldec(element_xml,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = u''
    __indent(element_xml)
    encoding = xmlencode
    encode = xmlencode
    bom = ''
    if xmlencode == 'UTF-16(LEBOM)':
        encoding = 'UTF-16'
        encode = 'UTF-16LE'
        bom = codecs.BOM_LE
    elif xmlencode == 'UTF-16(BEBOM)':
        encoding = 'UTF-16'
        encode = 'UTF-16BE'
        bom = codecs.BOM_BE
    elif xmlencode == 'UTF-16LE(BOM)':
        encoding = 'UTF-16LE'
        encode = 'UTF-16LE'
        bom = codecs.BOM_LE
    elif xmlencode == 'UTF-16BE(BOM)':
        encoding = 'UTF-16BE'
        encode = 'UTF-16BE'
        bom = codecs.BOM_BE
    if xmldeclare:
        xml += '<?xml version="1.0" encoding="' + encoding + \
            '" standalone="yes"?>\n'
    xml += ElementTree.tostring(element_xml,'utf-8').decode('utf-8')
    xml = bom + xml.encode(encode)
    return xml

def __indent(elem, level=0):
    i = "\n" + level*"  "
    if len(elem):
        if not elem.text or not elem.text.strip():
            elem.text = i + "  "
        if not elem.tail or not elem.tail.strip():
            elem.tail = i
        for elem in elem:
            __indent(elem, level+1)
        if not elem.tail or not elem.tail.strip():
            elem.tail = i 
    else:
        if level and (not elem.tail or not elem.tail.strip()):
            elem.tail = i
    if level == 0:
        elem.tail = "\n"

# request xml
def __request_getinfo(xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','getinfo')
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_auth(name,password,clientid,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','auth')
    element_user = ElementTree.Element('user')
    element_name = ElementTree.Element('name')
    element_name.text = name
    element_user.append(element_name)
    element_password = ElementTree.Element('password')
    element_password.text = password
    element_user.append(element_password)
    element_clientid = ElementTree.Element('clientid')
    element_clientid.text = clientid
    element_user.append(element_clientid)
    element_request.append(element_user)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_disconnect(session,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','disconnect')
    element_request.set('session',session)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_getsharelist(session,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','getsharelist')
    element_request.set('session',session)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_getdir(session,path,index=None,maxnum=None,regex=None,sort=None,
        reverse=None,item=None,precision=None,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','getdir')
    element_request.set('session',session)
    element_path = ElementTree.Element('path')
    element_path.text = path
    element_request.append(element_path)
    if (index or maxnum or regex or sort or reverse or item or precision):
        element_option = ElementTree.Element('option')
        if index:
            element_option.set('index',index)
        if maxnum:
            element_option.set('maxnum',maxnum)
        if regex:
            element_option.set('regex',regex)
        if sort:
            element_option.set('sort',sort)
        if reverse:
            element_option.set('reverse',reverse)
        if item or precision:
            element_statitemlist = ElementTree.Element('statitemlist')
            if precision:
                element_item = ElementTree.Element('item')
                element_item.set('precision',precision)
                element_item.text = 'mtime'
                element_statitemlist.append(element_item)
            if item:
                element_item = ElementTree.Element('item')
                element_item.text = item
                element_statitemlist.append(element_item)
            element_option.append(element_statitemlist)
        element_request.append(element_option)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_find(session,path,index=None,maxnum=None,regex=None,sort=None,
        reverse=None,item=None,precision=None,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','find')
    element_request.set('session',session)
    element_path = ElementTree.Element('path')
    element_path.text = path
    element_request.append(element_path)
    if (index or maxnum or regex or sort or reverse or item or precision):
        element_option = ElementTree.Element('option')
        if index:
            element_option.set('index',index)
        if maxnum:
            element_option.set('maxnum',maxnum)
        if regex:
            element_option.set('regex',regex)
        if sort:
            element_option.set('sort',sort)
        if reverse:
            element_option.set('reverse',reverse)
        if item or precision:
            element_statitemlist = ElementTree.Element('statitemlist')
            if precision:
                element_item = ElementTree.Element('item')
                element_item.set('precision',precision)
                element_item.text = 'mtime'
                element_statitemlist.append(element_item)
            if item:
                element_item = ElementTree.Element('item')
                element_item.text = item
                element_statitemlist.append(element_item)
            element_option.append(element_statitemlist)
        element_request.append(element_option)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_mkdir(session,path,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','mkdir')
    element_request.set('session',session)
    element_path = ElementTree.Element('path')
    element_path.text = path
    element_request.append(element_path)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_getmminfo(session,path,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','getmminfo')
    element_request.set('session',session)
    element_path = ElementTree.Element('path')
    element_path.text = path
    element_request.append(element_path)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_getresizedimage(session,path,width,height,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','getresizedimage')
    element_request.set('session',session)
    element_path = ElementTree.Element('path')
    element_path.text = path
    element_request.append(element_path)
    element_width = ElementTree.Element('width')
    element_width.text = width
    element_request.append(element_width)
    element_height = ElementTree.Element('height')
    element_height.text = height
    element_request.append(element_height)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_copy(session,path,path_dest,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','copy')
    element_request.set('session',session)
    element_path = ElementTree.Element('path')
    element_path.text = path
    element_request.append(element_path)
    element_path_dest = ElementTree.Element('path')
    element_path_dest.set('dest','true')
    element_path_dest.text = path_dest
    element_request.append(element_path_dest)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_move(session,path,path_dest,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','move')
    element_request.set('session',session)
    element_path = ElementTree.Element('path')
    element_path.text = path
    element_request.append(element_path)
    element_path_dest = ElementTree.Element('path')
    element_path_dest.set('dest','true')
    element_path_dest.text = path_dest
    element_request.append(element_path_dest)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_delete(session,path,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','delete')
    element_request.set('session',session)
    element_path = ElementTree.Element('path')
    element_path.text = path
    element_request.append(element_path)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_download(session,path,offset=None,maxsize=None,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','download')
    element_request.set('session',session)
    element_path = ElementTree.Element('path')
    element_path.text = path
    element_request.append(element_path)
    if offset or maxsize:
        element_option = ElementTree.Element('option')
        if offset:
            element_option.set('offset',str(offset))
        if maxsize:
            element_option.set('maxsize',str(maxsize))
        element_request.append(element_option)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_upload(session,path,addlen,offset=None,mtime=None,overwrite=None,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','upload')
    element_request.set('session',session)
    element_request.set('addlen',str(addlen))
    element_path = ElementTree.Element('path')
    element_path.text = path
    element_request.append(element_path)
    if offset or mtime or overwrite:
        element_option = ElementTree.Element('option')
        if offset:
            element_option.set('offset',str(offset))
        if mtime:
            element_option.set('mtime',mtime)
        if overwrite:
            element_option.set('overwrite',str(overwrite))
        element_request.append(element_option)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_geturl(session,path,protocol=None,expire=None,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','geturl')
    element_request.set('session',session)
    element_path = ElementTree.Element('path')
    element_path.text = path
    element_request.append(element_path)
    if protocol or expire:
        element_option = ElementTree.Element('option')
        if protocol:
            element_option.set('protocol',protocol)
        if expire:
            element_option.set('expire',expire)
        element_request.append(element_option)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

def __request_disableurl(session,url,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    element_request = ElementTree.Element('request')
    element_request.set('type','disableurl')
    element_request.set('session',session)
    element_url = ElementTree.Element('url')
    element_url.text = url
    element_request.append(element_url)
    return __request_xmldec(element_request,xmlencode,xmldeclare)

# connect
def __connect(host,port,ssl=True):
    PROXY_ENV_NAME="DROPBOX_PROXY"
    if PROXY_ENV_NAME in os.environ:
        proxy_host_port = os.environ[PROXY_ENV_NAME].split(":")
        proxy_host = proxy_host_port[0]
        proxy_port = None
        if len(proxy_host_port) > 1:
            proxy_port = int(proxy_host_port[1])
        if ssl:
            conn = httplib.HTTPSConnection(proxy_host, proxy_port)
        else:
            conn = httplib.HTTPConnection(proxy_host, proxy_port)
        conn.set_tunnel(host, port)
    else:
        if ssl:
            conn = httplib.HTTPSConnection(host, port)
        else:
            conn = httplib.HTTPConnection(host, port)
    return conn

# request
def __request(host,port,ssl,xml,data=None,datalen=None):
    contentlength = len(xml)
    if datalen:
        contentlength += ( len(separator) + datalen )
    method = "POST"
    uri = "/raps/api.cgi"
    header = { header_content_type : "text/html",
               header_content_length : contentlength }
    conn = __connect(host,port,ssl)
    conn.request(method,uri,xml,header)
    if debug:
        start_time = time.time()
        print xml
    if data:
        conn.send(separator)
        if hasattr(data,'read'):
            sendbuffersize = 65536
            senddatalen = 0
            while senddatalen < datalen:
                remainingdatalen = datalen - senddatalen
                if remainingdatalen < sendbuffersize:
                    sendbuffersize = remainingdatalen
                sendbuffer = data.read(sendbuffersize)
                conn.send(sendbuffer)
                senddatalen += sendbuffersize
        else:
            conn.send(data)
    response = conn.getresponse()
    #conn.close()
    if debug:
        elapsed_time = time.time() - start_time
        print("elapsed_time:" + str(elapsed_time))
        print("\n")
    return response

# request api
def request_getinfo(host,port,ssl,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_getinfo(xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_auth(host,port,ssl,user,password,clientid,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_auth(user,password,clientid,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_disconnect(host,port,ssl,session,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_disconnect(session,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_getsharelist(host,port,ssl,session,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_getsharelist(session,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_getdir(host,port,ssl,session,path,index=None,maxnum=None,regex=None,
        sort=None,reverse=None,item=None,precision=None,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_getdir(session,path,index,maxnum,regex,sort,reverse,item,
              precision,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_find(host,port,ssl,session,path,index=None,maxnum=None,regex=None,
        sort=None,reverse=None,item=None,precision=None,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_find(session,path,index,maxnum,regex,sort,reverse,item,
              precision,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_mkdir(host,port,ssl,session,path,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_mkdir(session,path,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_getmminfo(host,port,ssl,session,path,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_getmminfo(session,path,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_getresizedimage(host,port,ssl,session,path,width,height,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_getresizedimage(session,path,width,height,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_copy(host,port,ssl,session,path,path_dest,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_copy(session,path,path_dest,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_move(host,port,ssl,session,path,path_dest,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_move(session,path,path_dest,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_delete(host,port,ssl,session,path,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_delete(session,path,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_download(host,port,ssl,session,path,offset=None,maxsize=None,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_download(session,path,offset,maxsize,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_upload(host,port,ssl,session,path,data,addlen,offset=None,
        mtime=None,overwrite=None,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_upload(session,path,addlen,offset,mtime,overwrite,
              xmlencode,xmldeclare)
    return __request(host,port,ssl,xml,data,addlen)

def request_geturl(host,port,ssl,session,path,protocol=None,expire=None,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_geturl(session,path,protocol,expire,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

def request_disableurl(host,port,ssl,session,url,
        xmlencode=defaultencode,xmldeclare=defaultdeclare):
    xml = __request_disableurl(session,url,xmlencode,xmldeclare)
    return __request(host,port,ssl,xml)

class ErrorResponse(Exception):

    def __init__(self, result=None):
        self.result = result

class RapsSessionError(Exception):
    """RapsSession Error."""

    pass

class RapsSession:
    host = None
    port = None
    ssl = True
    user = None
    password = None
    clientid = None
    session = None
    xmlencode = defaultencode
    xmldeclare = defaultdeclare

    def set_host(self,host,port,ssl=True):
        self.host = host
        self.port = port
        self.ssl = ssl

    def set_auth(self,user,password,clientid):
        self.user = user
        self.password = password
        self.clientid = clientid

    def set_xmlencode(self,xmlencode,xmldeclare):
        self.xmlencode = xmlencode
        self.xmldeclare = xmldeclare

    def getinfo(self):
        return request_getinfo(self.host,self.port,self.ssl,
                   self.xmlencode,self.xmldeclare)

    def auth(self,user=None,password=None,clientid=None):
        if user == None:
            user = self.user
        if password == None:
            password = self.password
        if clientid == None:
            clientid = self.clientid
        response = request_auth(self.host,self.port,self.ssl,
                       user,password,clientid,
                       self.xmlencode,self.xmldeclare)
        if response.status != STATUS_OK:
            raise RapsSessionError
        xml = response.read()
        if debug:
            print xml
        element = ElementTree.fromstring(xml)
        type = get_attrib(element,'response','type')
        if type != 'auth':
            raise RapsSessionError
        session = get_attrib(element,'response','session')
        if not session:
            raise  RapsSessionError
        result = get_attrib(element,'response','result')
        if not result:
            raise RapsSessionError
        self.set_auth(user,password,clientid)
        self.session = session
        return int(result)

    def disconnect(self):
        response = request_disconnect(self.host,self.port,self.ssl,
                       self.session,
                       self.xmlencode,self.xmldeclare)
        if response.status != STATUS_OK:
            raise RapsSessionError
        xml = response.read()
        if debug:
            print xml
        element = ElementTree.fromstring(xml)
        type = get_attrib(element,'response','type')
        if type != 'disconnect':
            raise RapsSessionError
        session = get_attrib(element,'response','session')
        if not session:
            raise RapsSessionError
        result = get_attrib(element,'response','result')
        if not result:
            raise RapsSessionError
        return int(result)

    def getsharelist(self):
        return request_getsharelist(self.host,self.port,self.ssl,
                   self.session,
                   self.xmlencode,self.xmldeclare)

    def getdir(self,path,index=None,maxnum=None,regex=None,sort=None,
            reverse=None,item=None,precision=None):
        return request_getdir(self.host,self.port,self.ssl,
                   self.session,path,index,maxnum,regex,sort,reverse,item,
                   precision,self.xmlencode,self.xmldeclare)

    def find(self,path,index=None,maxnum=None,regex=None,sort=None,reverse=None,
            item=None,precision=None):
        return request_find(self.host,self.port,self.ssl,
                   self.session,path,index,maxnum,regex,sort,reverse,item,
                   precision,self.xmlencode,self.xmldeclare)

    def mkdir(self,path):
        return request_mkdir(self.host,self.port,self.ssl,
                   self.session,path,
                   self.xmlencode,self.xmldeclare)

    def getmminfo(self,path):
        return request_getmminfo(self.host,self.port,self.ssl,
                   self.session,path,
                   self.xmlencode,self.xmldeclare)

    def getresizedimage(self,path,width,height):
        return request_getresizedimage(self.host,self.port,self.ssl,
                   self.session,path,width,height,
                   self.xmlencode,self.xmldeclare)
     
    def copy(self,path,path_dest):
        return request_copy(self.host,self.port,self.ssl,
                   self.session,path,path_dest,
                   self.xmlencode,self.xmldeclare)

    def move(self,path,path_dest):
        return request_move(self.host,self.port,self.ssl,
                   self.session,path,path_dest,
                   self.xmlencode,self.xmldeclare)

    def delete(self,path):
        return request_delete(self.host,self.port,self.ssl,self.session,path,
                   self.xmlencode,self.xmldeclare)

    def download(self,path,offset=None,maxsize=None):
        return request_download(self.host,self.port,self.ssl,
                   self.session,path,offset,maxsize,
                   self.xmlencode,self.xmldeclare)

    def upload(self,path,data,addlen,offset=None,mtime=None,overwrite=None):
        return request_upload(self.host,self.port,self.ssl,
                   self.session,path,data,addlen,offset,mtime,overwrite,
                   self.xmlencode,self.xmldeclare)

    def geturl(self,path,protocol=None,expire=None):
        return request_geturl(self.host,self.port,self.ssl,
                   self.session,path,protocol,expire,
                   self.xmlencode,self.xmldeclare)

    def disableurl(self,url):
        return request_disableurl(self.host,self.port,self.ssl,
                   self.session,url,
                   self.xmlencode,self.xmldeclare)

class RapsClientError(Exception):
    """RapsClient Error."""

    pass

class RapsClient:
    sess = None

    def __init__(self, session):
        self.sess = session

    def __response_read(self,response,data=None):
        if debug:
            start_time = time.time()
        readbuffersize = 65536
        xmlwithdata = ''
        while True:
            readbuffer = response.read(readbuffersize)
            if not readbuffer:
                break
            xmlwithdata += readbuffer
            readdata = get_data(xmlwithdata)
            if len(readdata):
                break
        xml = get_xml(xmlwithdata)
        if data:
            data.write(readdata)
            while True:
                readbuffer = response.read(readbuffersize)
                if not readbuffer:
                    break
                data.write(readbuffer)
        if debug:
            elapsed_time = time.time() - start_time
            print xml
            print("elapsed_time:" + str(elapsed_time))
            print("\n")
        return xml

    def __check_response(self,element,type,session=None):
        response_type = get_attrib(element,'response','type')
        if not response_type:
            raise RapsClientError
        if response_type != type:
            raise RapsClientError
        if session:
            response_session = get_attrib(element,'response','session')
            if not response_session:
                raise RapsClientError
            if response_session != session:
                raise RapsClientError
        response_result = get_attrib(element,'response','result')
        if not response_result:
            raise RapsClientError
        result = int(response_result)
        if result != 0:
            raise ErrorResponse(result)

    def getinfo(self):
        response = self.sess.getinfo()
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'getinfo')
        return element

    def auth(self,user=None,password=None,clientid=None):
        result = self.sess.auth(user,password,clientid)
        if result != 0:
            raise ErrorResponse(result)

    def disconnect(self):
        result = self.sess.disconnect()
        if result != 0:
            raise ErrorResponse(result)

    def getsharelist(self):
        response = self.sess.getsharelist()
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'getsharelist',self.sess.session)
        return element

    def getdir(self,path,index=None,maxnum=None,regex=None,sort=None,
            reverse=None,item=None,precision=None):
        response = self.sess.getdir(path,index,maxnum,regex,sort,reverse,item,
                       precision)
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'getdir',self.sess.session)
        return element

    def find(self,path,index=None,maxnum=None,regex=None,sort=None,reverse=None,
            item=None,precision=None):
        response = self.sess.find(path,index,maxnum,regex,sort,reverse,item,precision)
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'find',self.sess.session)
        return element

    def mkdir(self,path):
        response = self.sess.mkdir(path)
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'mkdir',self.sess.session)
        return element

    def getmminfo(self,path,data=None):
        response = self.sess.getmminfo(path)
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response,data)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'getmminfo',self.sess.session)
        return element

    def getresizedimage(self,path,width,height,data=None):
        response = self.sess.getresizedimage(path,width,height)
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response,data)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'getresizedimage',self.sess.session)
        return element
     
    def copy(self,path,path_dest):
        response = self.sess.copy(path,path_dest)
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'copy',self.sess.session)
        return element

    def move(self,path,path_dest):
        response = self.sess.move(path,path_dest)
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'move',self.sess.session)
        return element

    def delete(self,path):
        response = self.sess.delete(path)
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'delete',self.sess.session)
        return element

    def download(self,path,data,offset=None,maxsize=None):
        response = self.sess.download(path,offset,maxsize)
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response,data)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'download',self.sess.session)
        return element

    def upload(self,path,data,addlen,offset=None,mtime=None,overwrite=None):
        response = self.sess.upload(path,data,addlen,offset,mtime,overwrite)
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'upload',self.sess.session)
        return element

    def geturl(self,path,protocol=None,expire=None):
        response = self.sess.geturl(path,protocol,expire)
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'geturl',self.sess.session)
        return element

    def disableurl(self,url):
        response = self.sess.disableurl(url)
        if response.status != STATUS_OK:
            raise RapsClientError
        xml = self.__response_read(response)
        element = ElementTree.fromstring(xml)
        self.__check_response(element,'disableurl',self.sess.session)
        return element

