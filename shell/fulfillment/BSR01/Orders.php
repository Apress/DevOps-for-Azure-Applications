<?php
//require_once('lib/MTR.php');
require_once('lib/PAT.php');
//require_once('lib/TRA.php');
//require_once('lib/KEY.php');
//require_once('lib/FMP.php');

class Orders
{
    private $logPath;
    private $multipleVendor;
    private $envCode;
    private $pmp2URL;
    private $tracking_path;
    private $invoice_path;

    public $isError;
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

//        $orders[] = Mage::getModel('sales/order')->load(100023991, 'increment_id');
        return $orders;
    }

    /**********************************************
     ************** Place Order *******************
     **********************************************/
    public function getInventoryForAllVendors($order,$vendorInfo){
//        $MTR = new MTR($this->logPath,$this->envCode);
        $PAT = new paAPI();
//        $TRA = new TRA($this->logPath,$this->envCode);
//        $KEY = new KEY($this->logPath,$this->envCode);
//        $FMP = new FMP($this->logPath,$this->envCode);

        //[vendor_partnumber => qty]
        $inventory = array();

        /*
         * MTR
         */
//        $post_xml = $MTR->buildXML($order,$vendorInfo['MTR']);
//        $params = array('orderxml' => $post_xml);
//        $returnXML = $MTR->callStockCheckAPI($params);
//
//        $inventory_MTR = $MTR->parseInventory($returnXML);
//        $inventory['MTR'] = $inventory_MTR;

        /*
         * PAT
         */
        $PAT->setUser('everyautopart_64022','autoparts1');
        $PAT->setAccountNum('64022');
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
        /*
        * TRA
        */
//        $returnXML = $TRA->inventoryCheck($order,$vendorInfo['TRA']);
//        $inventory_TRA = $TRA->parseXML($returnXML);
//        $inventory['TRA'] = $inventory_TRA;

        /*
        * KEY
        */
//        $returnXML = $KEY->inventoryCheck($order,$vendorInfo['KEY']);
//        $inventory_KEY = $KEY->parseXML($returnXML);
//        $inventory['KEY'] = $inventory_KEY;

        /*
         * FMP
         */
//        $body = $FMP->buildInventoryXML($order,$vendorInfo['FMP'],'FMP');
//        $returnXML = $FMP->inventoryCheck($body);
//        $inventory['FMP'] = $returnXML;
//
//        $body = $FMP->buildInventoryXML($order,$vendorInfo['F02'],'F02');
//        $returnXML = $FMP->inventoryCheck($body);
//        $inventory['F02'] = $returnXML;
//
//        $body = $FMP->buildInventoryXML($order,$vendorInfo['F50'],'F50');
//        $returnXML = $FMP->inventoryCheck($body);
//        $inventory['F50'] = $returnXML;

        return $inventory;
    }

    public function checkInventory($items,$vendorInfo,$allVendorsInventory){
        $MTR_ItemArray = array();
        $PAT_ItemArray = array();
        $TRA_ItemArray = array();
        $KEY_ItemArray = array();
        $F01_ItemArray = array();
        $F02_ItemArray = array();
        $F07_ItemArray = array();
        $F12_ItemArray = array();
        $F24_ItemArray = array();
        $F50_ItemArray = array();

        foreach($items as $item){
            $vendorPriority = $this->getVendorPriority($item->getSku());
            foreach($vendorPriority as $vendorCode){

                if(!empty($this->isError)){
                    $skip = 0;
                    foreach($this->isError[$vendorCode] as $obj){
                        if($item->getSku() == $obj->getSku()){
                            $skip = 1;
                        };
                    }
                    if($skip == 1){
                        continue;
                    }
                }

                $divisor = $vendorInfo[$vendorCode][$item->getSku()]["order_quantity_divisor"];
                if($divisor){
                    $qtyOrdered = round($item->getQtyOrdered()/$divisor);
                }
                else{
                    $qtyOrdered = round($item->getQtyOrdered());
                }

                $vendorPartNumber = $vendorInfo[$vendorCode][$item->getSku()]["vendor_part_number"];
                $qtyAvailable = $allVendorsInventory[$vendorCode][$vendorPartNumber];

                if($qtyOrdered <= $qtyAvailable){
                    $logContent = "Order ".$qtyOrdered." of ".$item->getSku()." From ".$vendorCode."</br>\r\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                    if($vendorCode == "MTR"){
                        array_push($MTR_ItemArray,$item);
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
                    elseif($vendorCode == "FMP"){
                        array_push($F02_ItemArray,$item);
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
                    break;
                }
            }
        }

        $itemsArray['MTR'] = $MTR_ItemArray;
        $itemsArray['PAT'] = $PAT_ItemArray;
        $itemsArray['TRA'] = $TRA_ItemArray;
        $itemsArray['KEY'] = $KEY_ItemArray;
        $itemsArray['F01'] = $F01_ItemArray;
        $itemsArray['F02'] = $F02_ItemArray;
        $itemsArray['F07'] = $F07_ItemArray;
        $itemsArray['F12'] = $F12_ItemArray;
        $itemsArray['F24'] = $F24_ItemArray;
        $itemsArray['F50'] = $F50_ItemArray;

        return $itemsArray;
    }

    public function callPlaceOrderProcess($order,$items,$itemsArray,$vendorInfo,$allVendorsInventory){

        echo "callPlaceOrderProcess";

        $MTR_items = $itemsArray['MTR'];
        $PAT_items = $itemsArray['PAT'];
        $TRA_items = $itemsArray['TRA'];
        $KEY_items = $itemsArray['KEY'];
        $F01_items = $itemsArray['F01'];
        $F02_items = $itemsArray['F02'];
        $F07_items = $itemsArray['F07'];
        $F12_items = $itemsArray['F12'];
        $F24_items = $itemsArray['F24'];
        $F50_items = $itemsArray['F50'];

        $paymentTitle = $order->getPayment()->getMethodInstance()->getTitle();
        $logContent = "Order Title = ".$paymentTitle."</br>\r\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

        if($paymentTitle == "eBay/Amazon Payment"){

            if(count($items) == (count($MTR_items) + count($PAT_items) + count($TRA_items) + count($KEY_items)
                + count($F01_items) + count($F02_items) + count($F07_items)
                + count($F12_items) + count($F24_items) + count($F50_items)
            )){
                $stateToChange = $this->_placeOrder($order,$itemsArray,$vendorInfo,$allVendorsInventory,$items);
                if($stateToChange == "on_hold"){

                    $logContent = "++++++++++ Order has been put on hold, Unable to place order at vendor. ++++++++++</br>\r\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                    $order->addStatusHistoryComment('Unable to place order at vendor.', true);
                    $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);
                    $order->save();
                }elseif($stateToChange == "try_next"){
                    echo "Try palce order from next vendor\n";
                }elseif($stateToChange == "wait_to_ship"){
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'wait_to_ship');
                    $order->save();
                }
            }
            else{
                $logContent = "++++++++++ Order #".$po_number = $order->getIncrementId()." has been put on hold due to one or more items out of stock at the vendor. ++++++++++</br>\r\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                $message = "Order #".$po_number = $order->getIncrementId()." has been put on hold due to one or more items out of stock at the vendor.\r\n\r\nPlease modify or contact customer for immediate customer support.";
                $subject = "*** Order #".$po_number = $order->getIncrementId()." On Hold, Out of Stock ***";

                $order->addStatusHistoryComment('Unable to place order at vendor.', true);
                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);
                $order->save();

                $this->sendCancelEmail($message,$subject);
            }

        }
        elseif($order->hasInvoices()){
            //after orders authorized by paypal, change their status back to wait to ship
            $logContent = "++++++++++ Status Changed Only ++++++++++</br>\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'wait_to_ship');
            $order->save();
        }
        else{
            if(count($items) == (count($MTR_items) + count($PAT_items) + count($TRA_items) + count($KEY_items)  + count($F01_items)
                + count($F02_items) + count($F07_items) + count($F12_items) + count($F24_items) + count($F50_items)
            )){
                $stateToChange = $this->_placeOrder($order,$itemsArray,$vendorInfo,$allVendorsInventory,$items);

                if($stateToChange == "on_hold"){
                    $logContent = "++++++++++ Order has been put on hold, Unable to place order at vendor. ++++++++++</br>\r\n";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                    $order->addStatusHistoryComment('Unable to place order at vendor.', false);
                    $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);
                    $order->save();
                }elseif($stateToChange == "try_next"){
                    echo "Try place order from next vendor\n";
                }else{
                    $order->addStatusHistoryComment('Wait for PayPal to authorize.', false);
                    $order->save();
                }
            }
            else{
                $logContent = "++++++++++ Order #".$po_number = $order->getIncrementId()." has been put on hold due to one or more items out of stock at the vendor. ++++++++++</br>\r\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

                $message = "Order #".$po_number = $order->getIncrementId()." has been put on hold due to one or more items out of stock at the vendor.\r\n\r\nPlease modify or contact customer for immediate customer support.";
                $subject = "*** Order #".$po_number = $order->getIncrementId()." On Hold, Out of Stock ***";

                $order->addStatusHistoryComment('Unable to place order at vendor.', true);
                $order->setState(Mage_Sales_Model_Order::STATE_HOLDED, true);
                $order->save();

                $this->sendCancelEmail($message,$subject);
            }
        }
    }

    public function _placeOrder($order,$itemsArray,$vendorInfo,$allVendorsInventory,$items){
//        $MTR = new MTR($this->logPath,$this->envCode);
        $PAT = new paAPI();
//        $TRA = new TRA($this->logPath,$this->envCode);
//        $KEY = new KEY($this->logPath,$this->envCode);
//        $FMP = new FMP($this->logPath,$this->envCode);

        $MTR_items = $itemsArray['MTR'];
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

        $orderDetail = array();
        $this->isError = 0;

        //MTR
        if(count($MTR_items) > 0){
//            $post_xml = $MTR->buildXML($order,$vendorInfo['MTR'],$MTR_items);
//
//            $params = array('orderxml' => $post_xml);
//            $returnXML = $MTR->callOrderSendAPI($params);
//            $MTR_status = $MTR->parseConfirmation($returnXML);
//
//            if($MTR_status['status'] != 1){
//                $this->isError = array('MTR' => $MTR_items);
//                $logContent = "MTR place order status = Failed (Error Code = ".$MTR_status['status'].")</br>\r\n";
//                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//            }else{
//                $orderDetail['MTR'] = $MTR_status['orderDetail'];
//                $logContent = "MTR place order status = Success </br>\r\n";
//                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//            }
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

            $PAT->setUser('everyautopart_64022','autoparts1');
            $PAT->setAccountNum('64022');
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
                $this->isError = array('PAT' => $PAT_items);

                $logContent = "PAT place order status = Failed </br>\n".$PAT_status->responseDetail."\r\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            }
            else{
                $orderDetail['PAT'] = $PAT_orderDetail;
                $logContent = "PAT place order status = Success </br>\r\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            };
        }

        //TRA
        if(count($TRA_items) > 0){
//            $returnXML = $TRA->placeOrder($order,$TRA_items,$vendorInfo['TRA']);
//
//            $TRA_orderDetail = array();
//            foreach($TRA_items as $item){
//                $TRA_orderDetail[$item->getSku()] = array('vendorInvoice'=>$returnXML['invoiceNumber'],'qty'=>$item->getQtyOrdered());
//            }
//            if($returnXML['status'] != 'PASS'){
//                $this->isError = array('TRA' => $TRA_items);
//                $logContent = "TRA place order status = Failed </br>\r\n";
//                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//            }
//            else{
//                $orderDetail['TRA'] = $TRA_orderDetail;
//                $logContent = "TRA place order status = Success </br>\r\n";
//                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//            };
        }

        //KEY
        if(count($KEY_items) > 0){
//            $returnXML = $KEY->placeOrder($order,$KEY_items,$vendorInfo['KEY']);
//
//            $KEY_orderDetail = array();
//            foreach($KEY_items as $item){
//                $KEY_orderDetail[$item->getSku()] = array('vendorInvoice'=>$item->getIncrementId(),'qty'=>$item->getQtyOrdered());
//            }
//            if($returnXML != "OK"){
//                $this->isError = array('KEY' => $KEY_items);
//                $logContent = $returnXML."</br>\r\n";
//                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//            }
//            else{
//                $orderDetail['KEY'] = $KEY_orderDetail;
//                $logContent = "KEY place order status = Success </br>\r\n";
//                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//            };
        }

        //F01
        if(count($F01_items) > 0){

//            $this->call_FMP_APIs($FMP,$order,$F01_items,$vendorInfo,'F01',1);

        }

        //F02
        if(count($F02_items) > 0){

//            $this->call_FMP_APIs($FMP,$order,$F02_items,$vendorInfo,'F02',2);

        }

        //F07
        if(count($F07_items) > 0){

//            $this->call_FMP_APIs($FMP,$order,$F07_items,$vendorInfo,'F07',2);

        }

        //F12
        if(count($F12_items) > 0){

//            $this->call_FMP_APIs($FMP,$order,$F12_items,$vendorInfo,'F12',1);

        }

        //F24
        if(count($F24_items) > 0){

//            $this->call_FMP_APIs($FMP,$order,$F24_items,$vendorInfo,'F24',1);

        }

        //F50
        if(count($F50_items) > 0){

//            $this->call_FMP_APIs($FMP,$order,$F50_items,$vendorInfo,'F50',1);

        }

        //FMP
        if(count($FMP_items) > 0){

//            $this->call_FMP_APIs($FMP,$order,$FMP_items,$vendorInfo,'F02',2);

        }

        $paymentTitle = $order->getPayment()->getMethodInstance()->getTitle();

        if($this->isError == 0){
            if($paymentTitle == "eBay/Amazon Payment"){
                $stateToChange = "wait_to_ship";

                $order->setOrderDetail(serialize($orderDetail));

                $orderDetailString = "";
                foreach($orderDetail as $vendorCode => $items){
                    $orderDetailString = $orderDetailString."[ $vendorCode ] </br>";
                    foreach($items as $sku => $detail){
                        $orderDetailString = $orderDetailString." --- $sku ---</br>";
                        foreach($detail as $title => $content){
                            $orderDetailString = $orderDetailString." $title => $content</br>";
                        }
                    }
                };
                echo $orderDetailString;

                $order->addStatusToHistory($order->getStatus(), $orderDetailString, false);
            }
            elseif(!$order->canInvoice()) {
                $message = "Order #".$po_number = $order->getIncrementId()." has been canceled due to PayPal denying the capture of the payment.\r\n\r\nPlease contact the customer and vendor immediately to notify them of this cancellation.\r\n\r\nThis email is auto-generated.";
                $subject = "*** Order #".$po_number = $order->getIncrementId()." Canceled, Unable to Capture Payment ***";
                $this->sendCancelEmail($message,$subject);

                $stateToChange = "on_hold";
            }else{
                $logContent = "Order #".$po_number = $order->getIncrementId()." invoice is created, change status to wait_to_authorize.\r\n";
                if(DEBUG == 'YES'){echo $logContent;}else{file_put_contents($this->logPath,$logContent, FILE_APPEND);}

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
                $order->setOrderDetail(serialize($orderDetail));
                $orderDetailString = "";

                foreach($orderDetail as $vendorCode => $items){
                    $orderDetailString = $orderDetailString."[ $vendorCode ] </br>";
                    foreach($items as $sku => $detail){
                        $orderDetailString = $orderDetailString." --- $sku ---</br>";
                        foreach($detail as $title => $content){
                            $orderDetailString = $orderDetailString." $title => $content</br>";
                        }
                    }
                };

                $order->addStatusToHistory($order->getStatus(), $orderDetailString, false);
            }

            $logContent = "End Place Order\r\n";
            if(DEBUG == 'YES'){echo $logContent;}else{file_put_contents($this->logPath,$logContent, FILE_APPEND);}

            return $stateToChange;

        }
        else{

            $logContent = "Placing Order Failed => Tried Next vendor for instead\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

            $itemsArray = $this->checkInventory($items,$vendorInfo,$allVendorsInventory,$this->isError);
            $this->callPlaceOrderProcess($order,$items,$itemsArray,$vendorInfo,$allVendorsInventory);
            $stateToChange = "tried_next";

            $logContent = "End Place Order\r\n";
            if(DEBUG == 'YES'){echo $logContent;}else{file_put_contents($this->logPath,$logContent, FILE_APPEND);}

            return $stateToChange;

        }

    }

    /**********************************************
     ************ Get Tracking Number *************
     **********************************************/
    public function getTrackingNumber($orders){

//        $MTR = new MTR($this->logPath,$this->envCode);
        $PAT = new paAPI();
//        $TRA = new TRA($this->logPath,$this->envCode);
//        $KEY = new KEY($this->logPath,$this->envCode);
//        $FMP = new FMP($this->logPath,$this->envCode);

        //MTR
//        $logContent = "================= MTR ================\n";
//        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//
//        $post_xml = $MTR->buildTrackingXML($orders);
//
//        $params = array('raw_input' => $post_xml);
//        $returnXML = $MTR->callTrackingAPI($params);
//        $ordersForClosing = $MTR->trackingXMLParser($returnXML,$orders);
//
//        foreach($ordersForClosing as $ponumber => $trackingArray){
//            $this->MTR_prepareShipment($ponumber,$trackingArray);
//        }

        //PAT
        $logContent = "================= PAT ================\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

        $PAT->setUser('everyautopart_64022','autoparts1');
        $PAT->setAccountNum('64457');
        $PAT->setClient("64022");

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

            $logContent = "calling API success"."</br>\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

            if(is_array($tracking_PAT)){
                $tracking_PAT = $tracking_PAT[0];
            }

            $logContent = "parse API success"."</br>\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

            $PAT_status = $tracking_PAT->Status;
            $trackingNumber = $tracking_PAT->TrackingNum;
            $shipping_cost = $tracking_PAT->ShippingCost;
            $shipping_date = $tracking_PAT->entryTime;
            $carrier = 'fedex';

            $logContent = "Try adding tracking number => ".$trackingNumber." to order => ".$orderNumber."</br>\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

            if($PAT_status == 'Shipped' && !empty($trackingNumber)){
                $this->prepareShipment($order,$PAT_OrderDetail,$carrier,$trackingNumber,$shipping_cost,$shipping_date,'PAT');
            }
            else{
                $logContent = "Status = ".$PAT_status." Tracking number = ".$trackingNumber."</br>\r\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            }
        }

        //TRA
//        $logContent = "================= TRA ================\n";
//        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//
//        foreach($orders as $order){
//            $orderNumber = $order->getIncrementId();
//
//            $orderDetail = $order->getOrderDetail();
//            $orderDetail = unserialize($orderDetail);
//            $TRA_OrderDetail = $orderDetail['TRA'];
//
//            if(!$TRA_OrderDetail){
//                continue;
//            }
//
//            $returnXML = $TRA->checkOrderStatus($order);
//            $tracking_TRA = simplexml_load_string($returnXML->GetOrderStatusResult->any);
//
//            $trackingObj = $tracking_TRA->GetOrderStatus;
//            if($trackingObj){
//                $status = strtolower((string)$trackingObj->Status);
//                $trackingNumber = (string)$trackingObj->Order->ShipInfo->Tracking->Number;
//                $carrier = strtolower((string)$trackingObj->Order->ShipInfo->Tracking->Type);
//
//            }else{
//                $status = strtolower((string)$tracking_TRA->Status);
//                $trackingNumber = (string)$tracking_TRA->Order->ShipInfo->Tracking->Number;
//                $carrier = strtolower((string)$tracking_TRA->Order->ShipInfo->Tracking->Type);
//            }
//
//            $logContent = "Try adding tracking number => ".$trackingNumber." to order => ".$orderNumber."</br>\r\n";
//            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//
//            if($status == 'success' && !empty($trackingNumber)){
//                $this->prepareShipment($order,$TRA_OrderDetail,$carrier,$trackingNumber);
//            }
//            else{
//                $logContent = "Status = ".$status." Tracking number = ".$trackingNumber."</br>\r\n";
//                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//            }
//        }

        //KEY
//        $logContent = "================= KEY ================\n";
//        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//
//        foreach($orders as $order){
//            $orderNumber = $order->getIncrementId();
//            $orderDetail = $order->getOrderDetail();
//            $orderDetail = unserialize($orderDetail);
//            $KEY_OrderDetail = $orderDetail['KEY'];
//
//            if(!$KEY_OrderDetail){
//                continue;
//            }
//
//            $trackingInfo = $KEY->checkOrderStatus($order);
//            $status = $trackingInfo['status'];
//            $carrier = $trackingInfo['carrier'];
//            $trackingNumber = $trackingInfo['trackingNumber'];
//
//            $status = str_replace(' ', '', $status);
//            $carrier = str_replace(' ', '', $carrier);
//            $trackingNumber = str_replace(' ', '', $trackingNumber);
//
//            $logContent = "Try adding tracking number => ".$trackingNumber." to order => ".$orderNumber."</br>\r\n";
//            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//
//            if(!empty($trackingNumber)){
//                $this->prepareShipment($order,$KEY_OrderDetail,$carrier,$trackingNumber);
//            }
//            else{
//                $logContent = "Status = ".$status." Tracking number = ".$trackingNumber."</br>\r\n";
//                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//            }
//        }

        //FMP
//        $logContent = "================= FMP ================\n";
//        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//
//        foreach($orders as $order){
//            $orderNumber = $order->getIncrementId();
//            $orderDetail = $order->getOrderDetail();
//            $orderDetail = unserialize($orderDetail);
//
//            $vendor_code = "FMP";
//            foreach($this->FMP_warehouse as $vc){
//                if(array_key_exists($vc,$orderDetail)){
//                    $vendor_code = $vc;
//                }
//            }
//
//            $FMP_OrderDetail = $orderDetail[$vendor_code];
//
//            if(!$FMP_OrderDetail){
//                continue;
//            }
//
//            foreach($FMP_OrderDetail as $_FMP_OrderDetail){
//                $invoice = $_FMP_OrderDetail['vendorInvoice'];
//                break;
//            }
//
//            $body = $FMP->buildGetTrackingXML($orderNumber,$invoice);
//            $trackingInfo = $FMP->getTracking($body);
//
//            $status = $trackingInfo['status'];
//            $carrier = $trackingInfo['carrier'];
//            $trackingNumber = $trackingInfo['trackingNumber'];
//
//            $status = str_replace(' ', '', $status);
//            $carrier = str_replace(' ', '', $carrier);
//            $trackingNumber = str_replace(' ', '', $trackingNumber);
//
//            $logContent = "Try adding tracking number => ".$trackingNumber." to order => ".$orderNumber."</br>\r\n";
//            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//
//            if(!empty($trackingNumber)){
//                $this->prepareShipment($order,$FMP_OrderDetail,$carrier,$trackingNumber);
//            }
//            else{
//                $logContent = "Status = ".$status." Tracking number = ".$trackingNumber."</br>\r\n";
//                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
//            }
//        }
    }

    private function MTR_prepareShipment($poNumber,$trackingArray){

        $logContent = "Start creating Shipment...\r\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
        $order = Mage::getModel('sales/order')->loadByIncrementId($poNumber);
        $orderItems = $order->getAllItems();

        foreach($trackingArray as $index => $tracking){
            $trackingNumber = $index;

            $qtyArray = array();
            foreach ($orderItems as $orderItem) {
                $skuArray = $tracking['items'];
                if(in_array($orderItem->getSku(),$skuArray)){
                    $qtyArray[$orderItem->getId()] = $orderItem->getQtyOrdered();
                }
            }

            $logContent = "qty array = ".var_export($qtyArray,true)."\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

            if($order->canShip())
            {
                $logContent = "Try shipping order ".$poNumber."\r\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                $carrier = $tracking['carrier'];
                $this->createShipment($poNumber,$qtyArray,$carrier,$trackingNumber);
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

                    $logContent = "The Order ".$poNumber." can not be shipped";
                    echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                }
            }
        }

        $logContent = "End creating Shipment...\r\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
    }

    private function prepareShipment($order,$OrderDetail,$carrier,$trackingNumber,$shipping_cost,$shipping_date,$vendor_code){

        if($carrier == 'fedex' || $carrier == 2 || $carrier == "FedExGround" || $carrier == "FED-X GROUND"){
            $carrier = 'Federal Express';
        }
        elseif($carrier == 'dhl'){
            $carrier = 'DHL';
        }
        elseif($carrier == 'ups'||$carrier == 'upg'){
            $carrier = 'United Parcel Service';
        }
        elseif($carrier == 'usps'){
            $carrier = 'United States Postal Service';
        }
        elseif($carrier == 6){
            $carrier = 'Truck';
        }
        else{
            $carrier = 'Custom';
        }

        $itemIdArray = array();
        $qtyArray = array();
        $sku_quantity = array();
        $sku_detail = array();

        if($order->canShip()){

            $orderItems = $order->getAllItems();

            //ALL Items
            foreach ($orderItems as $orderItem) {
                $itemIdArray[$orderItem->getSku()] = $orderItem->getId();
            }

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


            $poNumber = $order->getIncrementId();
            $this->createShipment($poNumber,$qtyArray,$carrier,$trackingNumber);

            //Magento
            $purchased_on = $order->getCreatedAt();
            $sale_tax = $order->getTaxAmount();

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

    private function createShipment($poNumber,$qtyArray,$carrier,$trackingNumber){
        try{
            $shipment = Mage::getModel('sales/order_shipment_api');
            $shipmentIncrementId = $shipment->create($poNumber, $qtyArray, "", false, true);//create(Order ID, Items Qty, Notes, true, true);

            $logContent = "shipment id = ".$shipmentIncrementId."\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

            if($carrier == 'fedex' || $carrier == 2 || $carrier == "FedExGround"){
                $carrier_code = 'fedex';
                $carrier_title = 'Federal Express';
            }
            elseif($carrier == 'dhl'){
                $carrier_code = 'dhl';
                $carrier_title = 'DHL';
            }
            elseif($carrier == 'ups'||$carrier == 'upg'){
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

        if($returnXML['status'] != "OK"){
            $this->isError = array($FMP_code => $FMP_items);
            $logContent = $returnXML['status']."</br>\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
        }
        else{
            $orderDetail['FMP'] = $FMP_orderDetail;
            $logContent = "FMP place order status = OK </br>\r\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
        };
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
            $mail->setToEmail(array('david@autosoez.com','jerry@autosoez.com','mcerda@rcoautoparts.com','customercare@strutsexpress.com'));
        }

        $mail->setBody($message);
        $mail->setSubject($subject);
        $mail->setFromEmail('customercare@strutsexpress.com');
        $mail->setFromName("customercare@strutsexpress.com");
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
