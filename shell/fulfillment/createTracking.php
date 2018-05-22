<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));//Magento Root Directory

/* Multiple Vendors */

$clientCode = $argv[1];

if($clientCode == "AAT01"){
    $multipleVendor = array('HAN','PAT','TRA','KEY','FMP','F01','F02','F07','F12','F24','F50','B06','B09','B13','B16','B19','B20','B23');
}elseif($clientCode == "BAP01"){
    $multipleVendor = array('TRA','KEY');
}elseif($clientCode == "BSR01"){
    $multipleVendor = array('PAT');
}else{
    $multipleVendor = array();
}

/* Vendor Invoice Info */

$now = date('Y-m-d', strtotime("now"));

$tracking_path = BASE_PATH.'/../../var/log/orders/tracking_'.$now.'.csv';
$invoice_path = BASE_PATH.'/../../var/log/orders/invoice_'.$now.'.csv';

$tracking_headers = "Order_Number,Invoice_Number,Tracking_Number,Shipping_Method,Vending_Date,Vendor,SKU_Quantity";
$invoice_headers = "Order_Number,Order_ID,Vendor_Code,Invoice_Number,Order_Date,Vending_Date,Vending_Cost_Subtotal,Vending_Shipping_Cost,Tax,SKU_Quantity";

if(!file_exists($tracking_path)){
    file_put_contents($tracking_path,$tracking_headers."\n");
}
if(!file_exists($invoice_path)){
    file_put_contents($invoice_path,$invoice_headers."\n");
}

/* Basic Settings */

require_once(BASE_PATH.'/../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../app/Mage.php');
require_once($clientCode."/Orders.php");

$baseURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
$pmp2URL = str_replace("http://www.","http://pmp2.",$baseURL);

$logPath = BASE_PATH."/../../var/log/orders/".$clientCode."_createTracking_".date("Ymd").".log";

if (!is_dir(BASE_PATH."/../../var/log/orders")){
    mkdir(BASE_PATH."/../../var/log/orders");
}
$processLogFile = BASE_PATH."/../../var/log/orders/createTracking.log";

$FMP_warehouse = array('FMP','F01','F02','F07','F12','F24','F50');
$BEC_warehouse = array('B06','B09','B13','B16','B19','B20','B23');

//$envCode = 'test';
$envCode = 'live';

$orderObj = new Orders($logPath,$multipleVendor,$envCode,$pmp2URL,$FMP_warehouse,$BEC_warehouse,$tracking_path,$invoice_path);

$processId = getmypid();
$processStatus = $orderObj->processCheck($processId,$processLogFile);
if($processStatus == 0){exit;}

$orders = $orderObj->getOrders('wait_to_ship');

$orderObj->getTrackingNumber($orders);

$orderObj->processUpdate($processLogFile);

