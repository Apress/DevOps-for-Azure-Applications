<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
$backURL = $_GET['backURL'];
$dropdownId = $_GET['dropdown_id'];
$parentId = $_GET['parent_id'];

switch ($_SERVER['HTTP_ORIGIN']){
    case 'http://'.$backURL: case 'https://'.$backURL:
    header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 1000');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    break;
}

require_once(BASE_PATH . '/../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../app/Mage.php');
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$read = Mage::getSingleton('core/resource')->getConnection('core_read');
if($dropdownId == 1){
    $sql = "SELECT  `value_id`, `name` FROM `mage_am_finder_value` WHERE `dropdown_id` =? AND `parent_id` =? ORDER BY `name` DESC";
}
else{
    $sql = "SELECT  `value_id`, `name` FROM `mage_am_finder_value` WHERE `dropdown_id` =? AND `parent_id` =? ORDER BY `name` ASC";
}
$records = $read->fetchAll($sql, array($dropdownId,$parentId));
$selector = array();
$optionString = '';
foreach($records as $record){
    $optionString = $optionString."<option value='".$record['value_id']."'>".$record['name']."</option>";
}

print_r($optionString);

