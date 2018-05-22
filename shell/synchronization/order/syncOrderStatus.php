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
    array('processing',
        'pending_payment',
        'holded',
        'wait_to_authorize',
        'payment_review',
        'fraud',
        'pending')
    )
);

process_log_order($logPath,"Start Sync Status Process...\n");

foreach($orders as $order){
    $order_id = $order->getIncrementId();  
      
    process_log_order($logPath,"order id = ".$order_id."\n");
    $new_order_status = $pmpObj->_getOrderStatus($order_id);
    
    if($new_order_status == 'wait_to_ship'){

        $stateToChange = createInvoice($order);
        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $stateToChange);
        
        $logContent = 'Order '.$order_id." status has not been changed to wait_to_ship\n";
        process_log_order($logPath,$logContent);                

    }else if($new_order_status == 'cancelled'){

        $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true);        
        
        $logContent = 'Order '.$order_id." status has not been changed to cancelled\n";
        process_log_order($logPath,$logContent);    

    }else if($new_order_status == 'on_hold'){
        
        $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);        
        
        $logContent = 'Order '.$order_id." status has not been changed to on_hold\n";
        process_log_order($logPath,$logContent);    

    }else{

        $logContent = 'Order '.$order_id." status has not been changed yet\n";
        process_log_order($logPath,$logContent);  
    }

    $order->save();
}

process_log_order($logPath,"End Sync Status Process...\n");

/* private funtions */

function process_log_order($logPath,$logContent){
    echo $logContent;
    file_put_contents($logPath,$logContent, FILE_APPEND);    
}

function createInvoice($order){
    try{
        //START Handle Invoice
        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
        $invoice->register();
        $invoice->getOrder()->setCustomerNoteNotify(false);
        $invoice->getOrder()->setIsInProcess(false);
        
        $order->addStatusHistoryComment('Automatically INVOICED.', false);
        
        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transactionSave->save();
        
        $stateToChange = "wait_to_ship";
        
        process_log_order($logPath,"Process successful, Magento order status changed to wait_to_ship\n");
        
        $invoice->sendEmail(true, '');

    }catch (Exception $e){
        $logContent = "capture online failed.\nError Massage: ".$e->getMessage()."\n";
        process_log_order($logPath,$logContent);

        $order->addStatusToHistory($order->getStatus(), $logContent, false);

        $message = $message."Order [".$order->getIncrementId().".] was not able to capture payment.
                            Please refer to the fulfillment log and the below error message
                            from PayPal regarding this order's payment error.\r\n";

        $message = $message."[error message from paypal]\r\n";

        $message = $message.$e->getMessage()."\r\n";

        $subject = "Fulfillment Error: Order payment failed";

        sendCancelEmail($message,$subject);

        $stateToChange = "on_hold";
    }
    return $stateToChange;
}

?>