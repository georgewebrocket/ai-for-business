<?php


/*ini_set('display_errors',1); 
error_reporting(E_ALL);*/


require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');
require_once ('php/controls.php');

$canAdd = TRUE; 
$canView = TRUE;


$list = new LISTCONTROL($dbo, "SELECT * FROM help ORDER BY show_order, id", 
        array(), 
        array(), 
        array(),
        "help_list.php", "help_item.php", "help",
        $canAdd, $canView);

$fields = [
["id", "text", "ID"],
["title", "text", "TITLE"],
["show_order", "text", "SHOW_ORDER"]
];

$list->setFields($fields);

/*
$list->setSearch(
    array("description", "category", "MYFIELD"), 
    array("text", "combobox", "text"), 
    array("Περιγραφή", "Κατηγορία", "MYFIELD")
    );
*/
/*
$list->setSearchFieldAttr("category", 
    array("SQL"=> "SELECT id, description FROM CATEGORIES", 
        "ID-FIELD" => "id", "DESC-FIELD" => "description")
    );
*/
/*
//autocomplete
$list->setSearchFieldAttr("id", 
array(
    "TABLE" => "customers",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "customer_name",
    "DESC-FIELD-2" => "vat_nr"
));
*/
/*
$list->setSearchFieldAttr("MYFIELD", 
array(
    "CRITERIA" => " AND 1  "
));
*/

$list->SearchList($_GET, TRUE, FALSE, "", 1000); //unlimited

/*
$rs = $list->getRS();

if ($rs) {
    $rsAUX = $dbo->getRS("SELECT * FROM AUX_TABLE");
    for ($i = 0; $i < count($rs); $i++) {
        $rs[$i]['MY_FIELD'] = func::vlookupRS("description", $rsAUX, $rs[$i]['MY_FIELD']);    
    }
}

$list->setRS($rs);
*/

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title><?php echo app::$project_name; ?> - help</title>        
        
        <?php include "_head.php"; ?>
        
        <style>
            
            #grid {
                max-width: 1000px;
            }

            /*#grid td:nth-child(4) {
                text-align:left;
            }*/

            /*#date_added_d1, #date_added_d2 {
                width:45%;
                display:inline-block;
            }*/
            
        </style>
        
        <?php $list->refreshScript();  ?>
        
               
    </head>
    
    <body>
        
        <?php include "blocks/header.php"; ?>
        
        <div class="padding-20">
            <h1>Edit Help</h1>
            
            
            <?php
            
            $list->ViewList("Άνοιγμα", 50, 1400, 750);
            
            ?>
            
            
        </div>
        
        <?php include "blocks/footer.php"; ?>
    </body>
    
</html>