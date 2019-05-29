#!/usr/bin/env python
# -*- mode: landisk-python; coding: utf-8; -*-
# vim:ts=4 sw=4 sts=4 ai si et sta
"""The script for the raps main module."""

import os
import sys
import raps.rapsmain as rapsmain
reload(sys)
sys.setdefaultencoding('utf-8')

# mail call
if __name__ == "__main__":
    #header = "Content-type: text/html\r\n"
    header = "Content-type: application/octet-stream\r\n"
    header += "Status:200 OK\r\n"
    header += "\r\n"
    sys.stdout.write(header)
    sys.stdout.flush()

    os.stat_float_times(True)
    c_raps = rapsmain.rapsmain()
    c_raps.main()
    sys.stdout.flush()
