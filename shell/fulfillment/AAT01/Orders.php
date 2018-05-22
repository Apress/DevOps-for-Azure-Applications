<?php

require_once('lib/PAT.php');
require_once('lib/TRA.php');
require_once('lib/KEY.php');
require_once('lib/FMP.php');
require_once('lib/BEC.php');
require_once('lib/HAN.php');

class Orders
{
    private $logPath;
    private $multipleVendor;
    private $envCode;
    private $pmp2URL;
    private $tracking_path;
    private $invoice_path;

    public $isError;

    public $allInventoryCheck;
    public $tempToOrderFrom;

    public $FMP_warehouse;
    public $BEC_warehouse;

    public function __construct($logPath,$multipleVendor,$envCode,$pmp2URL,$FMP_warehouse,$BEC_warehouse,$tracking_path,$invoice_path){
        $this->_buildParams($logPath,$multipleVendor,$envCode,$pmp2URL,$FMP_warehouse,$BEC_warehouse,$tracking_path,$invoice_path);
        $this->_connectToMage();
    }

    public function _buildParams($logPath,$multipleVendor,$envCode,$pmp2URL,$FMP_warehouse,$BEC_warehouse,$tracking_path,$invoice_path){
        $this->logPath = $logPath;
        $this->multipleVendor = $multipleVendor;
        $this->envCode = $envCode;
        $this->pmp2URL = $pmp2URL;
        $this->isError = '';
        $this->FMP_warehouse = $FMP_warehouse;
        $this->BEC_warehouse = $BEC_warehouse;
        $this->tracking_path = $tracking_path;
        $this->invoice_path = $invoice_path;
    }

    public function _connectToMage(){
        Mage::app('default');
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    }

    public function getOrders($status){
        Mage::app('admin');
        Mage::getSingleton("core/session", array("name" => "adminhtml"));
        Mage::register('isSecureArea',true);

        $orders = Mage::getResourceModel('sales/order_collection')->addFieldToFilter('status',array('status','eq'=>$status))->addAttributeToSelect('*');
        //$orders[] = Mage::getModel('sales/order')->load(100027986, 'increment_id');

        return $orders;
    }

    /**********************************************
     ************** Place Order *******************
     **********************************************/
    public function getInventoryForAllVendors($order,$vendorInfo){

        $HAN = new HAN($this->logPath,$this->envCode,$this->pmp2URL);
        $PAT = new paAPI();
        $TRA = new TRA($this->logPath,$this->envCode);
        $KEY = new KEY($this->logPath,$this->envCode);
        $FMP = new FMP($this->logPath,$this->envCode);

        $BEC = new BEC($this->logPath,$this->envCode);

        //[vendor_partnumber => qty]
        $inventory = array();

        /*
         * HAN
         */
        if(!empty($vendorInfo['HAN'])){
            $post_xml = $HAN->buildXML($order,$vendorInfo['HAN']);
            $params = array('orderxml' => $post_xml);
            $returnXML = $HAN->callStockCheckAPI($params);
            $inventory_HAN = $HAN->parseInventory($returnXML);
            $inventory['HAN'] = $inventory_HAN;
        }

        /*
         * PAT
         */

        if(!empty($vendorInfo['PAT'])){
            $PAT->setUser('apexpress_64457','aut0p4rts');
            $PAT->setAccountNum('64457');
            $PAT->setClient("AutoSoEZ");

            $items = $order->getAllVisibleItems();
            $patArray = array();
            foreach($items as $item){
                $vendor_sku = $vendorInfo['PAT'][$item->getSku()];
                $vendor_part_number = $vendor_sku["vendor_part_number"];
                $line_code = $vendor_sku["line_code"];
                $PAT->setLineCode($line_code);
                $PAT->setPartNumber($vendor_part_number);

                if($this->envCode == 'test'){
                    $PAT->set_status('test');
                }

                $inventory_PAT = json_decode($PAT->sendOrder('checkStock'));
                if($inventory_PAT->responseStatus == "Success"){
                    $patArray[$vendor_part_number] = (int)$inventory_PAT->responseDetail->instock;
                }
            }
            $inventory['PAT'] = $patArray;
        }

        /*
        * TRA
        */
        if(!empty($vendorInfo['TRA'])){
            $returnXML = $TRA->inventoryCheck($order,$vendorInfo['TRA']);
            $inventory_TRA = $TRA->parseXML($returnXML);
            $inventory['TRA'] = $inventory_TRA;
//        $inventory['TRA'] = array("8LTC7K"=>10);
        }

        /*
        * KEY
        */
        if(!empty($vendorInfo['KEY'])){
            $returnXML = $KEY->inventoryCheck($order,$vendorInfo['KEY']);
            $inventory_KEY = $KEY->parseXML($returnXML);
            $inventory['KEY'] = $inventory_KEY;
        }

        /*
         * FMP
         */
        if(!empty($vendorInfo['F01'])){
            $body = $FMP->buildInventoryXML($order,$vendorInfo['F01'],'F01');
            $returnXML = $FMP->inventoryCheck($body);
            $inventory['F01'] = $returnXML;
        }
        if(!empty($vendorInfo['F02'])){
            $body = $FMP->buildInventoryXML($order,$vendorInfo['F02'],'F02');
            $returnXML = $FMP->inventoryCheck($body);
            $inventory['F02'] = $returnXML;
        }
        if(!empty($vendorInfo['F07'])){
            $body = $FMP->buildInventoryXML($order,$vendorInfo['F07'],'F07');
            $returnXML = $FMP->inventoryCheck($body);
            $inventory['F07'] = $returnXML;
        }
        if(!empty($vendorInfo['F12'])){
            $body = $FMP->buildInventoryXML($order,$vendorInfo['F12'],'F12');
            $returnXML = $FMP->inventoryCheck($body);
            $inventory['F12'] = $returnXML;
        }
        if(!empty($vendorInfo['F24'])){
            $body = $FMP->buildInventoryXML($order,$vendorInfo['F24'],'F24');
            $returnXML = $FMP->inventoryCheck($body);
            $inventory['F24'] = $returnXML;
        }
        if(!empty($vendorInfo['F50'])){
            $body = $FMP->buildInventoryXML($order,$vendorInfo['F50'],'F50');
            $returnXML = $FMP->inventoryCheck($body);
            $inventory['F50'] = $returnXML;
        }
        if(!empty($vendorInfo['FMP'])){
            $body = $FMP->buildInventoryXML($order,$vendorInfo['FMP'],'FMP');
            $returnXML = $FMP->inventoryCheck($body);
            $inventory['FMP'] = $returnXML;
        }

        /*
        * BEC
        */
        $active_warehouse = array();
        foreach($this->BEC_warehouse as $BEC_warehouse_code){
            if(!empty($vendorInfo[$BEC_warehouse_code])){
                $active_warehouse[] = $BEC_warehouse_code;
            }
        }

        if(count($active_warehouse) > 0){
            //default 'B06', line code are all the same for each warehouse so B06 is good enough
            $body = $BEC->buildInventoryXML($order,$vendorInfo['B06']);
            $returnXML = $BEC->inventoryCheck($body,$active_warehouse);

            foreach($returnXML as $index=>$qty){
                $inventory[$index] = $qty;
            }
        }

        $logContent = "========== Vendor Inventory Check ============</br>\n";
        foreach($inventory as $index => $vendor_items){
            $logContent = $logContent."[".$index."]\n";
            foreach($vendor_items as $indexKey => $vendor_item){
                $logContent = $logContent.$indexKey.": ".$vendor_item."</br>\n";
            }
        };

        $this->allInventoryCheck = $logContent;

        return $inventory;
    }

    public function checkInventory($items,$vendorInfo,$allVendorsInventory){

        $HAN_ItemArray = array();
        $PAT_ItemArray = array();
        $TRA_ItemArray = array();
        $KEY_ItemArray = array();
        $F01_ItemArray = array();
        $F02_ItemArray = array();
        $F07_ItemArray = array();
        $F12_ItemArray = array();
        $F24_ItemArray = array();
        $F50_ItemArray = array();
        $FMP_ItemArray = array();
        $BEC_ItemArray = array();

        $tempToOrder = "</br>\n===== Placing Orders =====</br>\n";

        foreach($items as $item){
            $vendorPriority = $this->getVendorPriority($item->getSku());

            foreach($vendorPriority as $vendorCode){

                $divisor = $vendorInfo[$vendorCode][$item->getSku()]["order_quantity_divisor"];
                if($divisor){
                    $qtyOrdered = round($item->getQtyOrdered()/$divisor);
                }
                else{
                    $qtyOrdered = round($item->getQtyOrdered());
                }

                $vendorPartNumber = $vendorInfo[$vendorCode][$item->getSku()]["vendor_part_number"];

                //BEC exception
                if(in_array($vendorCode,$this->BEC_warehouse)){
                    $qtyAvailable = $allVendorsInventory['BEC'][$vendorPartNumber];

                    if($qtyOrdered <= $qtyAvailable){
                        $active_warehouse = array_intersect($vendorPriority,$this->BEC_warehouse);
//                        $active_warehouse = array("B09","B06");

                        foreach($active_warehouse as $_vendorCode){
                            $available_qty = $allVendorsInventory[$_vendorCode][$vendorPartNumber];

                            if($available_qty > 0){

                                $qtyOrdered = ($qtyOrdered - $available_qty);

                                if($qtyOrdered <= 0){
                                    $logContent = "Order ".($available_qty + $qtyOrdered)." of ".$item->getSku()." From ".$_vendorCode."</br>\r\n";
                                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                                    $tempToOrder = $tempToOrder.$logContent;

                                    $itemsArray[$_vendorCode][$item->getSku()] = ($available_qty + $qtyOrdered);
                                    break;
                                }else{
                                    $logContent = "Order ".$available_qty." of ".$item->getSku()." From ".$_vendorCode."</br>\r\n";
                                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                                    $tempToOrder = $tempToOrder.$logContent;

                                    $itemsArray[$_vendorCode][$item->getSku()] = $available_qty;
                                }
                            }
                        }

                        array_push($BEC_ItemArray,$item);

                        break;
                    }

                }else{
                    $qtyAvailable = $allVendorsInventory[$vendorCode][$vendorPartNumber];
                    if($qtyOrdered <= $qtyAvailable){
                        $logContent = "Order ".$qtyOrdered." of ".$item->getSku()." From ".$vendorCode."</br>\r\n";
                        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                        $tempToOrder = $tempToOrder.$logContent;

                        if($vendorCode == "HAN"){
                            array_push($HAN_ItemArray,$item);
                        }
                        elseif($vendorCode == "PAT"){
                            array_push($PAT_ItemArray,$item);
                        }
                        elseif($vendorCode == "TRA"){
                            array_push($TRA_ItemArray,$item);
                        }
                        elseif($vendorCode == "KEY"){
                            array_push($KEY_ItemArray,$item);
                        }
                        elseif($vendorCode == "F01"){
                            array_push($F01_ItemArray,$item);
                        }
                        elseif($vendorCode == "F02"){
                            array_push($F02_ItemArray,$item);
                        }
                        elseif($vendorCode == "F07"){
                            array_push($F07_ItemArray,$item);
                        }
                        elseif($vendorCode == "F12"){
                            array_push($F12_ItemArray,$item);
                        }
                        elseif($vendorCode == "F24"){
                            array_push($F24_ItemArray,$item);
                        }
                        elseif($vendorCode == "F50"){
                            array_push($F50_ItemArray,$item);
                        }
                        elseif($vendorCode == "FMP"){
                            array_push($FMP_ItemArray,$item);
                        }
                        break;
                    }
                }
            }
        }

        $itemsArray['HAN'] = $HAN_ItemArray;
        $itemsArray['PAT'] = $PAT_ItemArray;
        $itemsArray['TRA'] = $TRA_ItemArray;
        $itemsArray['KEY'] = $KEY_ItemArray;
        $itemsArray['F01'] = $F01_ItemArray;
        $itemsArray['F02'] = $F02_ItemArray;
        $itemsArray['F07'] = $F07_ItemArray;
        $itemsArray['F12'] = $F12_ItemArray;
        $itemsArray['F24'] = $F24_ItemArray;
        $itemsArray['F50'] = $F50_ItemArray;
        $itemsArray['FMP'] = $FMP_ItemArray;
        $itemsArray['BEC'] = $BEC_ItemArray;

        $this->tempToOrderFrom = $tempToOrder;

        return $itemsArray;
    }

    public function callPlaceOrderProcess($order,$items,$itemsArray,$vendorInfo){

        $HAN_items = $itemsArray['HAN'];
        $PAT_items = $itemsArray['PAT'];
        $TRA_items = $itemsArray['TRA'];
        $KEY_items = $itemsArray['KEY'];
        $F01_items = $itemsArray['F01'];
        $F02_items = $itemsArray['F02'];
        $F07_items = $itemsArray['F07'];
        $F12_items = $itemsArray['F12'];
        $F24_items = $itemsArray['F24'];
        $F50_items = $itemsArray['F50'];
        $FMP_items = $itemsArray['FMP'];
        $BEC_items = $itemsArray['BEC'];

        $paymentTitle = $order->getPayment()->getMethodInstance()->getTitle();
        $logContent = "Order Title = ".$paymentTitle."</br>\r\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

        if($paymentTitle == "eBay/Amazon Payment" || $paymentTitle == "M2E Pro Payment"){

            if(count($items) == (count($HAN_items) + count($PAT_items) + count($TRA_items) + count($KEY_items)
                + count($F01_items) + count($F02_items) + count($F07_items)
                + count($F12_items) + count($F24_items) + count($FMP_items) + count($F50_items)
                + count($BEC_items) )){

                $order->setIsPlaced(1);
                $stateToChange = $this->_placeOrder($order,$itemsArray,$vendorInfo,$items);

                if($stateToChange == "on_hold"){

                    $logContent = "+++++++++ Order has been put on hold. ++++++++++</br>\r\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                    $order->addStatusHistoryComment($logContent, false);
                    $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);
                    $order->save();


                }elseif($stateToChange == "wait_to_ship"){
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'wait_to_ship');
                    $order->save();
                }
            }
            else{
                $logContent = $this->allInventoryCheck."</br>\n";

                $logContent = $logContent."++++++++++ Order #".$po_number = $order->getIncrementId()." has been put on hold due to one or more items out of stock at the vendor. ++++++++++</br>\r\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                $message = "Order #".$po_number = $order->getIncrementId()." has been put on hold due to one or more items out of stock at the vendor.\r\n\r\nPlease modify or contact customer for immediate customer support.";
                $subject = "*** Order #".$po_number = $order->getIncrementId()." On Hold, Out of Stock ***";

                $order->addStatusHistoryComment($logContent, true);
                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);
                $order->save();

                $this->sendCancelEmail($message,$subject);
            }

        }
        elseif($order->hasInvoices()&& $order->getIsPlaced() == 1){
            //after orders authorized by paypal, change their status back to wait to ship
            $logContent = "++++++++++ Status Changed Only ++++++++++</br>\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'wait_to_ship');
            $order->save();
        }
        else{
            if(count($items) == (count($HAN_items) + count($PAT_items) + count($TRA_items) + count($KEY_items)
                + count($F01_items) + count($F02_items) + count($F07_items)
                + count($F12_items) + count($F24_items) + count($FMP_items) + count($F50_items)
                + count($BEC_items) )){

                $order->setIsPlaced(1);
                $stateToChange = $this->_placeOrder($order,$itemsArray,$vendorInfo,$items);

                if($stateToChange == "on_hold"){

                    $logContent = "+++++++++ Order has been put on hold. ++++++++++</br>\r\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                    $order->addStatusHistoryComment($logContent, false);
                    $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);
                    $order->save();

                }else{

                    $logContent = "Waiting for PayPal to confirm the capture of payment.\r\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                    $order->addStatusHistoryComment('Waiting for PayPal to confirm the capture of payment.', false);
                    $order->save();

                }
            }else{
                $logContent = $this->allInventoryCheck."</br>\n";
                $logContent = $logContent."++++++++++ Order #".$po_number = $order->getIncrementId()." has been put on hold due to one or more items out of stock at the vendor. ++++++++++</br>\r\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                $message = "Order #".$po_number = $order->getIncrementId()." has been put on hold due to one or more items out of stock at the vendor.\r\n\r\nPlease modify or contact customer for immediate customer support.";
                $subject = "*** Order #".$po_number = $order->getIncrementId()." On Hold, Out of Stock ***";

                $order->addStatusHistoryComment($logContent, true);
                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);
                $order->save();

                $this->sendCancelEmail($message,$subject);
            }
        }
    }

    public function _placeOrder($order,$itemsArray,$vendorInfo,$items){

        $HAN = new HAN($this->logPath,$this->envCode,$this->pmp2URL);
        $PAT = new paAPI();
        $TRA = new TRA($this->logPath,$this->envCode);
        $KEY = new KEY($this->logPath,$this->envCode);
        $FMP = new FMP($this->logPath,$this->envCode);
        $BEC = new BEC($this->logPath,$this->envCode);

        $customer_info = array("name"=>"Steve Gwinn",
            "phone"=>"816-223-7359",
            "street"=>"1600 W 40 HWY",
            "street2"=>"STE 207",
            "city"=>"Blue Springs",
            "region"=>"MO",
            "post"=>"64015",
        );

        $HAN_items = $itemsArray['HAN'];
        $PAT_items = $itemsArray['PAT'];
        $TRA_items = $itemsArray['TRA'];
        $KEY_items = $itemsArray['KEY'];
        $F01_items = $itemsArray['F01'];
        $F02_items = $itemsArray['F02'];
        $F07_items = $itemsArray['F07'];
        $F12_items = $itemsArray['F12'];
        $F24_items = $itemsArray['F24'];
        $F50_items = $itemsArray['F50'];
        $FMP_items = $itemsArray['FMP'];

        $BEC_items = $itemsArray['BEC'];

        $B06_qty = $itemsArray['B06'];
        $B09_qty = $itemsArray['B09'];
        $B13_qty = $itemsArray['B13'];
        $B16_qty = $itemsArray['B16'];
        $B19_qty = $itemsArray['B19'];
        $B20_qty = $itemsArray['B20'];
        $B23_qty = $itemsArray['B23'];

        $orderDetail = array();
        $this->isError = '';

        //HAN
        if(count($HAN_items) > 0){
            $post_xml = $HAN->buildXML($order,$vendorInfo['HAN'],$HAN_items);

            $params = array('orderxml' => $post_xml);
            $returnXML = $HAN->callOrderSendAPI($params);
/*            $returnXML = <<<EOF
<WrencheadCentral Rev="1.3" TransId="3413724313"><orderconf>  <header account="1234" comment="" delmethod="77" distributor="" errcode="SUCCESS" errmsg="" orderdate="2014-02-14 22:10:52" password="14Tennis" ponumber="100002071" pwd="" state="ordered" type="W" username="APEXPRESS" />
<part branch="4" branchname="Metro NC" errcode="SUCCESS" errmsg="" freight="" linecode="KYB" orderno="metro:6036530010053049:121135" partno="333417" qtyreq="1" qtysup="1" />
</orderconf></WrencheadCentral>
EOF;
*/
            $HAN_status = $HAN->parseConfirmation($returnXML);

            if($HAN_status['status'] != 1){

                $logContent = "HAN place order status = Failed </br>\r\n";
                $logContent = $logContent."***** WARNING ***** Order failed to place with HAN. Vendor provided error message: </br>\r\n";
                $logContent = $logContent.$HAN_status['status']."</br>\r\n";
                $order_response = $logContent."\n ============ Place Order Response =============\n".$HAN_status['order_response'];

                echo $logContent;file_put_contents($this->logPath,$order_response, FILE_APPEND);

                $this->isError = array('HAN' => $logContent);
            }else{
                $orderDetail['HAN'] = $HAN_status['orderDetail'];
            }
        }

        //PAT
        if(count($PAT_items) > 0){
            $po_number = $order->getIncrementId();
            $ship_method = $order->getShippingMethod();
            $customerName = $order->getCustomerName();
            $_shippingAddress = $order->getShippingAddress();
            $address = $_shippingAddress->getStreetFull();
            $addressArray = preg_split("/\r\n|\n|\r/", $address);
            $address1 = $addressArray[0];
            $address2 = $addressArray[1];

            $city = $_shippingAddress->getCity();
            $region = $_shippingAddress->getRegionCode();
            $post = $_shippingAddress->getPostcode();
            $post = substr($post, 0, 5);

            $PAT->setUser('apexpress_64457','aut0p4rts');
            $PAT->setAccountNum('64457');
            $PAT->setClient("AutoSoEZ");
            $PAT->set_cust_name($customerName);//ship to name
            $PAT->set_ship_add1($address1);//ship to address1
            $PAT->set_ship_add2($address2);//ship to address2
            $PAT->set_ship_city($city);//ship to city
            $PAT->set_ship_state($region);//ship to state
            $PAT->set_ship_zip($post);//ship to postal code
            $PAT->set_ship_meth('FDG');//ship method FDG=Ground, FD2=2nd Day, FDO=Overnight
            $PAT->set_order_num($po_number);//customer PO#
            $PAT->set_ship_country('US');//ship to country code

            $PAT->set_ship_alert(array(
                'recipient_email'=>'',
                'sender_email'=>'',
                'recipient_shipment_notification'=>"N",
                'sender_shipment_notification'=>"N",
                'recipient_exception_notification'=>"N",
                'sender_exception_notification'=>"N",
                'recipient_delivery_notification'=>"N",
                'sender_delivery_notification'=>"N"
            ));

            $PAT->set_special_services(array(
                'cod'=>"N",
                'cod_amount'=>'',
                'saturday_delivery'=>"N",
                'require_signature'=>"N",
                'signature_type'=>''
            ));

            $PAT_orderDetail = array();
            foreach($PAT_items as $item){
                $vendor_sku = $vendorInfo['PAT'][$item->getSku()];
                $vendor_part_number = $vendor_sku["vendor_part_number"];
                $line_code = $vendor_sku["line_code"];
                $PAT->setLineCode($line_code);
                $PAT->setPartNumber($vendor_part_number);

                $divisor = $vendorPartNumber = $vendorInfo['PAT'][$item->getSku()]["order_quantity_divisor"];
                if($divisor){
                    $qtyOrdered = round($item->getQtyOrdered()/$divisor);
                }
                else{
                    $qtyOrdered = round($item->getQtyOrdered());
                }

                $PAT_orderDetail[$item->getSku()] = array('qty'=>$qtyOrdered);
                $PAT->addItem(array('line_code'=>$line_code,'part_num'=>$vendor_part_number,'quantity'=>$qtyOrdered,'cost'=>'0'));
            }

            if($this->envCode == 'test'){
                $PAT->set_status('test');
            }
            $PAT_status = json_decode($PAT->sendOrder('enterOrder'));

            if($PAT_status->responseStatus != "Success"){
                $logContent = "PAT place order status = Failed </br>\r\n";
                $logContent = $logContent."***** WARNING ***** Order failed to place with PAT. Vendor provided error message: </br>\r\n";
                $logContent = $logContent.$PAT_status['status']."</br>\r\n";

                $order_response = $logContent."\n ============ Place Order Response =============\n".$PAT_status;

                echo $logContent;file_put_contents($this->logPath,$order_response, FILE_APPEND);

                $this->isError = array('PAT' => $logContent);
            }
            else{
                $orderDetail['PAT'] = $PAT_orderDetail;
            };
        }

        //TRA
        if(count($TRA_items) > 0){
            $returnXML = $TRA->placeOrder($order,$TRA_items,$vendorInfo['TRA']);

            $TRA_orderDetail = array();
            foreach($TRA_items as $item){
                $TRA_orderDetail[$item->getSku()] = array('orderId'=>$order->getIncrementId(),'qty'=>$item->getQtyOrdered());
            }
            if($returnXML['status'] != 'PASS'){
                $logContent = "TRA place order status = Failed </br>\r\n";
                $logContent = $logContent."***** WARNING ***** Order failed to place with TRA. Vendor provided error message: </br>\r\n";
                $logContent = $logContent.$returnXML['status']."</br>\r\n";

                $order_response = $logContent."\n ============ Place Order Response =============\n".$returnXML['order_response'];

                echo $logContent;file_put_contents($this->logPath,$order_response, FILE_APPEND);

                $this->isError = array('TRA' => $logContent);
            }
            else{
                $orderDetail['TRA'] = $TRA_orderDetail;
            };
        }

        //KEY
        if(count($KEY_items) > 0){
            $returnXML = $KEY->placeOrder($order,$KEY_items,$vendorInfo['KEY']);

            $KEY_orderDetail = array();
            foreach($KEY_items as $item){
                $KEY_orderDetail[$item->getSku()] = array('vendorInvoice'=>$item->getIncrementId(),'qty'=>$item->getQtyOrdered());
            }
            if($returnXML != "OK"){
                $logContent = "KEY place order status = Failed </br>\r\n";
                $logContent = $logContent."***** WARNING ***** Order failed to place with KEY. Vendor provided error message: </br>\r\n";
                $logContent = $logContent.$returnXML."</br>\r\n";

                $order_response = $logContent."\n ============ Place Order Response =============\n".$returnXML;

                echo $logContent;file_put_contents($this->logPath,$order_response, FILE_APPEND);

                $this->isError = array('KEY' => $logContent);
            }
            else{
                $orderDetail['KEY'] = $KEY_orderDetail;
            };
        }

        //FMP
        $FMP_array = array('FMP'=>$FMP_items,'F01'=>$F01_items,'F02'=>$F02_items,
                           'F07'=>$F07_items,'F12'=>$F12_items,'F24'=>$F24_items,
                           'F50'=>$F50_items);

        foreach($FMP_array as $code=>$items){
            if(count($items) > 0)
            {
                if(    $code == 'FMP'){$warehouseCode = '2';}
                elseif($code == 'F01'){$warehouseCode = '1';}
                elseif($code == 'F02'){$warehouseCode = '2';}
                elseif($code == 'F07'){$warehouseCode = '7';}
                elseif($code == 'F12'){$warehouseCode = '12';}
                elseif($code == 'F24'){$warehouseCode = '24';}
                elseif($code == 'F50'){$warehouseCode = '50';}
                else{  $warehouseCode = '2';}

                $FMP_orderDetail = $this->call_FMP_APIs($FMP,$order,$items,$vendorInfo,$code,$warehouseCode);
                if($FMP_orderDetail){
                    $orderDetail[$code] = $FMP_orderDetail;
                }
            }
        }

        //BEC
        $BEC_array = array('06'=>$B06_qty,'09'=>$B09_qty,'13'=>$B13_qty,
                           '16'=>$B16_qty,'19'=>$B19_qty,'20'=>$B20_qty,
                           '23'=>$B23_qty);

        foreach($BEC_array as $code => $qty_array){
            if(!empty($qty_array))
            {
                $ware_code = "B".$code;
                echo "*************** BEC place order from warehouse ".$ware_code."***************\n";
                //set isError in call_BEC_APIs
                $body = $BEC->buildPlaceOrderXML($order,$BEC_items,$qty_array,$vendorInfo[$ware_code],$customer_info,$code);
                $returnXML = $BEC->placeOrder($body);

                $BEC_orderDetail = array();

                foreach($qty_array as $sku => $qty){
                    $BEC_orderDetail[$sku] = array('vendorInvoice'=>(string)$returnXML['vendorInvoice'],'qty'=>$qty);
                }

                if($returnXML['status'] != "OK"){
                    $logContent = "BEC place order status = Failed </br>\r\n";
                    $logContent = $logContent."***** WARNING ***** Order failed to place with BEC. Vendor provided error message: </br>\r\n";
                    $logContent = $logContent.$returnXML['status']."</br>\r\n";

                    $order_response = $logContent."\n ============ Place Order Response =============\n".$returnXML['order_response'];

                    echo $logContent;file_put_contents($this->logPath,$order_response, FILE_APPEND);

                    $this->isError = array('BEC' => $logContent);
                }

                if($BEC_orderDetail){
                    $orderDetail["B".$code] = $BEC_orderDetail;
                }
            }
        }

        $paymentTitle = $order->getPayment()->getMethodInstance()->getTitle();

        /*
         * No matter success or fail print out placing order status
         */

        $order->setOrderDetail(serialize($orderDetail));

        $orderDetailString = $this->allInventoryCheck."</br>\n";
        $orderDetailString = $orderDetailString.$this->tempToOrderFrom."</br>\n";

        foreach($orderDetail as $vendorCode => $items){
            $orderDetailString = $orderDetailString." $vendorCode place order status = Success </br>\n";
            foreach($items as $sku => $detail){
                $orderDetailString = $orderDetailString." [ $sku ]</br>\n";
                foreach($detail as $title => $content){
                    $orderDetailString = $orderDetailString." $title => $content</br>\n";
                }
            }
        };

        file_put_contents($this->logPath,$orderDetailString, FILE_APPEND);

        if($this->isError == 0){

            //add comments into comment history
            $order->addStatusToHistory($order->getStatus(), $orderDetailString, false);

            if($paymentTitle == "eBay/Amazon Payment" || $paymentTitle == "M2E Pro Payment"){
                $stateToChange = "wait_to_ship";
            }
            elseif(!$order->canInvoice()) {

                echo "can not create invoice\n";
                $message = "Order #".$po_number = $order->getIncrementId()." has been canceled due to PayPal denying the capture of the payment.\r\n\r\nPlease contact the customer and vendor immediately to notify them of this cancellation.\r\n\r\nThis email is auto-generated.";
                $subject = "*** Order #".$po_number = $order->getIncrementId()." Canceled, Unable to Capture Payment ***";
                $this->sendCancelEmail($message,$subject);

                $stateToChange = "on_hold";

            }else{
                $logContent = "Try to capture online...\n";
                file_put_contents($this->logPath,$logContent, FILE_APPEND);

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
                }catch (Exception $e){
                    $logContent = "capture online failed.\nError Massage: ".$e->getMessage()."\n";
                    file_put_contents($this->logPath,$logContent, FILE_APPEND);
                    echo $logContent;

                    $order->addStatusToHistory($order->getStatus(), $logContent, false);

                    $message = "[autopartsexpress.com]\r\n";

                    $message = $message."Order [".$order->getIncrementId().".] was not able to capture payment.
                                        Please refer to the fulfillment log and the below error message
                                        from PayPal regarding this order's payment error.\r\n";

                    $message = $message."[error message from paypal]\r\n";

                    $message = $message.$e->getMessage()."\r\n";


                    $subject = "[autopartsexpress.com] - Fulfillment Error: Order payment failed";

                    $this->sendCancelEmail($message,$subject);

                    $stateToChange = "on_hold";
                }
            }

            $logContent = "End Place Order\r\n";
            file_put_contents($this->logPath,$logContent, FILE_APPEND);

            return $stateToChange;

        }
        else{
            $stateToChange = "on_hold";

            foreach($this->isError as $obj){

                $orderDetailString = $orderDetailString.$obj;
            };

            //add comments into comment history
            $order->addStatusToHistory($order->getStatus(), $orderDetailString, false);

            //send out error email
            echo $orderDetailString;file_put_contents($this->logPath,$orderDetailString, FILE_APPEND);

            $message = "[autopartsexpress.com]\r\n";
            $message = $message."Order [".$order->getIncrementId().".] was put into on hold. An error occurred during the place order process.\r\n";
            $message = $message.$orderDetailString."\r\n";
            $subject = "[autopartsexpress.com] - Fulfillment Error: Place order failed";
            $this->sendCancelEmail($message,$subject);

            return $stateToChange;

        }

    }

    /**********************************************
     ************ Get Tracking Number *************
     **********************************************/
    public function getTrackingNumber($orders){

        $HAN = new HAN($this->logPath,$this->envCode,$this->pmp2URL);
        $PAT = new paAPI();
        $TRA = new TRA($this->logPath,$this->envCode);
        $KEY = new KEY($this->logPath,$this->envCode);
        $FMP = new FMP($this->logPath,$this->envCode);
        $BEC = new BEC($this->logPath,$this->envCode);

        //HAN
        $logContent = "================= HAN ================\r\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

        $post_xml = $HAN->buildTrackingXML($orders);

        $params = array('raw_input' => $post_xml);
        $returnXML = $HAN->callTrackingAPI($params);


        $ordersForClosing = $HAN->trackingXMLParser($returnXML,$orders);

        foreach($ordersForClosing as $ponumber => $trackingArray){
            $this->HAN_prepareShipment($ponumber,$trackingArray);
        }

        //PAT
        $logContent = "================= PAT ================\r\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

        $PAT->setUser('apexpress_64457','aut0p4rts');
        $PAT->setAccountNum('64457');
        $PAT->setClient("AutoSoEZ");

        foreach($orders as $order){
            $items = $order->getAllVisibleItems();
            $skuArray = array();
            $brandCodeArray = array();
            foreach($items as $item){
                array_push($skuArray,"'".$item->getSku(). "'");
                $brandCode = substr($item->getSku(), 0, 3);
                array_push($brandCodeArray,$brandCode);
            }
        }

        $vendor_skus = $this->getVendorSku($skuArray,'PAT');
        $temp_mapping = [];
        foreach($vendor_skus as $index=>$obj){
            $temp_mapping[$obj["line_code"].$obj["vendor_part_number"]] = $index;
        }

        foreach($orders as $order){

            $orderDetail = $order->getOrderDetail();
            $orderDetail = unserialize($orderDetail);
            $PAT_OrderDetail = $orderDetail['PAT'];

            if(!$PAT_OrderDetail){
                continue;
            }

            $orderNumber = $order->getIncrementId();

            $logContent = "calling API for order number =".$orderNumber."</br>\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

            $PAT->setPoNumber($orderNumber);

            if($this->envCode == 'test'){
                $PAT->set_status('test');
            }

            $tracking_PAT = json_decode($PAT->checkOrderStatus());

            //            $raw = <<<EOF
            //{"PackageContents":[{"pkgid":"0005884964","sku":"TS 37246","tracking_number":"254306710037572","shipped_qty":"2"},{"pkgid":"0005884964","sku":"TS 71451","tracking_number":"254306710037572","shipped_qty":"1"},{"pkgid":"0005884964","sku":"TS 71452","tracking_number":"254306710037572","shipped_qty":"1"},{"pkgid":"0005884968","sku":"CE121.40053","tracking_number":"254306710037596","shipped_qty":"2"},{"pkgid":"0005884970","sku":"CE121.40046","tracking_number":"254306710037541","shipped_qty":"2"},{"pkgid":"0005884977","sku":"BA101-5302","tracking_number":"254306710037589","shipped_qty":"2"},{"pkgid":"0005884977","sku":"MEMK 90559","tracking_number":"254306710037589","shipped_qty":"2"},{"pkgid":"0005884977","sku":"RB521-713","tracking_number":"254306710037589","shipped_qty":"1"},{"pkgid":"0005884977","sku":"RB521-714","tracking_number":"254306710037589","shipped_qty":"1"},{"pkgid":"0005884990","sku":"TS 281952","tracking_number":"254306710037558","shipped_qty":"1"},{"pkgid":"0005884992","sku":"TS 281951","tracking_number":"254306710037565","shipped_qty":"1"}],"ShippingInfo":[{"pkgid":"0005884970","weight":"38.00","service":"FedEx Home Delivery","timestamp":"2016-02-02 15:29:01","length":"27","height":"12","width":"20","tracking_number":"254306710037541","carrier":"FedEx","freight":"48.28"},{"pkgid":"0005884990","weight":"27.95","service":"FedEx Home Delivery","timestamp":"2016-02-02 15:29:01","length":"16","height":"6","width":"16","tracking_number":"254306710037558","carrier":"FedEx","freight":"0.00"},{"pkgid":"0005884992","weight":"27.95","service":"FedEx Home Delivery","timestamp":"2016-02-02 15:29:02","length":"16","height":"6","width":"16","tracking_number":"254306710037565","carrier":"FedEx","freight":"0.00"},{"pkgid":"0005884964","weight":"38.25","service":"FedEx Home Delivery","timestamp":"2016-02-02 15:29:02","length":"27","height":"9","width":"14","tracking_number":"254306710037572","carrier":"FedEx","freight":"0.00"},{"pkgid":"0005884977","weight":"20.00","service":"FedEx Home Delivery","timestamp":"2016-02-02 15:29:02","length":"27","height":"9","width":"14","tracking_number":"254306710037589","carrier":"FedEx","freight":"0.00"},{"pkgid":"0005884968","weight":"20.00","service":"FedEx Home Delivery","timestamp":"2016-02-02 15:29:02","length":"36","height":"10","width":"10","tracking_number":"254306710037596","carrier":"FedEx","freight":"0.00"}],"responseDetail":"Order info and shipping detail response","responseStatus":"Success","orderInfo":[{"Status":"Shipped","OrderNum":"80562","InvoiceNum":"81187","PaPoNum":"100027857","ShippingCost":"0","TrackingNum":"254306710037541","ShippingWeight":"","cust_num":"64457","entryTime":"2016-01-31 17:30:19","branch":"8","CustPoNum":"100027857","brord":"880562"}]}
            //EOF;
            //            $tracking_PAT = json_decode($raw);

            if($tracking_PAT->responseStatus == "Success")
            {

                //sku package id mapping array
                $pkgids = [];
                foreach($tracking_PAT->PackageContents as $item){
                    $trim = preg_replace('/\s(?=)/', '', trim($item->sku));
                    print $trim;
                    $pkgids[$item->pkgid] = [$temp_mapping[$trim]=>$item->shipped_qty];
                }

                $shipping_info = [];
                foreach($tracking_PAT->orderInfo as $item){
                    $shipping_info[$item->TrackingNum] = array(
                        "invoice_num" => $item->InvoiceNum,
                        "shipping_cost" => $item->ShippingCost
                    );
                }

                //loop by package id
                foreach($tracking_PAT->ShippingInfo as $item){

                    $logContent = "calling API success"."</br>\r\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                    $logContent = "parse API success"."</br>\r\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                    $trackingNumber = $item->tracking_number;
                    $invoice_num = $shipping_info[$trackingNumber]["invoice_num"];
                    $shipping_cost = $shipping_info[$trackingNumber]["shipping_cost"];
                    $shipping_date = $item->timestamp;
                    $carrier = $item->carrier;
                    $skus_shipped = $pkgids[$item->pkgid];

                    $logContent = "Try adding tracking number => ".$trackingNumber." to order => ".$orderNumber."</br>\r\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                    $this->prepareShipment($order,$PAT_OrderDetail,$carrier,$trackingNumber,$shipping_cost,$shipping_date,'PAT',$skus_shipped,$invoice_num);

                }
            }
        }

        //TRA
        $logContent = "================= TRA ================\r\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

        foreach($orders as $order){
            $orderNumber = $order->getIncrementId();

            $orderDetail = $order->getOrderDetail();
            $orderDetail = unserialize($orderDetail);
            $TRA_OrderDetail = $orderDetail['TRA'];

            if(!$TRA_OrderDetail){
                continue;
            }

            $returnXML = $TRA->checkOrderStatus($order);
            $tracking_TRA = simplexml_load_string($returnXML->GetOrderStatusResult->any);

            $tracking_array = $tracking_TRA->GetOrderStatus;

            foreach($tracking_array as $trackingObj){

                $status = strtolower((string)$trackingObj->Status);
                $trackingNumber = (string)$trackingObj->Order->ShipInfo->Tracking->Number;

                if($status == 'success' && !empty($trackingNumber)){

                    $carrier = strtolower((string)$trackingObj->Order->ShipInfo->Tracking->Type);
                    $shipping_date = strtolower((string)$trackingObj->Order->InvDate);
                    $shipping_cost = strtolower((string)$trackingObj->Order->OtherCharges->Amount);

                    $this->prepareShipment($order,$TRA_OrderDetail,$carrier,$trackingNumber,$shipping_cost,$shipping_date,'TRA');

                    $logContent = "Try adding tracking number => ".$trackingNumber." to order => ".$orderNumber."</br>\r\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                }
                else{
                    $logContent = "Status = ".$status." Tracking number = ".$trackingNumber."</br>\r\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                }
            }
        }

        //KEY
        $logContent = "================= KEY ================\r\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

        foreach($orders as $order){
            $orderNumber = $order->getIncrementId();
            $orderDetail = $order->getOrderDetail();
            $orderDetail = unserialize($orderDetail);
            $KEY_OrderDetail = $orderDetail['KEY'];

            if(!$KEY_OrderDetail){
                continue;
            }

            $trackingInfo = $KEY->checkOrderStatus($order);
            $status = $trackingInfo['status'];
            $carrier = $trackingInfo['carrier'];
            $trackingNumber = $trackingInfo['trackingNumber'];
            $shipping_date = $trackingInfo['shipping_date'];
            $shipping_cost = $trackingInfo['shipping_cost'];

            $logContent = "Try adding tracking number => ".$trackingNumber." to order => ".$orderNumber."</br>\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

            if(!empty($trackingNumber)){
                $this->prepareShipment($order,$KEY_OrderDetail,$carrier,$trackingNumber,$shipping_cost,$shipping_date,'KEY');

            }
            else{
                $logContent = "Status = ".$status." Tracking number = ".$trackingNumber."</br>\r\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            }
        }

        //FMP
        $logContent = "================= FMP ================\r\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

        foreach($orders as $order){
            $orderNumber = $order->getIncrementId();
            $orderDetail = $order->getOrderDetail();
            $orderDetail = unserialize($orderDetail);

            $vendor_code = array();
            foreach($this->FMP_warehouse as $vc){
                if(array_key_exists($vc,$orderDetail)){
                    $vendor_code[] = $vc;
                }
            }

            foreach($vendor_code as $code){
                $FMP_OrderDetail = $orderDetail[$code];

                if(!$FMP_OrderDetail){
                    continue;
                }

                foreach($FMP_OrderDetail as $detail){
                    $invoice = $detail['vendorInvoice'];
                    break;
                }

                $body = $FMP->buildGetTrackingXML($orderNumber,$invoice);
                $trackingInfo = $FMP->getTracking($body);

                $status = $trackingInfo['status'];
                $carrier = $trackingInfo['carrier'];
                $trackingNumber = $trackingInfo['trackingNumber'];
                $shipping_date = $trackingInfo['shipping_date'];
                $shipping_cost = $trackingInfo['shipping_cost'];

                $status = str_replace(' ', '', $status);
                $carrier = str_replace(' ', '', $carrier);
                $trackingNumber = str_replace(' ', '', $trackingNumber);
                $shipping_date = str_replace(' ', '', $shipping_date);
                $shipping_cost = str_replace(' ', '', $shipping_cost);

                $logContent = "Try adding tracking number => ".$trackingNumber." to order => ".$orderNumber."</br>\r\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                if(!empty($trackingNumber)){
                    $this->prepareShipment($order,$FMP_OrderDetail,$carrier,$trackingNumber,$shipping_cost,$shipping_date,'FMP');
                }
                else{
                    $logContent = "Status = ".$status." Tracking number = ".$trackingNumber."</br>\r\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                }
            }

        }

        //BEC
        $logContent = "================= BEC ================\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

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

                $carrier = $trackingInfo['shipping method'];
                $shipping_cost = $trackingInfo['shipping_cost'];

                $item_info = $trackingInfo['item_info'];

                foreach($item_info as $trackingNumber => $sku_qty){
                    $status = 'OK';
                    $carrier = str_replace(' ', '', $carrier);
                    $trackingNumber = str_replace(' ', '', $trackingNumber);
                    $shipping_date = date('Y-m-d', strtotime("now"));

                    $logContent = "Try adding tracking number => ".$trackingNumber." with order number => ".$orderNumber."</br>\r\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                    if(!empty($trackingNumber)){
                        $this->prepareShipment($order,$sku_qty,$carrier,$trackingNumber,$shipping_cost,$shipping_date,'BEC');
                    }else{
                        $logContent = "Status = ".$status." Tracking number = ".$trackingNumber."</br>\r\n";
                        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                    }
                }
            }
        }
    }

    private function HAN_prepareShipment($poNumber,$trackingArray){

        $logContent = "Start creating Shipment...\r\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
        $order = Mage::getModel('sales/order')->loadByIncrementId($poNumber);
        $orderItems = $order->getAllItems();

        $shipmentCollection = Mage::getResourceModel('sales/order_shipment_collection')
            ->setOrderFilter($order)
            ->load();
        $tracknums = array();
        foreach ($shipmentCollection as $shipment){
            // This will give me the shipment IncrementId, but not the actual tracking information.
            foreach($shipment->getAllTracks() as $tracknum)
            {
                $tracknums[]=$tracknum->getNumber();
            }

        }

        foreach($trackingArray as $index => $tracking){
            $trackingNumber = $index;

            $qtyArray = array();
            $sku_quantity = array();
            $sku_detail = array();

            foreach ($orderItems as $orderItem) {
                $skuArray = $tracking['items'];

                if(in_array($orderItem->getSku(),$skuArray)){
                    $qtyArray[$orderItem->getId()] = $orderItem->getQtyOrdered();

                    $sku_quantity[$orderItem->getSku()] = $orderItem->getQtyOrdered();
                    $sku_detail[$orderItem->getSku()] = array(
                        'Quantity'=>$orderItem->getQtyOrdered(),
                        'Vendor_SKU_Cost'=>'',
                        'Vendor_SKU_Core_Charge'=>'',
                        'Vendor_Tax_Total'=>'');
                }
            }

            $logContent = "qty array = ".var_export($qtyArray,true)."\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

            if($order->canShip()&&!in_array($trackingNumber,$tracknums))
            {
                $logContent = "Try shipping order ".$poNumber."\r\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                $carrier = $tracking['carrier'];
                $this->createShipment($order,$qtyArray,$carrier,$trackingNumber,$sku_quantity,'HAN');
            }
            else{
                //for those orders which have invoice and shipment but can't turn to completed
                if($order->hasShipments() && $order->getBaseTotalDue()){

                    $order->setData('state', "complete");
                    $order->setStatus("complete");
                    $history = $order->addStatusHistoryComment('Order was set to complete manually.', false);
                    $history->setIsCustomerNotified(false);
                    $order->save();

                }else{

                    $logContent = "The Order ".$poNumber." can not be shipped\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                }
            }

            //vendor
            $shipping_date = $tracking['shipping_date'];
            $carrier = $tracking['carrier'];
            $shipping_cost = $tracking['shipping_cost'];

            //magento
            $purchased_on = $order->getCreatedAt();
            $sale_tax = $order->getTaxAmount();

            $vendor_invoice = array(
                'Order_Number'=>$poNumber,
                'Order_ID'=>'',
                'Vendor_Code'=>'HAN',
                'Invoice_Number'=>$poNumber,
                'Order_Date'=>$purchased_on,
                'Vending_Date'=>$shipping_date,
                'Vending_Cost_Subtotal'=>'',
                'Vending_Shipping_Cost'=>$shipping_cost,
                'Tax'=>$sale_tax
            );

            $invoice_row = '"';
            foreach($vendor_invoice as $cell){
                $invoice_row = $invoice_row.$cell.'","';
            }

            //SKU_Quantity
            $invoice_row = substr($invoice_row, 0, -1);
            $invoice_row = $invoice_row.serialize($sku_detail);

            file_put_contents($this->invoice_path,$invoice_row."\n",FILE_APPEND);

            $tracking_info = array(
                'Order_Number'=>$poNumber,
                'Invoice_Number'=>$poNumber,
                'Tracking_Number'=>$trackingNumber,
                'Shipping_Method'=>$carrier,
                'Vending_Date'=>$shipping_date,
                'Vendor_Code'=>'HAN'
            );

            $tracking_row = '"';
            foreach($tracking_info as $cell){
                $tracking_row = $tracking_row.$cell.'","';
            }

            //SKU_Quantity
            $tracking_row = substr($tracking_row, 0, -1);
            $tracking_row = $tracking_row.serialize($sku_quantity);
            file_put_contents($this->tracking_path,$tracking_row."\n",FILE_APPEND);

        }

        $logContent = "End creating Shipment...\r\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
    }

    private function prepareShipment($order,$OrderDetail,$carrier,$trackingNumber,$shipping_cost,$shipping_date,$vendor_code,$skus=[],$invoice=""){

        $itemIdArray = array();
        $qtyArray = array();
        $sku_quantity = array();
        $sku_detail = array();

        $shipmentCollection = Mage::getResourceModel('sales/order_shipment_collection')
            ->setOrderFilter($order)
            ->load();
        $tracknums = array();
        foreach ($shipmentCollection as $shipment){
            // This will give me the shipment IncrementId, but not the actual tracking information.
            foreach($shipment->getAllTracks() as $tracknum)
            {
                $tracknums[]=$tracknum->getNumber();
            }
        }

        if($order->canShip()&&!in_array($trackingNumber,$tracknums)){

            $orderItems = $order->getAllItems();

            //ALL Items
            foreach ($orderItems as $orderItem) {
                $itemIdArray[$orderItem->getSku()] = $orderItem->getId();
            }

            if ($vendor_code == "PAT"){
                foreach ($skus as $sku => $sku_qty) {

                    $qtyArray[$itemIdArray[$sku]] = (int)$sku_qty;
                    $sku_quantity[$sku] = (int)$sku_qty;
                    $sku_detail[$sku] = array(
                        'Quantity'=>(int)$sku_qty,
                        'Vendor_SKU_Cost'=>'',
                        'Vendor_SKU_Core_Charge'=>'',
                        'Vendor_Tax_Total'=>'');
                }

            }else{
                //Only in Order Detail
                foreach ($OrderDetail as $sku => $sku_info) {

                    $qtyArray[$itemIdArray[$sku]] = (int)$sku_info['qty'];
                    $sku_quantity[$sku] = (int)$sku_info['qty'];
                    $sku_detail[$sku] = array(
                        'Quantity'=>(int)$sku_info['qty'],
                        'Vendor_SKU_Cost'=>'',
                        'Vendor_SKU_Core_Charge'=>'',
                        'Vendor_Tax_Total'=>'');
                }
            }

            $poNumber = $order->getIncrementId();
            $this->createShipment($order,$qtyArray,$carrier,$trackingNumber,$sku_quantity,$vendor_code);

            //Magento
            $purchased_on = $order->getCreatedAt();
            $sale_tax = $order->getTaxAmount();

            if ($vendor_code == "PAT"){
                $vendor_invoice = array(
                    'Order_Number'=>$poNumber,
                    'Order_ID'=>'',
                    'Vendor_Code'=>$vendor_code,
                    'Invoice_Number'=>$invoice,
                    'Order_Date'=>$purchased_on,
                    'Vending_Date'=>$shipping_date,
                    'Vending_Cost_Subtotal'=>'',
                    'Vending_Shipping_Cost'=>$shipping_cost,
                    'Tax'=>$sale_tax
                );
            }else{
                $vendor_invoice = array(
                    'Order_Number'=>$poNumber,
                    'Order_ID'=>'',
                    'Vendor_Code'=>$vendor_code,
                    'Invoice_Number'=>$poNumber,
                    'Order_Date'=>$purchased_on,
                    'Vending_Date'=>$shipping_date,
                    'Vending_Cost_Subtotal'=>'',
                    'Vending_Shipping_Cost'=>$shipping_cost,
                    'Tax'=>$sale_tax
                );

            }

            $invoice_row = '"';
            foreach($vendor_invoice as $cell){
                $invoice_row = $invoice_row.$cell.'","';
            }

            //SKU_Quantity
            $invoice_row = substr($invoice_row, 0, -1);
            $invoice_row = $invoice_row.serialize($sku_detail);

            file_put_contents($this->invoice_path,$invoice_row."\n",FILE_APPEND);

            $tracking_info = array(
                'Order_Number'=>$poNumber,
                'Invoice_Number'=>$poNumber,
                'Tracking_Number'=>$trackingNumber,
                'Shipping_Method'=>$carrier,
                'Vending_Date'=>$shipping_date,
                'Vendor_Code'=>$vendor_code
            );

            $tracking_row = '"';
            foreach($tracking_info as $cell){
                $tracking_row = $tracking_row.$cell.'","';
            }

            //SKU_Quantity
            $tracking_row = substr($tracking_row, 0, -1);
            $tracking_row = $tracking_row.serialize($sku_quantity);
            file_put_contents($this->tracking_path,$tracking_row."\n",FILE_APPEND);

        }
    }

    private function createShipment($order,$qtyArray,$carrier,$trackingNumber,$sku_quantity,$vendor_code){
        $poNumber = $order->getIncrementId();

        $comment_history = "===== Receiving Tracking Number(s) =====</br>\n";
        foreach($sku_quantity as $sku=>$qty){
            $comment_history = $comment_history.$sku." (".$qty.")</br>\n";
        }
        $comment_history = $comment_history.$vendor_code."=>".$carrier.":".$trackingNumber."</br>\n";
        $comment_history = $comment_history."*************************</br>\n";
        //add comments into comment history
        $order->addStatusToHistory($order->getStatus(), $comment_history, false);
        $order->save();

        try{
            $shipment = Mage::getModel('sales/order_shipment_api');
            $shipmentIncrementId = $shipment->create($poNumber, $qtyArray, "", false, true);//create(Order ID, Items Qty, Notes, true, true);

            $logContent = "shipment id = ".$shipmentIncrementId."\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

            if($carrier == 'fedex' || $carrier == 2 || $carrier == "FedExGround" || $carrier == "FED-X GROUND"
                || $carrier == "FED-XGROUND" || $carrier == "FEDXGROUND" || $carrier == "Federal Express" || $carrier == "AUTOPARTSEXPRESSFEDEXGD"){

                $carrier_code = 'fedex';
                $carrier_title = 'Federal Express';
            }
            elseif($carrier == 'dhl'){

                $carrier_code = 'dhl';
                $carrier_title = 'DHL';
            }
            elseif($carrier == 'ups' || $carrier == 'upg'){

                $carrier_code = 'ups';
                $carrier_title = 'United Parcel Service';
            }
            elseif($carrier == 'usps'){

                $carrier_code = 'usps';
                $carrier_title = 'United States Postal Service';
            }
            elseif($carrier == 6){

                $carrier_code = 'custom';
                $carrier_title = 'Truck';
            }
            else{

                $carrier_code = 'custom';
                $carrier_title = 'Custom';
            }

            if ($shipmentIncrementId)
            {
                $logContent = "po number = ".$poNumber."\n";
                $logContent = $logContent."--- qty array ---\n";
                foreach($qtyArray as $id => $qty){
                    $logContent = $logContent.$id." => ".$qty."\n";
                }
                $logContent = $logContent."carrier number = ".$carrier."\n";
                $logContent = $logContent."tracking number = ".$trackingNumber."\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                $shipment->addTrack($shipmentIncrementId, $carrier_code, $carrier_title, $trackingNumber);





            }


        }catch(Exception $e){
            $logContent = 'Caught exception: '.$e->getMessage()."\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
        }
    }

    /**********************************************
     ************* General Functions **************
     **********************************************/

    public function call_FMP_APIs($FMP,$order,$FMP_items,$vendorInfo,$FMP_code,$warehouseCode){

        $body = $FMP->buildPlaceOrderXML($order,$FMP_items,$vendorInfo[$FMP_code],$warehouseCode);
        $returnXML = $FMP->placeOrder($body);

        $FMP_orderDetail = array();
        foreach($FMP_items as $item){
            $FMP_orderDetail[$item->getSku()] = array('vendorInvoice'=>(string)$returnXML['vendorInvoice'],'qty'=>$item->getQtyOrdered());
        }

        $errorArray = array();
        if($returnXML['status'] != "OK"){
            $errorArray[$FMP_code] = $FMP_items;
            if($FMP_code == "F02"){
                $errorArray['FMP'] = $FMP_items;
            }
            if($FMP_code == "FMP"){
                $errorArray['F02'] = $FMP_items;
            }

            $logContent = "FMP place order status = Failed </br>\r\n";
            $logContent = $logContent."***** WARNING ***** Order failed to place with FMP. Vendor provided error message: </br>\r\n";
            $logContent = $logContent.$returnXML['status']."</br>\r\n";

            $order_response = $logContent."\n ============ Place Order Response =============\n".$returnXML['order_response'];

            echo $logContent;file_put_contents($this->logPath,$order_response, FILE_APPEND);

            $this->isError = array('FMP' => $logContent);

            return 0;
        }
        else{

            return $FMP_orderDetail;
        }
    }

    public function getVendorSku($skuArray,$vendorCode){
        $skuString = implode(",",$skuArray);

        $targetURL = $this->pmp2URL.'api/getVendorSku';
        $fields = array('skuString'=>$skuString,'vendorCode'=>$vendorCode);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);

        print $result;

        $vendorArray = unserialize($result);

        if(!is_array($vendorArray)){

            $message = "[AAT01] There is a temporary pause to the fulfillment process due to PMP2 server downtime. The process will resume when PMP2 is running again.\r\n\r\nThis email is auto-generated.";
            $subject = "*** Fulfillment Process Failed ***";
            $this->sendCancelEmail($message,$subject);
            exit;
        }

        return $vendorArray;
    }

    public function getVendorPriority($sku){

        $targetURL = $this->pmp2URL.'api/getVendorPriority/'.urlencode($sku);
        $fields = array('sku'=>$sku);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $vendors = curl_exec($ch);
        curl_close($ch);

        return unserialize($vendors);
    }

    private function sendCancelEmail($message,$subject){
        $mail = Mage::getModel('core/email');
        $mail->setToName('Dear Customer');

        if($this->envCode == 'test'){
            $mail->setToEmail(array('jerry@autosoez.com'));
        }
        else{
            $mail->setToEmail(array('david@autosoez.com','arthur@autosoez.com','jerry@autosoez.com','cs@autopartsexpress.com'));
        }

        $mail->setBody($message);
        $mail->setSubject($subject);
        $mail->setFromEmail('cs@autopartsexpress.com');
        $mail->setFromName("cs@autopartsexpress.com");
        $mail->setType('text');// You can use 'html' or 'text'

        try {
            $mail->send();
            $logContent = "Cancel Order email sent</br>\r\n";
            if(DEBUG == 'YES'){echo $logContent;}else{file_put_contents($this->logPath,$logContent, FILE_APPEND);}
        }
        catch (Exception $e) {
            $logContent = "Unable to send the cancel Order email\r\n";
            if(DEBUG == 'YES'){echo $logContent;}else{file_put_contents($this->logPath,$logContent, FILE_APPEND);}
        }
    }

    public function processCheck($processId,$processLogPath){
        if(!file_exists($processLogPath)){
            $logContent = "date=".time().";id=".$processId;
            file_put_contents($processLogPath,$logContent, FILE_APPEND);
            return 1;
        }
        else{
            $logFile = fopen($processLogPath, 'r');
            $content = fgets($logFile);
            $lines = explode(";",$content);

            fclose($logFile);

            $date = substr( $lines[0], strrpos( $lines[0], '=' )+1 );
            $hr = ((time()-$date)/3600);
            $pid = substr( $lines[1], strrpos( $lines[1], '=' )+1 );
            //remove process log if process has ran over 0.5 hours
            if($hr > 2){
                unlink($processLogPath);
                $logContent = "######## Error: Process ".$pid." Running over 2 hours, Stop Current Process #########\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            }else{
                $logContent = "######## Warning: Process ".$pid." is running #########\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            }
            return 0;
        }
    }

    public function processUpdate($processLogPath){
        unlink($processLogPath);
    }

}
