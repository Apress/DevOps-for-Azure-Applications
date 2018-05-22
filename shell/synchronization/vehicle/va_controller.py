import os
import urllib2
import json
import datetime

client_info_path = '../../../app/etc/cfg/client_info.conf'
info_array = open(client_info_path, "r").read().split(';')

user_id = info_array[0].split('=')[1]
base_url = info_array[1].split('=')[1]
url_count = base_url + "/api/getVehicleSyncData/"+str(user_id)

row_count = urllib2.urlopen(url_count)

if row_count > 0:
    print "updating vehicle fitment and fitnotes\n"
    import va_selector
    import va_map
    import va_fitment
else:
    print "no updates needed\n"
