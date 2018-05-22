<?php
$isCli = php_sapi_name();

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));		//Magento Root Directory
DEFINE('LOG',$isCli);

if($isCli != 'cli'){
    switch ($_SERVER['HTTP_ORIGIN']) {
        case 'http://'.$_POST['backURL']: case 'https://'.$_POST['backURL']:
        header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
        header('Access-Control-Max-Age: 1000');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        break;
    }
}

require_once(BASE_PATH.'/../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../app/Mage.php');

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$date = new DateTime();
$logName = $date->format("Ymd_his");

$logFolder = BASE_PATH.'/../../var/log/custom_option_sync/';
$logPath = $logFolder.$logName.".log";
if(!file_exists($logFolder)){
    mkdir($logFolder);
}

$logContent = "========start process=========\n";
if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

create_custom_option_table($logPath);
$custom_options = fetch_from_pmp($logPath);
save_to_mage($logPath,$custom_options);

$logContent = "========end process=========\n";
if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}


function create_custom_option_table($logPath){
    $logContent = "Create mage_custom_option table if it doesn't exit\n";
    if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

    $write = Mage::getSingleton('core/resource')->getConnection('core_write');
    $write->query("CREATE TABLE IF NOT EXISTS `mage_custom_option` (
      `name` varchar(255) NOT NULL,
      `value` varchar(255) NOT NULL,
      PRIMARY KEY (`name`)
    )ENGINE=InnoDB DEFAULT CHARSET=utf8;");
}
function fetch_from_pmp($logPath){
    $custom_options = array();
    $dbCore = new mysqli(CORE_DB_HOST, CORE_DB_USER, CORE_DB_PASSWORD, CORE_DB_DATABASE);

    $sql = "SELECT * FROM custom_options";
    $result = $dbCore->query($sql);

    while($values = $result->fetch_object())
    {
        $custom_options[$values->name] = $values->value;
        $logContent = $values->name."=>".$values->value."\n";
        if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}
    }
    return $custom_options;
}
function save_to_mage($logPath,$custom_options){
    $write = Mage::getSingleton('core/resource')->getConnection('core_write');
    foreach($custom_options as $index => $option){
        $sql = sprintf("INSERT INTO mage_custom_option (name,value) VALUES (\"%s\",\"%s\") ON DUPLICATE KEY UPDATE name = VALUES(name)",$index,$option);
        $write->query($sql);
    }

}
