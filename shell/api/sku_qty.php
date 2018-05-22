<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));		//Magento Root Directory

require_once(BASE_PATH . '/../../app/etc/cfg/config.php');

error_reporting(E_ALL | E_STRICT);
ini_set('memory_limit', '512M');
require_once(BASE_PATH . '/../../app/Mage.php');
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$baseURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
$pmpURL = str_replace("http://www.","http://pmp2.",$baseURL);

$skuArray = $_POST['skus'];
$skus = json_decode($skuArray);

$result = array();

foreach($skus as $sku){

    $qty = _getQtyBySKU($sku);

    if($qty){
        $result[$sku] = $qty;
    }else{
        continue;
    }
}

echo json_encode($result);

/*
 * Private Function
 */

function _getConnection($type = 'core_read'){
    return Mage::getSingleton('core/resource')->getConnection($type);
}

function _getIdFromSku($sku){
    $connection = _getConnection('core_read');
    $sql        = "SELECT entity_id FROM mage_catalog_product_entity WHERE sku = ?";
    return $connection->fetchOne($sql, array($sku));
}

function _getQtyBySKU($sku){
    $connection     = _getConnection('core_write');
    $productId      = _getIdFromSku($sku);

    $sql            = "SELECT csi.qty FROM mage_cataloginventory_stock_item csi
                        INNER JOIN mage_cataloginventory_stock_status css
                        WHERE csi.product_id = ? AND csi.product_id = css.product_id";

    return $connection->fetchOne($sql, array($productId));
}