<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH . '/../../app/Mage.php');
$baseURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
$pmpURL = str_replace("http://www.","http://pmp.",$baseURL);

$filePath = BASE_PATH.'/../../media/vehicle';
$files = scandir($filePath);
$files = array_diff($files, array('.', '..'));

$logPath = BASE_PATH.'/../../var/log/va_sync';

if (!file_exists($logPath)) {
    mkdir($logPath, 0777, true);
}

$processHandler = $logPath.'/syncVehicleAppProcess.log';

$reset = $_GET['reset'];
if($reset == 1){
    unlink($processHandler);
    echo "process unlocked";
    exit;
}

processCheck($processHandler);

file_put_contents($logPath.'/syncVehicleApp.log',"Total processes in queue\n");
file_put_contents($logPath.'/syncVehicleApp.log',implode("\n",$files)."\n",FILE_APPEND);
file_put_contents($logPath.'/syncVehicleApp.log',"===============================\n",FILE_APPEND);

$index = 0;
foreach($files as $file){
    file_put_contents($logPath.'/syncVehicleApp.log',"Start syncing ".$file."\n",FILE_APPEND);
    $full = $filePath."/".$file;

    if($file == "." || $file == ".."){
        continue;
    }
    $fp = fopen ($full, 'r');
    if ($fp) {
        flock ($fp, LOCK_SH);
        while ($line = fgets ($fp)) {
            $fileContent = callFromPMP($pmpURL,$line);
            if($fileContent){
                saveToVamp($line,$fileContent);
                file_put_contents($logPath.'/'.$file,$line,FILE_APPEND);
            }else{
                file_put_contents($logPath.'/'.$file,$line.' ---> error',FILE_APPEND);
            }
        }
        flock ($fp, LOCK_UN);
        fclose ($fp);
    }

    file_put_contents($logPath.'/syncVehicleApp.log',"End syncing ".$file."\n",FILE_APPEND);
    unlink($full);
}

unlink($processHandler);

function callFromPMP($pmpURL,$filePath){
    $targetURL = $pmpURL.'api/vehicle/downloadVehicleApp?type=map&file='.$filePath;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $targetURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    if(curl_errno($ch)){
        $output = '';
    }
    curl_close($ch);
    return $output;
}

function saveToVamp($line,$fileContent){
    $line = preg_replace('/\s+/', ' ', trim($line));
    $filePath = BASE_PATH.'/../../../vamap/'.$line;
    $folderPath = substr($filePath, 0, strrpos($filePath, "/"));

    if (!file_exists($folderPath)) {
        mkdir($folderPath, 0777, true);
    }
    file_put_contents($filePath,$fileContent);
}

function processCheck($processHandler){
    if(!file_exists($processHandler)){
        file_put_contents($processHandler,time(), FILE_APPEND);
    }
    else{
        exit;
//        $logFile = fopen($processHandler, 'r');
//        $content = fgets($logFile);
//
//        fclose($logFile);
//
//        $hr = ((time()-$content)/3600);
//
//        //remove process log if process has ran over 2 hours
//        if($hr > 36){
//            unlink($processHandler);
//            file_put_contents($processHandler,time(), FILE_APPEND);
//        }
//        else{
//            exit;
//        }
    }
}