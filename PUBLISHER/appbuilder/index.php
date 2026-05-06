<?php

ini_set('display_errors',1); 
error_reporting(E_ALL);

require_once '../php/db.php';
require_once '../php/config.php';

$dbo = new DB(conn1::$connstr,conn1::$username,conn1::$password);

$sql = "SHOW TABLES";
$rsTables = $dbo->getRS($sql);
//var_dump($rsTables);


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
        <h2>Tables</h2>

        <?php

        for ($i=0; $i < count($rsTables); $i++) { 
            $array = array_values($rsTables[$i]);
            echo $array[0] . "<br/>";
        }

        ?>

    </div>


</div>

</body>
</html>