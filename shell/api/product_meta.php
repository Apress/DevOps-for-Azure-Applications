<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH.'/../../app/Mage.php');
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

changeReindexMode('manual');

$productsCollection = Mage::getModel('catalog/product')->getCollection()
    ->addAttributeToSelect(array('name', 'manufacturer', 'part_type'));

$productsCollection->setPageSize(20);

$pages = $productsCollection->getLastPageNumber();
$currentPage = 1;

do {
    $productsCollection->setCurPage($currentPage);
    $productsCollection->load();

    foreach ($productsCollection as $_product) {
        echo $_product->getId()."----".memory_get_usage()."\n";
        $storeName = "AutoPartsExpress";
        $name = $_product->getName();
        $brand = $_product->getAttributeText('manufacturer');
        $type = $_product->getPartType();

        $meta_Description = "utoPartsExpress carries quality".$type."such as ".$brand." ".$name." while offering fast delivery to your doorsteps. Trust ".$brand." and ".$storeName." for your auto parts needs.";
        $meta_title = $name." on sale!";
        $meta_keyword = $brand." ".$name.", ".$storeName.", ".$type;

        $_product->setMetaDescription($meta_Description);
        $_product->setMetaTitle($meta_title);
        $_product->setMetaKeyword($meta_keyword);
        $_product->save();
    }

    $currentPage++;
    //clear collection and free memory
    $productsCollection->clear();
} while ($currentPage <= $pages);

changeReindexMode('real_time');

function changeReindexMode($reindexMode){
    $processes = array(
        'Product Attributes'        => 1,
        'Product Prices'            => 2,
        'Catalog URL Rewrites'      => 3,
        'Product Flat Data'         => 4,
        'Category Flat Data'        => 5,
        'Category Products'         => 6,
        'Catalog Search index'      => 7,
        'Tag Aggregation Data'      => 8,
        'Stock Status'              => 9
    );

    foreach($processes as $process) {
        $process = Mage::getModel('index/process')->load($process);
        if($reindexMode == 'manual'){
            $process->setData('mode','manual')->save();
        }
        elseif($reindexMode == 'real_time'){
            $process->setData('mode','real_time')->save();
        }
    }
}