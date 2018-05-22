import os
import urllib2
import json
import datetime

tree_path = "../../../../vamap/"
client_info_path = '../../../app/etc/cfg/client_info.conf'
info_array = open(client_info_path, "r").read().split(';')

if not os.path.exists(tree_path):
	os.makedirs(tree_path)

def is_new(file_name, new_line):
	if not os.path.exists(file_name):
		return True
	content = set()
	for line in open(file_name, "r"):
		content.add(line.rstrip())

	if new_line.rstrip() not in content:
		return True
	return False

offset = 0
limit = 1000
user_id = info_array[0].split('=')[1]
base_url = info_array[1].split('=')[1]
url_count = base_url + "/api/getRowNumberById/"+str(user_id)+"/fitment"

row_count = urllib2.urlopen(url_count)
total_count = row_count.read()
print total_count
log_folder_path = "../../../var/log/syncVehicle/"
if not os.path.exists(log_folder_path):
	os.makedirs(log_folder_path)

timeobj = datetime.datetime.now()
current_date = timeobj.strftime("%d_%m_%Y")
current_time = timeobj.strftime("%H_%M_%S")
log_file_name = log_folder_path+"vafitment_"+str(current_date)+".log"
open(log_file_name, "w").write(str(current_time)+"\n")
os.chmod(log_file_name, 0777)

while int(offset) < int(total_count):

	url_map = "/api/getFitment/"+str(user_id)+"/"+str(offset)+"/"+str(limit)

	response = urllib2.urlopen(base_url + url_map)
	data = json.load(response)	

	offset += limit
	log_content = str(total_count) + " - " + str(offset)
	print log_content
	open(log_file_name, "a").write(log_content+"\n")

	for row in data:
		fitment = row["fitment_notes"]+"\n"
		sku_code = row["sku_code"].replace(":","@3A")

		fitment_path = tree_path + "9999/"
		if not os.path.exists(fitment_path):
			os.makedirs(fitment_path)
		
		fitment_path = fitment_path + sku_code[0:3] + "/"
		if not os.path.exists(fitment_path):
			os.makedirs(fitment_path)

		if is_new(fitment_path + sku_code, fitment):
			open(fitment_path + sku_code , "a").write(fitment)
			os.chmod(fitment_path, 0777)

open(log_file_name, "a").write("done")

update_status_url = base_url + "api/updateVehicleSyncData/" + str(user_id)
print "Update pmp2 sync data table => " + update_status_url
response = urllib2.urlopen(update_status_url)