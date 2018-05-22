<?php

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));

require_once(BASE_PATH."/../lib/mageModel.php");
require_once(BASE_PATH."/../lib/pmpModel.php");

$date = new DateTime();
$logFolder = BASE_PATH.'/../../../var/log/syncCategory/';
$logPath = $logFolder.$date->format("Y_m_d").".log";
$procfile = BASE_PATH.'/../pcheck/categoryprocnew.txt';
$procfilecomp = BASE_PATH.'/../pcheck/categoryproccomp.txt';
if(!file_exists($logFolder)){mkdir($logFolder);}

$client_info_path = BASE_PATH.'/../../../app/etc/cfg/client_info.conf';
$client_info = file_get_contents($client_info_path);
$client_info_array = explode(";", $client_info);

$user_id = explode("=", $client_info_array [0])[1];
$pmpURL = explode("=", $client_info_array [1])[1];

$pmpObj = new PmpObject($logPath,$pmpURL,$user_id);
$mageObj = new MageObject($logPath,$pmpObj,$date, $user_id);

process_log_category($logPath,"*** Start process ".$date->format("Y_m_d_h_i_s")."***");

//initialization the category tree
//Level 1
$type = 'root';
$parentMageId = 2;//Magento root category
$level_1_categories = $pmpObj->getCategory($type);
$level_1_categories = (array)json_decode($level_1_categories);

$logContent = "Start initializing ".count($level_1_categories)." categories...\n";
echo $logContent;file_put_contents($logPath,$logContent);

$mageObj->saveCategories($parentMageId,$level_1_categories,1);

//Level 2
foreach($level_1_categories as $item){
    $parentId = $item->category_id;
    $parentMage_string = $pmpObj->findMageId($parentId);
    $parentMage_array = (array)json_decode($parentMage_string);
    $parentMageId = $parentMage_array[0]->mage_id;

    echo "---level 2 parent id = ".$parentId."\n";
    $level_2_categories = $pmpObj->getCategory($parentId);
    $level_2_categories = (array)json_decode($level_2_categories);

    $mageObj->saveCategories($parentMageId,$level_2_categories,2);
    //Level 3
    foreach($level_2_categories as $item){
        $parentId = $item->category_id;
        $parentMage_string = $pmpObj->findMageId($parentId);
        $parentMage_array = (array)json_decode($parentMage_string);
        $parentMageId = $parentMage_array[0]->mage_id;

        echo "---------- level 3 parent id = ".$parentId."\n";
        $level_3_categories = $pmpObj->getCategory($parentId);
        $level_3_categories = (array)json_decode($level_3_categories);

        $mageObj->saveCategories($parentMageId,$level_3_categories,3);
    }
}

//after initialization add new only
$new_categories = $pmpObj->getCategory('add_new');
$new_categories = (array)json_decode($new_categories);

$logContent = "Start adding new ".count($new_categories)." categories...\n";
echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);

foreach($new_categories as $new_category){

    if(empty($new_category->category_parent_id)){
        $level = 1;
    }else{
        $new_parentMageId = $pmpObj->findMageId($new_category->category_parent_id);
        $new_parentMageId = (array)json_decode($new_parentMageId);

        foreach($new_parentMageId as $obj){
            if(empty($obj->category_parent_id)){
                $level = 2;
            }else{
                $level = 3;
            }
        }
    }
    $mageObj->addNewCategories($obj->mage_id,$new_category,$level);
}

//after initialization update only
$update_categories = $pmpObj->getCategory('update');
$update_categories = (array)json_decode($update_categories);

$logContent = "Start updating ".count($update_categories)." categories...\n";
echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);

foreach($update_categories as $update_category){

    if(empty($update_category->category_parent_id)){
        //means no parents
        $level = 1;
    }else{
        $update_parentMageId = $pmpObj->findMageId($update_category->category_parent_id);
        $update_parentMageId = (array)json_decode($update_parentMageId);

        foreach($update_parentMageId as $obj){
            if(empty($obj->category_parent_id)){
                //means parent has no parents
                $level = 2;
            }else{
                $level = 3;
            }
        }
    }

    $mageObj->updateCategories($update_category,$level);
}
//Tell Other scripts that this script is successfully complete so the next one can run
rename($procfile, $procfilecomp);

process_log_category($logPath,"*** end process ***");
function process_log_category($logPath,$logContent){
    echo $logContent,"\n";
    file_put_contents($logPath,$logContent."\n", FILE_APPEND);    
}
?>