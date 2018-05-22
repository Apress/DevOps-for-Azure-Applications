<?php
ignore_user_abort (true);
set_time_limit(0);

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));

require_once(BASE_PATH.'/../../../app/Mage.php');
$baseURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
$pmpURL = str_replace("http://www.","http://pmp2.",$baseURL);

$imageList = BASE_PATH.'/../../../media/imageList';
if (!file_exists($imageList)) {
    mkdir($imageList, 0777, true);
}

$files = scandir($imageList);
$files = array_diff($files, array('.', '..'));

$logPath = BASE_PATH.'/../../../var/log/sync_image';

if (!file_exists($logPath)) {
    mkdir($logPath, 0777, true);
}

$processHandler = $logPath.'/loadImgProcess.log';

$reset = $_GET['reset'];
if($reset == 1){
    unlink($processHandler);
    exit;
}

processCheck($processHandler);

$time = date('Y_m_d');
$logFileName = $logPath.'/load_image_'.$time.'.log';

file_put_contents($logFileName,"Total processes in queue\n",FILE_APPEND);
file_put_contents($logFileName,implode("\n",$files)."\n",FILE_APPEND);
file_put_contents($logFileName,"===============================\n",FILE_APPEND);

$index = 0;
foreach($files as $file){
    $brand_code = substr($file, 0, 3);
    file_put_contents($logFileName,"Start syncing ".$file."\n",FILE_APPEND);
    $full = $imageList."/".$file;

    if($file == "." || $file == ".."){
        continue;
    }
    $fp = fopen ($full, 'r');
    if ($fp) {
        flock ($fp, LOCK_SH);
        while ($line = fgets ($fp)){
            $info = preg_split('/\s+/', $line);
            pullImage($brand_code,$info,$pmpURL,$logFileName);
        }
        flock ($fp, LOCK_UN);
        fclose ($fp);
    }

    file_put_contents($logFileName,"End syncing ".$file."\n",FILE_APPEND);
    unlink($full);
}

unlink($processHandler);

function pullImage($brand_code,$info,$pmpURL,$logFileName){
    $file_name = $info[2];
    $downloadUrl = $pmpURL."image_server/get_sku_image/".$brand_code."/".$file_name."/";
//    $downloadUrl = "http://10.1.168.131/media/".$brand_code."/".$file_name;

    $saveFolderPath = BASE_PATH.'/../../../media/import/'.$brand_code."/";
    $saveFilePath = $saveFolderPath.$file_name;

    if (!file_exists($saveFolderPath)) {
        mkdir($saveFolderPath, 0777, true);
    }

    $ch = curl_init ($downloadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $image = curl_exec($ch);
    curl_close($ch);
//    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//    if($code == 404){
//        file_put_contents($logFileName,$brand_code."/".$file_name." not found\n",FILE_APPEND);
//    }
    file_put_contents($saveFilePath,$image);
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