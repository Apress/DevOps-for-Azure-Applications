<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));

require_once(BASE_PATH."/../lib/mageModel.php");
require_once(BASE_PATH."/../lib/pmpModel.php");
$start = microtime(true);
$date = new DateTime();

$logFolder = BASE_PATH.'/../../../var/log/syncProduct/';
$logPath = $logFolder.$date->format("Y_m_d").".log";
$procfile = BASE_PATH.'/../pcheck/productprocnew.txt';
$procfilecomp = BASE_PATH.'/../pcheck/productproccomp.txt';

if(!file_exists($logFolder)){mkdir($logFolder);}

$client_info_path = BASE_PATH.'/../../../app/etc/cfg/client_info.conf';
$client_info = file_get_contents($client_info_path);
$client_info_array = explode(";", $client_info);

$user_id = explode("=", $client_info_array [0])[1];
$pmpURL = explode("=", $client_info_array [1])[1];
// $pmpURL = "http://gcomsoez.staging.gcommerceinc.com/";
// $user_id = 8525;

$pmpObj = new PmpObject($logPath,$pmpURL,$user_id);
$mageObj = new MageObject($logPath,$pmpObj,$date);

//flush cache
$mageObj->flushCache();

process_log_product($logPath,"Start Sync Process...\n");
$changedItems = $pmpObj->_getChangedItems("sku_product");

process_log_product($logPath,"Changed Items = ".$changedItems['count']."\n");

if($changedItems['count']){
    $brands_info = $pmpObj->_getBrandRecord();
    $brands_info = (array)json_decode($brands_info);

    $processIndex = 0;
    foreach($changedItems['items'] as $itemType => $items){

        if($itemType == 'brand'){

            if(count($items)>0){
                $errorMessage = array();
                foreach($items as $item){
                    $record = (array)$brands_info[$item];$processIndex++;
                    process_log_product($logPath,"====================== Brand Processing Index ".$processIndex."===================\n");
                   
                   if(!$record){
                        $logContent = "Error occurred at retrieving PMP2 API response\n";
                        process_log_product($logPath,$logContent);                                                
                        $errorMessage[$item] = array('status'=>'error','msg'=>$logContent);
                        continue;
                    }
                    $result = $mageObj->_saveBrandToMage($record);

                    if(array_key_exists('brand_code',$result)){
                        process_log_product($logPath,"Save mage id back to pmp ".$result['brand_code']."=>".$result['combine_id']."\n");
                        $result = $pmpObj->saveMageIdBrand($result);
                    }
                    $errorMessage[$item] = array('status'=>$result['status'],'msg'=>$result['errorMessage']);
                }
                $pmpObj->_updateStatus($errorMessage,'brand');
            }
        }
        elseif($itemType == 'product'){

            if(count($items)>0){
                $part_items_array = array_chunk($items,500);

                foreach($part_items_array as $part_items){
                    $records= $pmpObj->_getProductRecord($part_items);
                    $records = (array)json_decode($records,true);                
                    $errorMessage = array();

                    foreach($part_items as $item){
                        $record = (array)$records[$item];$processIndex++;
                        process_log_product($logPath,"====================== Product Processing Index ".$processIndex."===================\n");
                        
                        if($record['status'] == 'error' || !$record){
                            $logContent = "Error occurred at retrieving PMP2 API response\n";
                            process_log_product($logPath,$logContent);
                            $errorMessage[$item] = array('status'=>'error','msg'=>$logContent);
                            continue;
                        }

                        $result = $mageObj->_saveItemToMage($itemType, $record, $brands_info);
                        $errorMessage[$item] = array('status'=>$result['status'],'msg'=>$result['errorMessage']);
                    }
                    $pmpObj->_updateStatus($errorMessage,'product');
                }
            }
        }
        elseif($itemType == 'sku'){
            if(count($items)>0){
                $part_items_array = array_chunk($items,500);

                foreach($part_items_array as $part_items){
                    $records= $pmpObj->_getSkuRecord($part_items);
                    $records = (array)json_decode($records,true);
                    $errorMessage = array();

                    foreach($part_items as $item){
                        $record = (array)$records[$item];$processIndex++;
                        process_log_product($logPath,"====================== SKU Processing Index ".$processIndex."===================\n");
                        
                        if(!$record){
                            $logContent = "Error occurred at retrieving PMP2 API response\n";
                            process_log_product($logPath,$logContent);
                            $errorMessage[$item] = array('status'=>'error','msg'=>$logContent);
                            continue;
                        }
                        $result = $mageObj->_saveItemToMage($itemType, $record, $brands_info);
                        if ($result['call_again'] == 1){
                            $result = $mageObj->_saveItemToMage($itemType, $record, $brands_info);
                        }
                        $errorMessage[$item] = array('status'=>$result['status'],'msg'=>$logContent);
                    }
                    $pmpObj->_updateStatus($errorMessage,'sku');
                }
            }
        }
        else{
            process_log_product($logPath,"Invalid Type => ".$itemType."\n");
        }
    }
}

process_log_product($logPath,"End Sync Process...\n");
$end = microtime(true);
$time = (($end - $start)/60);

//Tell Other scripts that this script is successfully complete so the next one can run
rename($procfile, $procfilecomp);

process_log_product($logPath,"Total time usage for DB2Mage = ".$time." mins\n");

function process_log_product($logPath,$logContent){
    echo $logContent;
    file_put_contents($logPath,$logContent, FILE_APPEND);    
}