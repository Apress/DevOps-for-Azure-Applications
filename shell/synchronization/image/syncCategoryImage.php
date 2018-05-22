<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH."/../../../app/etc/cfg/config.php");
require_once(BASE_PATH."/../../../app/Mage.php");
require_once(BASE_PATH."/../lib/pmpModel.php");

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$date = new DateTime();
$logFolder = BASE_PATH."/../../../var/log/syncCategoryImage/";
$logPath = $logFolder.$date->format("Y_m_d").".log";
$procfile = BASE_PATH.'/../pcheck/categoryimageprocnew.txt';
$procfilecomp = BASE_PATH.'/../pcheck/categoryimageproccomp.txt';

if(!file_exists($logFolder)){mkdir($logFolder);}

$client_info_path = BASE_PATH."/../../../app/etc/cfg/client_info.conf";
$client_info = file_get_contents($client_info_path);
$client_info_array = explode(";", $client_info);

$user_id = explode("=", $client_info_array [0])[1];
$pmpURL = explode("=", $client_info_array [1])[1];

$pmpObj = new PmpObject($logPath,$pmpURL,$user_id);

process_log_category_image($logPath,"*** Start process ".$date->format("Y_m_d_h_i_s")."***");

$changedItems = $pmpObj->getChangedImage('categoryimage');
$changedItems = json_decode($changedItems,true);
var_dump($changedItems);
$mage_ids = [];

foreach ($changedItems as $index => $path_array){
    
    //converting extension to .jpg
    $data['image'] = substr($path_array[0],0,strlen($path_array[0])-4).'.jpg';
    //$data['image'] = "test_image.jpg";    

    process_log_category_image($logPath, "key => ".$index." value = ".$path_array[0]);
    try{
        // $index = 9;
        $category = Mage::getModel('catalog/category')->load($index);
        $mage_ids[$index] = "";
        $category->addData($data);

        $category->save();
        $mage_ids[$index] = "";

        echo "new image path = ".$category->getImageUrl()."\n</br>";
    }catch(Exception $e){
        $mage_ids[$index] = $e->getMessage();
        process_log_category_image($logPath, $e->getMessage());
    }
}

$pmpObj->updateSyncImage($mage_ids,'category_image');

function _getConnection_category_image($type = 'core_read'){
    return Mage::getSingleton('core/resource')->getConnection($type);
}

//Tell Other scripts that this script is successfully complete so the next one can run
rename($procfile, $procfilecomp);

process_log_category_image($logPath,"*** end process ***");
function process_log_category_image($logPath,$logContent){
    echo $logContent,"\n";
    file_put_contents($logPath,$logContent."\n", FILE_APPEND);    
}
?>