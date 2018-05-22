<?php

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));		//Magento Root Directory

require_once(BASE_PATH.'/../../app/etc/cfg/config.php');

error_reporting(E_ALL | E_STRICT);
ini_set('memory_limit', '512M');
require_once(BASE_PATH.'/../../app/Mage.php');
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$sync_folder = BASE_PATH.'/../../var/log/inventory_sync/';
if(!file_exists($sync_folder)){
    mkdir($sync_folder);
}

$vendor = $_GET['code'];
$reset = $_GET['reset'];

//1:processing 0:not processing
//this switch should be at the bottom of the file
$processLog = $sync_folder.$vendor;

/*
 * /shell/deployment/Deploy2Mage_Inventory.php?code=PAT&reset=1
*/
if($reset == 1){
    file_put_contents($processLog,'0');
    exit;
}

//check process, 0 run 1 don't
$fp = fopen ($processLog, 'r');
if ($fp) {
    flock ($fp, LOCK_SH);
    while ($line = fgets ($fp)) {
        if(substr($line, 0, 1) == 1){
            exit;
        }
    }
    flock ($fp, LOCK_UN);
    fclose ($fp);
}
file_put_contents($processLog,'1');

$vendors = array('MTR','TRA','PAT','KEY','HYT','FMP');

if(!in_array($vendor,$vendors)){
    exit;
}

$log_name = $sync_folder."sync_".$vendor."_inventory.log";
unlink($log_name);

$start = microtime(true);

echo "========start process=========\n";
file_put_contents($log_name,"======== start process =========\n", FILE_APPEND);

//reset qty of a given vendor
//file_put_contents($log_name,"-------- start reset qty -----------\n", FILE_APPEND);
//getVendorSkuListAndReset($vendor);

echo "Start downloading ".$vendor." inventory data\n";
$data = getInventoryFromPmp($vendor);

$message = '';
$count   = 1;
$zeroSku = array();
foreach($data as $index =>$_data){
    if(_checkIfSkuExists($index)){
        try{
            if($_data > 0){
                _updateStocks($index,$_data);
            }else{
                $zeroSku[] = $index;
            }

            if($count % 5000 == 0){
                _setStocksZero($zeroSku);
                $zeroSku = array();
            }
            file_put_contents($log_name,$index.",".$_data."\n", FILE_APPEND);

        }catch(Exception $e){
            file_put_contents($log_name,$count .'> Error:: while Upating  Qty (' . $_data . ') of Sku (' . $index . ') => '.$e->getMessage()."\r\n", FILE_APPEND);
        }
    }else{
        file_put_contents($log_name,$count .'> Error:: Product with Sku (' . $index . ') does\'t exist.'."\r\n", FILE_APPEND);
    }
    $count++;
}

_setStocksZero($zeroSku);

$end = microtime(true);
$time = (($end - $start)/60);

file_put_contents($log_name,"======== end process =========\n", FILE_APPEND);
file_put_contents($log_name,"======== total process time => ".$time." =========\n", FILE_APPEND);

file_put_contents($processLog,'0');

/***************** UTILITY FUNCTIONS ********************/
function getVendorSkuListAndReset($vendor){
    $dbCore = new mysqli(CORE_DB_HOST, CORE_DB_USER, CORE_DB_PASSWORD, CORE_DB_DATABASE);
    $sql = "SELECT sku FROM vendor_inventory WHERE vendor_code = '".$vendor."'";

    $result = $dbCore->query($sql);

    $vendorSkuString = "(";
    $index = 0;
    while($item = $result->fetch_assoc()){
        $productId = _getIdFromSku($item["sku"]);
        $vendorSkuString = $vendorSkuString.$productId.",";

        if($index == 5000){
            $vendorSkuString = substr($vendorSkuString, 0, -1).")";
            _resetStocks($vendorSkuString);
            unset($vendorSku);
            $vendorSku = array();
            $index = 0;
        }
        $index++;
    }
}

function getInventoryFromPmp($vendor){
    $url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
    $url = str_replace("http://www.","http://pmp.",$url);
    $url = $url.'downloads/sync/Inventory_'.$vendor.'_sync.txt';

    $save_path = './Inventory_'.$vendor.'_sync.txt';

    unlink($save_path);
    $fp = fopen($save_path, 'w');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    $data = curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    $vendorArray = unserialize(file_get_contents($save_path));
    return $vendorArray;
}

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
                       csi.is_in_stock = 1,
                       css.qty = ?,
                       css.stock_status = 1
                       WHERE
                       csi.product_id = ?
                       AND csi.product_id = css.product_id";

    $connection->query($sql, array($newQty, $newQty, $productId));
}

function _setStocksZero($zeroSku){
    $zeroString = "('".implode("','",$zeroSku)."')";
    $connection     = _getConnection('core_write');

    $sql            = sprintf("UPDATE " . _getTableName('cataloginventory_stock_item') . " csi,
                       " . _getTableName('cataloginventory_stock_status') . " css
                       SET
                       csi.qty = 0,
                       csi.is_in_stock = 0,
                       css.qty = 0,
                       css.stock_status = 0
                       WHERE
                       csi.product_id in %s AND csi.product_id = css.product_id;",$zeroString);

    $connection->query($sql);
}