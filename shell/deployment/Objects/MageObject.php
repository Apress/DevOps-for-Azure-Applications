<?php
require_once(BASE_PATH . '/../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../app/Mage.php');
require_once("PmpObject.php");

class MageObject
{
    private $logPath;
    private $date;
    private $pmpObj;
    private $manufacturers;
    public  $status;
    public  $errorMessage;

    public function __construct($logPath,$pmpObj,$date)
    {
        $this->_buildParams($logPath,$pmpObj,$date);
        $this->_connectToMage();
        $this->_buildManufacturers();
    }

    public function _buildParams($logPath,$pmpObj,$date)
    {
        $this->logPath = $logPath;
        $this->pmpObj = $pmpObj;
        $this->date = $date->format("Y-m-d h:i:s");
    }

    public function _connectToMage()
    {
        Mage::app('default');
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    }

    public function _saveBrandToMage($records)
    {
        $id = explode("-",$records['mage_id']);

        if(in_array($id[1], $this->manufacturers)){
            return $this->_updateChangedManufacturer($records);
        }
        elseif(array_key_exists($records['name'],$this->manufacturers)){
            return array('status'=>"Not Synchronized",'errorMessage'=>"Could not find Mage Id in PMP");
        }
        else{
            return $this->_addNewManufacturer($records);
        }
    }

    public function _saveItemToMage($type, $pmpSKU)
    {
//        $time1 = microtime(true);
        $attributes 	= $pmpSKU['attributes'];
        $SKU 			= trim($attributes['sku']);
        $product 		= Mage::getModel('catalog/product')->loadByAttribute('sku', $SKU);

        $logContent = "Deploying Record {$SKU} to Website\n";
        echo $logContent;
        file_put_contents($this->logPath,$logContent, FILE_APPEND);

        if ($product === FALSE){
            $product = Mage::getModel('catalog/product')->setWebsiteIds(array(1))->setStoreId(0);
        }
        else{
            $product->load();
        }
//        $time2 = microtime(true);
        $this->_saveMageAttributes($product, $attributes);
//        $time3 = microtime(true);
        $this->_saveMagePartTypes($product, $pmpSKU['info']['part_types']);
//        $time4 = microtime(true);
        $this->_saveMageManufacturer($product, $pmpSKU['info']['manufacturer']);
//        $time5 = microtime(true);
        $stockItems = $this->_saveMageStockItems($product, $pmpSKU['stock']);
//        $time6 = microtime(true);

        //Assign categories to product
        if($type != 'SKU'){
            $logContent = "---Start category assignment process---\n";
            echo $logContent;
            file_put_contents($this->logPath,$logContent, FILE_APPEND);

            $this->_saveMageCategoryToProduct($product,$pmpSKU['info']['part_types']);
        }
//        $time7 = microtime(true);
        //If there are product changes, save them
        if(!empty($stockItems)){
            $rv = $product->setStockData($stockItems)->save();

            if ($rv === FALSE){
                $this->status = 'Not Synchronized';
                $logContent = "Error occurred during final save operation\n";
                echo $logContent;
                file_put_contents($this->logPath,$logContent, FILE_APPEND);
            }
            $this->status = 'Synchronized';
        }
        else{
            $rv = $product->save();

            if ($rv === FALSE){
                $this->status = 'Not Synchronized';
                $logContent = "Error occurred during final save operation\n";
                echo $logContent;
                file_put_contents($this->logPath,$logContent, FILE_APPEND);
            }
            $this->status = 'Synchronized';
        }
//        $time8 = microtime(true);
        //Save Images
        try{
            $this->_saveMageImages($product, $pmpSKU['media_gallery'],$SKU);
        }
        catch (Exception $e) {
            $logContent = 'Caught exception: '.$e->getMessage()."\n";
            echo $logContent;
            file_put_contents($this->logPath,$logContent, FILE_APPEND);
        }
//        $time9 = microtime(true);
        //Updates for SKUs only
        if($type == 'SKU'){
            $parentID = $this->_getParentId($attributes['parent_sku']);
            $logContent = "Assigning SKU to Product...\n";
            echo $logContent;
            file_put_contents($this->logPath,$logContent, FILE_APPEND);
            $this->_saveMageSkuLinks($product, $parentID);
        }
//        $time10 = microtime(true);
        $logContent = $this->status."\n";
        echo $logContent;
        file_put_contents($this->logPath,$logContent, FILE_APPEND);

        $this->pmpObj->_updateStatus($SKU, $this->status, $this->errorMessage,$this->date);
//        $time11 = microtime(true);

//        echo "1 =".($time2 - $time1)."\n";
//        echo "2 =".($time3 - $time2)."\n";
//        echo "3 =".($time4 - $time3)."\n";
//        echo "4 =".($time5 - $time4)."\n";
//        echo "5 =".($time6 - $time5)."\n";
//        echo "6 =".($time7 - $time6)."\n";
//        echo "7 =".($time8 - $time7)."\n";
//        echo "8 =".($time9 - $time8)."\n";
//        echo "9 =".($time10 - $time9)."\n";
//        echo "10 =".($time11 - $time10)."\n";
    }

    public function _saveMageCategoryToProduct($product, $partType)
    {
        $category_ids = array();
        foreach($partType as $part_type){

            $pmp_ids = $this->pmpObj->getCategoryId($part_type);
            foreach($pmp_ids as $pmp_id){
                $mage_ids = $this->pmpObj->getCategoryMageId($pmp_id);
                foreach($mage_ids as $mage_id){
                    array_push($category_ids, $mage_id);
                    $parentId_1 = Mage::getModel('catalog/category')->load($mage_id)->getParentId();
                    if($parentId_1){
                        array_push($category_ids, (string)$parentId_1);
                        $parentId_2 = Mage::getModel('catalog/category')->load($parentId_1)->getParentId();
                        if($parentId_2){
                            array_push($category_ids, (string)$parentId_2);
                            $parentId_3 = Mage::getModel('catalog/category')->load($parentId_2)->getParentId();
                            if($parentId_3){
                                array_push($category_ids, (string)$parentId_3);
                                $parentId_4 = Mage::getModel('catalog/category')->load($parentId_3)->getParentId();
                                if($parentId_4){
                                    array_push($category_ids, (string)$parentId_4);
                                }
                            }
                        }
                    }
                }
            }
        }
        $result = $comma_separated = implode(",", $category_ids);

        $logContent = "category_ids = ".$result."\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
        $product->setCategoryIds($category_ids);

    }

    public function _saveMageAttributes($product, $attributes)
    {
        $attributeCode = "selling_unit_number";
        $objModel = Mage::getModel('eav/entity_setup','core_setup');
        $attributeId = $objModel->getAttributeId('catalog_product', $attributeCode);
        if(!$attributeId){
            $this->createAttribute(strtolower($attributeCode), "Selling Unit Number", "text", "");;
            $this->assignAttribute($attributeCode);
        }

        foreach($attributes as $field => $value)
        {
            if($field == 'url_path' || $field == 'url_key')
            {
                $value = preg_replace('/[-]+/i', '-', $value);
            }
            $oldValue = $product->getData($field);
            if ($oldValue != $value)
            {
                //$logContent = $field." --- ".$oldValue." => ".$value."\n";
                //echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                $product->setData($field, $value);
            }
        }
    }

    public function _saveMageManufacturer($product, $manufacturer)
    {

        if(!in_array($manufacturer, array_keys($this->manufacturers)))
        {
            //$logContent = "Can't find this Manufacturer in Magento\n";
            //echo $logContent;
            //file_put_contents($this->logPath,$logContent, FILE_APPEND);
            $records = $this->pmpObj->_getBrandRecords($manufacturer);
            $this->_addNewManufacturer($records);
        }

        $oldValue = $product->getData('manufacturer');
        $newValue = $this->manufacturers[$manufacturer];

        if($oldValue != $newValue)
        {
            $product->setData('manufacturer', $newValue);
            //$logContent = "manufacturer --- ".$oldValue." => ".$newValue."\n";
            //echo $logContent;
            //file_put_contents($this->logPath,$logContent, FILE_APPEND);
        }
    }

    public function _saveMagePartTypes($product, $partTypes)
    {
        //$logContent = "--- Begin Part Type Assignment ---\n";
        //echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

        $part_type_string = "";
        foreach($partTypes as $partType){
            //$logContent = "---".$partType." ---\n";
            //echo $logContent;
            //file_put_contents($this->logPath,$logContent, FILE_APPEND);
            $part_type_string = $part_type_string.",".$partType;
        }

        $product->addData(array(
                'part_type' => $part_type_string
            )
        );
        //$logContent = "--- End of Part Type Assignment ---"."\n";
        //echo $logContent;
        //file_put_contents($this->logPath,$logContent, FILE_APPEND);
    }

    public function _saveMageStockItems($product, $stockFields)
    {
        $stockItems = array();
        foreach($stockFields as $field => $value)
        {
            $oldValue = $product->getStockItem($field);
            if($oldValue != $value)
            {
                $stockItems[$field] = $value;
                $logContent = $field." from ".$oldValue." to ".$value."\n";
                echo $logContent;
                file_put_contents($this->logPath,$logContent, FILE_APPEND);
            }
        }
        return $stockItems;
    }

    public function _saveMageImages($product, $newImages, $SKU)
    {
        $product->load();
        $gallery = $product->getMediaGallery();
        $image_modified = $this->pmpObj->getImageSync($SKU);

        if($image_modified == 1)
        {
            $attributes = $product->getTypeInstance()->getSetAttributes();

            if (isset($attributes['media_gallery']))
            {
                //delete all old images
                foreach ($gallery['images'] as $image)
                {
                    if ($attributes['media_gallery']->getBackend()->getImage($product, $image['file']))
                    {
                        $attributes['media_gallery']->getBackend()->removeImage($product, $image['file']);
                        $_importPath   = Mage::getBaseDir('media') . DS . 'catalog/product';
                        $_productImagePath = $_importPath.$image['file'];
                        unlink($_productImagePath);

                        $logContent = "Removed old image => ".$_productImagePath."\n";
                        echo $logContent;
                        file_put_contents($this->logPath,$logContent, FILE_APPEND);
                    }
                }
                $product->save();

                //insert new images
                $logContent = "Updating Media Gallery Images: "."\n";
                echo $logContent;
                file_put_contents($this->logPath,$logContent, FILE_APPEND);

                $i = 0;
                $product->load();
                foreach($newImages as $image)
                {
                    $importPath   = Mage::getBaseDir('media') . DS . 'import';
                    $productImagePath = $importPath. DS . $image;
                    if (!is_dir($importPath)){
                        mkdir($importPath);
                    }

                    if(file_exists($productImagePath)){
                        $logContent = "Insert new image => ".$productImagePath."\n";
                        echo $logContent;
                        file_put_contents($this->logPath,$logContent, FILE_APPEND);

                        if($i == 0){
                            $product->addImageToMediaGallery($productImagePath,array('thumbnail','small_image','image'),false,false);
                        }
                        else{
                            $product->addImageToMediaGallery($productImagePath,null,false,false);
                        }
                        $i=$i+1;
//                        unlink($productImagePath);
                    }else{
                        $logContent = "Can't find image => ".$productImagePath."\n";
                        echo $logContent;
                        file_put_contents($this->logPath,$logContent, FILE_APPEND);
                    }
                }

                $product->save();
                $this->status = 'Synchronized';
            }
        }
    }

    public function _saveMageSkuLinks($product, $parentID)
    {
        try{
            $productLink = Mage::getModel('catalog/product_link');
            $productLink->setData('linked_product_id', $product->getId());
            $productLink->setData('product_id', $parentID);
            $productLink->setData('link_type_id', 3);
            $productLink->save();
        }
        catch (Exception $e) {
            $logContent = "Link exists...\n";
            echo $logContent;
            file_put_contents($this->logPath,$logContent, FILE_APPEND);
        }
    }

    public function assignAttribute($code)
    {
        $objModel = Mage::getModel('eav/entity_setup','core_setup');
        $attributeId = $objModel->getAttributeId('catalog_product', $code);
        $attributeSetId = $objModel->getAttributeSetId('catalog_product','Default');
        $attributeGroupId = $objModel->getAttributeGroupId('catalog_product',$attributeSetId,'General');
        $objModel->addAttributeToSet('catalog_product',$attributeSetId,$attributeGroupId,$attributeId);
    }

    public function createAttribute($code, $label, $attribute_type, $product_type)
    {
        $_attribute_data = array(
            'attribute_code' => $code,
            'is_global' => '1',
            'frontend_input' => $attribute_type, //'boolean',
            'default_value_text' => '',
            'default_value_yesno' => '0',
            'default_value_date' => '',
            'default_value_textarea' => '',
            'is_unique' => '0',
            'is_required' => '0',
            'apply_to' => array($product_type), //array('grouped')
            'is_configurable' => '0',
            'is_searchable' => '0',
            'is_visible_in_advanced_search' => '0',
            'is_comparable' => '0',
            'is_used_for_price_rules' => '0',
            'is_wysiwyg_enabled' => '0',
            'is_html_allowed_on_front' => '0',
            'is_visible_on_front' => '0',
            'used_in_product_listing' => '0',
            'used_for_sort_by' => '0',
            'frontend_label' => $label
        );
        $model = Mage::getModel('catalog/resource_eav_attribute');
        if (!isset($_attribute_data['is_configurable'])) {
            $_attribute_data['is_configurable'] = 0;
        }
        if (!isset($_attribute_data['is_filterable'])) {
            $_attribute_data['is_filterable'] = 0;
        }
        if (!isset($_attribute_data['is_filterable_in_search'])) {
            $_attribute_data['is_filterable_in_search'] = 0;
        }
        if (is_null($model->getIsUserDefined()) || $model->getIsUserDefined() != 0) {
            $_attribute_data['backend_type'] = $model->getBackendTypeByInput($_attribute_data['frontend_input']);
        }
        $model->addData($_attribute_data);
        $model->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
        $model->setIsUserDefined(1);
        try {
            $model->save();
        } catch (Exception $e) { echo '<p>Sorry, error occured while trying to save the attribute. Error: '.$e->getMessage().'</p>'; }
    }

    public function processCheck($processId,$processLogPath)
    {
        if(!file_exists($processLogPath)){
            $logContent = "######## Create a new process #########\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

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

            //remove process log if process has ran over 2 hours
            if($hr > 2){
                unlink($processLogPath);
                $logContent = "######## Error: Process ".$pid." Running over 2 hours, Stop Current Process #########\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            }
            else{
                $logContent = "######## Warning: Process ".$pid." Running Running over 1 hour #########\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            }
        }
    }

    public function processUpdate($processLogPath)
    {
            unlink($processLogPath);
    }

    public function changeReindexMode($reindexMode)
    {
        $processes = array(
            'Product Attributes'        => 1,
            'Product Prices'            => 2,
            'Catalog URL Rewrites'      => 3,
            'Product Flat Data'         => 4,
            'Category Flat Data'        => 5,
            'Category Products'         => 6,
            'Catalog Search index'      => 7,
            'Tag Aggregation Data'      => 8,
            'Stock Status'              => 9
        );

        foreach($processes as $process) {
            $process = Mage::getModel('index/process')->load($process);
            if($reindexMode == 'manual'){
                $process->setData('mode','manual')->save();
            }
            elseif($reindexMode == 'real_time'){
                $process->setData('mode','real_time')->save();
            }
        }
    }

    public function callReindex()
    {

        $logContent = "Start Reindex Process...\n";
        echo $logContent;
        file_put_contents($this->logPath,$logContent, FILE_APPEND);
//        try
//        {
//            $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
//            $url = $baseUrl."shell/reindex.php";
//            $ch = curl_init();
//            curl_setopt($ch, CURLOPT_URL, $url);
//            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//            $output = curl_exec($ch);
//            var_dump($output);
//            curl_close($ch);
//        }
//        catch (Exception $e)
//        {
//            Mage::printException($e);
//        }

        if (!Mage::isInstalled()) {
            echo "Application is not installed.";
            exit;
        }

        Mage::app('admin')->setUseSessionInUrl(false);

        $indexes = array(
            'Product Attributes'        => 1,
            'Product Prices'            => 2,
            'Catalog URL Rewrites'      => 3,
            'Product Flat Data'         => 4,
            'Category Flat Data'        => 5,
            'Category Products'         => 6,
            'Catalog Search index'      => 7,
            'Tag Aggregation Data'      => 8,
            'Stock Status'              => 9
        );

        try
        {
            foreach ($indexes as $key => $i)
            {
                $logContent = "Starting " . $key . " Reindex\n";
                echo $logContent;
                file_put_contents($this->logPath,$logContent, FILE_APPEND);

                $process = Mage::getModel('index/process')->load($i);
                $process->reindexAll();
            }
        }
        catch (Exception $e)
        {
            Mage::printException($e);
        }

        $logContent = "End Reindex Process...\n";
        echo $logContent;
        file_put_contents($this->logPath,$logContent, FILE_APPEND);
    }

    private function _buildManufacturers()
    {
        $manufacturers = Mage::getResourceModel('eav/entity_attribute_collection')
            ->addFieldToFilter('attribute_code', 'manufacturer')
            ->getFirstItem()->setEntity(Mage::getModel('catalog/product')
            ->getResource())->getSource()->getAllOptions(false);

        $mftrItems = array();
        foreach($manufacturers as $item)
        {
            $mftrItems[$item['label']] = $item['value'];
        }
        $this->manufacturers = $mftrItems;
    }

    private function _addNewManufacturer($mfc)
    {
        $logContent = "Starting Adding an New Manufacturer\n";
        echo $logContent;
        file_put_contents($this->logPath,$logContent, FILE_APPEND);

        // Save Manufacturer to Magento
        $attr_model = Mage::getModel('catalog/resource_eav_attribute');
        $attr = $attr_model->loadByCode('catalog_product', 'manufacturer');
        $attr_id = $attr->getAttributeId();
        $option['attribute_id'] = $attr_id;

        //$option['value'][$mfc['code']][0] = $mfc['name'];
        $option['value']['option'][0] = $mfc['name'];
        $setup = new Mage_Eav_Model_Entity_Setup('core_setup');
        $setup->addAttributeOption($option);

        // Rebuild the Manufacturer list
        $this->_buildManufacturers();

        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $read->select()->from(MAGE_TABLE_PREFIX."am_shopby_filter")->where('attribute_id= ?', $attr_id);
        $records = $read->fetchAll($select);

        // Save Detailed Manufacturer Info to Magento Manufacturer Extension
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');

        $write->insert(MAGE_TABLE_PREFIX."am_shopby_value",
            array(
                'option_id'			=> $this->manufacturers[$mfc['name']],
                'filter_id'			=> $records[0]['filter_id'],
                'is_featured'		=> 0,
                'img_small'			=> $mfc['code'].'/logo_120x54.gif',
                'img_medium'		=> $mfc['code'].'/logo_210x210.gif',
                'img_big'			=> $mfc['code'].'/logo_210x210.gif',
                'meta_title'		=> $mfc['name'],
                'meta_descr'		=> $mfc['name'],
                'title'				=> $mfc['name'],
                'descr'				=> $mfc['description']
            )
        );
        //Save MageID back to PMP
        $lastInsertId = $write->lastInsertId();
        $option_id = $this->manufacturers[$mfc['name']];
        $combine_id = $lastInsertId."-".$option_id;
        return array('brand_code'=>$mfc['code'],'combine_id'=>$combine_id);
    }

    private function _getParentId($parentSKU)
    {
        $parent = Mage::getModel('catalog/product')->loadByAttribute('sku', $parentSKU);
        if ($parent === FALSE)
        {
            $this->_saveItemToMage('Product', $this->pmpObj->_getProductRecords($parentSKU));
            $parent = Mage::getModel('catalog/product')->loadByAttribute('sku', $parentSKU);
        }
        return $parent->getId();
    }

    private function _updateChangedManufacturer($mfc)
    {

        $logContent = "Starting Updating an Manufacturer\n";
        echo $logContent;
        file_put_contents($this->logPath,$logContent, FILE_APPEND);
        //seperate by "-"
        $ids = explode("-",$mfc['mage_id']);
        $value_id = $ids[0];
        $option_id = $ids[1];

        //save by option
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');

        $query = "UPDATE `mage_eav_attribute_option_value` SET `value` = :value WHERE `option_id` = :option_id";
        $binds = array(
            'value'           => $mfc['name'],
            'option_id'       => $option_id,
        );
        $write->query($query, $binds);

        // Save Detailed Manufacturer Info to Magento Manufacturer Extension

        $table = MAGE_TABLE_PREFIX."am_shopby_value";
        $query = "UPDATE $table SET title = :title, meta_title = :meta_title, descr = :descr, meta_descr = :meta_descr WHERE value_id = :value_id";
        $binds = array(
            'value_id'           => $value_id,
            'descr'              => $mfc['description'],
            'meta_descr'         => $mfc['description'],
            'title'              => $mfc['name'],
            'meta_title'         => $mfc['name']
        );

        try{
            $write->query($query, $binds);
            $this->status = 'Synchronized';
        }
        catch(Exception $e){
            $this->status = 'Not Synchronized';
            $this->errorMessage = $e;
        }
        return array('status'=>$this->status,'errorMessage'=>$this->errorMessage);
    }

}