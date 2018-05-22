<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH . '/../../../app/Mage.php');
$baseURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
$pmpURL = str_replace("http://www.","http://pmp2.",$baseURL);

$filePath = BASE_PATH.'/../../../media/vehicle';
$files = scandir($filePath);
$files = array_diff($files, array('.', '..'));

$logPath = BASE_PATH.'/../../../var/log/sync_vehicle';

if (!file_exists($logPath)) {
    mkdir($logPath, 0777, true);
}

$date = new DateTime();
$logName = "syncVehicleApp_".$date->format("Y_m_d");
$logName = $logPath."/".$logName;
file_put_contents($logName,"Script got called by crontab\n",FILE_APPEND);

$processHandler = $logPath.'/loadVehicleProcess.log';
processCheck($processHandler);

$content = "Total processes in queue\n";
echo $content;file_put_contents($logName,$content,FILE_APPEND);

$content = implode("\n",$files)."\n";
echo $content;file_put_contents($logName,$content,FILE_APPEND);

$content = "===============================\n";
echo $content;file_put_contents($logName,$content,FILE_APPEND);

$index = 0;
foreach($files as $file){
    $content = "Start syncing ".$file."\n";
    echo $content;file_put_contents($logName,$content,FILE_APPEND);

    $full = $filePath."/".$file;

    $data = explode('_', $file);
    $id = $data[1];

    if($file == "." || $file == ".."){
        continue;
    }
    $zip = new ZipArchive;

    if ($zip->open($full) === TRUE) {
        $zip->extractTo(BASE_PATH.'/../../../../vamap');
        $zip->close();
    } else {
        file_put_contents($logPath,"unzip failed\n",FILE_APPEND);
    }

    $content = "End syncing ".$file."\n";
    echo $content;file_put_contents($logName,$content,FILE_APPEND);

    $content = "update sync_vehicle table in pmp ".$file."\n";
    echo $content;file_put_contents($logName,$content,FILE_APPEND);
    updateSyncVehicle($pmpURL,$id,'done','No errors found',$logName);

    unlink($full);
}

unlink($processHandler);

function updateSyncVehicle($pmpURL,$id,$status,$msg,$logName){
    $updateURL = $pmpURL."/api/updateSyncVehicle/".$id."/".$status."/".$msg;
    file_put_contents($logName,$updateURL."\n",FILE_APPEND);

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $updateURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    var_dump($response);
}

function processCheck($processHandler){
    if(!file_exists($processHandler)){
        file_put_contents($processHandler,time(), FILE_APPEND);
    }
    else{
        echo "there is a process running...";
        exit;
    }
}