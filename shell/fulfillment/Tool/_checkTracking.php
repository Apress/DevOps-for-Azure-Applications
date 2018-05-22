<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));

require_once(BASE_PATH.'/../../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../../app/Mage.php');
require_once('../AAT01/lib/MTR.php');
require_once('../AAT01/lib/PAT.php');
require_once('../AAT01/lib/TRA.php');
require_once('../AAT01/lib/KEY.php');
require_once('../AAT01/lib/BEC.php');

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );
date_default_timezone_set('America/Los_Angeles');
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

Mage::app('admin');
Mage::getSingleton("core/session", array("name" => "adminhtml"));
Mage::register('isSecureArea',true);

//$orderNumber = $_GET['po'];
//$vendorCode = $_GET['code'];

$orderNumber = 100025295;
$vendorCode = 'BEC';


$order = Mage::getModel('sales/order')->load($orderNumber, 'increment_id');
$orders[] = $order;

$MTR = new MTR('','live');
$PAT = new paAPI();
$TRA = new TRA('','live');
$KEY = new KEY('','live');
$BEC = new BEC('','live');

if($vendorCode == 'MTR'){
    $post_xml = $MTR->buildTrackingXML($orders);
    $params = array('raw_input' => $post_xml);
    $returnXML = $MTR->callTrackingAPI($params);

    var_dump($returnXML);exit;
    $XML = simplexml_load_string($returnXML);

    $ordersForClosing = array();
    foreach($XML->order->shipment as $shipment){
        $poNum = $shipment->attributes()->ponumber;
        if($poNum == 100005233){
            $trackingNum = (string)$shipment->attributes()->trackingnumber;
            echo $trackingNum."</br>\n";
            $carrier = (string)$shipment->attributes()->service;

            $skuArray = array();
            foreach($shipment->item as $item){
                $brand = $item->attributes()->brand;
                $partNum = $item->attributes()->sku;

                $sku = $brand.":".$partNum;
                array_push($skuArray,$sku);
            }

            $tempArray1 = array('carrier'=>$carrier,'items'=>$skuArray);
            $tempArray2 = array($trackingNum=>$tempArray1);

            if($ordersForClosing[(string)$poNum]){
                echo "-------------</br>\n";
                $ordersForClosing[(string)$poNum] = array_merge($ordersForClosing[(string)$poNum],array($tempArray2));
            }
            else{
                echo "*************</br>\n";
                $ordersForClosing[(string)$poNum] = array($tempArray2);
            }
        }
//        $trackingNum = (string)$shipment->attributes()->trackingnumber;
//        $carrier = (string)$shipment->attributes()->service;
//
//        echo $poNum."</br>\n";
//        echo $trackingNum."</br>\n";
//        echo $carrier."</br>\n";
//
//        foreach($shipment->item as $item){
//            $brand = $item->attributes()->brand;
//            $partNum = $item->attributes()->sku;
//
//            echo $brand."</br>\n";
//            echo $partNum."</br>\n";
//        }
//        echo "===================</br>\n";
    }
//    var_dump($ordersForClosing);
}
elseif($vendorCode == 'PAT'){
    $PAT->setUser('apexpress_64457','aut0p4rts');
    $PAT->setAccountNum('64457');
    $PAT->setClient("AutoSoEZ");

    foreach($orders as $order){
//        $orderDetail = $order->getOrderDetail();
//        $PAT_OrderDetail = $orderDetail['PAT'];
//        $orderNumber = $order->getIncrementId();

        $PAT->setPoNumber(100011766);

        $tracking_PAT = json_decode($PAT->checkOrderStatus());
        var_dump($tracking_PAT);
        if(is_array($tracking_PAT)){
            $tracking_PAT = $tracking_PAT[0];
        }

        $PAT_status = $tracking_PAT->Status;
        $trackingNumber = $tracking_PAT->TrackingNum;
        $carrier = 'fedex';

        echo "Try adding tracking number => ".$trackingNumber." to order => ".$orderNumber."</br>\r\n";

    }
}
elseif($vendorCode == 'TRA'){
    foreach($orders as $order){
        $orderNumber = $order->getIncrementId();
echo $orderNumber;
        $orderDetail = $order->getOrderDetail();
        $TRA_OrderDetail = $orderDetail['TRA'];
        $returnXML = $TRA->checkOrderStatus($order);

        $tracking_TRA = simplexml_load_string($returnXML->GetOrderStatusResult->any);

        $trackingObj = $tracking_TRA->GetOrderStatus;
        echo $status."</br>";
        echo $trackingNumber."</br>";
        echo $carrier."</br>";
    }
}
elseif($vendorCode == 'KEY'){
    foreach($orders as $order){
        $orderNumber = $order->getIncrementId();
        $orderDetail = $order->getOrderDetail();
        $KEY_OrderDetail = $orderDetail['KEY'];

        $returnXML = $KEY->checkOrderStatus($order);
        var_dump($returnXML);
    }
}
elseif($vendorCode == 'BEC'){
    foreach($orders as $order){
        $orderNumber = $order->getIncrementId();
        $orderDetail = $order->getOrderDetail();
        $orderDetail = unserialize($orderDetail);

        $invoice_array = array();
        $sku_mapping = array();

        foreach($orderDetail as $BEC_OrderDetail){
            foreach($BEC_OrderDetail as $sku=>$itemDetail){
                $sku_with_dash = $sku;
                $sku_without_dash = str_replace('-','',$sku);
                $sku_mapping[$sku_without_dash] = $sku_with_dash;

                $invoice_array[] = $itemDetail['vendorInvoice'];
            }
        }

        foreach($invoice_array as $invoice){
            $body = $BEC->buildGetTrackingXML($orderNumber,$invoice);
            $trackingInfo = $BEC->getTracking($body,$sku_mapping);

            var_dump($trackingInfo);
        }
    }
}

if($trackingObj){
    $status = strtolower((string)$trackingObj->Status);
    $trackingNumber = (string)$trackingObj->Order->ShipInfo->Tracking->Number;
    $carrier = strtolower((string)$trackingObj->Order->ShipInfo->Tracking->Type);

}else{
    $status = strtolower((string)$tracking_TRA->Status);
    $trackingNumber = (string)$tracking_TRA->Order->ShipInfo->Tracking->Number;
    $carrier = strtolower((string)$tracking_TRA->Order->ShipInfo->Tracking->Type);
}
