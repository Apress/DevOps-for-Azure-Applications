<?php

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));

if(isset($argv[1])){
    $months = $argv[1];
}else{
    $months = 2;
}

$now = date('Y-m-d', strtotime("now"));

require_once(BASE_PATH . '/../../../app/etc/cfg/config.php');
require_once(BASE_PATH . '/../../../app/Mage.php');

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

Mage::app('admin');
Mage::getSingleton("core/session", array("name" => "adminhtml"));
Mage::register('isSecureArea',true);

$order_info_headers = "Order Number,Order Date,Order Status,Customer Name,Customer Email,Customer Group,Bill to Name,Billing Street,Billing City,Billing State,Billing Country,Billing Zip,Ship to Name,Shipping Street,Shipping City,Shipping State,Shipping Country,Shipping Zip,Payment Title,Credit Card Type,Credit Card Number,Payer ID,Payer Email,Payer Status,Payer Address Status,Merchant Protection Eligibility,Last Correlation ID,Address Verification System Response,CVV2 Check Result by PayPal,Last Transaction ID,Comments History,Subtotal,Shipping,Grand Total,Total Paid,Total Refunded,Total Due,Tax";
$order_items_headers = "Order Number,Sku,Qty Ordered,Qty Invoiced,Qty Shipped,Price,Order Refund";
//$order_tracking_headers = "Order Number,Carrier,Tracking Number,Shipping Date";

$order_info_path = BASE_PATH.'/../../../var/log/orders/order_info_'.$now.'.csv';
$order_items_path = BASE_PATH.'/../../../var/log/orders/order_items_'.$now.'.csv';

file_put_contents($order_info_path, $order_info_headers."\n");
file_put_contents($order_items_path, $order_items_headers."\n");
//file_put_contents(BASE_PATH.'/order_tracking_'.$now.'.csv',$order_tracking_headers."\n");

for($i = $months; $i > 0; $i--){

    $from = $i*(-30);
    $from = $from.' days';
    $to = ($i-1)*(-30);
    if($to == 0){
        $to = "now";
    }else{
        $to = $to.' days';
    }

    $fromDate = date('Y-m-d', strtotime($from));
    $toDate = date('Y-m-d', strtotime($to));

    echo "Fetch orders from ".$fromDate." to ".$toDate."\n";

    $orders = Mage::getModel('sales/order')->getCollection()
        ->addAttributeToFilter('created_at', array('from'=>$fromDate, 'to'=>$toDate));
    fetch_orders($orders,$order_info_path,$order_items_path);

    $orders = NULL;
}


function fetch_orders($orders,$order_info_path,$order_items_path){
    foreach($orders as $order){

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
//    $payer_payment_status = $additional_info["paypal_payment_status"];
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

        $order_info_row =
            '"'.$order_id.'","'.$purchased_on.'","'.$order_status.'","'.$customer_name.'","'.$email_address.'","'.$customer_group.'","'.
                $bill_to_name.'","'.$billing_address1." ".$billing_address2.'","'.$billing_city.'","'.$billing_region.'","'.$billing_country.'","'.$billing_post.'","'.
                $ship_to_name.'","'.$shipping_address1." ".$shipping_address2.'","'.$shipping_city.'","'.$shipping_region.'","'.$shipping_country.'","'.$shipping_post.'","'.
                $payment_title.'","'.$card_type.'","'.$last_4_digits.'","'.$payer_id.'","'.$payer_email.'","'.$payer_payer_status.'","'.$payer_address_status.'","'.$merchant_protection.'","'.$correlation_id.'","'.$avs.'","'.$cvv2.'","'.$last_tran_id.'","'.
                $commets_history.'","'.$subtotal.'","'.$shipping_amount.'","'.$grand_total.'","'.$amount_paid.'","'.$amount_refunded.'","'.$total_due.'","'.$sale_tax.'"'
        ;

        file_put_contents($order_info_path,$order_info_row."\n",FILE_APPEND);

        /*
       * order_tracking.csv
       * */
//        $shipmentCollection = $order->getShipmentsCollection();
//
//        foreach($shipmentCollection as $shipment){
//
//            $shipping_date = $shipment->getCreatedAt();
//            foreach($shipment->getAllTracks() as $trac){
//                $carrier = $trac->getTitle();
//                $tracking_number = $trac->getNumber();
//            echo $trac->getCarrierCode()."\n";
//
//                $order_tracking_row = '"'.$order_id.'","'.$carrier.'","'.$tracking_number.'","'.$shipping_date.'"';
//                file_put_contents(BASE_PATH.'/order_tracking_'.$now.'.csv',$order_tracking_row."\n",FILE_APPEND);
//            }
//        }


        /*
       * order_items.csv
       * */
        $orderedItems = $order->getAllVisibleItems();
        $orderedProductIds = array();

        $orderItems = $order->getAllItems();

        foreach ($orderItems as $order_item) {

            $sku = $order_item->getSku();
            $sku_qty = $order_item->getQtyOrdered();
            $sku_qyt_shipped = $order_item->getQtyShipped();
            $sku_qyt_invoiced = $order_item->getQtyInvoiced();
            $sku_price = $order_item->getPrice();
            $sku_refunded = $order_item->getQtyRefunded();

            $order_items_row = '"'.$order_id.'","'.$sku.'","'.$sku_qty.'","'.$sku_qyt_shipped.'","'.$sku_qyt_invoiced.'","'.$sku_price.'","'.$sku_refunded.'"';
            file_put_contents($order_items_path,$order_items_row."\n",FILE_APPEND);
        }
    }

}
