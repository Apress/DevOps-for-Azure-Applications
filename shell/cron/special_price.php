<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH . '/../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../app/Mage.php');

date_default_timezone_set("America/Los_Angeles");

$date = new DateTime();
$logName = $date->format("Ymd_his");

$start_time = microtime(true);

$logFolder = BASE_PATH.'/../../var/log/special_price_cron/';
$logPath = $logFolder."special_price_cron".$logName.".log";

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$mageRead = Mage::getSingleton('core/resource')->getConnection('core_read');
$mageWrite = Mage::getSingleton('core/resource')->getConnection('core_write');
$mageWrite->query(sprintf('TRUNCATE TABLE mage_am_finder_map_with_special_price'));

$groupedProducts = Mage::getModel('catalog/product')->getCollection()->addAttributeToFilter('type_id', array('eq' => 'grouped'));
$groupedProductModel = Mage::getModel('catalog/product_type_grouped');
$simpleProductModel = Mage::getModel('catalog/product');

foreach ($groupedProducts as $groupedProduct) {
    $groupProductId = $groupedProduct->getId();
    $childrenIdsSet = $groupedProductModel->getChildrenIds($groupProductId);

    $childSkusString = null;
    foreach($childrenIdsSet as $childrenIds){
        $childrenIdsString = implode('\',\'',$childrenIds);
        $result = $mageRead->query(sprintf("SELECT `sku` FROM `mage_catalog_product_entity` WHERE `entity_id` IN ('%s')",$childrenIdsString));
        while($childSku = $result->fetch())
        {
            $childSkusString = $childSkusString.'\''.$childSku['sku'].'\',';
        }
    }

    $vehicleIds = array();
    $result = $mageRead->query(sprintf('SELECT value_id FROM mage_am_finder_map WHERE pid=%s',$groupProductId));

    while($row = $result->fetch())
    {
        $vehicleIds[] = $row["value_id"];
    }

    $childSkusString = rtrim($childSkusString,",");
    if($vehicleIds){
        foreach($vehicleIds as $vehicleId){
            $pricePool = array();
            $specialPricePool = array();
            $result = $mageRead->query(sprintf("SELECT sku FROM mage_am_finder_fit_note WHERE sku IN (%s) AND vehicle_id = %s",$childSkusString,$vehicleId));
            while($row = $result->fetch())
            {
                $result = $mageRead->query(sprintf("SELECT `entity_id` FROM `mage_catalog_product_entity` WHERE `sku` = '%s'",$row["sku"]));
                $simpleProductId = $result->fetch();
                $simpleProduct = $simpleProductModel->load($simpleProductId);
                if($simpleProduct->getPrice()){
                    $pricePool[] = $simpleProduct->getPrice();
                }
                if($simpleProduct->getSpecialPrice()){
                    $specialPricePool[] =  $simpleProduct->getSpecialPrice();
                }
            }

            $minimalPrice = min($pricePool);
            if(count($specialPricePool) == 0){
                $minimalSpecialPrice = 0;
            }
            else{
                $minimalPrice = min(min($specialPricePool),$minimalPrice);
                $minimalSpecialPrice = 1;
            }
            $logContent = $vehicleId." --- ".$groupProductId."memory usage = ".(memory_get_usage()/1024/1024)."\n";
            file_put_contents($logPath,$logContent, FILE_APPEND);

            $mageWrite->query(sprintf('INSERT INTO mage_am_finder_map_with_special_price(price, special_price, value_id, pid) VALUE(%s,%s,%s,%s);',$minimalPrice,$minimalSpecialPrice,$vehicleId,$groupProductId));
        }
    }
}

$end_time = microtime(true);
$logContent = $end_time - $start_time;
file_put_contents($logPath,$logContent, FILE_APPEND);