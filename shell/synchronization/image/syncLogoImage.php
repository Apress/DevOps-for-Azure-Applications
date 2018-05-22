<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH."/../../../app/etc/cfg/config.php");
require_once(BASE_PATH."/../../../app/Mage.php");
require_once(BASE_PATH."/../lib/pmpModel.php");

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$date = new DateTime();
$logFolder = BASE_PATH."/../../../var/log/syncLogoImage/";
$logPath = $logFolder.$date->format("Y_m_d").".log";
$procfile = BASE_PATH.'/../pcheck/logoimageprocnew.txt';
$procfilecomp = BASE_PATH.'/../pcheck/logoimageproccomp.txt';

if(!file_exists($logFolder)){mkdir($logFolder);}

$client_info_path = BASE_PATH."/../../../app/etc/cfg/client_info.conf";
$client_info = file_get_contents($client_info_path);
$client_info_array = explode(";", $client_info);

$user_id = explode("=", $client_info_array [0])[1];
$pmpURL = explode("=", $client_info_array [1])[1];

$pmpObj = new PmpObject($logPath,$pmpURL,$user_id);

process_log_logo_image($logPath,"*** Start process ".$date->format("Y_m_d_h_i_s")."***");

$log_path = $pmpObj->getChangedImage('user');
$log_path = json_decode($log_path,true);

//converting extension to .jpg
$log_path = substr($log_path,0,strlen($log_path)-4).'.jpg';

process_log_logo_image($logPath, "key => ".$log_path);
//save by option
$connection = _getConnection_logo('core_write');
$error = "";
// Save Detailed Manufacturer Info to Magento Manufacturer Extension
try{
    Mage::getConfig()->saveConfig('design/header/logo_src', $log_path);
    Mage::getConfig()->reinit(); 

    $logo_src =   Mage::getStoreConfig('design/header/logo_src', 0);
    echo $logo_src;
}catch(Exception $e){
    $error = $e->getMessage();
    process_log_logo_image($logPath, $e->getMessage());
}

$pmpObj->updateSyncImage($error, 'store_logo');

function _getConnection_logo($type = 'core_read'){
    return Mage::getSingleton('core/resource')->getConnection($type);
}

//Tell Other scripts that this script is successfully complete so the next one can run
rename($procfile, $procfilecomp);

process_log_logo_image($logPath,"*** end process ***");
function process_log_logo_image($logPath,$logContent){
    echo $logContent,"\n";
    file_put_contents($logPath,$logContent."\n", FILE_APPEND);    
}
?>