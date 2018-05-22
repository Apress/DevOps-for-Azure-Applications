#!/usr/bin/php
<?php
require dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'app/Mage.php';

if (!Mage::isInstalled()) {
    echo "Application is not installed.";
    exit;
}

Mage::app('admin')->setUseSessionInUrl(false);

try {
#    Mage::getResourceSingleton('catalogrule/rule')->applyAllRulesForDateRange();
	$resource = Mage::getResourceSingleton('catalogrule/rule');
	$resource->applyAllRulesForDateRange($resource->formatDate(mktime(0,0,0)));
	Mage::app()->removeCache('catalog_rules_dirty');
}
 catch (Exception $e) {
    Mage::printException($e);
}
