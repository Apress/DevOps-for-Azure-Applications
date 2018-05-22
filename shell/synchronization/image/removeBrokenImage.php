<?php

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH.'/../../../app/Mage.php');

$brand_code = $argv[1];
$brand_filter = $brand_code.'%';

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$logFolder = BASE_PATH.'/../../../var/log/sync_image/';
$sku_log = $logFolder.$brand_code."_sku.log";
$image_name_log = $logFolder.$brand_code."_path.log";

if(!file_exists($logFolder)){mkdir($logFolder);}

$productsCollection = Mage::getModel('catalog/product')->getCollection()
    ->addAttributeToSelect('*')->addAttributeToFilter('sku', array('like' => $brand_filter));
//    ->addAttributeToSelect('*')->addAttributeToFilter('type_id','grouped');
//->addAttributeToSelect('*')->addAttributeToFilter('type_id','simple');

$productsCollection->setPageSize(500);
$pages = $productsCollection->getLastPageNumber();

$currentPage = 1;

$logContent = "Start Removing Process...\n";
//echo $logContent;
file_put_contents($sku_log,$logContent);
file_put_contents($image_name_log,$logContent);

do {

    $productsCollection->setCurPage($currentPage);
    $productsCollection->load();

    foreach ($productsCollection as $_product) {

        $hasBroken = 0;
        $sku = $_product->getSku();

        echo "------- ".$sku." -------\n";

        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
        $product->load();

        $attributes = $product->getTypeInstance()->getSetAttributes();
        $gallery = $attributes['media_gallery'];
        $galleryData = $product->getMediaGallery();

        foreach($galleryData['images'] as $image){

            $image_path = Mage::getBaseDir()."/media/catalog/product".$image['file'];
            echo "real file path => ".$image_path."\n";

            if(exif_imagetype($image_path) === false){
                $hasBroken++;

                $logContent = basename($image['file'])."\n";
                file_put_contents($image_name_log,$logContent, FILE_APPEND);

                $gallery->getBackend()->removeImage($product, $image['file']);
            }
        }

        if($hasBroken > 0){

            $logContent1 = $sku." => ".$hasBroken."\n";
            file_put_contents($sku_log,$logContent1, FILE_APPEND);

            $product->save();

            $product->load();
            $galleryData = $product->getMediaGallery();

            foreach($galleryData['images'] as $image){
                $product->setSmallImage($image['file'])
                    ->setThumbnail($image['file'])
                    ->setImage($image['file'])
                    ->save();
                break;
            }
        }

        $product->clearInstance();
        $_product->clearInstance();

    }

    $currentPage++;
    //clear collection and free memory

    $productsCollection->clear();

    echo "Memory usage = ".(memory_get_usage()/1024/1024) ."\n";


} while ($currentPage <= $pages);