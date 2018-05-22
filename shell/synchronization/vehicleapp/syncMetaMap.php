<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH . '/../../../app/Mage.php');
$baseURL = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
$pmpURL = str_replace("http://www.","http://pmp.",$baseURL);

$filePath = BASE_PATH.'/../../../../media/mpmeta';
$files = scandir($filePath);
$files = array_diff($files, array('.', '..'));

foreach($files as $file){
    $full = $filePath."/".$file;
    if($file != "." || $file != ".."){

        $fp = fopen ($full, 'r');
        if ($fp) {
            flock ($fp, LOCK_SH);
            while ($line = fgets ($fp)) {
                $fileContent = downloadFromPMP($pmpURL,$line);
                saveToVamp($line,$fileContent);
            }
            flock ($fp, LOCK_UN);
            fclose ($fp);
        }
    }
    unlink($full);
}

function downloadFromPMP($pmpURL,$filePath){
    $targetURL = $pmpURL.'api/vehicle/downloadVehicleApp?type=map&file='.$filePath;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $targetURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

function saveToVamp($file,$fileContent){
    $file = preg_replace('/\s+/', ' ', trim($file));
    $filePath = BASE_PATH.'/../../../../vamap/'.$file;
    echo $file."\n";
    $folderPath = substr($filePath, 0, strrpos($filePath, "/"));

    if (!file_exists($folderPath)) {
        mkdir($folderPath, 0777, true);
    }
    file_put_contents($filePath,$fileContent);
}