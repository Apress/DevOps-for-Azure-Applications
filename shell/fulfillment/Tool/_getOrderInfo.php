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

$fromDate = date('Y-m-d', strtotime('-90 days'));
$toDate = date('Y-m-d', strtotime("now"));

$orders = Mage::getModel('sales/order')->getCollection()
    ->addAttributeToFilter('created_at', array('from'=>$fromDate, 'to'=>$toDate));

$count = count($orders);
echo "Total count(in the pass 90 days) = ".$count."<br>";
file_put_contents('orderReport.csv',"order id,buyer name,email address,created time\n");
foreach($orders as $order){
    $paymentTitle = $order->getPayment()->getMethodInstance()->getTitle();
    if($paymentTitle != "eBay/Amazon Payment"){

        $orderId = $order->getIncrementId();
        $buyerName = $order->getCustomerName();
        $emailAddress = $order->getCustomerEmail();;
        $timeStamp = $order->getCreatedAt();

        file_put_contents('orderReport.csv','"'.$orderId.'","'.$buyerName.'","'.$emailAddress.'","'.$timeStamp.'"'."\n",FILE_APPEND);
    }
}

echo "done<br>";