<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
$backURL = $_GET['backURL'];

switch ($_SERVER['HTTP_ORIGIN']){
    case 'http://'.$backURL: case 'https://'.$backURL:
    header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 1000');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    break;
}

require_once(BASE_PATH . '/../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../app/Mage.php');
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$product = Mage::getModel('catalog/product');

$attributes = Mage::getResourceModel('eav/entity_attribute_collection')->setEntityTypeFilter($product->getResource()->getTypeId())->addFieldToFilter('attribute_code', 'manufacturer');
$attribute = $attributes->getFirstItem()->setEntity($product->getResource());
$manufacturers = $attribute->getSource()->getAllOptions(false);

$baseUrl = "http://www.autopartsexpress.autosoez.com";

$optionString = '';
foreach ($manufacturers as $manufacturer){
    $url = $baseUrl."shopby/". preg_replace('/[^0-9a-zA-Z]+/', '-', strtolower($manufacturer['label']))."?brand";
    echo "<option value='".$url."'>".$manufacturer['label']."</option>";
}


