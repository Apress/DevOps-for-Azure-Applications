<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH . '/../../../app/Mage.php');
$baseURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
$pmpURL = str_replace("http://www.","http://pmp2.",$baseURL);

$logPath = BASE_PATH.'/../../../var/log/sync_image';

if (!file_exists($logPath)) {
    mkdir($logPath, 0777, true);
}

$time = date('Y_m_d');
$logPath = $logPath.'/multiple_image_'.$time.'.log';

require_once(BASE_PATH."/../lib/mageModel.php");
require_once(BASE_PATH."/../lib/pmpModel.php");

$baseURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
$pmpURL = str_replace("http://www.","http://pmp2.",$baseURL);

$logContent = "Start Sync Process...\n";
echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);

$date = new DateTime();

$pmpObj = new PmpObject($logPath,$pmpURL);
$mageObj = new MageObject($logPath,$pmpObj,$date);

//flush cache
$mageObj->flushCache();

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

