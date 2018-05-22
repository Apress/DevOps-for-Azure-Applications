<?php

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH.'/../../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../../app/Mage.php');

require_once(BASE_PATH.'/../AAT01/lib/MTR.php');
//require_once(BASE_PATH.'/../AAT01/lib/PAT.php');
//require_once(BASE_PATH.'/../AAT01/lib/TRA.php');
//require_once(BASE_PATH.'/../AAT01/lib/KEY.php');

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );
date_default_timezone_set('America/Los_Angeles');
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

Mage::app('admin');
Mage::getSingleton("core/session", array("name" => "adminhtml"));
Mage::register('isSecureArea',true);

$orderNumber = $_GET['po'];
$vendorCode = $_GET['code'];

$order = Mage::getModel('sales/order')->load($orderNumber, 'increment_id');
$orderId = $order->getIncrementId();

$items = $order->getAllVisibleItems();
$skuArray = array();
$brandCodeArray = array();
foreach($items as $item){
    array_push($skuArray,"'".$item->getSku(). "'");
}

$vendorInfo = getVendorSku($skuArray,$vendorCode);

if($vendorCode == "MTR"){
    $response = placeOrder_MTR($order,$vendorInfo);
    var_dump($response);
}

/*
 * MTR
 * */
echo "Order = ".$orderId." has been attempted to be placed at ".$vendorCode."</br>\n";

function placeOrder_MTR($order,$vendorInfo){
    $po_number = $order->getIncrementId();
    $customerName = $order->getCustomerName();

    $items = $order->getAllVisibleItems();

    $_shippingAddress = $order->getShippingAddress();

    $address = $_shippingAddress->getStreetFull();
    $city = $_shippingAddress->getCity();
    $region = $_shippingAddress->getRegionCode();
    $post = $_shippingAddress->getPostcode();
    $part = '';
    foreach($items as $item){
        $vendor_sku = $vendorInfo[$item->getSku()];
        $vendor_part_number = $vendor_sku["vendor_part_number"];
        $line_code = $vendor_sku["line_code"];
        $divisor = $vendor_sku["order_quantity_divisor"];
        //implement order quantity divisor
        if($divisor){
            $qty = round($item->getQtyOrdered()/$divisor);
        }
        else{
            $qty = round($item->getQtyOrdered());
        }

        $part .= sprintf('<part cost="0" partno="%s" linecode="%s" branch="1" qtyreq="%s"></part>', $vendor_part_number, $line_code,$qty);
    }
    /*
    * build XML file
    **/
    $request_order =  <<<EOF
<order>
<header username="APEXPRESS" password="14Tennis" type="delivery" fillflag="shipcomplete" delmethod="" ponumber="$po_number" />
<comment type="ship" text="Ground" />
<comment type="general" text="" />
<shipto customer="$customerName" address1="$address" address2="" city="$city" state="$region" zip="$post" />
$part
</order>
EOF;

    $request_order = <<<EOF
<?xml version="1.0"?>
<WrencheadCentral Rev="1.2" TransId="3413724313">
$request_order
</WrencheadCentral>
EOF;

    /*
    * place order
    **/
    $url = 'http://shipping.metroap.com/erp/orderproxy?type=ordersend';

    $ch = curl_init();

    $params = array('orderxml' => $request_order);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $output = curl_exec($ch);
    curl_close($ch);

    return $output;
}

function getVendorSku($skuArray,$vendorCode){
    $skuString = implode(",",$skuArray);

    $pmp = new mysqli(CORE_DB_HOST, CORE_DB_USER, CORE_DB_PASSWORD, CORE_DB_DATABASE);
    $sql = "SELECT sku, vendor_part_number,line_code,order_quantity_divisor,preference_order
    FROM item_vendor WHERE sku in (".$skuString.") AND vendor_code = '".$vendorCode."'";

    $result = $pmp->query($sql);

    if(!$result){
        echo "error...";
    }

    $vendorArray = array();
    while($item = $result->fetch_assoc())
    {
        $vendorArray[(string)$item["sku"]] = array("vendor_part_number"=>(string)$item["vendor_part_number"],"line_code"=>$item["line_code"],"order_quantity_divisor"=>$item["order_quantity_divisor"],"preference_order"=>$item["preference_order"]);
    }

    return $vendorArray;
}