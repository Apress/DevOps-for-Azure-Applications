<?php
    DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
    require_once(BASE_PATH.'/../../app/etc/cfg/config.php');
    require_once(BASE_PATH.'/../../app/Mage.php');

    Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );
    date_default_timezone_set('America/Los_Angeles');
    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

    Mage::app('admin');
    Mage::getSingleton("core/session", array("name" => "adminhtml"));
    Mage::register('isSecureArea',true);

    $manufacturerCodes = array('AFE','Auto7','WeatherTech');
    $startTime = microtime(true);

foreach($manufacturerCodes as $manufacturerCode){
//create new brand
    $attr_model = Mage::getModel('catalog/resource_eav_attribute');
    $attr = $attr_model->loadByCode('catalog_product', 'manufacturer');
    $attr_id = $attr->getAttributeId();
    $option['attribute_id'] = $attr_id;

    $option['value']['option'][0] = $manufacturerCode;
    $setup = new Mage_Eav_Model_Entity_Setup('core_setup');
    $setup->addAttributeOption($option);

    $manufacturers = Mage::getResourceModel('eav/entity_attribute_collection')
        ->addFieldToFilter('attribute_code', 'manufacturer')
        ->getFirstItem()->setEntity(Mage::getModel('catalog/product')
        ->getResource())->getSource()->getAllOptions(false);

    $mftrItems = array();
    foreach($manufacturers as $item)
    {
        if($item['label'] == $manufacturerCode){
            $manufacturerId = $item['value'];
            echo "ID of ".$manufacturerCode." = ".$manufacturerId."</br>\n";
        }
    }
    if(!empty($manufacturerId)){
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $read->select()->from(MAGE_TABLE_PREFIX."am_shopby_filter")->where('attribute_id= ?', $attr_id);
        $records = $read->fetchAll($select);

        // Save Detailed Manufacturer Info to Magento Manufacturer Extension
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');

        if($manufacturerCode == 'AFE'){
            $write->insert(MAGE_TABLE_PREFIX."am_shopby_value",
                array(
                    'option_id'			=> $manufacturerId,
                    'filter_id'			=> $records[0]['filter_id'],
                    'is_featured'		=> 0,
                    'img_small'			=> 'AFE/logo_120x54.gif',
                    'img_medium'		=> 'AFE/logo_210x210.gif',
                    'img_big'			=> 'AFE/logo_210x210.gif',
                    'meta_title'		=> 'AFE',
                    'meta_descr'		=> 'advanced FLOW Engineering, inc. (aFe Power) is a manufacturer of high performance after-market parts for the automotive industry. aFe Power specializes in a complete platform of performance parts including; performance air filters, cold air intake systems, exhaust systems, manifolds, intercoolers, turbo chargers, throttle body spacers, tuners and more.',
                    'title'				=> 'AFE',
                    'descr'				=> 'advanced FLOW Engineering, inc. (aFe Power) is a manufacturer of high performance after-market parts for the automotive industry. aFe Power specializes in a complete platform of performance parts including; performance air filters, cold air intake systems, exhaust systems, manifolds, intercoolers, turbo chargers, throttle body spacers, tuners and more.'
                )
            );
        }
        elseif($manufacturerCode == 'Auto7'){
            $write->insert(MAGE_TABLE_PREFIX."am_shopby_value",
                array(
                    'option_id'			=> $manufacturerId,
                    'filter_id'			=> $records[0]['filter_id'],
                    'is_featured'		=> 0,
                    'img_small'			=> 'ATS/logo_120x54.gif',
                    'img_medium'		=> 'ATS/logo_210x210.gif',
                    'img_big'			=> 'ATS/logo_210x210.gif',
                    'meta_title'		=> 'Auto7',
                    'meta_descr'		=> 'Auto 7 is a supplier of original equipment quality automotive parts for Hyundai, Kia, and GM-Daewoo vehicles. The parts are made in Korea and are ISO-14000, QS-9000, or ISO/TS-16949 certified auto parts. The Auto 7 brand of Korean replacement parts covers a wide range or original equipment replacement product categories including suspension, engine parts, filters, steering and more, featuring many parts that aer not typically available from traditional aftermarket brands.',
                    'title'				=> 'Auto7',
                    'descr'				=> 'Auto 7 is a supplier of original equipment quality automotive parts for Hyundai, Kia, and GM-Daewoo vehicles. The parts are made in Korea and are ISO-14000, QS-9000, or ISO/TS-16949 certified auto parts. The Auto 7 brand of Korean replacement parts covers a wide range or original equipment replacement product categories including suspension, engine parts, filters, steering and more, featuring many parts that aer not typically available from traditional aftermarket brands.'
                )
            );
        }
        elseif($manufacturerCode == 'WeatherTech'){
            $write->insert(MAGE_TABLE_PREFIX."am_shopby_value",
                array(
                    'option_id'			=> $manufacturerId,
                    'filter_id'			=> $records[0]['filter_id'],
                    'is_featured'		=> 0,
                    'img_small'			=> 'WTH/logo_120x54.gif',
                    'img_medium'		=> 'WTH/logo_210x210.gif',
                    'img_big'			=> 'WTH/logo_210x210.gif',
                    'meta_title'		=> 'WeatherTech',
                    'meta_descr'		=> 'WeatherTech has been providing the best in automotive protection and vehicle accessories since 1989 by providing the world with the highest quality of vehicle protection products, continually exceeding expectations and setting the bar in the industry. Their investment in leading-edge technology ensures a superior product quality and their commitment to excellence in every floor mat, floor liner, window deflector, and anything else they make.',
                    'title'				=> 'WeatherTech',
                    'descr'				=> 'WeatherTech has been providing the best in automotive protection and vehicle accessories since 1989 by providing the world with the highest quality of vehicle protection products, continually exceeding expectations and setting the bar in the industry. Their investment in leading-edge technology ensures a superior product quality and their commitment to excellence in every floor mat, floor liner, window deflector, and anything else they make.'
                )
            );
        }

        //Save MageID back to PMP
        $lastInsertId = $write->lastInsertId();
        $option_id = $manufacturerId;
        $combine_id = $lastInsertId."-".$option_id;
        echo $combine_id."</br>";

        $connection = new mysqli(CORE_DB_HOST, CORE_DB_USER, CORE_DB_PASSWORD, CORE_DB_DATABASE);
        if ($connection->connect_error){
            echo "say something here...\n";
        }
        $sql = "UPDATE brand SET mage_id = '".$combine_id."' WHERE brand_code = '".$manufacturerCode."'";
        $connection->query($sql);
    }
}