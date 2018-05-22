<style type="">
    .filebox {
        padding: 50px 0px 0px 0px;
        left: -5%;
        width: 312px;
        margin: 0px auto;
    }

    div.link_clickbox_left {
        display: inline-block;
        border: 1px solid #dddddd;
        width: 300px;
        height: 160px;
        padding: 30px;
        margin-right: 0px;
        -webkit-border-top-left-radius: 15px;
        -webkit-border-bottom-left-radius: 15px;
        -moz-border-radius-topleft: 15px;
        -moz-border-radius-bottomleft: 15px;
        border-top-left-radius: 15px;
        border-bottom-left-radius: 15px;
        -webkit-border-top-right-radius: 15px;
        -webkit-border-bottom-right-radius: 15px;
        -moz-border-radius-topright: 15px;
        -moz-border-radius-bottomright: 15px;
        border-top-right-radius: 15px;
        border-bottom-right-radius: 15px;
        -moz-box-shadow: 2px 2px 6px #dddddd;
        -webkit-box-shadow: 2px 2px 6px #dddddd;
        box-shadow: 0px 5px 1px #dddddd;
        background-image: url(static/images/category_download_d.png);
    }
    .generated_date{
        float: right;
        margin: 30px 25% 0 0;
    }
</style>

<?php
DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH . '/../../app/Mage.php');
Mage :: app("default") -> setCurrentStore( Mage_Core_Model_App :: ADMIN_STORE_ID );

$mediaUrl = Mage::getBaseUrl('media');
$mediaPath = Mage::getBaseDir('media');

$productListFolder = $mediaUrl."productList";
$logPath = $mediaPath."/productList/error.log";

$generatedTime = '';
$fp = fopen ($logPath, 'r');
if ($fp) {
    flock ($fp, LOCK_SH);
    while ($line = fgets ($fp)) {
        $generatedTime = $line;
    }
    flock ($fp, LOCK_UN);
    fclose ($fp);
}
?>

<div id="category_download" class="filebox">
    <div class="link_clickbox_left">

        <span class="field_head">Simple Product Listing</span>
        <div class="content">

            <ul class="discription">
                <li>simple_product_list.txt</li>
                <li>[Brand] [SKU] [UPC] [Price] [Core Deposit]</li>
            </ul>

            <a class="download_click" href="<?php echo $productListFolder?>/simple_product_list.zip">
                <img src="static/images/category_download_b.png">
            </a>

        </div>
    </div>
</div>

<div class="generated_date"><?php echo $generatedTime;?></div>
