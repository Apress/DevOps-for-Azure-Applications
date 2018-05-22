<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));

require_once(BASE_PATH."/../../../app/etc/cfg/config.php");
require_once(BASE_PATH."/../../../app/Mage.php");
require_once(BASE_PATH."/../lib/pmpModel.php");

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$date = new DateTime();
$logFolder = BASE_PATH."/../../../var/log/syncUniversal/";
$logPath = $logFolder.$date->format("Y_m_d").".log";
$procfile = BASE_PATH.'/../pcheck/universalprocnew.txt';
$procfilecomp = BASE_PATH.'/../pcheck/universalproccomp.txt';

if(!file_exists($logFolder)){mkdir($logFolder);}

$client_info_path = BASE_PATH."/../../../app/etc/cfg/client_info.conf";
$client_info = file_get_contents($client_info_path);
$client_info_array = explode(";", $client_info);

$user_id = explode("=", $client_info_array [0])[1];
$pmpURL = explode("=", $client_info_array [1])[1];

/*Sync Universal Map*/
process_log_universal($logPath,"*** Start process ".$date->format("Y_m_d_h_i_s")."***");

$pmpObj = new PmpObject($logPath,$pmpURL,$user_id);
$read_connection = Mage::getSingleton('core/resource')->getConnection('core_read');
$write_connection = Mage::getSingleton('core/resource')->getConnection('core_write');

$changedMapItems = $pmpObj->getChangedUniversal('productuniversal');
$changedMapItems = json_decode($changedMapItems,true);

$keys = [];

foreach ($changedMapItems['is_universal'] as $product_code){
    process_log_universal($logPath, "*** universal products ***");
    process_log_universal($logPath, "key => ".$product_code);
    try{        
        $sql = "SELECT count(*) FROM mage_universal_map WHERE product_code = '$product_code';";
        $count = $read_connection->fetchOne($sql);        
        if($count == 0){
            $sql = "INSERT INTO mage_universal_map (product_code) VALUES ('$product_code');";
            $write_connection->query($sql);              
        }                
        $keys[$product_code] = "";
    }catch(Exception $e){
        $keys[$product_code] = $e->getMessage();
        echo $e->getMessage();
    } 
}

/* Delete not universal */
foreach ($changedMapItems['not_universal'] as $product_code){
    process_log_universal($logPath, "*** remove from universal products ***");
    process_log_universal($logPath, "key => ".$product_code);
    try{
        $sql = "DELETE FROM mage_universal_map WHERE product_code = '$product_code';";        
        $write_connection->query($sql);  
        $keys[$product_code] = "";
    }catch(Exception $e){
        $keys[$product_code] = $e->getMessage();
        process_log_universal($logPath, $e->getMessage());
    } 
}

$pmpObj->updateSyncUniversal($keys, 'product_universal');

$keys = [];
/* Sync Universal Feature*/
$changedFeatureItems = $pmpObj->getChangedUniversal('skuuniversal');
$changedFeatureItems = json_decode($changedFeatureItems,true);

foreach ($changedFeatureItems['is_universal'] as $sku => $feature_array){
    $product_code = $feature_array['product_code'];
    $feature = $feature_array['feature'];

    process_log_universal($logPath, "*** universal products features***");
    process_log_universal($logPath, "key => ".$product_code." feature =>".$feature);
    try{        
        $sql = "SELECT count(*) FROM mage_universal_feature 
        WHERE sku = '$sku';";
        $count = $read_connection->fetchOne($sql);        
        if($count == 0){
            $sql = "INSERT INTO mage_universal_feature (sku,product_code,feature) 
            VALUES ('$sku','$product_code','$feature');";
            $write_connection->query($sql);              
        }else{
            $sql = "UPDATE mage_universal_feature 
            SET product_code = '$product_code',
            feature = '$feature'
            WHERE sku = '$sku'
            ;";
            $write_connection->query($sql);              
        }              
        $keys[$sku] = "";
    }catch(Exception $e){
        $keys[$sku] = $e->getMessage();
        process_log_universal($logPath, $e->getMessage());
    } 
}


/* Delete not universal */
foreach ($changedFeatureItems['not_universal'] as $sku => $feature_array){
    process_log_universal($logPath, "*** remove from universal products features***");
    process_log_universal($logPath, "key => ".$product_code." feature =>".$feature);
    try{
        $sql = "DELETE FROM mage_universal_feature WHERE sku = '$sku';";        
        $write_connection->query($sql);  
        $keys[$sku] = "";
    }catch(Exception $e){
        $keys[$sku] = $e->getMessage();
        process_log_universal($logPath, $e->getMessage());
    } 
}

$pmpObj->updateSyncUniversal($keys, 'sku_universal');

//Tell Other scripts that this script is successfully complete so the next one can run
rename($procfile, $procfilecomp);

process_log_universal($logPath,"*** end process ***");
function process_log_universal($logPath,$logContent){
    echo $logContent,"\n";
    file_put_contents($logPath,$logContent."\n", FILE_APPEND);    
}
?>