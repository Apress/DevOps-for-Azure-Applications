<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH . '/../../../app/etc/cfg/config.php');
require_once(BASE_PATH . '/../../../app/Mage.php');

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );
date_default_timezone_set('America/Los_Angeles');
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

Mage::app('admin');
Mage::getSingleton("core/session", array("name" => "adminhtml"));
Mage::register('isSecureArea',true);

$orderNumber = $_GET['po'];
$status = $_GET['status'];

$order = Mage::getModel('sales/order')->load($orderNumber, 'increment_id');
$orderId = $order->getIncrementId();

if($status == 'processing'){

    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
}
elseif($status == 'complete'){
//    $order->setState(Mage_Sales_Model_Order::STATE_COMPLETE, true);
    $order->setData('state', "complete");
    $order->setStatus("complete");
    $history = $order->addStatusHistoryComment('Order was set to Complete manually.', false);
    $history->setIsCustomerNotified(false);
    $order->save();
}

elseif($status == 'holded'){

    $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);
}elseif($status == 'canceled'){

    $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);
}elseif($status == 'wait_to_ship'){

    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'wait_to_ship');
}

$order->save();
echo $orderId." has been changed to ".$status."</br>\n";
