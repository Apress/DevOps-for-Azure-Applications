<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH . '/../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../app/Mage.php');
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$products = Mage::getModel('catalog/product')
    ->getCollection()
    ->addAttributeToSelect('*')
    ->addAttributeToFilter('image', 'no_selection');

echo count($products);

foreach($products as $product)
{
    echo $product->getSku()."</br>";
    file_put_contents('skus_wt_images.log',$product->getSku()."",FILE_APPEND);
}