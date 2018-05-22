<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH."/../../../app/etc/cfg/config.php");
require_once(BASE_PATH."/../../../app/Mage.php");
require_once(BASE_PATH."/../lib/pmpModel.php");

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$date = new DateTime();
$logFolder = BASE_PATH."/../../../var/log/syncSkuImage/";
$logPath = $logFolder.$date->format("Y_m_d").".log";
$procfile = BASE_PATH.'/../pcheck/skuimageprocnew.txt';
$procfilecomp = BASE_PATH.'/../pcheck/skuimageproccomp.txt';

if(!file_exists($logFolder)){mkdir($logFolder);}

$client_info_path = BASE_PATH."/../../../app/etc/cfg/client_info.conf";
$client_info = file_get_contents($client_info_path);
$client_info_array = explode(";", $client_info);

$user_id = explode("=", $client_info_array [0])[1];
$pmpURL = explode("=", $client_info_array [1])[1];
// $pmpURL = "http://pmp2.gcomsoez.com/";

$pmpObj = new PmpObject($logPath,$pmpURL,$user_id);

process_log_sku_image($logPath,"*** Start process ".$date->format("Y_m_d_h_i_s")."***");

$changedSkuItems = $pmpObj->getChangedImage('skuimage');
$changedItems = json_decode($changedSkuItems,true);
$sku_codes = [];

$connection = _getConnection_sku('core_write');
foreach ($changedItems as $index => $path_array){
    if(_checkIfSkuExists($index)){
        try{
            $sku_codes[$index] = "";
            $productId = _getIdFromSku($index);
            $sql = "DELETE FROM mage_catalog_product_entity_media_gallery WHERE entity_id = $productId;";                                                                                      
            $connection->query($sql);
            $sql = "DELETE FROM mage_catalog_product_entity_varchar WHERE entity_id = $productId AND attribute_id in (85,86,87);";                                                                                      
            $connection->query($sql);
            foreach( $path_array as $order => $path){
                //adding extra folder for sku image and converting extension to .jpg 
                $path = "sku/".substr($path,0,strlen($$path)-4).'.jpg';
                echo 'final path = '.$path.'\n';

                process_log_sku_image($logPath, "key => ".$index." value = ".$path);
                if ($order == 0){
                    $sql = "INSERT INTO mage_catalog_product_entity_varchar (entity_type_id, attribute_id, store_id, entity_id, value) VALUES (4, 85, 0, $productId, '$path'),(4, 86, 0, $productId, '$path'),(4, 87, 0, $productId, '$path');";
                    $connection->query($sql);
                }
                $sql = "INSERT INTO mage_catalog_product_entity_media_gallery (entity_id, attribute_id, value) VALUES ($productId, 88, '$path');";                                                     
                $connection->query($sql);
                
                $sql = "SELECT value_id FROM mage_catalog_product_entity_media_gallery ORDER BY value_id DESC LIMIT 1";
                $last_id = $connection->fetchOne($sql);
                
                $sql = "INSERT INTO mage_catalog_product_entity_media_gallery_value (value_id, position, store_id) VALUES ($last_id, $order, 0);";
                $connection->query($sql);
            }  

        }catch(Exception $e){
            $sku_codes[$index] = $e->getMessage();
            $error_msg = $count .'> Error:: while Upating  image (' . $_data . ') of Sku (' . $index . ') => '.$e->getMessage()."\r\n";
            process_log_sku_image($logPath, $error_msg);
        }
    }else{
        $sku_codes[$index] = "Sku - $index does not exist.";
        $error_msg = $count .'> Error:: Product with Sku (' . $index . ') does\'t exist.'."\r\n";
        process_log_sku_image($logPath, $error_msg);
    }    
}

$pmpObj->updateSyncImage($sku_codes, 'sku_image');

function _getConnection_sku($type = 'core_read'){
    return Mage::getSingleton('core/resource')->getConnection($type);
}

function _checkIfSkuExists($sku){
    $connection = _getConnection_sku('core_read');
    $sql = "SELECT COUNT(*) AS count_no 
    FROM mage_catalog_product_entity
    WHERE sku = ?";
    $count      = $connection->fetchOne($sql, array($sku));
    if($count > 0){
        return true;
    }else{
        return false;
    }
}

function _getIdFromSku($sku){
    $connection = _getConnection_sku('core_read');
    $sql        = "SELECT entity_id FROM mage_catalog_product_entity WHERE sku = ?";
    return $connection->fetchOne($sql, array($sku));
}

//Tell Other scripts that this script is successfully complete so the next one can run
rename($procfile, $procfilecomp);

process_log_sku_image($logPath,"*** end process ***");

function process_log_sku_image($logPath,$logContent){
    echo $logContent,"\n";
    file_put_contents($logPath,$logContent."\n", FILE_APPEND);    
}
?>