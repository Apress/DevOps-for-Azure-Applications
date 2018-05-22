<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH."/../../../app/etc/cfg/config.php");
require_once(BASE_PATH."/../../../app/Mage.php");
require_once(BASE_PATH."/../lib/pmpModel.php");

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$date = new DateTime();
$logFolder = BASE_PATH."/../../../var/log/syncBrandImage/";
$logPath = $logFolder.$date->format("Y_m_d").".log";
$procfile = BASE_PATH.'/../pcheck/brandimageprocnew.txt';
$procfilecomp = BASE_PATH.'/../pcheck/brandimageproccomp.txt';

if(!file_exists($logFolder)){mkdir($logFolder);}

$client_info_path = BASE_PATH."/../../../app/etc/cfg/client_info.conf";
$client_info = file_get_contents($client_info_path);
$client_info_array = explode(";", $client_info);

$user_id = explode("=", $client_info_array [0])[1];
$pmpURL = explode("=", $client_info_array [1])[1];

$pmpObj = new PmpObject($logPath,$pmpURL,$user_id);
process_log_brand_image($logPath,"*** Start process ".$date->format("Y_m_d_h_i_s")."***");

$changedItems = $pmpObj->getChangedImage('brandimage');
$changedItems = json_decode($changedItems,true);

$mage_ids = [];
$connection = _getConnection_brand_image('core_write');
foreach ($changedItems as $index => $path_array){ 
    process_log_brand_image($logPath, "key => ".$index." value = ".$path_array[0]);
    $ids = explode("-",$index);
    $value_id = $ids[0];
    $option_id = $ids[1];

    //save by option
    $connection = _getConnection_brand_image('core_write');

    //converting extension to .jpg
    $jpg_image_name = substr($path_array[0],0,strlen($path_array[0])-4).'.jpg';    

    // Save Detailed Manufacturer Info to Magento Manufacturer Extension
    $query = "UPDATE mage_am_shopby_value SET 
        img_small = '$jpg_image_name', 
        img_medium = '$jpg_image_name', 
        img_big = '$jpg_image_name' WHERE value_id = $value_id;";

    try{
        $mage_ids[$index] = "";
        $connection->query($query);
    }catch(Exception $e){
        $mage_ids[$index] = $e->getMessage();
        process_log_brand_image($logPath, $e->getMessage());
    }
}

$pmpObj->updateSyncImage($mage_ids, 'brand_image');

function _getConnection_brand_image($type = 'core_read'){
    return Mage::getSingleton('core/resource')->getConnection($type);
}

//Tell Other scripts that this script is successfully complete so the next one can run
rename($procfile, $procfilecomp);

process_log_brand_image($logPath,"*** end process ***");
function process_log_brand_image($logPath,$logContent){
    echo $logContent,"\n";
    file_put_contents($logPath,$logContent."\n", FILE_APPEND);    
}
?>

