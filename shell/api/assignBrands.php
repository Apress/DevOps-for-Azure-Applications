<?php

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH.'/../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../app/Mage.php');

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );
date_default_timezone_set('America/Los_Angeles');
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

Mage::app('admin');
Mage::getSingleton("core/session", array("name" => "adminhtml"));
Mage::register('isSecureArea',true);

$manufacturerCode = $argv[1];
$manufacturerId = $argv[2];

$read = Mage::getSingleton('core/resource')->getConnection('core_read');
$select = $read->select("sku")->from("mage_catalog_product_entity")->where('sku LIKE ?', $manufacturerCode.'%');

$records = $read->fetchAll($select);
$productObj = Mage::getModel('catalog/product');
foreach($records as $index => $record){
    $sku = $record["sku"];
    echo $index." => ".$sku."\n";
    $product 		= $productObj->loadByAttribute('sku', $sku);
    $product->setData('manufacturer', $manufacturerId);
    $product->save();
    unset($product);
}