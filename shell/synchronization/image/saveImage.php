<?php
$isCli = php_sapi_name();
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
DEFINE('LOG',$isCli);

$start = microtime(true);

require_once("../lib/mageModel.php");
require_once("../lib/pmpModel.php");

$date = new DateTime();

$logName = $date->format("Y_m_d");
$logFolder = BASE_PATH.'/../../../var/log/sync_image/';
$logPath = $logFolder."save_image_".$logName.".log";

if(!file_exists($logFolder)){mkdir($logFolder);}

$processHandler = $logPath.'/saveImgProcess.log';

processCheck($processHandler);

$baseURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
$pmpURL = str_replace("http://www.","http://pmp2.",$baseURL);

$pmpObj = new PmpObject($logPath,$pmpURL);
$mageObj = new MageObject($logPath,$pmpObj,$date);

//flush cache
$mageObj->flushCache();

$logContent = "Start Sync Process...\n";
echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);

$changedItems = $pmpObj->_getImageChangedList();
$changedItems = (array)json_decode($changedItems);

$startProcessTime = time();

if(count($changedItems['sku'])>0){
    $processIndex = 0;
    $keys = array();
    foreach($changedItems['sku'] as $item){
        $keys[] = $item;
    }
    $record = $pmpObj->getImagePath("sku",$keys);
    $record = (array)json_decode($record);
    $result = $mageObj->_saveMageImages($record);

    $response = $pmpObj->updateImageStatus($result);
}
elseif(count($changedItems['product'])>0){
    $processIndex = 0;
    $keys = array();
    foreach($changedItems['product'] as $item){
        $keys[] = $item;
    }
    $record = $pmpObj->getImagePath("product",$keys);
    $record = (array)json_decode($record);
    $result = $mageObj->_saveMageImages($record);
    $pmpObj->updateImageStatus($result);
}

$logContent = "End Sync Process...\n";
echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);

$end = microtime(true);
$time = (($end - $start)/60);

echo "Total time usage for DB2Mage = ".$time." mins\n";
unlink($processHandler);

function processCheck($processHandler){
    if(!file_exists($processHandler)){
        file_put_contents($processHandler,time(), FILE_APPEND);
    }
    else{
        echo "there is a process running...";
        exit;
    }
}