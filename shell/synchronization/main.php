<?php
require_once("lib/pmpModel.php");

$client_info_path = '../../app/etc/cfg/client_info.conf';
$client_info = file_get_contents($client_info_path);
$client_info_array = explode(";", $client_info);
$user_id = explode("=", $client_info_array [0])[1];
$pmpURL = explode("=", $client_info_array [1])[1];
$logPath = "";
$proc_file_check_path = getcwd()."/pcheck/";

$proc_file_newstatus = 'new.txt';
$proc_file_compstatus = 'comp.txt';

$category_proc_filename = 'categoryproc';	
$brand_proc_filename = 'brandproc';	
$product_proc_filename = 'productproc';	
$inventory_proc_filename = 'inventoryproc';	
$brandimage_proc_filename = 'brandimageproc';	
$categoryimage_proc_filename = 'categoryimageproc';	
$logoimage_proc_filename = 'logoimageproc';	
$productimage_proc_filename = 'productimageproc';	
$skuimage_proc_filename = 'skuimageproc';	
$universal_proc_filename = 'universalproc';	


$pmpObj = new PmpObject($logPath,$pmpURL,$user_id);
$reIndexFlg = FALSE;




//Process Category
echo "\n***changed category***\n";
echo "Scandir = ".$proc_file_check_path."\n";
$proc_complete_check = $scanned_directory = array_diff(scandir($proc_file_check_path), array('..', '.'));
$prior_proc_file = $proc_file_check_path.$category_proc_filename.$proc_file_newstatus;
if ($proc_complete_check == FALSE)//
{
  
  $handle = fopen($prior_proc_file, "x+");
  fclose($handle);
  
  require_once("category/syncCategory.php");
  
}
else 
{
//IF categoryproccompnew file still exists means category sync is still processing.
	if(file_exists($prior_proc_file))
	{
		require_once("category/syncCategory.php");
	}
}





//Process Brand
$proc_complete_check = $scanned_directory = array_diff(scandir($proc_file_check_path), array('..', '.'));
$prior_proc_file = $proc_file_check_path.$category_proc_filename.$proc_file_compstatus;
if (!$proc_complete_check == FALSE )
{
	if(file_exists($prior_proc_file))
	{
		//DElETE prior processing file
		unlink($prior_proc_file);
		
		//CREATE new processing file for Brand
		$prior_proc_file= $proc_file_check_path.$brand_proc_filename.$proc_file_newstatus;
		$handle = fopen($prior_proc_file, "x+");
		fclose($handle);
		$changedItems = $pmpObj->_getChangedItems("brand");
		
		echo "\n***changed brand***\n";
		echo "Changed Items = ".count($changedItems['items']['brand'])."\n";
		if(count($changedItems['items']['brand']) > 0){
			require_once("product/syncBrand.php");
		}else 
		{
			//Tell Other scripts that this script is successfully complete so the next one can run
			$curr_proc_file_new = $proc_file_check_path.$brand_proc_filename.$proc_file_newstatus;
			$curr_proc_file_comp = $proc_file_check_path.$brand_proc_filename.$proc_file_compstatus;
			rename($curr_proc_file_new, $curr_proc_file_comp);
		}
	}
	Else
	{
		//IF categoryproccompnew file still exists means category sync is still processing.
		if(file_exists($proc_file_check_path.$brand_proc_filename.$proc_file_newstatus))
		{
			$changedItems = $pmpObj->_getChangedItems("brand");
			
			echo "\n***changed brand***\n";
			echo "Changed Items = ".count($changedItems['items']['brand'])."\n";
			if(count($changedItems['items']['brand']) > 0){
				require_once("product/syncBrand.php");
			}else
			{
				//Tell Other scripts that this script is successfully complete so the next one can run
				$curr_proc_file_new = $proc_file_check_path.$brand_proc_filename.$proc_file_newstatus;
				$curr_proc_file_comp = $proc_file_check_path.$brand_proc_filename.$proc_file_compstatus;
				rename($curr_proc_file_new, $curr_proc_file_comp);
			}
		}
	}
}





//Process Product
$proc_complete_check = $scanned_directory = array_diff(scandir($proc_file_check_path), array('..', '.'));
$prior_proc_file = $proc_file_check_path.$brand_proc_filename.$proc_file_compstatus;
if (!$proc_complete_check == FALSE )
{
	if(file_exists($prior_proc_file))
	{
		//DElETE prior processing file
		unlink($prior_proc_file);
		
		//CREATE new processing file for Product
		$prior_proc_file= $proc_file_check_path.$product_proc_filename.$proc_file_newstatus;
		$handle = fopen($prior_proc_file, "x+");
		fclose($handle);
		
		$changedItems = $pmpObj->_getChangedItems("sku_product",1);
		echo "\n***changed sku and product***\n";
		echo "Changed Items Product = ".count($changedItems['items']['product'])."\n";
		echo "Changed Items Sku = ".count($changedItems['items']['sku'])."\n";
		if(count($changedItems['items']['product']) > 0 || count($changedItems['items']['sku']) > 0){
			require_once("product/syncProduct.php");
			$reIndexFlg = TRUE;
		}else
		{
			//Tell Other scripts that this script is successfully complete so the next one can run
			$curr_proc_file_new = $proc_file_check_path.$product_proc_filename.$proc_file_newstatus;
			$curr_proc_file_comp = $proc_file_check_path.$product_proc_filename.$proc_file_compstatus;
			rename($curr_proc_file_new, $curr_proc_file_comp);
		}
	}
	Else
	{
		//IF categoryproccompnew file still exists means product sync is still processing.
		if(file_exists($proc_file_check_path.$product_proc_filename.$proc_file_newstatus))
		{
			$changedItems = $pmpObj->_getChangedItems("sku_product",1);
			echo "\n***changed sku and product***\n";
			echo "Changed Items Product = ".count($changedItems['items']['product'])."\n";
			echo "Changed Items Sku = ".count($changedItems['items']['sku'])."\n";
			if(count($changedItems['items']['product']) > 0 || count($changedItems['items']['sku']) > 0){
				require_once("product/syncProduct.php");
				$reIndexFlg = TRUE;
			}else
			{
				//Tell Other scripts that this script is successfully complete so the next one can run
				$curr_proc_file_new = $proc_file_check_path.$product_proc_filename.$proc_file_newstatus;
				$curr_proc_file_comp = $proc_file_check_path.$product_proc_filename.$proc_file_compstatus;
				rename($curr_proc_file_new, $curr_proc_file_comp);
			}
		}
	}
}



//Process Inventory
$proc_complete_check = $scanned_directory = array_diff(scandir($proc_file_check_path), array('..', '.'));
$prior_proc_file = $proc_file_check_path.$product_proc_filename.$proc_file_compstatus;
if (!$proc_complete_check == FALSE )
{
	if(file_exists($prior_proc_file))
	{
		//DElETE prior processing file
		unlink($prior_proc_file);
		
		//CREATE new processing file for inventory
		$prior_proc_file= $proc_file_check_path.$inventory_proc_filename.$proc_file_newstatus;
		$handle = fopen($prior_proc_file, "x+");
		fclose($handle);
		
		$changedItems = json_decode($pmpObj->getChangedInventory());
		echo "\n***changed inventory***\n";
		echo "Changed Items = ".count($changedItems)."\n";
		if(count($changedItems) > 0){
			require_once("inventory/syncInventory.php");
			$reIndexFlg = TRUE;
		}else
		{
			//Tell Other scripts that this script is successfully complete so the next one can run
			$curr_proc_file_new = $proc_file_check_path.$inventory_proc_filename.$proc_file_newstatus;
			$curr_proc_file_comp = $proc_file_check_path.$inventory_proc_filename.$proc_file_compstatus;
			rename($curr_proc_file_new, $curr_proc_file_comp);
		}
	}
	Else
	{
		//IF file still exists means inventory sync is still processing.
		if(file_exists($proc_file_check_path.$inventory_proc_filename.$proc_file_newstatus))
		{
			$changedItems = json_decode($pmpObj->getChangedInventory());
			echo "\n***changed inventory***\n";
			echo "Changed Items = ".count($changedItems)."\n";
			if(count($changedItems) > 0){
				require_once("inventory/syncInventory.php");
				$reIndexFlg = TRUE;
			}else
			{
				//Tell Other scripts that this script is successfully complete so the next one can run
				$curr_proc_file_new = $proc_file_check_path.$inventory_proc_filename.$proc_file_newstatus;
				$curr_proc_file_comp = $proc_file_check_path.$inventory_proc_filename.$proc_file_compstatus;
				rename($curr_proc_file_new, $curr_proc_file_comp);
			}
		}
	}
}






//Process BrandImage
$proc_complete_check = $scanned_directory = array_diff(scandir($proc_file_check_path), array('..', '.'));
$prior_proc_file = $proc_file_check_path.$inventory_proc_filename.$proc_file_compstatus;
if (!$proc_complete_check == FALSE )
{
	if(file_exists($prior_proc_file))
	{
		//DElETE prior processing file
		unlink($prior_proc_file);
		
		//CREATE new processing file for categoryimage
		$prior_proc_file= $proc_file_check_path.$brandimage_proc_filename.$proc_file_newstatus;
		$handle = fopen($prior_proc_file, "x+");
		fclose($handle);
		
		$changedItems = $pmpObj->getChangedImage('brandimage');
		$changedItems = json_decode($changedItems,true);
		echo "\n***changed brand image***\n";
		echo "Changed Items = ".count($changedItems)."\n";
		if(count($changedItems) > 0){
			require_once("image/syncBrandImage.php");
		}else
		{
			//Tell Other scripts that this script is successfully complete so the next one can run
			$curr_proc_file_new = $proc_file_check_path.$brandimage_proc_filename.$proc_file_newstatus;
			$curr_proc_file_comp = $proc_file_check_path.$brandimage_proc_filename.$proc_file_compstatus;
			rename($curr_proc_file_new, $curr_proc_file_comp);
		}
	}
	Else
	{
		//IF file still exists means brandimage sync is still processing.
		if(file_exists($proc_file_check_path.$brandimage_proc_filename.$proc_file_newstatus))
		{
			$changedItems = $pmpObj->getChangedImage('brandimage');
			$changedItems = json_decode($changedItems,true);
			echo "\n***changed brand image***\n";
			echo "Changed Items = ".count($changedItems)."\n";
			if(count($changedItems) > 0){
				require_once("image/syncBrandImage.php");
			}else
			{
				//Tell Other scripts that this script is successfully complete so the next one can run
				$curr_proc_file_new = $proc_file_check_path.$brandimage_proc_filename.$proc_file_newstatus;
				$curr_proc_file_comp = $proc_file_check_path.$brandimage_proc_filename.$proc_file_compstatus;
				rename($curr_proc_file_new, $curr_proc_file_comp);
			}
		}
	}
}




//Process CategoryImage
$proc_complete_check = $scanned_directory = array_diff(scandir($proc_file_check_path), array('..', '.'));
$prior_proc_file = $proc_file_check_path.$brandimage_proc_filename.$proc_file_compstatus;
if (!$proc_complete_check == FALSE )
{
	if(file_exists($prior_proc_file))
	{
		//DElETE prior processing file
		unlink($prior_proc_file);
		
		//CREATE new processing file for categoryimage
		$prior_proc_file= $proc_file_check_path.$categoryimage_proc_filename.$proc_file_newstatus;
		$handle = fopen($prior_proc_file, "x+");
		fclose($handle);
		
		$changedItems = $pmpObj->getChangedImage('categoryimage');
		$changedItems = json_decode($changedItems,true);
		echo "\n***changed category image***\n";
		echo "Changed Items = ".count($changedItems)."\n";
		if(count($changedItems) > 0){
			require_once("image/syncCategoryImage.php");
		}else
		{
			//Tell Other scripts that this script is successfully complete so the next one can run
			$curr_proc_file_new = $proc_file_check_path.$categoryimage_proc_filename.$proc_file_newstatus;
			$curr_proc_file_comp = $proc_file_check_path.$categoryimage_proc_filename.$proc_file_compstatus;
			rename($curr_proc_file_new, $curr_proc_file_comp);
		}
	}
	Else
	{
		//IF file still exists means categoryimage sync is still processing.
		if(file_exists($proc_file_check_path.$categoryimage_proc_filename.$proc_file_newstatus))
		{
			$changedItems = $pmpObj->getChangedImage('categoryimage');
			$changedItems = json_decode($changedItems,true);
			echo "\n***changed category image***\n";
			echo "Changed Items = ".count($changedItems)."\n";
			if(count($changedItems) > 0){
				require_once("image/syncCategoryImage.php");
			}else
			{
				//Tell Other scripts that this script is successfully complete so the next one can run
				$curr_proc_file_new = $proc_file_check_path.$categoryimage_proc_filename.$proc_file_newstatus;
				$curr_proc_file_comp = $proc_file_check_path.$categoryimage_proc_filename.$proc_file_compstatus;
				rename($curr_proc_file_new, $curr_proc_file_comp);
			}
		}
	}
}





//Process LogoImage
$proc_complete_check = $scanned_directory = array_diff(scandir($proc_file_check_path), array('..', '.'));
$prior_proc_file = $proc_file_check_path.$categoryimage_proc_filename.$proc_file_compstatus;
if (!$proc_complete_check == FALSE )
{
	if(file_exists($prior_proc_file))
	{
		//DElETE prior processing file
		unlink($prior_proc_file);
		
		//CREATE new processing file for logoimage
		$prior_proc_file= $proc_file_check_path.$logoimage_proc_filename.$proc_file_newstatus;
		$handle = fopen($prior_proc_file, "x+");
		fclose($handle);
		
		$changedItems = $pmpObj->getChangedImage('user');
		$changedItems = json_decode($changedItems,true);
		echo "\n***changed store logo***\n";
		echo "Changed Items = ".count($changedItems)."\n";
		if(count($changedItems) > 0){
			require_once("image/syncLogoImage.php");
		}else
		{
			//Tell Other scripts that this script is successfully complete so the next one can run
			$curr_proc_file_new = $proc_file_check_path.$logoimage_proc_filename.$proc_file_newstatus;
			$curr_proc_file_comp = $proc_file_check_path.$logoimage_proc_filename.$proc_file_compstatus;
			rename($curr_proc_file_new, $curr_proc_file_comp);
		}
	}
	Else
	{
		//IF file still exists means logoimage sync is still processing.
		if(file_exists($proc_file_check_path.$logoimage_proc_filename.$proc_file_newstatus))
		{
			$changedItems = $pmpObj->getChangedImage('user');
			$changedItems = json_decode($changedItems,true);
			echo "\n***changed store logo***\n";
			echo "Changed Items = ".count($changedItems)."\n";
			if(count($changedItems) > 0){
				require_once("image/syncLogoImage.php");
			}else
			{
				//Tell Other scripts that this script is successfully complete so the next one can run
				$curr_proc_file_new = $proc_file_check_path.$logoimage_proc_filename.$proc_file_newstatus;
				$curr_proc_file_comp = $proc_file_check_path.$logoimage_proc_filename.$proc_file_compstatus;
				rename($curr_proc_file_new, $curr_proc_file_comp);
			}
		}
	}
}






//Process ProductImage
$proc_complete_check = $scanned_directory = array_diff(scandir($proc_file_check_path), array('..', '.'));
$prior_proc_file = $proc_file_check_path.$logoimage_proc_filename.$proc_file_compstatus;
if (!$proc_complete_check == FALSE )
{
	if(file_exists($prior_proc_file))
	{
		//DElETE prior processing file
		unlink($prior_proc_file);
		
		//CREATE new processing file for productimage
		$prior_proc_file= $proc_file_check_path.$productimage_proc_filename.$proc_file_newstatus;
		$handle = fopen($prior_proc_file, "x+");
		fclose($handle);
		
		$changedItems = $pmpObj->getChangedImage('productimage');
		$changedItems = json_decode($changedItems,true);
		echo "\n***changed product image***\n";
		echo "Changed Items = ".count($changedItems)."\n";
		if(count($changedItems) > 0){
			require_once("image/syncProductImage.php");
		}else
		{
			//Tell Other scripts that this script is successfully complete so the next one can run
			$curr_proc_file_new = $proc_file_check_path.$productimage_proc_filename.$proc_file_newstatus;
			$curr_proc_file_comp = $proc_file_check_path.$productimage_proc_filename.$proc_file_compstatus;
			rename($curr_proc_file_new, $curr_proc_file_comp);
		}
		
	}
	Else
	{
		//IF file still exists means productimage sync is still processing.
		if(file_exists($proc_file_check_path.$productimage_proc_filename.$proc_file_newstatus))
		{
			$changedItems = $pmpObj->getChangedImage('productimage');
			$changedItems = json_decode($changedItems,true);
			echo "\n***changed product image***\n";
			echo "Changed Items = ".count($changedItems)."\n";
			if(count($changedItems) > 0){
			   require_once("image/syncProductImage.php");
			}else
			{
				//Tell Other scripts that this script is successfully complete so the next one can run
				$curr_proc_file_new = $proc_file_check_path.$productimage_proc_filename.$proc_file_newstatus;
				$curr_proc_file_comp = $proc_file_check_path.$productimage_proc_filename.$proc_file_compstatus;
				rename($curr_proc_file_new, $curr_proc_file_comp);
			}
			
		}
	}
}





//Process SKUImage
$proc_complete_check = $scanned_directory = array_diff(scandir($proc_file_check_path), array('..', '.'));
$prior_proc_file = $proc_file_check_path.$productimage_proc_filename.$proc_file_compstatus;
if (!$proc_complete_check == FALSE )
{
	if(file_exists($prior_proc_file))
	{
		//DElETE prior processing file
		unlink($prior_proc_file);
		
		//CREATE new processing file for skuimage
		$prior_proc_file= $proc_file_check_path.$skuimage_proc_filename.$proc_file_newstatus;
		$handle = fopen($prior_proc_file, "x+");
		fclose($handle);
		
		$changedItems = $pmpObj->getChangedImage('skuimage');
		$changedItems = json_decode($changedItems,true);
		echo "\n***changed sku image***\n";
		echo "Changed Items = ".count($changedItems)."\n";
		if(count($changedItems) > 0){
			require_once("image/syncSkuImage.php");
		}else
		{
			//Tell Other scripts that this script is successfully complete so the next one can run
			$curr_proc_file_new = $proc_file_check_path.$skuimage_proc_filename.$proc_file_newstatus;
			$curr_proc_file_comp = $proc_file_check_path.$skuimage_proc_filename.$proc_file_compstatus;
			rename($curr_proc_file_new, $curr_proc_file_comp);
		}
		
	}
	Else
	{
		//IF file still exists means skuimage sync is still processing.
		if(file_exists($proc_file_check_path.$skuimage_proc_filename.$proc_file_newstatus))
		{
			$changedItems = $pmpObj->getChangedImage('skuimage');
			$changedItems = json_decode($changedItems,true);
			echo "\n***changed sku image***\n";
			echo "Changed Items = ".count($changedItems)."\n";
			if(count($changedItems) > 0){
				require_once("image/syncSkuImage.php");
			}else
			{
				//Tell Other scripts that this script is successfully complete so the next one can run
				$curr_proc_file_new = $proc_file_check_path.$skuimage_proc_filename.$proc_file_newstatus;
				$curr_proc_file_comp = $proc_file_check_path.$skuimage_proc_filename.$proc_file_compstatus;
				rename($curr_proc_file_new, $curr_proc_file_comp);
			}
			
		}
	}
}





//Process Universal
$proc_complete_check = $scanned_directory = array_diff(scandir($proc_file_check_path), array('..', '.'));
$prior_proc_file = $proc_file_check_path.$skuimage_proc_filename.$proc_file_compstatus;
if (!$proc_complete_check == FALSE )
{
	if(file_exists($prior_proc_file))
	{
		//DElETE prior processing file
		unlink($prior_proc_file);
		
		//CREATE new processing file for universal
		$prior_proc_file= $proc_file_check_path.$universal_proc_filename.$proc_file_newstatus;
		$handle = fopen($prior_proc_file, "x+");
		fclose($handle);
		
		$changedItems = $pmpObj->getChangedUniversal('productuniversal');
		$changedItems = json_decode($changedItems,true);
		echo "Changed Items IS Universal = ".count($changedItems['is_universal'])."\n";
		echo "Changed Items NOT Universal = ".count($changedItems['not_universal'])."\n";
		
		
		if (count($changedItems['is_universal']) > 0 || count($changedItems['not_universal'])>0){
			require_once("vehicle/syncUniversal.php");
		//	if(count($changedItems['not_universal'])>0)
		//	{
				$reIndexFlg = TRUE;
		//	}
		}else
		{
			//Tell Other scripts that this script is successfully complete so the next one can run
			$curr_proc_file_new = $proc_file_check_path.$universal_proc_filename.$proc_file_newstatus;
			$curr_proc_file_comp = $proc_file_check_path.$universal_proc_filename.$proc_file_compstatus;
			rename($curr_proc_file_new, $curr_proc_file_comp);
		}
		
	}
	Else
	{
		//IF file still exists means universal sync is still processing.
		if(file_exists($proc_file_check_path.$universal_proc_filename.$proc_file_newstatus))
		{
			$changedItems = $pmpObj->getChangedUniversal('productuniversal');
			$changedItems = json_decode($changedItems,true);
			echo "\n***changed universal***\n";
			echo "Changed Items IS Universal = ".count($changedItems['is_universal'])."\n";
			echo "Changed Items NOT Universal = ".count($changedItems['not_universal'])."\n";
			//if (count($changedItems) > 0){
			//	require_once("vehicle/syncUniversal.php");
			//}
			if (count($changedItems['is_universal']) > 0 || count($changedItems['not_universal'])>0){
				require_once("vehicle/syncUniversal.php");
				//if(count($changedItems['not_universal'])>0)
				//{
					$reIndexFlg = TRUE;
				//}
			}else
			{
				//Tell Other scripts that this script is successfully complete so the next one can run
				$curr_proc_file_new = $proc_file_check_path.$universal_proc_filename.$proc_file_newstatus;
				$curr_proc_file_comp = $proc_file_check_path.$universal_proc_filename.$proc_file_compstatus;
				rename($curr_proc_file_new, $curr_proc_file_comp);
			}
			
		}
	}
	
	if($reIndexFlg == TRUE)
	{
		require_once("../reindex.php");
	}
	
	if(file_exists($proc_file_check_path.$universal_proc_filename.$proc_file_compstatus))
	{
		//DElETE prior processing file
		unlink($proc_file_check_path.$universal_proc_filename.$proc_file_compstatus);
	}
}


?>