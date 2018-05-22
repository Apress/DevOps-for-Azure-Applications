<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

DEFINE('BASE_PATH', realpath(dirname(__FILE__)));
require_once(BASE_PATH."/../app/Mage.php");

umask(0);
$app = Mage::app('default');
Mage::getSingleton('core/session', array('name' => 'adminhtml'));

$username = $_GET['username'];
//$username = 'admin';

$raw_url = $_GET['url'];

$valid_url = array('promo_catalog',
    'promo_quote',
    'bannerslider/adminhtml_bannerslider',
    'customer',
    'customer_online',
    'report_customer/totals',
    'report_customer/orders',
    'report_shopcart/product',
    'report_shopcart/abandoned',
    'report_customer/accounts',
    'report_review/customer',
    'report_review/product'
);

if(!in_array($raw_url, $valid_url)){
    echo "Invalid URL";
    exit();
}

$user = Mage::getModel('admin/user')->loadByUsername($username);

if (Mage::getSingleton('adminhtml/url')->useSecretKey()) {
    Mage::getSingleton('adminhtml/url')->renewSecretUrls();
}

$session = Mage::getSingleton('admin/session');
$session->setIsFirstVisit(true);
$session->setUser($user);
$session->setAcl(Mage::getResourceModel('admin/acl')->loadAcl());
Mage::dispatchEvent('admin_session_user_login_success',array('user'=>$user));


$url = Mage::getUrl('adminhtml/'.$raw_url);

$url = str_replace('autologin.php', 'index.php', $url);

if ($raw_url == "bannerslider/adminhtml_bannerslider"){
    $url = str_replace("zpanel/","",$url);
}

//header('Location:  '.$url);
echo "<html>";
echo "<head>
<script src='https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js'></script>
<style>
iframe { overflow:hidden; }
</style>
</head>";
//echo "<iframe src='$url' onload=\"this.width=screen.width;this.height=screen.height;\"></iframe>";
echo "<iframe src='$url' width=100% height=100% frameBorder=\"0\"></iframe>";
echo "
<script type='text/javascript'> 
    
    $('iframe').on('load', function(){
        console.log($('iframe').contents().find('.nav-bar').length);
        $('iframe').contents().find('.nav-bar').css({
            display: 'none'
        });
        $('iframe').contents().find('.header-top').css({
            display: 'none'
        });
        $('iframe').contents().find('.notification-global').css({
            display: 'none'
        });        
        $('iframe').contents().find('.footer').css({
            display: 'none'
        });
        $('iframe').contents().find('#message-popup-window-mask').css({
            display: 'none'
        });
        $('iframe').contents().find('#message-popup-window').css({
            display: 'none'
        });
        
        $('iframe').contents().find('#message-popup-window').css({
            display: 'none'
        });
        
        $('iframe').contents().find('#message-popup-window').css({
            display: 'none'
        });
        var dom_banner = $('iframe').contents().find('.bannerslider-adminhtml-bannerslider-edit');
        if (dom_banner){
            var dom_element = $('iframe').contents().find('#filename_image');        
            if(dom_element){
                var uri = dom_element.attr(\"src\");
                var lastslashindex = uri.lastIndexOf('/');
                var result= \"https://\" + document.domain + \"/media/\" + uri.substring(lastslashindex  + 1);
                dom_element.attr(\"src\", result);
                dom_element.parent().attr(\"href\", result);
            }    
        }                        
    });

    
</script>";
echo "</html>";
//exit();
