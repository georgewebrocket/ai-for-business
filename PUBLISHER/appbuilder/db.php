<?php

/*
EXAMPLE
$mydb = new DB("mysql:host=db25.grserver.gr:3306;dbname=v6zijufi_epagelmatias_crm;charset=utf8",
"epagelmatias",
"gp1117EP#");
*/
class DB
{
	protected $_conn;
	
	public function __construct($strconn,$username,$password) {
		$this->_conn = new PDO($strconn, $username, $password);
	}
	
	/*
	EXAMPLES	
	$rs = $mydb->getRS("SELECT * FROM USERS");
	$rs = $mydb->getRS("SELECT * FROM USERS WHERE username=? AND password=?", array("george", "123"));
	//RETURNS a 2-dimensional array (rows/fields)
	echo $rs[0]['id'];
	*/
	public function getRS($sql, $params = NULL) {
		$stmt = $this->_conn->prepare($sql);
		if ($params==NULL) {
			$stmt->execute();
		}
		else {
			$stmt->execute($params);
		}
		$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (self::IsMultiDim($rs)) {
                    return $rs;
                }
                else {
                    return FALSE;
                }
                
		//return $rs;
	}
        
        static function IsMultiDim($array) {
            if (count($array) == count($array, COUNT_RECURSIVE)) 
            {
              return FALSE;
            }
            else
            {
              return TRUE;
            }
        }
	
	/*
	EXAMPLES
	$rs = $mydb->execSQL("UPDATE USER SET password=? WHERE username=?", array("456","george"));
	echo $rs; //returns affected rows = 1
	=============================
	$rs = $mydb->execSQL("INSERT INTO USERS (fullname, username, password) VALUES (?,?,?)", 
		array("George Papagiannis", "george","456"));
	echo $rs; //returns last inserted id
	=============================
	$rs = $mydb->execSQL("DELETE FROM USERS WHERE username=?", 
		array("george"));
	echo $rs; //returns last inserted id
	*/
	public function execSQL($sql, $params = NULL) {
		try {
                    
            //$this->_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            //$this->_conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            $stmt = $this->_conn->prepare($sql);
            if ($params==NULL) {
                $stmt->execute();
            }
            else {
                $stmt->execute($params);
            }
            
            $err = $stmt->errorInfo();
        	if (count($err)==3 && $err[2]!="") { echo "<!--".$err[2]."-->"; }
        	/*echo "<!--******";
			var_dump($err);
			echo "-->";*/
            
            $sqltype = substr($sql, 0, 6);
            switch ($sqltype) {
                case "INSERT":
                    //echo "<!--inserting...-->";
                    return $this->_conn->lastInsertId();
                default:
                    $rs = $stmt->rowCount();
                    return $rs;
                    //return $stmt->errorInfo();
            }
		}
		catch(PDOException $ex) {
            echo "<!--ERROR-";
            var_dump($ex->getMessage());
            echo "-->";	
            return false;
		}	
		
	}
	
	public function getLastIDsql($table) {
		return "SELECT * FROM ".$table." WHERE id=".$this->_conn->lastInsertId();
	}
	
	public function getCols($table) {
		$sql = "SHOW COLUMNS FROM ".$table;
		$stmt = $this->_conn->prepare($sql);
		$stmt->execute();
		$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
		//print_r($rs);
		$cols = array();
		for ($i=0;$i<count($rs);$i++) {
			$cols[$i] = $rs[$i]['Field'];	
		}
		return $cols;
		
	}

    public function getColsExt($table) {
		$sql = "SHOW COLUMNS FROM ".$table;
		$stmt = $this->_conn->prepare($sql);
		$stmt->execute();
		$rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
		//print_r($rs);
		$cols = array();
		for ($i=0;$i<count($rs);$i++) {
			$cols[$i][0] = $rs[$i]['Field'];
            $cols[$i][1] = $rs[$i]['Type'];	
		}
		return $cols;
		
	}
        
	public function getConn() {
		return $this->_conn;
	}
	
}





/*
$sql = "SELECT * FROM USERS WHERE category=?";
$fields = "ID, FULLNAME"; $fields = "";
$orderBy = " ORDER BY ID ";
$params = array(10);
$curPage = isset($_GET['page'])? $_GET['page']: 0; 
$rowsperpage = 20;
$link = "category.php?id=10&page=";
$rsPage = new RS_PAGE($db1, $sql, $fields, $orderBy, $rowsperpage, $curPage, $params, $link);
$rs = $rsPage->getRS();
$rsPage->getPageLinks();
$rsPage->getPrev();
$rsPage->getNext();
$rsPage->getCount();
*/
class RS_PAGE
{
    private $_dbo, $_sql, $_rs, $_nrOfRows, $_currentPage, $_countAll, $_countAllRows, $_link, 
            $_pageSClass, $_currentSClass, $_prev, $_next;
    
    public function __construct($dbo, $sql, $fields, $orderBy, $nrOfRows = 10,
            $currentPage = 0, $params = NULL, $link = "", 
            $RS = NULL, $RSCOUNT = 0, $pageLinksView = 0 ) 
    {
        $this->_dbo = $dbo;
        $this->_nrOfRows = $nrOfRows;
        $this->_link = $link;
        $this->_currentPage = $currentPage;
        $this->_pageSClass = "paginate";
        $this->_currentSClass = "paginate-current";
        $this->_prev = "<";
        $this->_next = ">";
        $this->_first = "<<";
        $this->_last = ">>";
        $this->_pageLinksView = $pageLinksView; // show page links π.χ. αν είναι $pageLinksView = 2
                                                // και επιλεγμενο είνα 6 θα εμφανίσει "4 5 6 7 8"
                                                // αν $pageLinksView = 0 θα εμφανίσει όλα
        
        $start = $this->_currentPage * $this->_nrOfRows;
        
        if ($sql!='') {            

            if ($fields!="") {
                $sql = str_replace("*", $fields, $sql);
            }
            
            if ($RSCOUNT == 0) {
                if ($params == NULL) {
                    $rsCount = $dbo->getRS($sql);
                    //$countRows = self::count_rs_rows($this->_dbo,$sql);
                }
                else {
                    $rsCount = $dbo->getRS($sql,$params);
                    //$countRows = self::count_rs_rows($this->_dbo,$sql,$params);
                }

                //echo "<!--$sql-->";
                $this->_countAllRows =count($rsCount);
                //$this->_countAllRows = $countRows;
                //pages
                $this->_countAll = ceil(count($rsCount)/$this->_nrOfRows);
                //$this->_countAll = ceil($countRows/$this->_nrOfRows);
            }
            else {
                $this->_countAllRows = $RSCOUNT;
                $this->_countAll = ceil(($RSCOUNT)/$this->_nrOfRows);
            }
            
            $sql .= " $orderBy ";            
            $sql .= " LIMIT $start, $nrOfRows ";
            //echo $sql;
            if ($params == NULL) {
                $this->_rs = $dbo->getRS($sql);
            }
            else {
                $this->_rs = $dbo->getRS($sql,$params);
            } 
        }
        if ($RS != NULL) {            
            //$this->_countAll = intval($RSCOUNT/$this->_nrOfRows);
            $this->_rs = $RS;
        }
    }     
    
    
    static function count_rs_rows($conn, $sql, $params = NULL){
        $result = 0;
        if($sql != ""){
            $strpos_from = stripos($sql, "from");
            $sql_for_count = substr_replace($sql,"SELECT COUNT(*) AS ROWS_COUNT ",0,$strpos_from);
            
            if ($params == NULL) {
                $rsCount = $conn->getRS($sql_for_count);                
            }
            else {
                $rsCount = $conn->getRS($sql_for_count,$params);
            }
            
            if($rsCount){
                $result = $rsCount[0]['ROWS_COUNT'];
            }
        }
        return $result;
    }
    
    

    public function getRS() {
        return $this->_rs;
    }
    
    public function getPageLinks0() {
        for ($i=0;$i<$this->_countAll;$i++) {
            $startCurrent = $i;
            $pageIndex = $i + 1;
            if ($i==$this->_currentPage) {
                echo "<a class=\"$this->_currentSClass\" href=\"".$this->_link.$i."\">$pageIndex</a>";
            }
            else {
                if($this->_pageLinksView > 0){
                    if(($this->_currentPage - $this->_pageLinksView) <= $i && ($this->_currentPage + $this->_pageLinksView) >= $i){
                        echo "<a class=\"$this->_pageSClass\" href=\"".$this->_link.$i."\">$pageIndex</a>";
                    }
                }else{
                    echo "<a class=\"$this->_pageSClass\" href=\"".$this->_link.$i."\">$pageIndex</a>";
                }
            }
            
        }
    }
    
    
    public function getPageLinks($nrPrevNext = 5) {
        
        for ($i = $this->_currentPage - $nrPrevNext;$i < $this->_countAll && $i<$this->_currentPage + $nrPrevNext;$i++) {
            if ($i>=0) {
                $startCurrent = $i;
                $pageIndex = $i + 1;
                if ($i==$this->_currentPage) {
                    echo "<a class=\"$this->_currentSClass\" href=\"".$this->_link.$i."\">$pageIndex</a> ";
                }
                else {
                    echo "<a class=\"$this->_pageSClass\" href=\"".$this->_link.$i."\">$pageIndex</a> ";
                }
            }
            
        }
    }
    
    
    public function prev($val) {
        $this->_prev = $val;
    }
    
    public function next($val) {
        $this->_next = $val;
    }
    
    public function first($val) {
        $this->_first = $val;
    }
    
    public function last($val) {
        $this->_last = $val;
    }
    
    public function getPrev() {
        if ($this->_currentPage>0) { 
            $prev = $this->_currentPage-1;
            echo "<a class=\"$this->_pageSClass\" href=\"".$this->_link.$prev."\">$this->_prev</a>";                    
        } 
    }
    
    public function getNext() {
        if ($this->_currentPage<$this->_countAll-1) { 
            $next = $this->_currentPage+1; 
            $pageIndex = $next + 1;
            echo "<a class=\"$this->_pageSClass\" href=\"".$this->_link.$next."\">$this->_next</a>";             
        }
    }
    
    public function getFirst() {
        if ($this->_currentPage - $this->_pageLinksView >0) {
            $first = 0;
            echo "<a title=\"First page\" class=\"$this->_pageSClass\" href=\"".$this->_link.$first."\">$this->_first</a>";
        }
    }
    
    public function getLast() {
        if ($this->_currentPage<$this->_countAll-1-$this->_pageLinksView) {
            $last = $this->_countAll-1;
            echo "<a title=\"Last page\" class=\"$this->_pageSClass\" href=\"".$this->_link.$last."\">$this->_last</a>";
        }
    }
    
    public function getCount() {
        return $this->_countAllRows;
    }
    
}

?>