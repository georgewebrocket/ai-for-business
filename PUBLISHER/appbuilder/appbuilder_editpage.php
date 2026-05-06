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
        
        if ($cols[$i][0]=="id") {
            $filedType = "ID";
        }
        else if (strpos($cols[$i][1], "decimal")!==false) {
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
<div class="grid-20-80">

    <div class="left-menu">
        <?php include "appbuilder_menu.php" ?>
    </div>

    <div class="code">
        <h1>Edit page</h1>
        <form action="appbuilder_editpage.php" method="post">
        <input name="TxtTable" id="TxtTable" type="text" value="<?php echo $mytable ?>">
        <input name="BtnOK" type="submit" value="Submit">

        </form>

        <xmp>
        

        <?php if (isset($_POST['TxtTable'])) { 



        echo <<<EOT

<?php

//ini_set('display_errors',1); 
//error_reporting(E_ALL);

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
//require_once('php/session.php');
require_once ('php/controls.php');

\$id = \$_GET['id'];
\$item = new {$mytable}(\$dbo, \$id);

if (\$id==0) {
    \$candidate_id = \$_REQUEST['candidate_id'];
    \$item->candidate_id(\$candidate_id);
}

\$canSave = TRUE;
\$canDelete = TRUE;

\$itemControl = new ITEMCONTROL(\$dbo, \$item, 
    array(), 
    array(), 
    array(),
    "{$mytable}.php", 
    \$canSave, \$canDelete);

\$fields = $fieldsStr;

\$itemControl->setFields(\$fields);

/*
\$itemControl->setFieldAttr("category", 
    array("SQL"=> "SELECT id, description FROM AUX_TABLE", 
        "ID-FIELD" => "id", "DESC-FIELD" => "description")
    ); 
    
\$itemControl->setFieldAttr("photo", 
    array("HOST"=> app::$host, "WIDTH"=>150, "HEIGHT"=>150)
    );
        
\$rsYears = func::getYears(50, 0, true);
\$itemControl->setFieldAttr("year_from", 
    array("SQL"=> "",
        "RS" => \$rsYears, 
        "ID-FIELD" => "id", "DESC-FIELD" => "year")
    );



*/
        
\$saveRes = \$itemControl->SaveItem(\$_POST);
\$delRes = \$itemControl->DeleteItem(\$_GET);

\$id = \$id==0? \$save: \$id;

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title><?php echo app::\$project_name; ?></title>        
        
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
        
        \$itemControl->ViewItem(\$saveRes, \$delRes);
            
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