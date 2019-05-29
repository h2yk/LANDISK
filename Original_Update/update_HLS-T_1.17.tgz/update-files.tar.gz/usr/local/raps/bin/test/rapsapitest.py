#!/usr/bin/env python
# -*- coding:utf-8 -*-
import sys
import urllib
import os
import time
import shutil
import re

import raps

import traceback

def __find_first_element_by_tag(element,tag):
    if element.tag == tag:
        return element
    for subelement in list(element):
        found = __find_first_element_by_tag(subelement,tag)
        if found != None:
            return found
    return None

def __get_attrib(element,tag,attrib):
    value = None
    element = __find_first_element_by_tag(element,tag)
    if element != None:
        value = element.get(attrib,default=None)
    if value == None:
        value = ''
    return value

def __upload_tree(client,path,localpath):
    filelist = os.listdir(localpath)
    for file in filelist:
        subpath      =      path + '/' + file
        sublocalpath = localpath + '/' + file
        if os.path.isdir(sublocalpath):
            client.mkdir(subpath)
            __upload_tree(client,subpath,sublocalpath)
        else:
            client.upload(subpath,
                          open(sublocalpath,'r'),
                          os.path.getsize(sublocalpath))

def __download_tree(client,path,localpath):
    element = client.getidr(path)
    element = __find_first_element_by_tag(element,'filelist')
    if element != None:
        list = element.findall('file')
        for element in list:
            subname = __find_first_element_by_tag(element,'name').text
            subpath      =      path + '/' + subname
            sublocalpath = localpath + '/' + subname
            if element.get('isdir',default=None) == 'true':
                if not os.path.isdir(sublocalpath):
                    os.mkdir(sublocalpath)
                __download_tree(client,subpath,sublocalpath)
            else:
                client.download(subpath,open(sublocalpath,'w'))

# main    
if __name__ != '__main__':
    exit(1)

xmlencode    = sys.argv[1]
xmldeclare   = sys.argv[2]
protocol     = sys.argv[3]
host         = sys.argv[4]
port         = int(sys.argv[5])
user         = sys.argv[6]
password     = sys.argv[7]
session      = ''
if user == '':
    password = ''
    session  = sys.argv[7]
method       = sys.argv[8]

if xmldeclare == 'with_xmldeclare':
    xmldeclare = True
else:
    xmldeclare = False
if protocol == 'https':
    ssl = True
else:
    ssl = False

sess = raps.RapsSession()
sess.set_xmlencode(xmlencode,xmldeclare)
sess.set_host(host,port,ssl)
sess.set_auth(user,password,"rapsapitest")
sess.session = session
client = raps.RapsClient(sess)

if ( method == 'getinfo' ):
    element = client.getinfo()
elif ( method == 'auth' ):
    client.auth()
elif ( method == 'disconnect' ):
    client.disconnect()
else:
    if session == '':
        client.auth()
    try:
        if ( method == 'getsharelist' ):
            element = client.getsharelist()
        elif ( method == 'getdir' ):
            path      = sys.argv[9]
            index     = sys.argv[10]
            maxnum    = sys.argv[11]
            regex     = sys.argv[12]
            sort      = sys.argv[13]
            reverse   = sys.argv[14]
            item      = sys.argv[15]
            precision = sys.argv[16]
            element = client.getdir(path,index,maxnum,regex,sort,reverse,item,precision)
        elif ( method == 'find' ):
            path      = sys.argv[9]
            index     = sys.argv[10]
            maxnum    = sys.argv[11]
            regex     = sys.argv[12]
            sort      = sys.argv[13]
            reverse   = sys.argv[14]
            item      = sys.argv[15]
            precision = sys.argv[16]
            element = client.find(path,index,maxnum,regex,sort,reverse,item,precision)
        elif ( method == 'mkdir' ):
            path      = sys.argv[9]
            element = client.mkdir(path)
        elif ( method == 'getmminfo' ):
            path      = sys.argv[9]
            localpath = sys.argv[10]
            file = open(localpath, 'w')
            element = client.getmminfo(path,file)
            file.close()
        elif ( method == 'getresizedimage' ):
            path      = sys.argv[9]
            width     = sys.argv[10]
            height    = sys.argv[11]
            localpath = sys.argv[12]
            file = open(localpath, 'w')
            element = client.getresizedimage(path,width,height,file)
            file.close()
        elif ( method == 'copy' ):
            path      = sys.argv[9]
            path_dest = sys.argv[10]
            element = client.copy(path,path_dest)
        elif ( method == 'move' ):
            path      = sys.argv[9]
            path_dest = sys.argv[10]
            element = client.move(path,path_dest)
        elif ( method == 'delete' ):
            path      = sys.argv[9]
            element = client.delete(path)
        elif ( method == 'download' ):
            path      = sys.argv[9]
            localpath = sys.argv[10]
            offset    = sys.argv[11]
            maxsize   = sys.argv[12]
            file = open(localpath, 'w')
            element = client.download(path,file,offset,maxsize)
            file.close()
        elif ( method == 'upload' ):
            path      = sys.argv[9]
            localpath = sys.argv[10]
            addlen    = sys.argv[11]
            offset    = sys.argv[12]
            mtime     = sys.argv[13]
            overwrite = sys.argv[14]
            file = open(localpath, 'r')
            if ( addlen == 'auto' ):
                addlen = os.path.getsize(localpath)
            element = client.upload(path,file,addlen,offset,mtime,overwrite)
            file.close()
        elif ( method == 'geturl' ):
            path      = sys.argv[9]
            protocol  = sys.argv[10]
            expire    = sys.argv[11]
            element = client.geturl(path,protocol,expire)
        elif ( method == 'disableurl' ):
            url       = sys.argv[9]
            element = client.disableurl(url)
        if session == '':
            client.disconnect()
    except Exception, err:
        traceback.print_exc()
        if session == '':
            client.disconnect()
        raise err
