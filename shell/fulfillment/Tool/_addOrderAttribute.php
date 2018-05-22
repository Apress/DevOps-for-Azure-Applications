<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH . '/../../../app/Mage.php');

Mage::app()->setCurrentStore(Mage::getModel('core/store')->load(Mage_Core_Model_App::ADMIN_STORE_ID));
$installer = new Mage_Sales_Model_Mysql4_Setup;
//$attribute  = array(
//    'type'          => 'text',
//    'backend_type'  => 'text',
//    'frontend_input' => 'text',
//    'is_user_defined' => true,
//    'label'         => 'Order Detail',
//    'visible'       => true,
//    'required'      => false,
//    'user_defined'  => false,
//    'searchable'    => false,
//    'filterable'    => false,
//    'comparable'    => false,
//    'default'       => ''
//);
//$installer->addAttribute('order', 'order_detail', $attribute);
//$installer->endSetup();

$attribute  = array(
    'type'          => 'int',
    'backend_type'  => 'int',
    'frontend_input' => 'int',
    'is_user_defined' => true,
    'label'         => 'Is Placed',
    'visible'       => true,
    'required'      => false,
    'user_defined'  => true,
    'searchable'    => true,
    'filterable'    => true,
    'comparable'    => true,
    'default'       => 0
);

$installer->addAttribute('order', 'is_placed', $attribute);
$installer->endSetup();

echo "done...";