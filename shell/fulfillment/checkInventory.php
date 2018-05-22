<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
$clientCode = $argv[1];
//$clientCode = "AAT01";

require_once(BASE_PATH.'/../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../app/Mage.php');
require_once($clientCode."/Orders.php");

if (!is_dir(BASE_PATH."/../../var/log/orders")){
    mkdir(BASE_PATH."/../../var/log/orders");
}

$baseURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
$pmp2URL = str_replace("http://www.","http://pmp2.",$baseURL);

$processLogFile = BASE_PATH."/../../var/log/orders/checkInventory.log";
if($clientCode == "AAT01"){

    $multipleVendor = array('HAN','PAT','TRA','KEY','FMP','F01','F02','F07','F12','F24','F50','B06','B09','B13','B16','B19','B20','B23');

    $BEC_warehouse = array('B06','B09','B13','B16','B19','B20','B23');
    $FMP_warehouse = array('FMP','F01','F02','F07','F12','F24','F50');

}elseif($clientCode == "BAP01"){

    $multipleVendor = array('TRA','KEY');
    $FMP_warehouse = NULL;
    $BEC_warehouse = NULL;

}elseif($clientCode == "BSR01"){

    $multipleVendor = array('PAT');
    $FMP_warehouse = NULL;
    $BEC_warehouse = NULL;

}else{

    $multipleVendor = array();
    $FMP_warehouse = NULL;
    $BEC_warehouse = NULL;

}

$logPath = BASE_PATH."/../../var/log/orders/".$clientCode."_checkInventory_".date("Ymd").".log";

//$envCode = 'test';
$envCode = 'live';

$orderObj = new Orders($logPath,$multipleVendor,$envCode,$pmp2URL,$FMP_warehouse,$BEC_warehouse);

$processId = getmypid();
$processStatus = $orderObj->processCheck($processId,$processLogFile);
if($processStatus == 0){exit;}

$orders = $orderObj->getOrders('processing');
$vendorInfo = array();
foreach($orders as $order){

    $logContent = "[Order Number = ".$order->getIncrementId()."]\r\n";
    echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);

    $items = $order->getAllVisibleItems();
    $skuArray = array();
    $brandCodeArray = array();
    foreach($items as $item){
        array_push($skuArray,"'".$item->getSku(). "'");

        $brandCode = substr($item->getSku(), 0, 3);
        array_push($brandCodeArray,$brandCode);
    }

    if(in_array('HYT',$brandCodeArray)){
        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'cs_fulfillment');
        $order->save();
    }
    else{
        foreach($multipleVendor as $vendorCode){

            $vendorInfo[$vendorCode] = $orderObj->getVendorSku($skuArray,$vendorCode);
        }

        $allVendorsInventory = $orderObj->getInventoryForAllVendors($order,$vendorInfo);

        $logContent = "========== Vendor Inventory Check ============\r\n";
        foreach($allVendorsInventory as $index => $vendor_items){
            $logContent = $logContent."[".$index."]\r\n";
            foreach($vendor_items as $indexKey => $vendor_item){
                $logContent = $logContent.$indexKey.": ".$vendor_item."\r\n";
            }
        };
        echo $logContent;file_put_contents($logPath,$logContent, FILE_APPEND);
        $itemsArray = $orderObj->checkInventory($items,$vendorInfo,$allVendorsInventory);

        $orderObj->callPlaceOrderProcess($order,$items,$itemsArray,$vendorInfo);

    }
}

$orderObj->processUpdate($processLogFile);
?>