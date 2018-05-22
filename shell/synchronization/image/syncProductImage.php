<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH."/../../../app/etc/cfg/config.php");
require_once(BASE_PATH."/../../../app/Mage.php");
require_once(BASE_PATH."/../lib/pmpModel.php");

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$date = new DateTime();
$logFolder = BASE_PATH."/../../../var/log/syncProductImage/";
$logPath = $logFolder.$date->format("Y_m_d").".log";
$procfile = BASE_PATH.'/../pcheck/productimageprocnew.txt';
$procfilecomp = BASE_PATH.'/../pcheck/productimageproccomp.txt';

if(!file_exists($logFolder)){mkdir($logFolder);}

$client_info_path = BASE_PATH."/../../../app/etc/cfg/client_info.conf";
$client_info = file_get_contents($client_info_path);
$client_info_array = explode(";", $client_info);

$user_id = explode("=", $client_info_array [0])[1];
$pmpURL = explode("=", $client_info_array [1])[1];

$pmpObj = new PmpObject($logPath,$pmpURL,$user_id);
process_log_product_image($logPath,"*** Start process ".$date->format("Y_m_d_h_i_s")."***");

$changedProductItems = $pmpObj->getChangedImage('productimage');

$changedItems = json_decode($changedProductItems,true);
$sku_codes = [];

$connection = _getConnection_product_image('core_write');

// $smallImageId           = _getAttributeId('small_image');
// $imageId                = _getAttributeId('image');
// $thumbnailId            = _getAttributeId('thumbnail');
// $mediaGalleryId         = _getAttributeId('media_gallery');

// echo $smallImageId."\n";
// echo $imageId."\n";
// echo $thumbnailId."\n";
// echo $mediaGalleryId."\n";

// exit();

foreach ($changedItems as $index => $path_array){
	if(_checkIfProductExists($index)){
        try{
            $sku_codes[$index] = "";
            $productId = _getProductIdFromSku($index);

            echo "product id = ".$productId."\n";
            $sql = "DELETE FROM mage_catalog_product_entity_media_gallery WHERE entity_id = $productId;";                                                                                      
            $connection->query($sql);
            $sql = "DELETE FROM mage_catalog_product_entity_varchar WHERE entity_id = $productId AND attribute_id in (85,86,87);";                                                                                      
            $connection->query($sql);
            foreach( $path_array as $order => $path){ 
                //converting extension to .jpg 
                $path = substr($path,0,strlen($$path)-4).'.jpg';               
                process_log_product_image($logPath, "key => ".$index." value = ".$path);

                if ($order == 0){
                    $sql = "INSERT INTO mage_catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (4, 85, 0, $productId, '$path'),(4, 86, 0, $productId, '$path'),(4, 87, 0, $productId, '$path');";
                    $connection->query($sql);
                }
                $sql = "INSERT INTO mage_catalog_product_entity_media_gallery (entity_id, attribute_id, value) VALUES ($productId, 88, '$path');";                                                     
                $connection->query($sql);
                
                $sql = "SELECT value_id FROM mage_catalog_product_entity_media_gallery ORDER BY value_id DESC LIMIT 1";
                $last_id = $connection->fetchOne($sql);
                echo "last id =".$last_id."\n";
                $sql = "INSERT INTO mage_catalog_product_entity_media_gallery_value (value_id, position, store_id) VALUES ($last_id, $order, 0);";
                $connection->query($sql);
            }  

        }catch(Exception $e){
            $sku_codes[$index] = $e->getMessage();
            $error_msg = $count .'> Error:: while Upating  image (' . $_data . ') of Sku (' . $index . ') => '.$e->getMessage()."\r\n";
            process_log_product_image($logPath, $error_msg);
        }
    }else{
        $sku_codes[$index] = "Product - $index does not exist.";
        $error_msg .'> Error:: Product with Sku (' . $index . ') does\'t exist.'."\r\n";
        process_log_product_image($logPath, $error_msg);
    }    
}

$pmpObj->updateSyncImage($sku_codes, 'product_image');

function _getConnection_product_image($type = 'core_read'){
    return Mage::getSingleton('core/resource')->getConnection($type);
}

function _checkIfProductExists($sku){
    $connection = _getConnection_product_image('core_read');
    $sql = "SELECT COUNT(*) AS count_no 
    FROM mage_catalog_product_entity
    WHERE sku = ?";
    $count      = $connection->fetchOne($sql, array($sku));
    if($count > 0){
    	echo "product exists = TRUE  ".$sku;
        return true;
    }else{
    	echo "product exists = FALSE  ".$sku;
        return false;
    }
}

function _getProductIdFromSku($sku){
    $connection = _getConnection_product_image('core_read');
    $sql        = "SELECT entity_id FROM mage_catalog_product_entity WHERE sku = ?";
    return $connection->fetchOne($sql, array($sku));
}

function _getTableName($tableName){
    return Mage::getSingleton('core/resource')->getTableName($tableName);
}
 
function _getAttributeId($attribute_code = 'price'){
    $connection = _getConnection_product_image('core_read');
    $sql = "SELECT attribute_id
                FROM " . _getTableName('eav_attribute') . "
            WHERE
                entity_type_id = ?
                AND attribute_code = ?";
    $entity_type_id = _getEntityTypeId();
    return $connection->fetchOne($sql, array($entity_type_id, $attribute_code));
}
 
function _getEntityTypeId($entity_type_code = 'catalog_product'){
    $connection = _getConnection_product_image('core_read');
    $sql        = "SELECT entity_type_id FROM " . _getTableName('eav_entity_type') . " WHERE entity_type_code = ?";
    return $connection->fetchOne($sql, array($entity_type_code));
}

//Tell Other scripts that this script is successfully complete so the next one can run
rename($procfile, $procfilecomp);

process_log_product_image($logPath,"*** end process ***");
function process_log_product_image($logPath,$logContent){
    echo $logContent,"\n";
    file_put_contents($logPath,$logContent."\n", FILE_APPEND);    
}
?>