<?php

$db1 = new DB(conn1::$connstr, conn1::$username, conn1::$password);
$dbo = $db1;
header('Content-Type: text/html; charset=utf-8');

$ltoken = "l=gr"; $lang = "gr";
$locale = "GR";
$section = "";

//die("<h1>Site under maintenance</h1>");

date_default_timezone_set("Europe/Athens");




$langs = [
    'gr' => "greek",
    'en' => "english",
    'fr' => "french",
    'de' => "german",
    'ru' => "russian",
    'cn' => "chinese",
    'it' => "italian",
    'es' => "spanish",
    'ar' => "arabic",

];
