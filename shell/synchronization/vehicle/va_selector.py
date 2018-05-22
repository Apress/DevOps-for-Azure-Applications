import os
import urllib2
import json
import datetime

tree_path = "../../../../va_selector"
map_path = "../../../../vamap"
client_info_path = '../../../app/etc/cfg/client_info.conf'
info_array = open(client_info_path, "r").read().split(';')

if not os.path.exists(tree_path):
	os.makedirs(tree_path)
if not os.path.exists(map_path):
	os.makedirs(map_path)	

def sort_file_content(file_name):
	with open(file_name, "r") as f:
		temp_array = {}
		for line in f.readlines():
			temp_array[line.rstrip().split("=")[1]] = line.rstrip()
		open(file_name , "w").writelines("")			
		for k, v in sorted(temp_array.items()):
			open(file_name , "a").writelines(v+"\n")

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
url_count = base_url + "/api/getRowNumber/aces_lookup"

row_count = urllib2.urlopen(url_count)
total_count = row_count.read()
print total_count

log_folder_path = "../../../var/log/syncVehicle/"
if not os.path.exists(log_folder_path):
	os.makedirs(log_folder_path)
timeobj = datetime.datetime.now()
current_date = timeobj.strftime("%d_%m_%Y")
current_time = timeobj.strftime("%H_%M_%S")
log_file_name = log_folder_path+"vaselector_"+str(current_date)+".log"
open(log_file_name, "w").write(str(current_time)+"\n")
os.chmod(log_file_name, 0777)

while int(offset) < int(total_count):

	url_tree = "/api/getTree/"+str(offset)+"/"+str(limit)
	response = urllib2.urlopen(base_url + url_tree)
	data = json.load(response)  

	offset += limit
	log_content = str(total_count) + " - " + str(offset)
	print log_content
	open(log_file_name, "a").write(log_content+"\n")
	
	for row in data:	
		year = str(row["year_id"])
		make_id			= str(row["make_id"])
		make_text 		= str(row["make"])
		model_id		= str(row["model_id"])
		model_text 		= str(row["model"])
		submodel_id 	= str(row["submodel_id"])
		submodel_code 	= str(row["submodel_id"])+"_"+str(row["aspiration_id"])+"_"+str(row["fuel_type_id"])+"_"+str(row["engine_base_id"])
		submodel_made 	= str(row["sub_model"]).rstrip() + " - " + str(row["sub_model_make"])
		
		if is_new(tree_path+"/.meta", year+"="+year):
			open(tree_path+"/.meta" , "a").write(year+"="+year+"\n")
			sort_file_content(tree_path+"/.meta")
			os.chmod(tree_path+"/.meta", 0777)
			open(map_path+"/.meta" , "a").write(year+"="+year+"\n")
			sort_file_content(map_path+"/.meta")
			os.chmod(map_path+"/.meta", 0777)
		
		tree_level = tree_path + "/" + year
		map_level = map_path + "/" + year

		if not os.path.exists(tree_level):
			os.makedirs(tree_level)
			os.makedirs(map_level)

		if is_new(tree_level+"/.meta", make_id+"="+make_text):
			open(tree_level+"/.meta" , "a").write(make_id+"="+make_text+"\n")
			sort_file_content(tree_level+"/.meta")		
			os.chmod(tree_level+"/.meta", 0777)
			open(map_level+"/.meta" , "a").write(make_id+"="+make_text+"\n")
			sort_file_content(map_level+"/.meta")		
			os.chmod(map_level+"/.meta", 0777)

		tree_level = tree_level + "/" + make_id
		map_level = map_level + "/" + make_id

		if not os.path.exists(tree_level):
			os.makedirs(tree_level)
			os.makedirs(map_level)

		if is_new(tree_level+"/.meta", model_id+"="+model_text):
			open(tree_level+"/.meta" , "a").write(model_id+"="+model_text+"\n")
			sort_file_content(tree_level+"/.meta")
			os.chmod(tree_level+"/.meta", 0777)
			open(map_level+"/.meta" , "a").write(model_id+"="+model_text+"\n")
			sort_file_content(map_level+"/.meta")
			os.chmod(map_level+"/.meta", 0777)
		
		tree_level = tree_level + "/" + model_id
		map_level = map_level + "/" + model_id

		if not os.path.exists(tree_level):
			os.makedirs(tree_level)
			os.makedirs(map_level)
		
		if is_new(tree_level+"/.meta", submodel_code+"="+submodel_made):
			open(tree_level+"/.meta" , "a").write(submodel_code+"="+submodel_made+"\n")
			sort_file_content(tree_level+"/.meta")
			os.chmod(tree_level+"/.meta", 0777)	
			open(map_level+"/.meta" , "a").write(submodel_code+"="+submodel_made+"\n")
			sort_file_content(map_level+"/.meta")
			os.chmod(map_level+"/.meta", 0777)	

open(log_file_name, "a").write("done")