<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));		//Magento Root Directory

require_once(BASE_PATH.'/../../app/etc/cfg/config.php');

error_reporting(E_ALL | E_STRICT);
//ini_set('memory_limit', '512M');
date_default_timezone_set('America/Los_Angeles');
require_once(BASE_PATH.'/../../app/Mage.php');
Mage::app('default');
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

$read = Mage::getSingleton('core/resource')->getConnection('core_read');
$select = $read->select()->from(MAGE_TABLE_PREFIX."bannerslider");
$records = $read->fetchAll($select);

$disable_id_array = '';
$enable_id_array = '';
foreach($records as $item){
    if(strtotime($item["created_time"])>strtotime(now())||strtotime($item["update_time"])<strtotime(now())){
        $disable_id_array[] = $item["bannerslider_id"];
    }
    else{
        $enable_id_array[] = $item["bannerslider_id"];
    }
}

$write = Mage::getSingleton('core/resource')->getConnection('core_write');
foreach($enable_id_array as $id){
    $query = "update ".MAGE_TABLE_PREFIX."bannerslider set status = 1 where bannerslider_id = ".$id;
    echo $query."\n";
    $write->query($query);
}

foreach($disable_id_array as $id){
    $query = "update ".MAGE_TABLE_PREFIX."bannerslider set status = 2 where bannerslider_id = ".$id;
    echo $query."\n";
    $write->query($query);
}



