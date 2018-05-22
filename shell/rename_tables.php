#!/usr/bin/php
<?php
DEFINE('OLD_PREFIX', 'magemage');
DEFINE('NEW_PREFIX', 'mage');

require dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'app/Mage.php';

Mage::app('default');
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

$db = Mage::getSingleton('core/resource')->getConnection('core_write');

foreach($db->fetchCol('SHOW TABLES') AS $old_table)
{
	$new_table = preg_replace("/^".OLD_PREFIX."/", NEW_PREFIX, $old_table);
	if($new_table != $old_table)
	{
		echo "Renaming $old_table to $new_table\n";
		$db->query("RENAME TABLE `$old_table`  TO `$new_table`");
	}
}