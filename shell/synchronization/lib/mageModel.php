<?php
require_once(BASE_PATH . '/../../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../../app/Mage.php');
require_once("pmpModel.php");

class MageObject
{
    private $logPath;
    private $date;
    private $pmpObj;
    private $manufacturers;
    public  $status;
    public  $errorMessage;

    public function __construct($logPath,$pmpObj,$date){
        $this->_buildParams($logPath,$pmpObj,$date);
        $this->_connectToMage();
        $this->_buildManufacturers();
    }

    public function _buildParams($logPath,$pmpObj,$date){
        $this->logPath = $logPath;
        $this->pmpObj = $pmpObj;
        $this->date = $date->format("Y-m-d h:i:s");
    }

    public function _connectToMage(){
        Mage::app('default');
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    }

    public function flushCache(){
        Mage::app()->getCacheInstance()->flush();
    }

    public function _saveBrandToMage($record){
        $id = explode("-",$record['mage_id']);

        if(in_array($id[1], $this->manufacturers)){
            return $this->_updateChangedManufacturer($record);
        }
        elseif(array_key_exists($record['name'],$this->manufacturers)){
            $logContent = "Brand name exists in magento but no mage id can be found in PMP\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            return array('status'=>"error",'errorMessage'=>$logContent);
        }
        else{
            return $this->_addNewManufacturer($record);
        }
    }

    public function _saveItemToMage($type, $pmpSKU, $brands_info){

        $attributes 	= (array)$pmpSKU['attributes'];
        $info           = (array)$pmpSKU['info'];
        $SKU 			= trim($attributes['sku']);
        $call_again     = 0;//for new product creation

        $logContent = "Deploying Record {$SKU} to Website\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

        $storeName = Mage::app()->getStore()->getFrontendName();
        $productName = $attributes['name'];
        $partType = $info['part_types'];
        $brand_code = $info['brand_code'];
        $brandName = $info['manufacturer'];
        $category_ids = $info['category_ids'];

        //check if brand exit;
        if(!in_array($brandName, array_keys($this->manufacturers))){

            $logContent = "brand name does not exit in magento\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            return array('status'=>'error','errorMessage'=>$logContent,'call_again'=>$call_again);

        }else{

            $product 		= Mage::getModel('catalog/product')->loadByAttribute('sku', $SKU);

            if ($product === FALSE){
                $product = Mage::getModel('catalog/product')->setWebsiteIds(array(1))->setStoreId(0);
                $call_again = 1;
            }
            else{
                $product->load();

            }
        }

        $meta_Description = $storeName." carries quality".$partType."such as ".$brandName." ".$productName." while offering fast delivery to your doorsteps. Trust ".$brandName." and ".$storeName." for your auto parts needs.";
        $meta_title = $productName." on sale!";
        $meta_keyword = $brandName." ".$productName.", ".$storeName.", ".$partType;

        $product->setMetaDescription($meta_Description);

        $product->setMetaTitle($meta_title);

        $product->setMetaKeyword($meta_keyword);

        $this->_saveMageAttributes($product, $attributes);

        $this->_saveMagePartTypes($product, $type);

        if(array_key_exists($brand_code,$brands_info)){
            $brand_info[$brand_code] = (array)$brands_info[$brand_code];
        }else{
            $brand_info = NULL;
        }

        $error = $this->_saveMageManufacturer($product, $brandName, $brand_info);
        if($error == 1){
            $logContent = "can not save manufacturer\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            return array('status'=>'error','errorMessage'=>$logContent);
        }

        $stockItems = $this->_saveMageStockItems($product, (array)$pmpSKU['stock']);

        //Assign categories to product
        if($type != 'sku'){
            if($category_ids){
                $product->setCategoryIds($category_ids);
            }
        }

        if(!empty($stockItems)){
            $rv = $product->setStockData($stockItems)->save();

            if ($rv === FALSE){
                $this->status = 'error';
                $logContent = "Error occurred during final save operation\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            }else{
                $this->status = 'done';
            }
        }
        else{
            $rv = $product->save();

            if ($rv === FALSE){
                $this->status = 'error';
                $logContent = "Error occurred during final save operation\n";
                echo $logContent;
                file_put_contents($this->logPath,$logContent, FILE_APPEND);
            }else{
                $this->status = 'done';
            }
        }

        //check if parent product exist;
        if($type == 'sku'){
            $parentID = $this->_getParentId($attributes['parent_sku']);
            if($parentID != "error"){
                $logContent = "Assigning SKU to Product...\n";
                echo $logContent;
                file_put_contents($this->logPath,$logContent, FILE_APPEND);
                $this->_saveMageSkuLinks($product, $parentID);
            }else{
                return array('status'=>'error','errorMessage'=>'parent product does not exit in magento','call_again'=>$call_again);
            }
        }

        $logContent = $this->status."\n";
        echo $logContent;
        file_put_contents($this->logPath,$logContent, FILE_APPEND);

        return array('status'=>$this->status,'errorMessage'=>$this->errorMessage,'call_again'=>$call_again);
    }

    public function _saveMageAttributes($product, $attributes){
        $selling_unit_number_code = "selling_unit_number";
        $quality_level_code = "quality_level";

        $objModel = Mage::getModel('eav/entity_setup','core_setup');

        $selling_unit_number_id = $objModel->getAttributeId('catalog_product', $selling_unit_number_code);
        $quality_level_id = $objModel->getAttributeId('catalog_product', $quality_level_code);

        if(!$selling_unit_number_id){
            $this->createAttribute(strtolower($selling_unit_number_code), "Selling Unit Number", "text", "");;
            $this->assignAttribute($selling_unit_number_code);
        }

        if(!$quality_level_id){
            $this->createAttribute(strtolower($quality_level_code), "Quality Level", "text", "");;
            $this->assignAttribute($quality_level_code);
        }

        foreach($attributes as $field => $value)
        {
            if($field == 'status' && $value == 0){
                $value = 2;
            }elseif($field == 'url_path' || $field == 'url_key')
            {
                $value = preg_replace('/[-]+/i', '-', $value);
            }
            $oldValue = $product->getData($field);

            $logContent = $field." => ".$value."\n";
            echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            if ($oldValue != $value)
            {
                $product->setData($field, $value);
            }
        }
    }

    public function _saveMageManufacturer($product, $manufacturer, $brand_info){
        $error = 0;

        if(!in_array($manufacturer, array_keys($this->manufacturers))){

            if(count($brand_info) != 0){

                $this->_addNewManufacturer($brand_info);
            }else{

                $error = 1;
                $logContent = "Can not get new manufacturer info from pmp\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                return $error;
            }
        }
        $oldValue = $product->getData('manufacturer');
        $newValue = $this->manufacturers[$manufacturer];

        if($oldValue != $newValue)
        {
            $product->setData('manufacturer', $newValue);
        }

        return $error;
    }

    public function _saveMagePartTypes($product, $partTypes){
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

    public function _saveMageStockItems($product, $stockFields){
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

    public function _saveMageSkuLinks($product, $parentID){
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

    public function _saveMageImages($records){
        $statusArray = array();
        foreach($records as $sku => $record){

            $brandCode = substr($sku,0,3);
            $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);

            if(!$product){
                $logContent = "Can not find ".$sku." in magento \n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                $statusArray[$sku]['status'] = "error";
                $statusArray[$sku]['error_msg'] = $logContent;
                continue;
            }else{
                $logContent = ">>> Sku = ".$sku."\n";
                echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
            }

            $product->load();
            $attributes = $product->getTypeInstance()->getSetAttributes();

            if (isset($attributes['media_gallery']))
            {

                //delete all old images
                $gallery = $attributes['media_gallery'];
                $galleryData = $product->getMediaGallery();
                foreach($galleryData['images'] as $image){

                    if ($gallery->getBackend()->getImage($product, $image['file'])) {
                        $gallery->getBackend()->removeImage($product, $image['file']);
                    }

                    $_importPath   = Mage::getBaseDir('media') . DS . 'catalog/product';
                    $_productImagePath = $_importPath.$image['file'];
                    unlink($_productImagePath);

                    $logContent = "Removed old image => ".$_productImagePath."\n";
                    echo $logContent;
                    file_put_contents($this->logPath,$logContent, FILE_APPEND);
                }
                $product->save();

                //insert new images
                $logContent = "Updating Media Gallery Images: "."\n";
                echo $logContent;
                file_put_contents($this->logPath,$logContent, FILE_APPEND);

                $i = 0;
                $product->load();
                foreach($record as $image)
                {
                    $importPath   = Mage::getBaseDir('media') . DS . 'import';
                    $productImagePath = $importPath."/".$brandCode."/".$image;
                    if (!is_dir($importPath)){
                        mkdir($importPath);
                    }

                    if(file_exists($productImagePath)){
                        $logContent = "Insert new image => ".$image."\n";
                        echo $logContent;
                        file_put_contents($this->logPath,$logContent, FILE_APPEND);

                        if($i == 0){
                            $product->addImageToMediaGallery($productImagePath,array('thumbnail','small_image','image'),false,false);
                        }
                        else{
                            $product->addImageToMediaGallery($productImagePath,null,false,false);
                        }
                        $i=$i+1;
                    }else{
                        $logContent = "Can not find image => ".$image."\n";
                        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);
                        $statusArray[$sku]['status'] = "error";
                        $statusArray[$sku]['error_msg'] = $logContent;
                    }
                }
                $product->save();
            }
            if(empty($statusArray[$sku]['status'])){
                $statusArray[$sku]['status'] = "done";
            }
        }
        return $statusArray;
    }

    public function saveCategories($parentMageId,$changedItems,$level){

        foreach($changedItems as $item)
        {
            try{
                $cat1_url_key =  strtolower(str_replace(" ", "-", $item->category_name));

                $category = null;
                //new category
                if(empty($item->mage_id)){

                    $logContent = "new ".$item->category_name." category created\n";
                    echo $logContent; file_put_contents($this->logPath,$logContent, FILE_APPEND);
                    $category = new Mage_Catalog_Model_Category();
                }
                //exist category
                else{
                    $logContent = "existing ".$item->category_name."(".$item->mage_id.") category updated\n";
                    echo $logContent; file_put_contents($this->logPath,$logContent, FILE_APPEND);

                    $category = Mage::getModel('catalog/category')->load($item->mage_id);
                    if(!$category->getId()){
                        echo "Mage Id ".$item->mage_id." does not exist in Magento\n";
                        continue;
                    }
                }

                $category->setName($item->category_name);
                $category->setUrlKey($cat1_url_key);
                $category->setIsActive($item->category_active);
                $category->setIsAnchor(1);

                if($level == 1){
                    $category->setIncludeInMenu(1);
                    $category->setDisplayMode('PAGE');
                }elseif($level == 2){
                    $category->setIncludeInMenu(1);
                    $category->setDisplayMode('PRODUCTS_AND_PAGE');
                }else{
                    $category->setIncludeInMenu(0);
                    $category->setDisplayMode('PRODUCTS_AND_PAGE');
                }

                $category->setAvailableSortByOptions('name', 'price', 'brand');
                $category->setDefaultSortBy('name');
                $category->setDescription($item->category_description);
                $data['image'] = $item->category_colloge;
                $category->addData($data);

//                if(empty($item->mage_id)){
                    $parentCategory = Mage::getModel('catalog/category')->load($parentMageId);
                    $logContent = "category path = ".$parentCategory->getPath()."\n";
                    echo $logContent; file_put_contents($this->logPath,$logContent, FILE_APPEND);

                    $category->setPath($parentCategory->getPath());
//                }
                $category->save();

                //save new mage id back to pmp
                if($item->mage_id == 0){
                    $logContent = "save new mage id = ".$category->getId()." back to pmp with ".$item->category_id."\n";
                    echo $logContent; file_put_contents($this->logPath,$logContent, FILE_APPEND);
                    $this->pmpObj->updateCatMageId($category->getId(),$item->category_id);
                }
                $errorMessage[$item->category_id] = array('status'=>'done','msg'=>'none');
                $this->pmpObj->_updateStatus($errorMessage,'category');
            }catch (Exception $e){
                $errorMessage[$item->category_id] = array('status'=>'done','msg'=>$e);
                $this->pmpObj->_updateStatus($errorMessage,'category');
            }

        }
    }

    public function addNewCategories($parentMageId,$item,$level){

        try{
            $cat1_url_key =  strtolower(str_replace(" ", "-", $item->category_name));

            $category = null;
            //new category
            if(empty($item->mage_id)){
                $logContent = "new ".$item->category_name." category created\n";
                echo $logContent; file_put_contents($this->logPath,$logContent, FILE_APPEND);
                $category = new Mage_Catalog_Model_Category();
            }
            //exist category
            else{
                $logContent = "existing ".$item->category_name."(".$item->mage_id.") category updated\n";
                echo $logContent; file_put_contents($this->logPath,$logContent, FILE_APPEND);
                $category = Mage::getModel('catalog/category')->load($item->mage_id);
                if(!$category->getId()){
                    echo "Mage Id ".$item->mage_id." does not exist in Magento\n";
                }
            }

            $category->setName($item->category_name);
            $category->setUrlKey($cat1_url_key);
            $category->setIsActive($item->category_active);
            $category->setIsAnchor(1);

            if($level == 1){
                $category->setIncludeInMenu(1);
                $category->setDisplayMode('PAGE');
            }elseif($level == 2){
                $category->setIncludeInMenu(1);
                $category->setDisplayMode('PRODUCTS_AND_PAGE');
            }else{
                $category->setIncludeInMenu(0);
                $category->setDisplayMode('PRODUCTS_AND_PAGE');
            }

            $category->setAvailableSortByOptions('name', 'price', 'brand');
            $category->setDefaultSortBy('name');
            $category->setDescription($item->category_description);
            $data['image'] = $item->category_colloge;
            $category->addData($data);

            if($item->mage_id == 0){
                $parentCategory = Mage::getModel('catalog/category')->load($parentMageId);
                $logContent = "category path = ".$parentCategory->getPath()."\n";
                echo $logContent; file_put_contents($this->logPath,$logContent, FILE_APPEND);

                $category->setPath($parentCategory->getPath());
            }
            $category->save();

            //save new mage id back to pmp
            if($item->mage_id == 0){
                $logContent = "save new mage id back to pmp\n";
                echo $logContent; file_put_contents($this->logPath,$logContent, FILE_APPEND);
                $this->pmpObj->updateCatMageId($category->getId(),$item->category_id);
            }
            $this->pmpObj->updateSyncCategory($item->category_id,'none');
        }catch (Exception $e){
            $this->pmpObj->updateSyncCategory($item->category_id,$e);
        }
    }

    public function updateCategories($item,$level){

        if(!empty($item->mage_id)){//this step prevents updating categories with mage_id

            try{
                $cat1_url_key =  strtolower(str_replace(" ", "-", $item->category_name));

                $category = null;

                $logContent = "existing ".$item->category_name."(".$item->mage_id.") category updated\n";
                echo $logContent; file_put_contents($this->logPath,$logContent, FILE_APPEND);

                $category = Mage::getModel('catalog/category')->load($item->mage_id);

                if(!$category->getId()){

                    echo "Mage Id ".$item->mage_id." does not exist in Magento\n";

                }else{

                    $category->setName($item->category_name);
                    $category->setUrlKey($cat1_url_key);
                    $category->setIsActive($item->category_active);
                    $category->setIsAnchor(1);

                    if($level == 1){
                        $category->setIncludeInMenu(1);
                        $category->setDisplayMode('PAGE');
                    }elseif($level == 2){
                        $category->setIncludeInMenu(1);
                        $category->setDisplayMode('PRODUCTS_AND_PAGE');
                    }else{
                        $category->setIncludeInMenu(0);
                        $category->setDisplayMode('PRODUCTS_AND_PAGE');
                    }

                    $category->setAvailableSortByOptions('name', 'price', 'brand');
                    $category->setDefaultSortBy('name');
                    $category->setDescription($item->category_description);
                    $data['image'] = $item->category_collage;

                    $category->addData($data);

                    $category->save();
                    $this->pmpObj->updateSyncCategory($item->category_id,'none');
                }

            }catch (Exception $e){

                $this->pmpObj->updateSyncCategory($item->category_id,$e);

            }
        }
    }


    public function assignAttribute($code){
        $objModel = Mage::getModel('eav/entity_setup','core_setup');
        $attributeId = $objModel->getAttributeId('catalog_product', $code);
        $attributeSetId = $objModel->getAttributeSetId('catalog_product','Default');
        $attributeGroupId = $objModel->getAttributeGroupId('catalog_product',$attributeSetId,'General');
        $objModel->addAttributeToSet('catalog_product',$attributeSetId,$attributeGroupId,$attributeId);
    }

    public function createAttribute($code, $label, $attribute_type, $product_type){
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

    public function processCheck($processId,$processLogPath){
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

    public function processUpdate($processLogPath){
        unlink($processLogPath);
    }

    private function _buildManufacturers(){
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

    private function _updateChangedManufacturer($mfc){
        $logContent = "Starting Updating Manufacturer => ".$mfc['name']."\n";
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
        $query = "UPDATE mage_am_shopby_value SET title = :title, meta_title = :meta_title, descr = :descr, meta_descr = :meta_descr,img_small = :img_small, img_medium = :img_medium, img_big = :img_big WHERE value_id = :value_id;";
        $binds = array(
            'descr'                 => $mfc['description'],
            'meta_descr'            => $mfc['description'],
            'title'                 => $mfc['name'],
            'meta_title'            => $mfc['name'],
            'value_id'              => $value_id,
            'img_small'			    => $mfc['code'].'/logo_120x54.jpg',
            'img_medium'		    => $mfc['code'].'/logo_210x210.jpg',
            'img_big'			    => $mfc['code'].'/logo_210x210.jpg'
        );
        try{
            $write->query($query, $binds);
            $this->status = 'done';
        }
        catch(Exception $e){
            $this->status = 'error';
            $this->errorMessage = $e;
        }
        return array('status'=>$this->status,'errorMessage'=>$this->errorMessage);
    }

    private function _addNewManufacturer($mfc){

        $logContent = "Starting Adding an New Manufacturer => ".$mfc['name']."\n";
        echo $logContent;file_put_contents($this->logPath,$logContent, FILE_APPEND);

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
                // 'img_small'			=> $mfc['code'].'/logo_120x54.jpg',
                // 'img_medium'		=> $mfc['code'].'/logo_210x210.jpg',
                // 'img_big'			=> $mfc['code'].'/logo_210x210.jpg',
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

    private function _getParentId($parentSKU){
        $parent = Mage::getModel('catalog/product')->loadByAttribute('sku', $parentSKU);
        if ($parent === FALSE)
        {
//            $this->_saveItemToMage('Product', $this->pmpObj->_getProductRecord($parentSKU));
//            $parent = Mage::getModel('catalog/product')->loadByAttribute('sku', $parentSKU);
            return "error";
        }else{
            return $parent->getId();
        }
    }
}