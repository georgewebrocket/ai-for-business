<?php

require_once '../php/db.php';
require_once '../php/config.php';

ini_set('display_errors',1); 
error_reporting(E_ALL);

$db1 = new DB(conn1::$connstr,conn1::$username,conn1::$password);

if (isset($_POST['TxtTable'])) {
    $mytable = $_POST['TxtTable'];
    if ($mytable!='') {
        $cols = $db1->getCols($mytable);
        $colscount = count($cols);
    }
}

?>

<html>
<head>
<title>DB CLASS MAKER</title>

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

<script>
const copyButtonLabel = "Copy Code";

// use a class selector if available
let blocks = document.querySelectorAll("pre");

blocks.forEach((block) => {
  // only add button if browser supports Clipboard API
  if (navigator.clipboard) {
    let button = document.createElement("button");

    button.innerText = copyButtonLabel;
    block.appendChild(button);

    button.addEventListener("click", async () => {
      await copyCode(block, button);
    });
  }
});
	</script>

</head>
<body>

<div class="grid-20-80">

	<div class="left-menu">
        <?php include "appbuilder_menu.php" ?>
    </div>

	<div class="code">

		<form action="classbuilder.php" method="post">
		<input name="TxtTable" id="TxtTable" type="text">
		<input name="BtnOK" type="submit" value="Submit">

		</form>

		<?php if (isset($_POST['TxtTable'])) { ?>

		<h1><?php echo $mytable; ?></h1>

		
		<?php

		$tab = '&nbsp;&nbsp;&nbsp;&nbsp;';

		echo '<br/><br/>/*<br/>FIELDS<br/>';
		for ($i=0;$i<$colscount;$i++) {
			echo $cols[$i]."<br/>";
			
		}
		echo '*/<br/>';

		echo 'class '.$mytable. '<br/>{<br/><br/>';

		//protected vars
		echo 'protected $_myconn, ';
		for ($i=0;$i<$colscount;$i++) {
			echo '$_' . $cols[$i];
			if ($i<$colscount-1) {
				echo ', ';
			} 
			else {
				echo ' ';	
			}
		}
		echo ';<br/><br/>';

		echo 'protected $_rs';

		echo ';<br/><br/>';

		//construct function
		echo 'public function __construct($myconn, $_id, $my_rows = NULL, $_ssql = \'\') { <br/>
			'.$tab.'$all_rows = NULL; <br/>
			'.$tab.'$this->_id = $_id; <br/>
			'.$tab.'$this->_myconn = $myconn; <br/>
			'.$tab.'if ($my_rows==NULL) { <br/>
			'.$tab.$tab.'$ssql = "SELECT * FROM ' . $mytable . ' WHERE id=?"; <br/>
			'.$tab.$tab.'$all_rows = $this->_myconn->getRS($ssql, array($_id)); <br/>
			'.$tab.$tab.'} <br/>		
			'.$tab.'else if ($_ssql!=\'\') { <br/>
			'.$tab.$tab.'$ssql = $_ssql; <br/>
			'.$tab.$tab.'$all_rows = $this->_myconn->getRS($ssql); <br/>
			'.$tab.$tab.'} <br/>		
			'.$tab.'else { <br/>
			'.$tab.$tab.'$rows = $my_rows; <br/>
			'.$tab.$tab.'$all_rows = arrayfunctions::filter_by_value($rows, \'id\', $this->_id); <br/>            
			'.$tab.'}<br/>
			'.$tab.'$icount = $all_rows? count($all_rows): 0; <br/><br/>
			'.$tab.'if ($all_rows) { <br/>';
			
		for ($i=1;$i<$colscount;$i++) {
			echo $tab.$tab.'$this->_' . $cols[$i] . ' = $all_rows[0][\'' . $cols[$i] . '\']; <br/>';
		}

		echo $tab.$tab.'$this->_rs = $all_rows; <br/>';

		echo $tab.'} <br/>';
		echo '} <br/><br/>';

		//id
		echo 'public function get_id() { <br/>';
		echo $tab.'return $this->_id;  <br/>';
		echo '}  <br/><br/>';

		//id
		echo 'public function set_id($val) { <br/>';
		echo $tab.'$this->_id = $val;  <br/>';
		echo '}  <br/><br/>';


		echo 'public function get_rs() { <br/>';
		echo $tab.'return $this->_rs;  <br/>';
		echo '}  <br/><br/>';



		for ($i=1;$i<$colscount;$i++) {
			echo 'public function ' . $cols[$i] . '($val = NULL) { <br/>';
				echo $tab.'if ($val === NULL) {';
			echo $tab.$tab.'return $this->_' . $cols[$i] . ';  <br/>';
			echo $tab.'}  <br/>';
				echo $tab.'else {';	
			echo $tab.$tab.'$this->_' . $cols[$i] . ' = $val;  <br/>';
				echo $tab.'}<br/>';
			echo '}  <br/><br/>';	
		}

		//Savedata function
		echo 'public function Savedata() { <br/>
			'.$tab.'if ($this->_id==0) { <br/>
			'.$tab.'$ssql = "INSERT INTO ' . $mytable . ' ( <br/>';
		for ($i=1;$i<$colscount;$i++) {
			echo  $tab.$cols[$i];
			if ($i<$colscount-1) { echo ',<br/>'; } 
			else { echo '<br/>'; }
		}

		echo $tab.') VALUES (';
		for ($i=1;$i<$colscount;$i++) {
			echo  '?';
			if ($i<$colscount-1) { echo ', '; } 
			else { echo ')"; <br/>';	}
		}
		echo $tab.'$result = $this->_myconn->execSQL($ssql, array( <br/>';
		for ($i=1;$i<$colscount;$i++) {
			echo  $tab.$tab.'$this->_' . $cols[$i];
			if ($i<$colscount-1) { echo ', <br/>'; } 
			else { echo ')); <br/>'; }
		}							

		echo $tab.'$ssql = $this->_myconn->getLastIDsql(\''.$mytable.'\');<br/>';

		echo '<br/>
			'.$tab.$tab.'$newrows = $this->_myconn->getRS($ssql); <br/>
			'.$tab.$tab.'$this->_id = $newrows[0][\'id\']; <br/>			
			'.$tab.'} <br/>
			'.$tab.'else { <br/>
			'.$tab.$tab.'$ssql = "UPDATE ' . $mytable . ' set <br/>';
		for ($i=1;$i<$colscount;$i++) {
			echo  $tab.$tab.$cols[$i] . ' = ?';
			if ($i<$colscount-1) { echo ', <br/>'; } 
			else { echo '<br/>'; }
		}	
		echo $tab.$tab.'WHERE id = ?"; <br/>';
		echo $tab.$tab.'$result = $this->_myconn->execSQL($ssql, array( <br/>';
		for ($i=1;$i<$colscount;$i++) {
			echo  $tab.$tab.'$this->_' . $cols[$i];
			if ($i<$colscount-1) { echo ', <br/>'; } 
			else { echo ',<br/>'.$tab.$tab.'$this->_id));<br/>'; }
		}							
		echo $tab.'} <br/>
			'.$tab.'if ($result===false) { <br/>
			'.$tab.$tab.'return false; <br/>
			'.$tab.'} <br/>		
			'.$tab.'return true; <br/>
			} <br/><br/>';

		//Delete function
		echo 'public function Delete() { <br/>
			'.$tab.'$ssql = "DELETE FROM ' . $mytable . ' WHERE id=?"; <br/>
			'.$tab.'$result = $this->_myconn->execSQL($ssql, array($this->_id));  <br/>  
			'.$tab.'if ($result===false) { <br/>
			'.$tab.$tab.'return false; <br/>
			'.$tab.'} <br/>else { <br/>		
			'.$tab.'return true; <br/>}<br/>
			} <br/><br/>';
			
		echo '}';


		} 
				
		?>
		

	</div>

</div>




</body>

</html>





