<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH . '/../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../app/Mage.php');
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$write = Mage::getSingleton('core/resource')->getConnection('core_write');
$write->query("DELETE FROM `mage_directory_country_region` WHERE `code` IN ('AS','AF','AA','AC','AE','AM','AP','FM','GU','MH','MP','PW')");

echo "done...";