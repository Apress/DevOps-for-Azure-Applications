<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH.'/../../app/Mage.php');
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

changeReindexMode('manual');

$category = Mage::getModel('catalog/category');


for($i = 2; $i < 500; $i++){
    $cat = $category->load($i);
    if($cat){
        $name = $cat->getName();
        $storeName = "AutoPartsExpress";

        echo $name."\n";

        $general['meta_title'] = $storeName.": After Market Auto Parts ".$name;
        $general['meta_keywords'] = $storeName.", ".$name;
        $general['meta_description'] = "Missing anything? Look for it here in our wide array of miscellaneous ".$name." prats. Here, we collect the odds and ends of auto repair.";

        $category->addData($general);
        $category->save();
    }
}

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
    ?>

