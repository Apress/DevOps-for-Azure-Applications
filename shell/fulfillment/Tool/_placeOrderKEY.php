<?php
$keyNumber = 'ad179b62-719a-4004-abbc-7346fe1d7383';
$fullAccountNo = '11110430000';

$full_part_number = 'W248LTC30K';
$order_number = '90000001';

//$returnXML = inventoryCheck($keyNumber,$fullAccountNo,$full_part_number);
//var_dump($returnXML);

$returnXML = placeOrder($keyNumber,$fullAccountNo,$full_part_number,$order_number);
var_dump($returnXML);

function inventoryCheck($keyNumber,$fullAccountNo,$order_number){
    $wsdl="http://order.ekeystone.com/WSElectronicorder/ElectronicOrder.asmx?WSDL";

    $order_params = array(
        'Key' => $keyNumber,
        'FullAccountNo' => $fullAccountNo,
        'FullPartNo' => $order_number
    );

    $client = new SoapClient($wsdl);
    $response = $client->CheckInventoryBulk($order_params);

    $returnXML = $response->CheckInventoryBulkResult->any;

    return $returnXML;
}

function placeOrder($keyNumber,$fullAccountNo,$full_part_number,$order_number){
    $wsdl="http://order.ekeystone.com/WSElectronicorder/ElectronicOrder.asmx?WSDL";

    $order_params = array(
        'Key' => $keyNumber,
        'FullAccountNo' => $fullAccountNo,
        'FullPartNo' => $full_part_number,
        'Quant' => 1,
        'DropShipFirstName' => "Steve",
        'DropShipMiddleInitial' => '',
        'DropShipLastName' => 'Gwinn',
        'DropShipCompany' => '',
        'DropShipAddress1' => "1600 W 40 HWY STE 207",
        'DropShipAddress2' => "",
        'DropShipCity' => "Blue Springs",
        'DropShipState' => "MO",
        'DropShipPostalCode' => "64015",
        'DropShipPhone' => "8162237359",
        'DropShipCountry' => 'USA',
        'DropShipEmail' => '',
        'PONumber' => $order_number,
        'AdditionalInfo' => '',
        'ServiceLevel' => ''
    );

    $client = new SoapClient($wsdl);
    $returnXML = $client->ShipOrderDropShip($order_params);
    return $returnXML;
}