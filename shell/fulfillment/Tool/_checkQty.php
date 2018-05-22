<?php

class Address{

    public function __construct(){
        $this->_buildParams();
    }

    public function _buildParams(){

    }

    public function getStreetFull(){
        return '1600 W 40 HWY STE 207';
    }

    public function getCity(){
        return 'Blue Springs';
    }

    public function getRegionCode(){
        return 'MO';
    }

    public function getPostcode(){
        return '64015';
    }
}

class Item{

    private $sku;

    public function __construct($sku){
        $this->_buildParams($sku);
    }

    public function _buildParams($sku){
        $this->sku = $sku;
    }

    public function getSku(){
        return $this->sku;
    }

    public function getQtyOrdered(){
        return 1;
    }
}

class Order{

    private $sku;

    public function __construct($sku){
        $this->_buildParams($sku);
    }

    public function _buildParams($sku){
        $sku = substr($sku, 1);
        $sku = substr($sku, 0, -1);
        $this->sku = $sku;
    }

    public function getIncrementId(){
        return "111111111";
    }

    public function getShippingMethod(){
        return "Fedex";
    }

    public function getCustomerName(){
        return "Steve Gwinn";
    }

    public function getShippingAddress(){
        $address = new Address();
        return $address;
    }

    public function getAllVisibleItems(){

        $item = new Item($this->sku);

        return array($item);
    }
}

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
//$clientCode = $argv[1];
$clientCode = "AAT01";

require_once(BASE_PATH.'/../../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../../app/Mage.php');
require_once(BASE_PATH."/../".$clientCode."/Orders.php");

$baseURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
//$baseURL = "http://www.approvedautoparts.com/";

//$pmp2URL = str_replace("http://www.","http://pmp2.",$baseURL);
$pmp2URL = 'http://pmp2.autopartsexpress.com/';

$processLogFile = BASE_PATH."/../../var/log/orders/TEST_checkInventory.log";
$logPath = BASE_PATH."/../../../var/log/orders/TEST_checkInventory_".date("Ymd").".log";

$multipleVendor = array('PAT','TRA','KEY','FMP','F01','F02','F07','F12','F24','F50','B06','B09','B13','B16','B19','B20','B23');

$BEC_warehouse = array('B06','B09','B13','B16','B19','B20','B23');
$FMP_warehouse = array('FMP','F01','F02','F07','F12','F24','F50');

//$envCode = 'test';
$envCode = 'live';

//$sku = "'".$_GET['sku']."'";
$sku = "'MOG:K8709'";

$order = new Order($sku);

$orderObj = new Orders($logPath,$multipleVendor,$envCode,$pmp2URL,$FMP_warehouse,$BEC_warehouse);

$vendorInfo = array();

$skuArray = array($sku);

$brandCode = substr($sku, 0, 3);

foreach($multipleVendor as $vendorCode){

    $vendorInfo[$vendorCode] = $orderObj->getVendorSku($skuArray,$vendorCode);
}

$allVendorsInventory = $orderObj->getInventoryForAllVendors($order,$vendorInfo);

foreach($allVendorsInventory as $index => $vendor){

    foreach($vendor as $sku => $qty){

        if($qty != 0){
            echo $index."[".$sku."]"." = ".$qty."\n";
        }
    }

};