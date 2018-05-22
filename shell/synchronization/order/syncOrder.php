<?php

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));

require_once(BASE_PATH . '/../../../app/etc/cfg/config.php');
require_once(BASE_PATH . '/../../../app/Mage.php');
require_once(BASE_PATH . '/../lib/pmpModel.php');

$date = new DateTime();
$logFolder = BASE_PATH.'/../../../var/log/syncOrder/';
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

// $lastOrderId = $pmpObj->_getLatestOrderId();
// $lastOrderId = 100000003;

$orders = Mage::getModel('sales/order')->getCollection()
// ->addAttributeToFilter('increment_id', array('gt' => $lastOrderId))
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
// $orders[] = Mage::getModel('sales/order')->load(100000011, 'increment_id');

process_log_order($logPath,"Start Sync Process...\n");

foreach($orders as $order){    

    $order_id = $order->getIncrementId();       

    /*get order detail */
    $order_info = fetch_orders($order);
    
    process_log_order($logPath,"order id = ".$order_id."\n");

    //pmp2 check if order_id exists
    $order_exists = $pmpObj->_getIsOrderCreated($order_id);
    echo "order exists = ".$order_exists."\n";
    if($order_exists == 0){
        /* get order items detail */
        $order_item_info = getOrderItem($order);

        $response1 = $pmpObj->_updateOrder($order_info);
        $response2 = $pmpObj->_updateOrderItems($order_item_info,$order_id);
        // $response1 = 1;
        // $response2 = 1;

        if ($response1 == 1 && $response2 == 1){
            // $stateToChange = createInvoice($order);
            // $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, $stateToChange);
            process_log_order($logPath,"Process successful, Magento order info have been created in PMP2\n");
            
        }else{        
            // $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);
            $subject = "Fulfillment Error";
            $logContent = "Order sync process failed, order sync process now is terminated until next iteration.\n";
            process_log_order($logPath, $logContent);
            sendCancelEmail($logContent,$subject);
            //exit();
        }
        // $order->save();
    }
    
}

process_log_order($logPath,"End Sync Process...\n");

/* private funtions */

function process_log_order($logPath,$logContent){
    echo $logContent;
    file_put_contents($logPath,$logContent, FILE_APPEND);    
}

function fetch_orders($order){
    //Order Info
    $order_id = $order->getIncrementId();
    $purchased_on = $order->getCreatedAt();
    $order_status = $order->getStatusLabel();

    //Customer Info
    $customer_name = $order->getCustomerName();
    $email_address = $order->getCustomerEmail();
    $customer_group_id = $order->getCustomerGroupId();
    $customer_group = Mage::getModel('customer/group')->load($customer_group_id)->getCustomerGroupCode();

    //Billing
    $_billing_address = $order->getBillingAddress();

    $bill_to_name = $_billing_address->getName();
    $_address_temp2 = $_billing_address->getStreetFull();
    $billing_address_array = explode("\n", $_address_temp2);
    $billing_address1 = $billing_address_array[0];
    $billing_address2 = $billing_address_array[1];
    if(!$billing_address2){$billing_address2='';}
    $billing_city = $_billing_address->getCity();
    $billing_region = $_billing_address->getRegionCode();
    $billing_post = $_billing_address->getPostcode();
    $billing_country = $_billing_address->getCountry();

    $billing_address1 = str_replace(array('&', '<', '>', '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $billing_address1);
    $billing_address2 = str_replace(array('&', '<', '>', '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $billing_address2);
    $billing_city = str_replace(array('&', '<', '>', '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $billing_city);
    $billing_region = str_replace(array('&', '<', '>', '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $billing_region);

    //Shipping
    $_shipping_address = $order->getShippingAddress();

    $ship_to_name = $_shipping_address->getName();
    $_address_temp1 = $_shipping_address->getStreetFull();

    $shipping_address_array = explode("\n", $_address_temp1);
    $shipping_address1 = $shipping_address_array[0];
    $shipping_address2 = $shipping_address_array[1];
    if(!$shipping_address2){$shipping_address2='';}
    $shipping_city = $_shipping_address->getCity();
    $shipping_region = $_shipping_address->getRegionCode();
    $shipping_post = $_shipping_address->getPostcode();
    $shipping_country = $_shipping_address->getCountry();

    $customer_name = str_replace(array('&', '<', '>', '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $customer_name);
    $shipping_address1 = str_replace(array('&', '<', '>',  '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $shipping_address1);
    $shipping_address2 = str_replace(array('&', '<', '>', '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $shipping_address2);
    $shipping_city = str_replace(array('&', '<', '>', '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $shipping_city);
    $shipping_region = str_replace(array('&', '<', '>', '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $shipping_region);

    //Payment
    $payment = $order->getPayment();
    $payment_instance = $payment->getMethodInstance();
    $additional_info = $payment->getData('additional_information');

    $payment_title = $payment_instance->getTitle();
    $card_type = $payment->getData('cc_type');
    $last_4_digits = "xxxx-".$payment->getData('cc_last4');

    $payer_id = $additional_info["paypal_payer_id"];
    $payer_email = $additional_info["paypal_payer_email"];
    //$payer_payment_status = $additional_info["paypal_payment_status"];
    $payer_payer_status = $additional_info["paypal_payer_status"];
    $payer_address_status = $additional_info["paypal_address_status"];
    $merchant_protection = $additional_info["paypal_protection_eligibility"];
    $correlation_id = $additional_info["paypal_correlation_id"];
    $avs = $additional_info["paypal_avs_code"];
    $cvv2 = $additional_info["paypal_cvv2_match"];
    $last_tran_id = $payment->getData('last_trans_id');


    //Comments History
    $commentsObject = $order->getStatusHistoryCollection(true);

    $commets_history = "";
    foreach ($commentsObject as $commentObj) {
        $ch_time = $commentObj->getCreatedAt();
        $ch_comments = $commentObj->getComment();
        $ch_status = $commentObj->getStatus();
        if(empty($ch_time)){
            $ch_time = "-";
        }
        if(empty($ch_comments)){
            $ch_comments = "-";
        }
        if(empty($ch_status)){
            $ch_status = "-";
        }
        $commets_history = $commets_history.$ch_time."@@".$ch_comments."@@" .$ch_status."||";

    }
    $commets_history = str_replace(array('&', '<', '>', '\'', '"'), array('&amp;', '&lt;', '&gt;', '&apos;', '&quot;'), $commets_history);

    //Order Total
    $subtotal = $order->getSubtotal();
    $shipping_amount = $order->getShippingAmount();
    $grand_total = $order->getGrandTotal();
    $amount_paid = $payment->getData('amount_paid');
    $amount_refunded = $payment->getData('base_amount_refunded');
    $total_due = $order->getBaseTotalDue();//paid if this equals to 0
    $sale_tax = $order->getTaxAmount();

    $order_info['order_id'] = $order_id;
    $order_info['purchased_on'] = $purchased_on;
    $order_info['order_status'] = $order_status;
    $order_info['customer_name'] = $customer_name;
    $order_info['email_address'] = $email_address;
    $order_info['customer_group'] = $customer_group;
    $order_info['bill_to_name'] = $bill_to_name;
    $order_info['billing_address1'] = $billing_address1;
    $order_info['billing_address2'] = $billing_address2;
    $order_info['billing_city'] = $billing_city;
    $order_info['billing_region'] = $billing_region;
    $order_info['billing_country'] = $billing_country;
    $order_info['billing_post'] = $billing_post;
    $order_info['ship_to_name'] = $ship_to_name;
    $order_info['shipping_address1'] = $shipping_address1;
    $order_info['shipping_address2'] = $shipping_address2;
    $order_info['shipping_city'] = $shipping_city;
    $order_info['shipping_region'] = $shipping_region;
    $order_info['shipping_country'] = $shipping_country;
    $order_info['shipping_post'] = $shipping_post;
    $order_info['payment_title'] = $payment_title;
    $order_info['card_type'] = $card_type;
    $order_info['last_4_digits'] = $last_4_digits;
    $order_info['payer_id'] = $payer_id;
    $order_info['payer_email'] = $payer_email;
    $order_info['payer_payer_status'] = $payer_payer_status;
    $order_info['payer_address_status'] = $payer_address_status;
    $order_info['merchant_protection'] = $merchant_protection;
    $order_info['correlation_id'] = $correlation_id;
    $order_info['avs'] = $avs;
    $order_info['cvv2'] = $cvv2;
    $order_info['last_tran_id'] = $last_tran_id;
    $order_info['commets_history'] = $commets_history;
    $order_info['subtotal'] = $subtotal;
    $order_info['shipping_amount'] = $shipping_amount;
    $order_info['grand_total'] = $grand_total;
    $order_info['amount_paid'] = $amount_paid;
    $order_info['amount_refunded'] = $amount_refunded;
    $order_info['total_due'] = $total_due;
    $order_info['sale_tax'] = $sale_tax;

    return $order_info;
}

function getOrderItem($order){
    /* order_items.csv */
    $orderedItems = $order->getAllVisibleItems();
    $orderedProductIds = array();

    $orderItems = $order->getAllItems();
    $result_order_items = [];
    foreach ($orderItems as $order_item) {
        $item = [];

        $sku = $order_item->getSku();
        $sku_qty = $order_item->getQtyOrdered();
        $sku_qyt_shipped = $order_item->getQtyShipped();
        $sku_qyt_invoiced = $order_item->getQtyInvoiced();
        $sku_price = $order_item->getPrice();
        $sku_refunded = $order_item->getQtyRefunded();
        
        $item['sku_qty'] = $sku_qty;
        $item['sku_qyt_shipped'] = $sku_qyt_shipped;
        $item['sku_qyt_invoiced'] = $sku_qyt_invoiced;
        $item['sku_price'] = $sku_price;
        $item['sku_refunded'] = $sku_refunded;

        $result_order_items[$sku] = $item;    
    }
    return $result_order_items; 
}

function sendCancelEmail($message,$subject){
    $mail = Mage::getModel('core/email');
    $mail->setToName('Dear Customer');
    
    $mail->setToEmail(array('jy1215jy@gmail.com'));    

    $mail->setBody($message);
    $mail->setSubject($subject);
    $mail->setFromEmail('cs@autosoez.com');
    $mail->setFromName("cs@autosoez.com");
    $mail->setType('text');// You can use 'html' or 'text'

    try {
        $mail->send();
        $logContent = "Cancel Order email sent</br>\r\n";
        process_log_order($logPath,$logContent);
    }
    catch (Exception $e) {
        $logContent = "Unable to send the cancel Order email\r\n";
        process_log_order($logPath,$logContent);
    }
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
