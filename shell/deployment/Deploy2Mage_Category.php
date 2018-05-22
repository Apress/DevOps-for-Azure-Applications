<?php
$isCli = php_sapi_name();
$backURL = $_POST['backURL'];

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));		//Magento Root Directory
DEFINE('LOG',$isCli);

if($isCli != 'cli'){
    switch ($_SERVER['HTTP_ORIGIN']) {
        case 'http://'.$backURL: case 'https://'.$backURL:
        header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
        header('Access-Control-Max-Age: 1000');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        break;
    }
}

require_once(BASE_PATH.'/../../app/etc/cfg/config.php');
require_once(BASE_PATH.'/../../app/Mage.php');

Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$date = new DateTime();
$logName = $date->format("Ymd_his");

$logFolder = BASE_PATH.'/../../var/log/category_sync/';
$logPath = $logFolder."category_sync_".$logName.".log";
if(!file_exists($logFolder)){
    mkdir($logFolder);
}

$logContent = "========start process=========\n";
echo $logContent;
if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

delete_categories($logPath);
sync_categories($logPath);

$logContent = "========end process=========\n";
echo $logContent;
if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

/*================================
* Run before uploading new records
==================================*/
function sync_categories($logPath){

    $logContent = "+++++++Start Sync+++++++\n";
    echo $logContent;
    if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

    /*
     * Level 1
     * */
    $dbCore = new mysqli(CORE_DB_HOST, CORE_DB_USER, CORE_DB_PASSWORD, CORE_DB_DATABASE);

    $sql = "SELECT id,full_category_name,category_description,image_name,active_flag,mage_id FROM category WHERE parent_id IS NULL || parent_id = 0 ORDER BY full_category_name";
    $cat1_result = $dbCore->query($sql);

    if(!$cat1_result){
        echo "ERORR...";
    }
    while($cat1 = $cat1_result->fetch_object())
    {
        $cat1_parentId = '2';
        $cat1_url_key =  strtolower(str_replace(" ", "-", $cat1->full_category_name));

        $logContent = $logContent = "~~~~~~~~~ Level 1 ~~~~~~~~~ with parent id = NULL\n";
        echo $logContent;
        if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

        $category1 = null;
        //new category
        if($cat1->mage_id == 0){
            $logContent = "New a category = ".$cat1->full_category_name."\n";
            echo $logContent;
            if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}
            $category1 = new Mage_Catalog_Model_Category();
        }
        //exist category
        else{
            $logContent = "Update a existing category = ".$cat1->full_category_name."(".$cat1->mage_id.")"."\n";
            echo $logContent;
            if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

            $category1 = Mage::getModel('catalog/category')->load($cat1->mage_id);
        }

        $category1->setName($cat1->full_category_name);
        $category1->setUrlKey($cat1_url_key);
        $category1->setIsActive($cat1->active_flag);
        $category1->setIsAnchor(1);
        $category1->setDisplayMode('PAGE');
        $category1->setIncludeInMenu(1);
        $category1->setAvailableSortByOptions('name', 'price', 'brand');
        $category1->setDefaultSortBy('name');
        $category1->setDescription($cat1->category_description);
        $data['image'] = $cat1->image_name;
        $category1->addData($data);

        if($cat1->mage_id == 0){
            $parentCategory1 = Mage::getModel('catalog/category')->load($cat1_parentId);
            $category1->setPath($parentCategory1->getPath());
        }
        $category1->save();

        //save new categoryID back to pmp
        if($cat1->mage_id == 0){
            $sql = "update category set mage_id = ".$category1->getId()." where id =".$cat1->id;
            $dbCore->query($sql);
        }

        /*
         * Level 2
         * */

        $sql = "SELECT id,full_category_name,category_description,image_name,active_flag,mage_id FROM category WHERE parent_id = ".$cat1->id." ORDER BY full_category_name";
        $cat2_result = $dbCore->query($sql);

        if(!$cat2_result){
            echo "ERORR...";
        }
        while($cat2 = $cat2_result->fetch_object())
        {
            $logContent = "~~~~~~~~~ Level 2 ~~~~~~~~~ with parent id = ".$cat1->id."\n";
            echo $logContent;
            if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

            $parentId = $category1->getId();
            $cat2_url_key =  strtolower(str_replace(" ", "-", $cat2->full_category_name));

            $category2 = null;
            //exist category
            if($cat2->mage_id == 0){
                $logContent = "New a category = ".$cat2->full_category_name."\n";
                echo $logContent;
                if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

                $category2 = new Mage_Catalog_Model_Category();
            }
            //new category
            else{
                $logContent = "Update a existing category = ".$cat2->full_category_name."(".$cat2->mage_id.")"."\n";
                echo $logContent;
                if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

                $category2 = Mage::getModel('catalog/category')->load($cat2->mage_id);
            }

            $category2->setName($cat2->full_category_name);
            $category2->setUrlKey($cat2_url_key);
            $category2->setIsActive($cat2->active_flag);
            $category2->setIsAnchor(1);
            $category2->setDisplayMode('PRODUCTS_AND_PAGE');
            $category2->setIncludeInMenu(1);
            $category2->setAvailableSortByOptions('name', 'price', 'brand');
            $category2->setDefaultSortBy('name');
            $category2->setDescription($cat2->category_description);
            $data['image'] = $cat2->image_name;
            $category2->addData($data);

            if($cat2->mage_id == 0){
                $parentCategory2 = Mage::getModel('catalog/category')->load($parentId);
                $category2->setPath($parentCategory2->getPath());
            }
            $category2->save();
            if($cat2->mage_id == 0){
                $sql = "update category set mage_id = ".$category2->getId()." where id =".$cat2->id;
                $dbCore->query($sql);
            }

            /*
           * Level 3
           * */
            $sql = "SELECT id,full_category_name,category_description,image_name,active_flag,mage_id FROM category WHERE parent_id = ".$cat2->id." ORDER BY full_category_name";
            $cat3_result = $dbCore->query($sql);

            if(!$cat3_result){
                echo "ERORR...";
            }
            while($cat3 = $cat3_result->fetch_object())
            {
                $logContent = $logContent = "~~~~~~~~~ Level 3 ~~~~~~~~~ with parent id = ".$cat2->id."\n";
                echo $logContent;
                if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

                $parentId = $category2->getId();
                $cat3_url_key =  strtolower(str_replace(" ", "-", $cat3->full_category_name));

                $category3 = null;
                //new category
                if($cat3->mage_id == 0){
                    $logContent = "New a category = ".$cat3->full_category_name."\n";
                    echo $logContent;
                    if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

                    $category3 = new Mage_Catalog_Model_Category();
                }
                //exist category
                else{
                    $logContent = "Update a existing category = ".$cat3->full_category_name."(".$cat3->mage_id.")"."\n";
                    echo $logContent;
                    if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

                    $category3 = Mage::getModel('catalog/category')->load($cat3->mage_id);
                }

                $category3->setName($cat3->full_category_name);
                $category3->setUrlKey($cat3_url_key);
                $category3->setIsActive($cat3->active_flag);
                $category3->setIsAnchor(1);
                $category3->setDisplayMode('PRODUCTS_AND_PAGE');
                $category3->setIncludeInMenu(0);
                $category3->setAvailableSortByOptions('name', 'price', 'brand');
                $category3->setDefaultSortBy('name');
                $category3->setDescription($cat3->category_description);
                $data['image'] = $cat3->image_name;
                $category3->addData($data);
                if($cat3->mage_id == 0){
                    $parentCategory3 = Mage::getModel('catalog/category')->load($parentId);
                    $category3->setPath($parentCategory3->getPath());
                }
                $category3->save();

                if($cat3->mage_id == 0){
                    $sql = "update category set mage_id = ".$category3->getId()." where id =".$cat3->id;
                    $dbCore->query($sql);
                }
            }
            unset($category3);
        }
        unset($category2);
    }
    unset($category1);

    $logContent = "+++++++End Sync+++++++\n";
    echo $logContent;
    if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}
}

function delete_categories($logPath){
    $pmp_category_array = array();
    $www_category_array = array();

    $dbCore = new mysqli(CORE_DB_HOST, CORE_DB_USER, CORE_DB_PASSWORD, CORE_DB_DATABASE);

    $sql = "SELECT mage_id FROM category WHERE 1";
    $cat1_result = $dbCore->query($sql);

    if(!$cat1_result){
        $logContent = "PMP DB connection error\n";
        echo $logContent;
        if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}
    }
    while($cat1 = $cat1_result->fetch_object())
    {
        array_push($pmp_category_array,$cat1->mage_id);
    }

    $rootCategory = Mage::getModel('catalog/category')->load(2);
    $categories = $rootCategory->getChildrenCategories();
    foreach ($categories as $category_1) {
        array_push($www_category_array,$category_1->getId());

        $categories = $category_1->getChildrenCategories();
        foreach ($categories as $category_2) {
            array_push($www_category_array,$category_2->getId());

            $categories = $category_2->getChildrenCategories();
            foreach ($categories as $category_3) {
                array_push($www_category_array,$category_3->getId());
            }
        }
    }

    $logContent = "=========Start deleting categories=========\n";
    echo $logContent;
    if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}

    foreach($www_category_array as $www_category){

        if (!in_array($www_category, $pmp_category_array, true)) {
            $category = Mage::getModel('catalog/category')->load($www_category);
            $category->delete();
        }
    }

    $logContent = "=========End deleting categories=========\n";
    echo $logContent;
    if(LOG == 'cli'){echo $logContent;}else{file_put_contents($logPath,$logContent, FILE_APPEND);}
}

function move_category_images_to_magento($logPath){
    $sourcePath = IMG_MEDIA_PATH.'categories';
    $category_folder = WWW_MEDIA_PATH.'catalog';
    $destinationPath = WWW_MEDIA_PATH.'catalog/category';

    if(!file_exists($sourcePath)){
        return;
    }

    if(!file_exists($category_folder)){
        mkdir($category_folder);
    }
    if (!file_exists($destinationPath)) {
        //Recursive Copy Files under Certain Path
        $dir = opendir($sourcePath);
        @mkdir($destinationPath);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($sourcePath . '/' . $file) ) {
                    recurse_copy($sourcePath . '/' . $file,$destinationPath . '/' . $file);
                }
                else {
                    copy($sourcePath . '/' . $file,$destinationPath . '/' . $file);
                }
            }
        }
        closedir($dir);
        mkdir($destinationPath);
    }
    else{
        $dir = opendir($sourcePath);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($sourcePath . '/' . $file) ) {
                    recurse_copy($sourcePath . '/' . $file,$destinationPath . '/' . $file);
                }
                else {
                    copy($sourcePath . '/' . $file,$destinationPath . '/' . $file);
                }
            }
        }
        closedir($dir);

    }
}
