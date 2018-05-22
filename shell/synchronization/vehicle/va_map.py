import os
import sys
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
url_count = base_url + "/api/getRowNumberById/"+str(user_id)+"/fitnotes"

row_count = urllib2.urlopen(url_count)
total_count = row_count.read()
print total_count

log_folder_path = "../../../var/log/syncVehicle/"
if not os.path.exists(log_folder_path):
	os.makedirs(log_folder_path)

timeobj = datetime.datetime.now()
current_date = timeobj.strftime("%d_%m_%Y")
current_time = timeobj.strftime("%H_%M_%S")
log_file_name = log_folder_path+"vamap_"+str(current_date)+".log"
open(log_file_name, "w").write(str(current_time)+"\n")
os.chmod(log_file_name, 0777)

while int(offset) < int(total_count):
	
	url_map = "/api/getFitNotes/"+str(user_id)+"/"+str(offset)+"/"+str(limit)
	response = urllib2.urlopen(base_url + url_map)
	data = json.load(response)
	
	offset += limit
	log_content = str(total_count) + " - " + str(offset)
	print log_content
	open(log_file_name, "a").write(log_content+"\n")

	for row in data:	
		ymms = row["sub_model_id_ymms"].split('/')
		year = ymms[0]
		make = ymms[1]
		model = ymms[2]
		submodel = ymms[3]

		fitnotes = row["fit_notes"].rstrip()
		product_code = row["product_code"]

		map_path = tree_path+year+"/"+make+"/"+model+"/"+submodel+".map"
		notes_path = tree_path+year+"/"+make+"/"+model+"/"+submodel				

		if is_new(map_path, product_code):			
			try:
				open(map_path , "a").write(product_code +"\n")
			except:
				print("unable to write "+product_code +" to " + map_path)

		if is_new(notes_path, fitnotes):						
			try:
				open(notes_path , "a").write(fitnotes + "\n")
			except:
				print("unable to write "+fitnotes +" to " + notes_path)	

open(log_file_name, "a").write("done")