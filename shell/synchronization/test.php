<?php
require_once("lib/pmpModel.php");

$client_info_path = '../../app/etc/cfg/client_info.conf';
$client_info = file_get_contents($client_info_path);
$client_info_array = explode(";", $client_info);
$user_id = explode("=", $client_info_array [0])[1];
$pmpURL = explode("=", $client_info_array [1])[1];

$pmpObj = new PmpObject($logPath,$pmpURL,$user_id);

$changedItems = $pmpObj->_getChangedItems("brand");
echo "\n***changed brand***\n";
var_dump($changedItems);

$changedItems = $pmpObj->_getChangedItems("sku_product",10);
echo "\n***changed sku and product***\n";
var_dump($changedItems);

$changedItems = json_decode($pmpObj->getChangedInventory());
echo "\n***changed inventory***\n";
var_dump($changedItems);

$changedItems = $pmpObj->getChangedImage('brandimage');
$changedItems = json_decode($changedItems,true);
echo "\n***changed brand image***\n";
var_dump($changedItems);

$changedItems = $pmpObj->getChangedImage('categoryimage');
$changedItems = json_decode($changedItems,true);
echo "\n***changed category image***\n";
var_dump($changedItems);

$changedItems = $pmpObj->getChangedImage('storelogo');
$changedItems = json_decode($changedItems,true);
echo "\n***changed store logo***\n";
var_dump($changedItems);

$changedItems = $pmpObj->getChangedImage('productimage');
$changedItems = json_decode($changedItems,true);
echo "\n***changed product image***\n";
var_dump($changedItems);

$changedItems = $pmpObj->getChangedImage('skuimage');
$changedItems = json_decode($changedItems,true);
echo "\n***changed sku image***\n";
var_dump($changedItems);

$changedMapItems = $pmpObj->getChangedUniversal('productuniversal');
$changedMapItems = json_decode($changedMapItems,true);
echo "\n***changed universal***\n";
var_dump($changedMapItems);
?>