<?php



$startElIndex = 0;

$url = strtok($_SERVER["REQUEST_URI"],'?');     //accept get parameters in url
$redirect_url = $_SERVER["REQUEST_URI"];
$path = ltrim($url, '/');                       // Trim leading slash(es)
$elements = explode('/', $path); 

$url1 = ""; $url2 = ""; $url3 = ""; $url4 = ""; $url5 = ""; $url6 = ""; $url7 = "";
$thispageLink = "";
if (count($elements)>=$startElIndex + 1) 
{$url1 = urldecode($elements[$startElIndex +0]); $thispageLink = $url1;} ///
if (count($elements)>=$startElIndex + 2) 
{$url2 = urldecode($elements[$startElIndex +1]); $thispageLink .= "/" . $url2;} ///
if (count($elements)>=$startElIndex + 3) 
{$url3 = urldecode($elements[$startElIndex +2]); $thispageLink .= "/" . $url3;} ///
if (count($elements)>=$startElIndex + 4) 
{$url4 = urldecode($elements[$startElIndex +3]); $thispageLink .= "/" . $url4;} ///
if (count($elements)>=$startElIndex + 5) 
{$url5 = urldecode($elements[$startElIndex +4]); $thispageLink .= "/" . $url5;} ///
if (count($elements)>=$startElIndex + 6) 
{$url6 = urldecode($elements[$startElIndex +5]); $thispageLink .= "/" . $url6;} ///
if (count($elements)>=$startElIndex + 7) 
{$url7 = urldecode($elements[$startElIndex +6]); $thispageLink .= "/" . $url7;} ///


switch ($url1) {
    case "":
		include "home.php";	
		break;

	

    default:
		break;
}
