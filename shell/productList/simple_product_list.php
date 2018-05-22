<?php

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH . '/../../app/Mage.php');
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$mediaPath = Mage::getBaseDir('media');

$date = new DateTime();
$time = $date->format("m_d_Y");

$productListFolder = $mediaPath."/productList";

$simpleFilePath = sprintf($productListFolder."/simple_product_list.txt");
$simpleZipPath = sprintf($productListFolder."/simple_product_list.zip");
$logPath = sprintf($productListFolder."/error.log");

if(!file_exists($productListFolder)){mkdir($productListFolder);}

$header = array("Brand","SKU","UPC","Price","Core Deposit");

$startTime = time();

unlink($simpleFilePath);
unlink($simpleZipPath);
echo "Remove ".$simpleFilePath."\n";
echo "Remove ".$simpleZipPath."\n";

if(!file_exists($simpleFilePath)){
    $tmpHeader = implode("\t",$header);
    file_put_contents($simpleFilePath, $tmpHeader."\n");
}

$productsCollection = Mage::getModel('catalog/product')->getCollection()
    ->addAttributeToSelect('*')->addAttributeToFilter('type_id','simple');

$productsCollection->setPageSize(500);

$pages = $productsCollection->getLastPageNumber();
$currentPage = 1;

do {
    echo $currentPage."\n";

    $productsCollection->setCurPage($currentPage);
    $productsCollection->load();

    foreach ($productsCollection as $_product) {
        $brand = $_product->getAttributeText('manufacturer');
        $sku = $_product->getSku();
        $upc = $_product->getUpc();
        $price = $_product->getPrice();
        $core_price = $_product->getCoreDepositPrice();

        $content = array($brand,$sku,$upc,$price,$core_price);
        $content = implode("\t",$content);
        file_put_contents($simpleFilePath, $content."\n", FILE_APPEND);
    }

    $currentPage++;
    //clear collection and free memory
    $productsCollection->clear();

} while ($currentPage <= $pages);

$zip = new ZipArchive();
$zip->open($simpleZipPath, ZipArchive::CREATE);
$zip->addFile($simpleFilePath, "simple_product_list.txt");

$zip->close();

file_put_contents($logPath,"This file was generated on ".$time."\n");

$endTime = time();
echo "Total usage = ".($endTime - $startTime);


