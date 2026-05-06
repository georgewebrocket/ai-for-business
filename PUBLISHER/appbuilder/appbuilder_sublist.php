<?php

ini_set('display_errors',0); 
// error_reporting(E_ALL);

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
        <h1>Sublist</h1>
        <form action="appbuilder_sublist.php" method="post">
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

\$canAdd = FALSE; //custom
\$canView = TRUE;

\$parent_id = \$_REQUEST['id'];

\$list = new LISTCONTROL(\$dbo, "SELECT * FROM $mytable WHERE PARENT_ID=\$parent_id", 
        array(), 
        array(), 
        array(),
        "{$mytable}s.php", "{$mytable}.php", "{$mytable}",
        \$canAdd, \$canView);

\$fields = $fieldsStr;

\$list->setFields(\$fields);

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

echo "<a class=\"modalBtn btn btn-primary\" data-href=\"{$mytable}.php?id=0&PARENT_ID=\$parent_id\" data-title=\"{$mytable}\">Προσθήκη {$mytable}</a>";
?>

<style>
    
</style>

<?php
\$list->ViewList("Άνοιγμα", 50, 1200, 700);



EOT;


        

        }

        ?>

        
        </xmp>

    </div>


</div>

</body>
</html>