<?php
$year = $_GET['year'];
$make = $_GET['make'];
$model = $_GET['model'];
$subModel = $_GET['submodel'];

$filePath = './../../../vamap/';
$fileName = $filePath."/".$year."/".$make."/".$model."/".$subModel;

if (file_exists($fileName)) {
    $content = file_get_contents($fileName);
    echo $content;
}else{
    echo "sorry, file not found!";
}