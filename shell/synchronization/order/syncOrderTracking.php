<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));

require_once(BASE_PATH . '/../../../app/etc/cfg/config.php');
require_once(BASE_PATH . '/../../../app/Mage.php');
require_once(BASE_PATH . '/../lib/pmpModel.php');

$date = new DateTime();
$logFolder = BASE_PATH.'/../../../var/log/syncOrderStatus/';
$logPath = $logFolder.$date->format("Y_m_d").".log";
if(!file_exists($logFolder)){mkdir($logFolder);}

$client_info_path = BASE_PATH.'/../../../app/etc/cfg/client_info.conf';
$client_info = file_get_contents($client_info_path);
$client_info_array = explode(";", $client_info);

$user_id = explode("=", $client_info_array [0])[1];
$pmpURL = explode("=", $client_info_array [1])[1];

$pmpObj = new PmpObject($logPath,$pmpURL,$user_id);

/*retrieving order data from magento*/
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

Mage::app('admin');
Mage::getSingleton("core/session", array("name" => "adminhtml"));
Mage::register('isSecureArea',true);

$orders = Mage::getModel('sales/order')->getCollection()
->addFieldToFilter('status',
array('status','in'=>
    array('wait_to_ship')
    )
);

process_log_order($logPath,"Start Sync Status Process...\n");

foreach($orders as $order){
    $order_id = $order->getIncrementId();    
    process_log_order($logPath,"order id = ".$order_id."\n");
    $track_array = $pmpObj->_getOrderTracking($order_id);
    if (count($track_array) > 0){
        foreach($track_array as $trackInfo){
            // $trackInfo = ['sku'=>'','carrier_id'=>'','tracking_number'=>''];
            createShipment($order, $trackInfo);
        }
    }else if($track_array == 'cancelled'){
        $logContent = 'Order '.$order_id." has been changed to On Hold\n";
        process_log_order($logPath,$logContent);        
        $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);        
    }else{
        process_log_order($logPath,"Error occured during getting data from pmp2 database\n");
    }
}

process_log_order($logPath,"End Sync Status Process...\n");

/* private funtions */

function process_log_order($logPath,$logContent){
    echo $logContent;
    file_put_contents($logPath,$logContent, FILE_APPEND);    
}

function createShipment($order, $trackInfo){
    $carrier_id = $trackInfo['carrier_id'];
    $trackingNumber = $trackInfo['tracking_number'];

    $poNumber = $order->getIncrementId();    
    $shipment = Mage::getModel('sales/order_shipment_api');
    $orderItems = $order->getAllItems();

    foreach ($orderItems as $orderItem) {
        $sku_code = $orderItem->getSku();            
        if($sku_code == $trackInfo['sku_code']){
            $qtyArray[$orderItem->getId()] = $orderItem->getQtyOrdered();
        }            
    }
    try{    
        $shipmentIncrementId = $shipment->create($poNumber, $qtyArray, "", false, true);//create(Order ID, Items Qty, Notes, true, true);

        if($carrier_id == 1){
            $carrier_code = 'fedex';
            $carrier_title = 'Federal Express';
        }elseif($carrier_id == 3){
            $carrier_code = 'dhl';
            $carrier_title = 'DHL';
        }elseif($carrier_id == 2){
            $carrier_code = 'ups';
            $carrier_title = 'United Parcel Service';
        }else{
            $carrier_code = 'custom';
            $carrier_title = 'Custom';
        }

        if ($shipmentIncrementId){
            $shipment->addTrack($shipmentIncrementId, $carrier_code, $carrier_title, $trackingNumber);
            process_log_order($logPath,"Tracking number = ".$trackingNumber
            ." for Order = ".$poNumber
            ." with SKU = ".$sku_code." --- Done\n");
        }

    }catch(Exception $e){
        $logContent = 'Caught exception: '.$e->getMessage()."\r\n";
        process_log_order($logPath,"Error occured during adding 
        Tracking number = ".$trackingNumber
        ." for Order = ".$poNumber
        ." with SKU = ".$sku_code."\n");
    }
}

?>