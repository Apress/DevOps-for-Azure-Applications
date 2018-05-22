#!/usr/bin/php
<?php
//require dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'app/Mage.php';

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));

echo "Base Path: ".BASE_PATH;
require_once(BASE_PATH."/../../../app/Mage.php");

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
        echo "Starting " . $key . " Reindex\n";
        $process = Mage::getModel('index/process')->load($i);
        $process->reindexAll();
    }
}
catch (Exception $e)
{
    Mage::printException($e);
}
