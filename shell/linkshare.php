<?php

if(filter_has_var(INPUT_GET, 'siteID') && preg_match('/^[a-zA-Z0-9\-\._\/\*]{34}$/', $_GET['siteID']) && filter_has_var(INPUT_GET, 'url') && filter_input(INPUT_GET, 'url', FILTER_VALIDATE_URL))
{

	if(!in_array(parse_url($_GET['url'], PHP_URL_HOST), array($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME']))){
		header('Location: ' . $_SERVER['HTTP_HOST']);
		exit;
	}

    // expires in 2 years	
	setcookie("linkshare[tr]", $_GET['siteID'], time() + 60 * 60 * 24 * 30 * 12 * 2, '/');
	setcookie("linkshare[land]", date('Ymd_Hi'), time() + 60 * 60 * 24 * 30 * 12 * 2, '/');
	
	header('Location: ' . $_GET['url']);
}
else
{
	header('Location: ' . $_SERVER['HTTP_HOST']);
	exit;
}
