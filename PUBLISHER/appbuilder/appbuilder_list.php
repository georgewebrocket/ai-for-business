<?php

ini_set('display_errors',1); 
error_reporting(E_ALL);

require_once 'db.php';
require_once '../php/config.php';

$dbo = new DB(conn1::$connstr,conn1::$username,conn1::$password);

$mytable = "";
if (isset($_POST['TxtTable'])) {
    $mytable = $_POST['TxtTable'];
    if ($mytable!='') {
        $cols = $dbo->getColsExt($mytable);
        $colscount = count($cols);
    }

    
    $fieldsStr = "[\n";
    for ($i=0; $i < count($cols); $i++) { 
        $fieldsStr .= "[\"" . $cols[$i][0] . "\"";
        
        if (strpos($cols[$i][1], "decimal")!==false) {
            $filedType = "currency";
        }
        else if (strpos($cols[$i][1], "smallint")!==false) {
            $filedType = "checkbox";
        }
        else {
            $filedType = "text";
        }
        $fieldsStr .= ", \"" . $filedType . "\""; 
        
        $fieldsStr .= ", \"" . strtoupper($cols[$i][0]) . "\"]";

        if ($i<count($cols)-1) {
            $fieldsStr .= ",";
        }
        $fieldsStr .= "\n";
    }
    $fieldsStr .= "]";

}



?>

<html>
<head>
<title>APP BUILDER</title>

<style>
    body {
        font-family: sans-serif;
    }
    
    .code {
        font-family:Courier, sans-serif;
        font-size:16px;
        padding:30px;
    }
    .grid-20-80 {
        display:grid;
        grid-template-columns: 20% 80%;
    }
    .left-menu {
        background-color: #ddd;
        padding:30px;
    }
</style>

</head>

<body>

<div class="grid-20-80">

    <div class="left-menu">
        <?php include "appbuilder_menu.php" ?>
    </div>

    <div class="code">

        <h1>List</h1>
        <form action="appbuilder_list.php" method="post">
        <input name="TxtTable" id="TxtTable" type="text" value="<?php echo $mytable ?>">
        <input name="BtnOK" type="submit" value="Submit">

        </form>

        <xmp>
        

        <?php if (isset($_POST['TxtTable'])) { 



        echo <<<EOT

<?php


/*ini_set('display_errors',1); 
error_reporting(E_ALL);*/


require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
//require_once('php/session.php');
require_once ('php/controls.php');

\$canAdd = TRUE; 
\$canView = TRUE;


\$list = new LISTCONTROL(\$dbo, "SELECT * FROM $mytable ", 
        array(), 
        array(), 
        array(),
        "{$mytable}s.php", "{$mytable}.php", "{$mytable}",
        \$canAdd, \$canView);

\$fields = $fieldsStr;

\$list->setFields(\$fields);

/*
\$list->setSearch(
    array("description", "category", "MYFIELD"), 
    array("text", "combobox", "text"), 
    array("Περιγραφή", "Κατηγορία", "MYFIELD")
    );
*/
/*
\$list->setSearchFieldAttr("category", 
    array("SQL"=> "SELECT id, description FROM CATEGORIES", 
        "ID-FIELD" => "id", "DESC-FIELD" => "description")
    );
*/
/*
//autocomplete
\$list->setSearchFieldAttr("id", 
array(
    "TABLE" => "customers",
    "ID-FIELD" => "id",
    "DESC-FIELD" => "customer_name",
    "DESC-FIELD-2" => "vat_nr"
));
*/
/*
\$list->setSearchFieldAttr("MYFIELD", 
array(
    "CRITERIA" => " AND 1  "
));
*/

\$list->SearchList(\$_GET, TRUE, FALSE, "", 1000); //unlimited

/*
\$rs = \$list->getRS();

if (\$rs) {
    \$rsAUX = \$dbo->getRS("SELECT * FROM AUX_TABLE");
    for (\$i = 0; \$i < count(\$rs); \$i++) {
        \$rs[\$i]['MY_FIELD'] = func::vlookupRS("description", \$rsAUX, \$rs[\$i]['MY_FIELD']);    
    }
}

\$list->setRS(\$rs);
*/

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title><?php echo app::\$project_name; ?> - {$mytable}</title>        
        
        <?php include "_head.php"; ?>
        
        <style>
            
            /*#grid {
                max-width: 800px;
            }*/

            /*#grid td:nth-child(4) {
                text-align:left;
            }*/

            /*#date_added_d1, #date_added_d2 {
                width:45%;
                display:inline-block;
            }*/
            
        </style>
        
        <?php \$list->refreshScript();  ?>
        
               
    </head>
    
    <body>
        
        <?php include "blocks/header.php"; ?>
        
        <div class="padding-20">
            <h1>{$mytable}</h1>
            
            
            <?php
            
            \$list->ViewList("Άνοιγμα", 50, 1400, 750);
            
            ?>
            
            
        </div>
        
        <?php include "blocks/footer.php"; ?>
    </body>
    
</html>


EOT;

        }
        ?>
        </xmp>
    
</div>


</div>

</body>
</html>