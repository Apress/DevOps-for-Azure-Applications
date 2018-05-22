<?php
class PmpObject
{
    private $dbCore;
    private $logPath;
    public  $status;
    public  $errorMessage;

    public function __construct($logPath)
    {
        $this->_buildParams($logPath);
        $this->_connectToDB();
    }

    public function _buildParams($logPath)
    {
        $this->logPath = $logPath;
    }

    public function _connectToDB()
    {
        $this->dbCore = new mysqli(CORE_DB_HOST, CORE_DB_USER, CORE_DB_PASSWORD, CORE_DB_DATABASE);
        if ($this->dbCore->connect_error){
            file_put_contents($this->logPath,'ERROR: Could not connect to database: '.$this->dbCore->connect_error."\n", FILE_APPEND);
        }
        return TRUE;
    }

    public function _disconnectFromDB()
    {
        return $this->dbCore->close();
    }

    public function _getBrandRecords($brand)
    {
        $result = $this->dbCore->query("SELECT
				mage_id AS mage_id,
				brand_code AS code,
				brand_name AS name,
				brand_description AS description
			FROM brand
			WHERE (brand_name = '{$brand}' || brand_code = '{$brand}')
				AND active_flag = 1
			LIMIT 1"
        );

        if(!$result){
            $logContent = 'Query failed: '.$this->dbCore->error."\n";
            if(LOG == 'cli'){echo $logContent;}else{file_put_contents($this->logPath,$logContent, FILE_APPEND);}
            return "error";
        }

        return $result->fetch_assoc();
    }

    public function _getProductRecords($SKU)
    {
        $brandCode = substr($SKU, 0, 3);
        $product = array();

        // GET DATA FROM MAIN QUERY
        $result = $this->dbCore->query("SELECT
					p.product_code			AS sku,
					p.product_description 	AS description,
					p.product_name 			AS name,
					p.product_feature_text 	AS short_description,
					#p.part_type_name		AS part_type,
					p.brand_code			AS brand_code,
					p.active_flag 			AS status,
					'4'						AS visibility,
					'4'						AS attribute_set_id,
					'grouped'				AS type_id,
					'0'						AS has_options,
					'0'						AS required_options,
					p.product_id			AS legacy_product_id,
					b.brand_name			AS manufacturer
					#						AS universal,
					#						AS part_numbers,
					#product_class_name,
					#search_rank_code,
					#product_image_name,
					#universal_type_name
				FROM product p
					JOIN brand b ON p.brand_code = b.brand_code
				WHERE product_code = '{$SKU}'"
        );

        if(!$result){
            file_put_contents($this->logPath,'Query failed: '.$this->dbCore->error."\n", FILE_APPEND);
        }

        if(!$result->num_rows){
            file_put_contents($this->logPath,'Product not found in database'."\n", FILE_APPEND);
        }

        $product['attributes'] 				= $result->fetch_assoc();
        $product['attributes']['weight'] 	= 2.99;
        $product['attributes']['url_key'] 	= $this->_formatURL($product['attributes']['manufacturer'], $product['attributes']['name']);
        $product['attributes']['url_path'] 	= $product['attributes']['url_key'];
        $product['info']['manufacturer'] 	= $product['attributes']['manufacturer'];
        unset($product['attributes']['manufacturer']);

        // GET STOCK INFO
        $product['stock'] = array(
            'is_in_stock' 				=> 1,
            'use_config_manage_stock'	=> 1
        );

        // GET SKU GALLERY IMAGES
        $result = $this->dbCore->query("SELECT image_name
				FROM product_image
				WHERE product_code = '{$SKU}'
					AND brand_code = '{$brandCode}'
				ORDER BY sort_order_number
				LIMIT 4"
        );

        if(!$result){
            file_put_contents($this->logPath,'Query failed: '.$this->dbCore->error."\n", FILE_APPEND);
        }

        while($item = $result->fetch_assoc())
        {
            $product['media_gallery'][] = $brandCode . DIRECTORY_SEPARATOR
                . pathinfo($item['image_name'], PATHINFO_FILENAME) . '.'
                . pathinfo($item['image_name'], PATHINFO_EXTENSION);
        }

        // GET PART TYPES
        $result = $this->dbCore->query("SELECT
					part_type_name
				FROM product_part_type
				WHERE product_code = '{$SKU}'
				ORDER BY order_number");

        if(!$result){
            file_put_contents($this->logPath,'Query failed: '.$this->dbCore->error."\n", FILE_APPEND);
        }

        while($item = $result->fetch_assoc())
        {
            $product['info']['part_types'][] = $item['part_type_name'];
        }
        // RETURN CONSOLIDATED OBJECT
        return $product;
    }

    public function  _getSkuRecords($SKU)
    {

        $brandCode = substr($SKU, 0, 3);
        $sku = array();

        // GET DATA FROM MAIN QUERY

        $result = $this->dbCore->query("SELECT
					'simple'				AS type_id,
					i.sku 					AS sku,
					i.item_id				AS item_id,
					i.display_name			AS name,
					i.weight				AS weight,
					i.list_price			AS msrp,
					i.map_price				AS map_price,
					i.our_price				AS price,
					i.jobber_price	 		AS jobber_price,
					i.amazon_price	 		AS amazon_price,
					i.ebay_price 			AS ebay_price,
					i.core_deposit_price	AS core_deposit_price,
					i.part_type_name		AS part_type,
					i.upc_code				AS upc,
					i.product_code			AS parent_sku,
					i.asin_code			    AS amazon_asin,
					'-'						AS description,
					'-'						AS short_description,
					'3'						AS visibility,
					'1'						AS enable_googlecheckout,
					i.active_flag 			AS status,
					'2'						AS tax_class_id,
					'0'						AS has_options,
					'0'						AS required_options,
					'4'						AS attribute_set_id,
					b.brand_name			AS manufacturer,
					height					AS height,
					width					AS width,
					depth					AS depth,
					i.selling_unit_number   AS selling_unit_number,
					i.selling_unit_quantity AS selling_unit_quantity
					#condition_code,

					#selling_unit_quantity,
					#request_order_quantity,
					#clean_sku
					#						AS dci_sku_attr,
					#						AS dci_features,
					#						AS legacy_product_id,
					#						AS msrp_enabled
				FROM item i
					JOIN brand b ON i.brand_code = b.brand_code
				WHERE sku = '{$SKU}'"
        );

        if(!$result){
            file_put_contents($this->logPath,'Query failed: '.$this->dbCore->error."\n", FILE_APPEND);
        }

        if(!$result->num_rows){
            file_put_contents($this->logPath,'SKU not found in database'."\n", FILE_APPEND);
        }

        $sku['attributes'] 				= $result->fetch_assoc();
        $sku['attributes']['price']     = $sku['attributes']['price'] + $sku['attributes']['core_deposit_price'];
        $sku['attributes']['msrp']      = $sku['attributes']['msrp'] + $sku['attributes']['core_deposit_price'];

        $sku['attributes']['url_path'] 	= $this->_formatURL($sku['attributes']['manufacturer'], $sku['attributes']['name'], $sku['attributes']['sku']);

        $sku['attributes']['url_key'] 	= $sku['attributes']['url_path'];
        $sku['info']['manufacturer'] 	= $sku['attributes']['manufacturer'];
        $sku['info']['part_types'][]    = $sku['attributes']['part_type'];
        unset($sku['attributes']['manufacturer']);
        unset($sku['attributes']['part_type']);

        $result = $this->dbCore->query("SELECT stock_quantity AS qty
                    FROM vendor_inventory
                    WHERE sku = '{$SKU}'"
        );

        if(!$result){
            file_put_contents($this->logPath,'Query failed: '.$this->dbCore->error."\n", FILE_APPEND);
        }

        if(!$result->num_rows){
            file_put_contents($this->logPath,'SKU not found in database'."\n", FILE_APPEND);
        }

        $items = $result->fetch_assoc();

        $qty = 0;
        foreach($items as $item ){
            $qty = $qty + $item['qty'];
        }

        $selling_unit_qty = isset($sku['attributes']['selling_unit_quantity'])? $sku['attributes']['selling_unit_quantity'] : 1;
        $sku['stock'] = array(
            'qty'						=> $qty,
            'use_config_manage_stock'	=> 1,
            'qty_increments'			=> $selling_unit_qty,
            'use_config_enable_qty_inc'	=> 0,
            'use_config_qty_increments'	=> 0,
            'enable_qty_increments'		=> 1,
            'is_in_stock' 				=> 1
        );

        // GET SKU GALLERY IMAGES

        $result = $this->dbCore->query("SELECT image_name
			FROM item_image
				WHERE sku = '{$SKU}'
					AND brand_code = '{$brandCode}'
				ORDER BY sort_order_number
				LIMIT 4"
        );

        if(!$result){
            file_put_contents($this->logPath,'Query failed: '.$this->dbCore->error."\n", FILE_APPEND);
        }

        while($item = $result->fetch_assoc())
        {
            $sku['media_gallery'][] = $brandCode . DIRECTORY_SEPARATOR
                .pathinfo($item['image_name'], PATHINFO_FILENAME) . '.'
                . pathinfo($item['image_name'], PATHINFO_EXTENSION);
        }
        return $sku;
    }

    public function _getOptionValues($attr)
    {
        $attribute = Mage::getModel('eav/entity_attribute');
        $attribute->loadByCode(4, $attr);

        $valuesCollection = Mage::getResourceModel('eav/entity_attribute_option_collection')
            ->setAttributeFilter($attribute->getId())
            ->setStoreFilter(Mage_Core_Model_App::ADMIN_STORE_ID, false)
            ->load();

        $values = array();
        foreach ($valuesCollection as $item) {
            $values[$item->getValue()] = $item->getId();
        }

        return $values;
    }

    public function _getChangedItems()
    {
        $limit = 500;

        $brand		= $this->_processChangedItems('Brand',$limit);
        $limit = $limit - count($brand);
        $product	= $this->_processChangedItems('Product',$limit);
        $limit = $limit - count($product);
        $sku		= $this->_processChangedItems('SKU',$limit);

        return array('items' => array(
            'Brand'	=> $brand,
            'Product'	=> $product,
            'SKU'		=> $sku
        ),
            'count' => count($sku) + count($product) + count($brand)
        );
    }

    public function _processChangedItems($type,$limit)
    {
        // Return Manual cli Products/SKUs, if any
        if(!empty($this->manualProducts))
        {
            $dump = var_export($this->manualProducts,true);
            if(DEBUG_MODE == 'ON'){ file_put_contents(BASE_PATH.'/../../var/log/'.$this->logName,"========== Change Items Array ============\n".$dump."\n", FILE_APPEND); }
            return isset($this->manualProducts[strtolower($type)])
                ? $this->manualProducts[strtolower($type)] : array();
        }

        $result = $this->dbCore->query("SELECT key_value
			FROM deploy
			WHERE target_system_name = 'Web'
				AND type_name = '{$type}'
				AND deploy_timestamp IS NULL
				AND error_message_text IS NULL
			ORDER BY type_name,key_value   ASC
			LIMIT $limit"
        );

        if(!$result)
            if(DEBUG_MODE == 'ON'){ file_put_contents(BASE_PATH.'/../../var/log/'.$this->logName,'Query failed: '.$this->dbCore->error."\n", FILE_APPEND); }

        $items = array();
        while($item = $result->fetch_object())
        {
            $items[] = $item->key_value;
        }

        // RETURN CONSOLIDATED OBJECT
        return $items;
    }

    public function saveMageIdBrand($array)
    {
        try{
            $this->dbCore->query("UPDATE brand SET mage_id = '{$array['combine_id']}' WHERE brand_code = '{$array['brand_code']}';");
            $this->status = 'Synchronized';
        }
        catch(Exception $e){
            $this->status = 'Not Synchronized';
            $this->errorMessage = $e;
        }
        return array('status'=>$this->status,'errorMessage'=>$this->errorMessage);
    }

    public function getCategoryId($part_type)
    {
        $result = $this->dbCore->query("SELECT category_id FROM part_type_category WHERE  part_type_name ='".$part_type."'");
        $pmp_ids = $result->fetch_assoc();
        return $pmp_ids;
    }

    public function getCategoryMageId($pmp_id)
    {
        $result = $this->dbCore->query("SELECT mage_id FROM category WHERE id = ".$pmp_id);
        $mage_ids = $result->fetch_assoc();
        return $mage_ids;
    }

    public function getImageSync($SKU)
    {
        //if image changes are detected
        $result = $this->dbCore->query("SELECT process_image FROM deploy WHERE key_value ='".$SKU."'");

        if(!$result){
            $logContent = 'Query failed: '.$this->dbCore->error."\n";
            if(LOG == 'cli'){echo $logContent;}else{file_put_contents($this->logPath,$logContent, FILE_APPEND);}
        }

        $image_modified = 0;

        while($item = $result->fetch_object())
        {
            $image_modified = $item->process_image;
        }
        return $image_modified;
    }

    private function _getParentId($parentSKU)
    {
        $parent = Mage::getModel('catalog/product')->loadByAttribute('sku', $parentSKU);

        if ($parent === FALSE)
        {
            file_put_contents($this->logPath,"--- Parent Product not found for SKU. Begin Automatic Product Deployment ---"."\n", FILE_APPEND);
            file_put_contents($this->logPath,"---".$parentSKU."---"."\n", FILE_APPEND);

            $this->_saveItemToMage('Product', $this->_getProductRecords($parentSKU));

            file_put_contents($this->logPath,"--- End Automatic Product Deployement ---"."\n", FILE_APPEND);

            $parent = Mage::getModel('catalog/product')->loadByAttribute('sku', $parentSKU);
        }
        return $parent->getId();
    }

    private function _formatURL($brand, $name, $sku = null)
    {
        return strtolower(
            preg_replace("/[ |-]+/", "-",
                trim(preg_replace("/[^a-zA-Z0-9-]/", " ",
                        $brand
                            .'-'
                            .($sku ? '-' . substr($sku, strpos($sku, ':')+1) : '')
                            .'-'
                            .preg_replace("/^{$brand}/", "", $name))
                )
            )
        );
    }

    public function _updateStatus($key, $status, $errorMessage,$date)
    {
        // Gathering Parameters
        $deployedAt =  $date;

        if ($status == 'Synchronization Failed' && empty($errorMessage))
            $errorMessage = 'Unknown Error';

        // Update database
        $result = $this->dbCore->query("UPDATE deploy
			SET status_timestamp	= CURRENT_TIMESTAMP(0),
				status_text			= '{$status}',
				error_message_text	= '{$errorMessage}',
				deploy_timestamp	= '{$deployedAt}',
				process_image = NULL,
				process_id = NULL
			WHERE 1=1
				AND key_value 			= '{$key}'
				AND target_system_name	= 'Web'
				AND deploy_timestamp IS NULL"
        );

        if(!$result){
            $logContent = 'Query failed: '.$this->dbCore->error."\n";
            if(LOG == 'cli'){echo $logContent;}else{file_put_contents($this->logPath,$logContent, FILE_APPEND);}
        }

        return TRUE;
    }
}