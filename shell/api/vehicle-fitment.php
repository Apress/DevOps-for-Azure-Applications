<?php

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH . '/../../app/Mage.php');

$sku = $_GET['sku'];
$brandCode = $result = substr($sku, 0, 3);
$encodeSku = str_replace("%","@",urlencode($sku));

Mage::app("default")->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

$baseDir =  Mage::getBaseDir();
$baseDir = substr($baseDir, 0, strrpos($baseDir, "/"));
$file = $baseDir.'/vamap/9999/'.$brandCode."/".$encodeSku;

$fp=fopen($file,'r');
if($fp){
    while($line=fgets($fp)){
        $line = chop($line);
        if(!$line) continue;
        $temp = explode('=',$line,2);

        $vehicle_string[] = $temp[1];
    }
    fclose($fp);

    sort($vehicle_string, SORT_STRING);

    $length = sizeof($vehicle_string);

    $half_index = (($length % 2) == 1) ? floor($length / 2) + 1 : floor($length / 2);

    foreach($vehicle_string as $index => $str)
    {
        if($index == 0) echo '<div style="float: left; width: 368px;">';

        echo '<div style="margin-bottom:10px;">'. $str . '</div>';

        if($index + 1 == $half_index) echo '</div><div style="float: left; width: 368px;">';

        if($index == $length -1) echo '</div>';
    }
}
else{

    $read = Mage::getSingleton('core/resource')->getConnection('core_read');
    $sql   = "SELECT sku FROM mage_universal_feature WHERE sku ='".$sku."'";
    $result = $read->fetchAll($sql);

    $contactUrl = '<a href="/contact">customer service</a>';

    if($result){
        echo "This part fits virtually all vehicles. If you are unsure, please contact ".$contactUrl." to confirm the fitment.";
    }
    else{
        echo "This part does not have any fitments. Please contact ".$contactUrl." for more information on fitment for your specific vehicle.";
    }
}

