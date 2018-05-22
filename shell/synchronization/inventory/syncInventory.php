<?php
error_reporting(E_ALL | E_STRICT);
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));		//Magento Root Directory
ini_set('memory_limit', '512M');

require_once(BASE_PATH . '/../../../app/etc/cfg/config.php');
require_once(BASE_PATH . '/../../../app/Mage.php');
require_once(BASE_PATH."/../lib/pmpModel.php");

$date = new DateTime();
$logFolder = BASE_PATH.'/../../../var/log/syncInventory/';
$logPath = $logFolder.$date->format("Y_m_d").".log";
$procfile = BASE_PATH.'/../pcheck/inventoryprocnew.txt';
$procfilecomp = BASE_PATH.'/../pcheck/inventoryproccomp.txt';
if(!file_exists($logFolder)){mkdir($logFolder);}

$client_info_path = BASE_PATH.'/../../../app/etc/cfg/client_info.conf';
$client_info = file_get_contents($client_info_path);
$client_info_array = explode(";", $client_info);

$user_id = explode("=", $client_info_array [0])[1];
$pmpURL = explode("=", $client_info_array [1])[1];
// $pmpURL = "http://pmp2.gcomsoez.com/";
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );
$pmpObj = new PmpObject($logPath,$pmpURL,$user_id);

process_log_inventory($logPath,"*** Start process ".$date->format("Y_m_d_h_i_s")."***");

echo "Start downloading inventory data\n";
$changedItems = json_decode($pmpObj->getChangedInventory());

$sku_codes = "'";
foreach($changedItems as $index =>$_data){
    if(_checkIfSkuExists($index)){
        try{
            _updateStocks($index,$_data);
            
            process_log_inventory($logPath, "key => ".$index." value = ".$_data);
            $sku_codes .= $index."','";

        }catch(Exception $e){
            $sku_codes .= $index."','";
            $error_msg = $count .'> Error:: while Upating  Qty (' . $_data . ') of Sku (' . $index . ') => '.$e->getMessage()."\r\n";
            process_log_inventory($logPath, $error_msg);
            
        }
    }else{
        $sku_codes .= $index."','";
        $error_msg = $count .'> Error:: Product with Sku (' . $index . ') does\'t exist.'."\r\n";
        process_log_inventory($logPath, $error_msg);        
    }
    $count++;
}
$sku_codes = $sku_codes."'";

$pmpObj->updateSyncInventory($sku_codes);

/***************** UTILITY FUNCTIONS ********************/
function _getConnection($type = 'core_read'){
    return Mage::getSingleton('core/resource')->getConnection($type);
}

function _getTableName($tableName){
    return Mage::getSingleton('core/resource')->getTableName($tableName);
}

function _getEntityTypeId($entity_type_code = 'catalog_product'){
    $connection = _getConnection('core_read');
    $sql        = "SELECT entity_type_id FROM " . _getTableName('eav_entity_type') . " WHERE entity_type_code = ?";
    return $connection->fetchOne($sql, array($entity_type_code));
}

function _checkIfSkuExists($sku){
    $connection = _getConnection('core_read');
    $sql        = "SELECT COUNT(*) AS count_no FROM " . _getTableName('catalog_product_entity') . " WHERE sku = ?";
    $count      = $connection->fetchOne($sql, array($sku));
    if($count > 0){
        return true;
    }else{
        return false;
    }
}

function _getIdFromSku($sku){
    $connection = _getConnection('core_read');
    $sql        = "SELECT entity_id FROM " . _getTableName('catalog_product_entity') . " WHERE sku = ?";
    return $connection->fetchOne($sql, array($sku));
}

function _resetStocks($vendorSkuString){
    $connection     = _getConnection('core_write');

    echo "start the first step...\n";
    $sql            = "UPDATE " . _getTableName('cataloginventory_stock_item') . " SET qty = 0, is_in_stock = 0 WHERE product_id in ".$vendorSkuString;
    $connection->query($sql);

    echo "start the second step...\n";
    $sql            = "UPDATE " . _getTableName('cataloginventory_stock_status') . " SET qty = 0, stock_status = 0 WHERE product_id in ".$vendorSkuString;
    $connection->query($sql);
}

function _updateStocks($index,$data){
    $connection     = _getConnection('core_write');
    $sku            = $index;
    $newQty         = $data;
    $productId      = _getIdFromSku($sku);

    $sql            = "UPDATE " . _getTableName('cataloginventory_stock_item') . " csi,
                       " . _getTableName('cataloginventory_stock_status') . " css
                       SET
                       csi.qty = ?,
                       csi.is_in_stock = ?,
                       css.qty = ?,
                       css.stock_status = ?
                       WHERE
                       csi.product_id = ?
                       AND csi.product_id = css.product_id";

    $isInStock      = $newQty > 0 ? 1 : 0;
    $stockStatus    = $newQty > 0 ? 1 : 0;

    $connection->query($sql, array($newQty, $isInStock, $newQty, $stockStatus, $productId));
}

//Tell Other scripts that this script is successfully complete so the next one can run
rename($procfile, $procfilecomp);

process_log_inventory($logPath,"*** end process ***");
function process_log_inventory($logPath,$logContent){
    echo $logContent,"\n";
    file_put_contents($logPath,$logContent."\n", FILE_APPEND);    
}
?>