<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH.'/../../../app/Mage.php');
Mage::app('default');
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

$logPath = BASE_PATH.'/../../../var/log/sync_vehicle';

if (!file_exists($logPath)) {
    mkdir($logPath, 0777, true);
}

$date = new DateTime();
$logName = "syncUniversal".$date->format("Y_m_d");
$logName = $logPath."/".$logName;

$content = "Start Universal Sync Process...\n";
echo $content;file_put_contents($logName,$content,FILE_APPEND);

$baseURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);

$pmpURL = str_replace("http://www.","http://pmp2.",$baseURL);

$brandCodeArray = getUniversalSyncList($pmpURL);

$brandCodeArray = array_unique($brandCodeArray);

if(count($brandCodeArray) > 0){

    foreach($brandCodeArray as $brandCode){

        $content = "Syncing brand => ".$brandCode."\n";
        echo $content;file_put_contents($logName,$content,FILE_APPEND);

        $content = "Removing magento old records\n";
        echo $content;file_put_contents($logName,$content,FILE_APPEND);

        getUniversalMap($pmpURL,$brandCode);

        deleteUniversalFeature($brandCode);

        $totalCount = getUniversalCount($pmpURL,$brandCode);

        $increment = 500;

        $content = "Updating universal features\n";
        echo $content;file_put_contents($logName,$content,FILE_APPEND);

        for($i = 0;$i <= $totalCount;$i += $increment){

            getUniversalFeature($pmpURL,$brandCode,$increment,$i);

        }

        $content = "Updating pmp2 status\n";

        echo $content;file_put_contents($logName,$content,FILE_APPEND);

        updateSyncUniversal($pmpURL,$brandCode."_universal",'done','no_error_found');
    }
}

/*
 * Call PMP2 APIs
 */

function getUniversalSyncList($pmpURL){
    $targetURL = $pmpURL.'api/getUniversalSyncList';

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $targetURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $listString = curl_exec($ch);
    curl_close($ch);
    $brandCodeArray = explode(',',$listString);
    return $brandCodeArray;
}

function getUniversalMap($pmpURL,$brandCode){
    $targetURL = $pmpURL.'api/getUniversalMap/'.$brandCode;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $targetURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $insertString = curl_exec($ch);
    curl_close($ch);

    $insertString = json_decode($insertString);

    if($insertString){
        updateMageUniversalMap($insertString);
    }
}

function getUniversalCount($pmpURL,$brandCode){
    $targetURL = $pmpURL.'api/getUniversalCount/'.$brandCode;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $targetURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $count = curl_exec($ch);
    curl_close($ch);
    $count = json_decode($count);
    return $count;
}

function getUniversalFeature($pmpURL,$brandCode,$increment,$i){
    $targetURL = $pmpURL.'api/getUniversalFeature/'.$brandCode."/".$increment."/".$i;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $targetURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $insertString = curl_exec($ch);
    curl_close($ch);
    $insertString = json_decode($insertString);

    if($insertString){
        updateMageUniversalFeature($insertString);
    }
}

function updateSyncUniversal($pmpURL,$key,$status,$msg){
    $updateURL = $pmpURL."/api/updateSyncUniversal/".$key."/".$status."/".$msg;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $updateURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    var_dump($response);
}

/*
 * Magento models
 */
function updateMageUniversalMap($insertString){

    $write = Mage::getSingleton('core/resource')->getConnection('core_write');
    $sql   = "TRUNCATE mage_universal_map";
    $write->query($sql);
    $sql   = "INSERT INTO mage_universal_map (product_code) VALUES ".$insertString;

    $write->query($sql);
}

function deleteUniversalFeature($brandCode){
    $write = Mage::getSingleton('core/resource')->getConnection('core_write');
    $sql   = "DELETE FROM mage_universal_feature WHERE sku like '".$brandCode.":%'";

    $write->query($sql);
}

function updateMageUniversalFeature($insertString){
    $write = Mage::getSingleton('core/resource')->getConnection('core_write');
    $sql   = "INSERT INTO mage_universal_feature (`sku`,`product_code`,`feature`) VALUES ".$insertString;
    $write->query($sql);
}
