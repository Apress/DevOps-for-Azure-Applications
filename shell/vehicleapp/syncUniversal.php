<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH . '/../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../app/Mage.php');
Mage::app('default');
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
$brandCode = $_GET['code'];

$pmpConnection = new mysqli(CORE_DB_HOST, CORE_DB_USER, CORE_DB_PASSWORD, CORE_DB_DATABASE);
$insertString = getUniversalMapFromPMP($pmpConnection);
updateMageUniversalMap($insertString);

getUniversalFeatureFromPMP($pmpConnection,$brandCode);

//generateCSVFromPMP($pmpConnection);

$pmpConnection->close();

function getUniversalMapFromPMP($pmpConnection){

    $result = $pmpConnection->query('SELECT product_code FROM product WHERE is_universal = 1');

    $insertString = "(";
    while($row = $result->fetch_assoc()){
        $insertString = $insertString."'".$row['product_code']."'),(";
    }
    $insertString = substr($insertString, 0, -3).")";

    return $insertString;
}

function getUniversalFeatureFromPMP($pmpConnection,$brandCode){
    $sql = "SELECT sku,product_code, universal_feature FROM item WHERE is_universal = 1 AND brand_code = '".$brandCode."'";
    $result = $pmpConnection->query($sql);

    deleteUniversalFeature($brandCode);

    $index = 0;
    $insertString = "(";
    while($row = $result->fetch_assoc()){
        $cleanFeature = str_replace("'","\'",$row['universal_feature']);
        $insertString = $insertString."'".$row['sku']."','".$row['product_code']."','".$cleanFeature."'),(";

        if($index == 500){
            $insertString = substr($insertString, 0, -3).")";
            updateMageUniversalFeature($insertString);

            $insertString = "(";
            $index = 0;
        }
        $index++;
    }
    $insertString = substr($insertString, 0, -3).")";
    updateMageUniversalFeature($insertString);
}

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

//function generateCSVFromPMP($pmpConnection){
//
//    $result = $pmpConnection->query("SELECT sku, universal_feature FROM item WHERE is_universal = 1 AND brand_code = 'STI'");
//    $insertString = "SKU,UNIVERSAL,SKU FEATURES\n";
//    while($row = $result->fetch_assoc()){
//        $insertString = $insertString.$row['sku'].",1,".$row['universal_feature']."\n";
//    }
//    file_put_contents('universal_STI.csv',$insertString);
//
//}