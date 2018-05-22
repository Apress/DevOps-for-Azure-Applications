<?php
class PmpObject
{
    public  $status;
    public  $errorMessage;
    public  $pmpURL;
    public  $user_id;

    public function __construct($logPath, $pmpURL, $user_id){
        $this->_buildParams($logPath,$pmpURL);
        $this->user_id = $user_id;
    }

    public function _buildParams($logPath,$pmpURL){
        $this->pmpURL = $pmpURL;
    }

    public function _getBrandRecord(){
        $targetURL = $this->pmpURL.'api/getBrand/'.$this->user_id;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    public function _getProductRecord($items){
        $fields = array('product'=>json_encode($items));

        $targetURL = $this->pmpURL.'api/getProduct/'.$this->user_id;            

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    public function  _getSkuRecord($items){
        $fields = array('sku'=>json_encode($items));
        $targetURL = $this->pmpURL.'api/getSku/'.$this->user_id;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    public function _getChangedItems($type,$limit = 50000){
        if ($type == "brand"){
            $brand		= $this->_processChangedItems('brand',$limit);
            $brand = json_decode($brand);            
        }else{
            $brand = array();
        }
        $limit = $limit - count($brand);

        if($type == "sku_product"){
            $product	= $this->_processChangedItems('product',$limit);
            $product = json_decode($product);
        }else{
            $product = array();
        }                
        $limit = $limit - count($product);

        if($type == "sku_product"){
            $sku		= $this->_processChangedItems('sku',$limit);
            $sku = json_decode($sku);
        }else{
            $sku = array();
        }
        
        return array('items' => array(
            'brand'	=> $brand,
            'product'	=> $product,
            'sku'		=> $sku
        ),
            'count' => count($sku) + count($product) + count($brand)
        );
    }

    public function _processChangedItems($type,$limit){
        $targetURL = $this->pmpURL.'api/getChangedList/'.$this->user_id."/".$type.'/'.$limit;
        echo $targetURL;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    /* sync Inventory */
    public function getChangedInventory(){
        $targetURL = $this->pmpURL.'api/getChangedInventory/'.$this->user_id;
        echo $targetURL."\n";
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);        
        return $output;
    }

    public function updateSyncInventory($sku_codes){        

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);

        $fields = array('errors'=>$sku_codes);
        $targetURL = $this->pmpURL.'api/updateSyncInventory/'.$this->user_id;
        echo $targetURL."\n";
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    /* sync Image */
    public function getChangedImage($type){
        $targetURL = $this->pmpURL.'api/getChangedImage/'.$this->user_id.'/'.$type;
        echo $targetURL."\n</br>";
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    public function updateSyncImage($sync_data_keys, $type){
        $targetURL = $this->pmpURL.'api/updateSyncImage/'.$this->user_id."/".$type;
        echo $targetURL."\n</br>";
        
        $fields = array('keys'=>serialize($sync_data_keys));
       // var_dump($fields);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    /* Sync Universal */
    public function getChangedUniversal($type){
        $targetURL = $this->pmpURL.'api/getChangedUniversal/'.$this->user_id.'/'.$type;
        echo $targetURL."</br>";
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    public function updateSyncUniversal($sync_data_keys, $type){
        $targetURL = $this->pmpURL.'api/updateSyncUniversal/'.$this->user_id."/".$type;
        echo $targetURL;
        $fields = array('keys'=>serialize($sync_data_keys));
        var_dump($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);        
        return $output;
    }
    public function saveMageIdBrand($array){
        $targetURL = $this->pmpURL.'api/setBrandMageId/'.$array['brand_code'].'/'.$array['combine_id'].'/'.$this->user_id;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        $output = json_decode($output);
        $temp = explode("-",$output);
        $status = $temp[0];
        $msg = $temp[1];

        return array('status'=>$status,'errorMessage'=>$msg);
    }

    public function _getImageChangedList(){
        $limit = 2000;
        $targetURL = $this->pmpURL.'api/getImageChangedList/'.$limit.'/'.$this->user_id;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public function getImagePath($type,$keys){
        $targetURL = $this->pmpURL.'api/getImagePath/'.$this->user_id;
        $fields = array('type'=>$type,'keys'=>json_encode($keys));
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public function getCategory($type){
        $targetURL = $this->pmpURL.'api/getCategory/'.$this->user_id."/".$type;
        echo $targetURL;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }    

    public function findMageId($category_id){
        $targetURL = $this->pmpURL.'api/findMageId/'.$this->user_id."/".$category_id;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public function updateCatMageId($mageId,$categoryId){
        $targetURL = $this->pmpURL.'api/updateCatMageId/'.$this->user_id."/".$mageId."/".$categoryId;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public function updateSyncCategory($category_id,$status){
        $targetURL = $this->pmpURL.'api/updateSyncCategory/'.$this->user_id."/".$category_id."/".$status;
        echo $targetURL;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    private function _formatURL($brand, $name, $sku = null){
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

    public function _updateStatus($errorMessage, $type){

        $fields = array('error'=>json_encode($errorMessage));
        $targetURL = $this->pmpURL.'api/updateSync/'.$this->user_id."/".$type;
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public function updateImageStatus($result){
        $targetURL = $this->pmpURL.'api/updateImageStatus/'.$this->user_id;;

        $fields = array('error'=>serialize($result));

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }


    public function _getRowNumber($table){
        $targetURL = $this->pmpURL.'api/getRowNumber/'.$table;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }


    public function _getRowNumberById($user_id, $table){
        $targetURL = $this->pmpURL.'api/getRowNumberById/'.$this->user_id.'/'.$table;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }
    
    public function _getTree($offset, $limit){
        $targetURL = $this->pmpURL.'api/getTree/'.$offset.'/'.$limit;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }
    
     public function _getFitment($user_id, $offset, $limit){
        $targetURL = $this->pmpURL.'api/getFitment/'.$this->user_id.'/'.$offset.'/'.$limit;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

     public function _getFitNotes($user_id, $offset, $limit){
        $targetURL = $this->pmpURL.'api/getFitNotes/'.$this->user_id.'/'.$offset.'/'.$limit;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    /* order */
    public function _getLatestOrderId(){
        $targetURL = $this->pmpURL.'api/getLatestOrderId/'.$this->user_id;
        echo $targetURL."\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public function _updateOrder($order){

        $fields = array('order'=>serialize($order));
        // var_dump($fields);
        $targetURL = $this->pmpURL.'api/updateOrder/'.$this->user_id;
        echo $targetURL."\n";
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    public function _updateOrderItems($orderItems,$order_id){

        $fields = array('orderItems'=>serialize($orderItems));
        $targetURL = $this->pmpURL.'api/updateOrderItems/'.$this->user_id."/".$order_id;
        echo $targetURL."\n";
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return unserialize($output);
    }

    public function _getOrderStatus($order_id){
        $targetURL = $this->pmpURL.'api/getOrderStatus/'.$this->user_id."/".$order_id;
        echo $targetURL."\n";
        $ch = curl_init();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return unserialize($output);
    }

    public function _getOrderTracking($order_id){
        $targetURL = $this->pmpURL.'api/getOrderTracking/'.$this->user_id."/".$order_id;
        echo $targetURL."\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return unserialize($output);
    }

    public function _getIsOrderCreated($order_id){
        $targetURL = $this->pmpURL.'api/getIsOrderCreated/'.$this->user_id."/".$order_id;
        echo $targetURL."\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return unserialize($output);
    }
}