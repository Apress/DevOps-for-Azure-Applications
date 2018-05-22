<?php
$isCli = php_sapi_name();
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
DEFINE('LOG',$isCli);

$syncMode = $argv[1];

if(isset($_POST['Brand'])||isset($_POST['SKU'])||isset($_POST['Product'])){
    switch ($_SERVER['HTTP_ORIGIN']) {
        case 'http://'.$_POST['backURL']: case 'https://'.$_POST['backURL']:
        header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
        header('Access-Control-Max-Age: 1000');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        break;
    }
    $isCli = "www";
}

$start = microtime(true);

require_once("Objects/MageObject.php");
require_once("Objects/PmpObject.php");

$date = new DateTime();

$logName = $date->format("Ymd");
$logFolder = BASE_PATH.'/../../var/log/product_sync/';
$logPath = $logFolder."product_sync_".$logName.".log";

if(!file_exists($logFolder)){mkdir($logFolder);}

$pmpObj = new PmpObject($logPath);
$mageObj = new MageObject($logPath,$pmpObj,$date);

$logContent = "************************************** Start Sync Process **************************************\n";
echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);

$changedItems = '';
if($isCli == 'www'){
    $logContent = "Sync thru WWW\n";
    echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);
    $items['Brand'] = $_POST['Brand'];
    $items['SKU'] = $_POST['SKU'];
    $items['Product'] = $_POST['Product'];
    $changedItems['items'] = $items;
    $changedItems['count'] = $_POST['count'];
}
else{
    $processId = getmypid();
    $mageObj->changeReindexMode('manual');

    if(!$syncMode){
        $processLogFile = $logFolder."product_sync_process.log";
        $processStatus = $mageObj->processCheck($processId,$processLogFile);
        if($processStatus == 0){exit;}
    }

    $logContent = "Sync thru cli\n";
    echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);
    $changedItems = $pmpObj->_getChangedItems();
}

$startProcessTime = time();
$timeLimit = 1;

if($changedItems['count']){
    $processIndex = 1;
    foreach($changedItems['items'] as $itemType => $items){
        if($itemType == 'Brand'){
            if(count($items)>0){
                foreach($items as $item){
                    $processIndex++;
                    if(((time() - $startProcessTime)/3600) > $timeLimit){
                        $logContent = "Time Out, Stop Sync Process...\n";
                        echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);
                        break;
                    }
                    else{
                        $logContent = "====================== Brand Processing Index ".$processIndex."===================\n";
                        echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);
                    }

                    $records = $pmpObj->_getBrandRecords($item);
                    if($records == "error"){
                        $pmpObj->_updateStatus($item, 'error', 'active flag = N or Sql error',$chane_time);
                        continue;
                    }
                    $result = $mageObj->_saveBrandToMage($records);
                    if(array_key_exists('brand_code',$result)){
                        $result = $pmpObj->saveMageIdBrand($result);
                    }
                    $chane_time = $date->format("Y-m-d h:i:s");
                    $pmpObj->_updateStatus($item, $result['status'], $result['errorMessage'],$chane_time);
                }
            }
        }
        elseif($itemType == 'SKU'){
            if(count($items)>0){
                foreach($items as $item){
                    $processIndex++;
                    if(((time() - $startProcessTime)/3600) > $timeLimit){
                        $logContent = "Time Out, Stop Sync Process...\n";
                        echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);
                        break;
                    }
                    else{
                        $logContent = "====================== SKU Processing Index ".$processIndex."===================\n";
                        echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);
                    }

                    $time1 = microtime(true);
                    $records = $pmpObj->_getSkuRecords($item);
                    $mageObj->_saveItemToMage($itemType, $records);
                }
            }
        }
        elseif($itemType == 'Product'){
            if(count($items)>0){
                foreach($items as $item){
                    $processIndex++;
                    if(((time() - $startProcessTime)/3600) > $timeLimit){
                        $logContent = "Time Out, Stop Sync Process...\n";
                        echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);
                        break;
                    }
                    else{
                        $logContent = "====================== Product Processing Index ".$processIndex."===================\n";
                        echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);
                    }

                    $records = $pmpObj->_getProductRecords($item);
                    $mageObj->_saveItemToMage($itemType, $records);
                }
            }
        }
        else{
            $logContent = "Invalid Type => ".$itemType."\n";
            echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);
        }
    }
}

$logContent = "************************************** End Sync Process **************************************\n";
echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);

$end = microtime(true);
$time = (($end - $start)/60);

echo "Total time usage for DB2Mage = ".$time." mins\n";

//if($isCli != 'www'){
//    if(!$syncMode){
//        if($changedItems['count']){
//            $start = microtime(true);
//            $mageObj->callReindex();
//
//            $end = microtime(true);
//            $time = (($end - $start)/60);
//            echo "Total time usage for Reindex = ".$time." mins\n";
//        }
//        $logContent = "Change Reindex Mode To Real Time...\n";
//        echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);
//        $mageObj->changeReindexMode('real_time');
//
//        $logContent = "Remove Process Log File...\n";
//        echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);
//        $mageObj->processUpdate($processLogFile);
//    }
//}

