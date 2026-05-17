<?php

//ini_set('display_errors',1); 
//error_reporting(E_ALL);

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once ('php/controls.php');

$id = $_GET['id'];
$item = new help($dbo, $id);

if ($id==0) {
    $rsNewHelpOrder = $dbo->getRS("SELECT IFNULL(MAX(show_order),0)+1 AS show_order FROM help");
    $item->show_order($rsNewHelpOrder[0]['show_order']);
}

$canSave = TRUE;
$canDelete = TRUE;

$itemControl = new ITEMCONTROL($dbo, $item, 
    array(), 
    array(), 
    array(),
    "help_item.php", 
    $canSave, $canDelete);

$fields = [
["id", "ID", "ID"],
["title", "text", "TITLE"],
["content", "richtextbox", "CONTENT"],
["title_en", "text", "TITLE (en)"],
["content_en", "richtextbox", "CONTENT (en)"],
["show_order", "text", "SHOW_ORDER"]
];

$itemControl->setFields($fields);

/*
$itemControl->setFieldAttr("category", 
    array("SQL"=> "SELECT id, description FROM AUX_TABLE", 
        "ID-FIELD" => "id", "DESC-FIELD" => "description")
    ); 
    
$itemControl->setFieldAttr("photo", 
    array("HOST"=> app::, "WIDTH"=>150, "HEIGHT"=>150)
    );
        
$rsYears = func::getYears(50, 0, true);
$itemControl->setFieldAttr("year_from", 
    array("SQL"=> "",
        "RS" => $rsYears, 
        "ID-FIELD" => "id", "DESC-FIELD" => "year")
    );



*/
        
$saveRes = $itemControl->SaveItem($_POST);
$delRes = $itemControl->DeleteItem($_GET);

$id = $id==0? $saveRes: $id;

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title><?php echo app::$project_name; ?></title>        
        
        <?php include "_head.php"; ?>

        <style>
        
        </style>

        <script>
            $(function() {

            });
            
        </script>
        
               
    </head>
    
    <body>
        
        <div class="padding-20">
            
        <?php    
        
        $itemControl->ViewItem($saveRes, $delRes);
            
        ?>
            
        </div>

        <?php include "blocks/footer.php"; ?>
        
    </body>
    
</html>

