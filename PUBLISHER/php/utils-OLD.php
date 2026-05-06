<?php


class arrayfunctions
{
    
    //Array function
    static function filter_by_value ($array, $index, $value){
        $i=0;
        $newarray = array();
        if(is_array($array) && count($array)>0) 
        {            
            foreach(array_keys($array) as $key){
                $temp[$key] = $array[$key][$index];
                
                if ($temp[$key] == $value){
                    $newarray[$i] = $array[$key];
                    $i++;
                }                
            }
            //return $newarray;
        }
        return $newarray;
    } 
    
    static function filter_by_value_compare ($array, $index, $value, $compare="eq"){
        $i=0;
        $newarray = array();
        if(is_array($array) && count($array)>0) 
        {            
            foreach(array_keys($array) as $key){
                $temp[$key] = $array[$key][$index];
                switch ($compare) {
                    case "eq":
                        if ($temp[$key] == $value){
                            $newarray[$i] = $array[$key];
                            $i++;
                        }
                        break;
                    case "gt":
                        if ($temp[$key] > $value){
                            $newarray[$i] = $array[$key];
                            $i++;
                        }
                        break;
                    case "lt":
                        if ($temp[$key] < $value){
                            $newarray[$i] = $array[$key];
                            $i++;
                        }
                        break;
                    case "gte":
                        if ($temp[$key] >= $value){
                            $newarray[$i] = $array[$key];
                            $i++;
                        }
                        break;
                    case "lte":
                        if ($temp[$key] <= $value){
                            $newarray[$i] = $array[$key];
                            $i++;
                        }
                        break;
                    case "neq":
                        if ($temp[$key] != $value){
                            $newarray[$i] = $array[$key];
                            $i++;
                        }
                        break;
                }
                                
            }
            //return $newarray;
        }
        return $newarray;
    }
    
}

class func
{    
    
    static function getArrayFromFile($filePath) {
        if (!file_exists($filePath)) {
            return [
                'id'=> './.',
                'id'=> './.',
            ];
        }
    
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $result = [];
    
        foreach ($lines as $line) {
            $value = trim($line);
            if ($value !== '') {
                $result[] = [
                    'id' => $value,
                    'description' => $value
                ];
            }
        }
    
        return $result;
    }



     /**
 * Filter an array of associative arrays by a specific field/value pair.
 *
 * @param array  $items      The input array of associative arrays.
 * @param string $field      The key to check in each sub-array.
 * @param mixed  $value      The value to match (uses === for comparison).
 * @param bool   $reindex    Whether to reindex the returned array (default: true).
 *
 * @return array             The filtered (and optionally reindexed) array.
 */
static function filterArrayByField(array $items, string $field, $value, bool $reindex = true): array
{
    $filtered = array_filter(
        $items,
        function (array $item) use ($field, $value) {
            return array_key_exists($field, $item) && $item[$field] === $value;
        }
    );

    return $reindex ? array_values($filtered) : $filtered;
}
    
    
    
    static function validateIntegerInput($input) {
        // Trim whitespace from the input
        $input = trim($input);
    
        // Check if the input is a non-empty string
        if (!empty($input)) {
            // Check if the input is a valid integer
            if (ctype_digit($input) || ($input[0] === '-' && ctype_digit(substr($input, 1)))) {
                return true; // Input is a valid integer
            } else {
                return false; // Input is not a valid integer
            }
        } else {
            return false; // Input is empty
        }
    }
    
    
    static function CheckAFM($afm)
    {
        if (!preg_match('/^(EL){0,1}[0-9]{9}$/i', $afm))
            return false;
        if (strlen($afm) > 9)
            $afm = substr($afm, 2);

        $remainder = 0;
        $sum = 0;

        for ($nn = 2, $k = 7, $sum = 0; $k >= 0; $k--, $nn += $nn)
            $sum += $nn * ($afm[$k]);
        $remainder = $sum % 11;

        return ($remainder == 10) ? $afm[8] == '0'
                                  : $afm[8] == $remainder;
    }
    
    
    static function secureString($str) {
        $search = array("'", "SELECT", "DROP", "DELETE", "INSERT");
        return str_ireplace($search, "", $str);
    }
    
    
    static function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    
    
    static function str14toDate($str14, $delimiter="-", $locale = "GR") {
        if (strlen($str14)!=14) {
            return $str14;
        } 
        $YYYY = substr($str14, 0, 4);
        $MM = substr($str14, 4, 2);
        $DD = substr($str14, 6, 2);
        switch ($locale) {
            case "GR":
                return $DD.$delimiter.$MM.$delimiter.$YYYY;
                break;
            case "EN":
                return $YYYY.$delimiter.$MM.$delimiter.$DD;
                break;
            default:
        }
        
    }
    
    
    static function str14toDateTime($str14, $delimiter="-", $locale = "GR", $showsecs=FALSE) {
        if (strlen($str14)!=14) {
            return $str14;
        } 
        $YYYY = substr($str14, 0, 4);
        $MM = substr($str14, 4, 2);
        $DD = substr($str14, 6, 2);
        $HH = substr($str14, 8, 2);
        $mm = substr($str14, 10, 2);
        $ss = substr($str14, 12, 2);
        
        switch ($locale) {
            case "GR":
                $str = $DD.$delimiter.$MM.$delimiter.$YYYY." ".$HH.":".$mm;
                if ($showsecs) {
                    $str .= ":".$ss;
                }
                return $str;
                break;
            case "EN":
                return $YYYY.$delimiter.$MM.$delimiter.$DD." ".$HH.":".$mm.":".$ss;
                break;
            default:
        }
        
    }
    
    
    static function str14toGDATE($str14, $delimiter="-", $locale = "GR") {
        if (strlen($str14)!=14) {
            return $str14;
        } 
        $YYYY = substr($str14, 0, 4);
        $MM = substr($str14, 4, 2);
        $DD = substr($str14, 6, 2);
        
        return $YYYY.$delimiter.$MM.$delimiter.$DD." 00:00:00";
        
    }
    
    
    static function grDate($str, $delimiter="-", $delimiter2="/") {
        $str_sections = explode($delimiter, $str);
        return $str_sections[2].$delimiter2.$str_sections[1].$delimiter2.$str_sections[0];
    }
    
    static function grDateToDate($str, $delimiter="/", $delimiter2="-") {
        $str_sections = explode($delimiter, $str);
        return $str_sections[2].$delimiter2.$str_sections[1].$delimiter2.$str_sections[0];        
    }
    
    static function grDateToDate2($str, $delimiter="/", $delimiter2="-") {
        $str_sections = explode($delimiter, $str);
        if(self::validateDate($str)){
            return $str_sections[2].$delimiter2.$str_sections[1].$delimiter2.$str_sections[0];
        }else{
            return false;
        }
    }
    
    static function datetimeToGrDate($val, $delim="-") {
        $ar = explode(" ", $val);
        $ar1 = explode("-", $ar[0]);
        return $ar1[2] . $delim . $ar1[1] . $delim . $ar1[0];
    }
    
    
    static function dateTo14str($val, $delimiter = array("/","-","."), $locale = "GR") {
        $delem = self::explode_by_array($delimiter, $val);
        switch ($locale) {
            case "GR":
                $theYear = str_pad($delem[2], 4, '0', STR_PAD_LEFT);
                $theMonth = str_pad($delem[1], 2, '0', STR_PAD_LEFT);
                $theDay = str_pad($delem[0], 2, '0', STR_PAD_LEFT);
                return $theYear.$theMonth.$theDay."000000";
                break;
            case "EN":
                return $delem[2].$delem[0].$delem[1]."000000";
                break;
            default:
        }
    }
    
    
    static function date14addYear($date14, $nrOfYears) {
        $YYYY = (int) substr($date14, 0, 4);
        $MM = substr($date14, 4, 2);
        $DD = substr($date14, 6, 2);
        $YYYY += $nrOfYears;
        return (string) $YYYY . $MM . $DD . "000000";
        
    }

    static function subDays($date, $days, $sDateFormat='d/m/Y') {
        //set $date 30/05/2021
        //return date 29/05/2021  
        $date1 = date_create_from_format($sDateFormat, $date);
        date_sub($date1, date_interval_create_from_date_string($days.' days'));
        return date_format($date1, $sDateFormat); 
    }

    static function addDays($date, $days, $sDateFormat='d/m/Y') {
        //set $date 30/05/2021
        //return date 29/05/2021  
        $date1 = date_create_from_format($sDateFormat, $date);
        date_add($date1, date_interval_create_from_date_string($days.' days'));
        return date_format($date1, $sDateFormat); 
    }

    static function getDateDiff($date1, $date2, $sDateFormat='d-m-Y') {
        //Create a date object out of a string (e.g. from a database):
        $date1 = date_create_from_format($sDateFormat, $date1);

        //Create a date object out of today's date:
        $date2 = date_create_from_format($sDateFormat, $date2);

        $interval = (array) date_diff($date1, $date2);

        return $interval;
    }
    
    
    static function explode_by_array($delim, $input) {
        $unidelim = $delim[0];
        $step_01 = str_replace($delim, $unidelim, $input); //Extra step to create a uniform value
        return explode($unidelim, $step_01);
    }
    
    
    static function format($val,$type,$locale="GR") {
        switch ($type) {
            case "DATE":
                return self::str14toDate($val, "/", $locale);
                break;
            case "PERCENTAGE":
                if ($val=="") {
                    return "";
                }
                return number_format($val, 0)." %";
            case "CURRENCY":
                //echo $val;
                return self::nrToCurrency($val, $locale);
                break;
			case "CURRENCY3":
                //echo $val;
                return self::nrToCurrency($val, $locale, 3);
                break;
            case "CURRENCYNODOTSGR":
                return str_replace(".", "", 
                    self::nrToCurrency($val, $locale));
                break;
            case "YESNO":
                return self::yesno($val);
            case "YESNOHASCONTENT":
                return self::yesno($val, "CUSTOM", array("......",""));
            case "YESNOCHECKICON":
                //echo "YESNOCHECKICON";
                return self::yesno($val, "CUSTOM", array("<i class=\"fa fa-check\"></i>",""));
                break;
			case "CHECK":
                return self::yesno($val, "CUSTOM", array("<span style=\"font-size: 24px;\" class=\"glyphicon glyphicon-ok\"></span>",""));
            default :
                return $val;
        }
    }
    
    static function nrToCurrency($val, $locale="GR", $numAfterPoint=2, $zeroVal = "") {
        //$numAfterPoint - the maximum number of decimal places
        if ($val=='') {
            return '';
        }
        switch ($locale) {
            case "GR":
            case "gr":
                if ($val==0) {
                    return $zeroVal;
                }
                else {
                    return number_format($val, $numAfterPoint, ",", ".");
                }
                break;
            case "EN":
            case "en":
                return number_format($val, $numAfterPoint, ".", ",");
                break;
            default :
        }
    }
    
    static function removeZerosADecimalPoint($num){
        //remove unnecessary zeros after the decimal point
        return rtrim(rtrim($num, '0'), '.,');
    }
    
    static function CurrencyToNr($val,$locale="GR") {
        $mystr = $val;
        switch ($locale) {
            case "GR":
                $mystr = str_replace(".", "", $mystr);
                $mystr = str_replace(",", ".", $mystr);
                return $mystr;
                break;
            case "EN":
                $mystr = str_replace(",", "", $mystr);
                return $mystr;
                break;
            default :
        }
    }
    
    static function vlookup($fieldname, $tablename, $criteria, $conn)
    {
        $ssql = "SELECT " . $fieldname . " FROM " . $tablename . " WHERE " . $criteria;	
        $all_rows = $conn->getRS($ssql);
        $iCount = count($all_rows);
                
        if ($iCount > 0) {
            return $all_rows[0][$fieldname];
        }
        else {
            return "";	
        }
    }
    
    
    
    static function vlookupRS($fieldname, $rs, $idval) {
        for ($i = 0; $i < count($rs); $i++) {
            if ($rs[$i]['id'] == $idval) {
                return $rs[$i][$fieldname];
            }
        }
    }

    /*
    example
    $criteria = [
        ['field'=>'field1', 'val'=>val1],
        ['field'=>'field2', 'val'=>val2]
    ]
    */
    static function vlookupRS_multiField($fieldname, $rs, $criteria) {
        for ($i = 0; $i < count($rs); $i++) {
            $condition = TRUE;
            foreach ($criteria as $criterion) {
                if ($rs[$i][$criterion['field']] != $criterion['val']) {
                    $condition = FALSE;
                }
            }
            if ($condition) {
                return $rs[$i][$fieldname];
            }
            /*else {
                return "";
            }*/
        }
        return "";
    }
    
    
    
    static function yesno($val, $locale="GR", $arrayYN = NULL)
    {
        switch ($locale) {
            case "GR":
                switch ($val){
                    case 1:
                        return "Ναι";
                        break;
                    case 2:
                        return "Όχι";
                        break;
                    default:
                        return "Όχι";
                        break;
                }
            case "EN":
                switch ($val){
                    case 1:
                        return "Υes";
                        break;
                    case 2:
                        return "Νo";
                        break;
                    default:
                        return "Νo";
                        break;
                }
            case "CUSTOM":
                        switch ($val){
                    case 1:
                        return $arrayYN[0];
                        break;
                    case 2:
                        return $arrayYN[1];
                        break;
                    default:
                        return "";
                        break;
                }
                 
        }
    }
    
    static function validateNumber($val, $format = "", $locale="GR")
    {
        $return_numeric=0;
        if($format === "INT"){
            switch ($locale) {
                case "GR":
                    if(!empty($val)){
                        if(is_numeric($val)){
                            $return_numeric = intval($val);
                        }
                    }
                    break;
                case "EN":
                    break;

            }            
        }
        return $return_numeric;
    }
    
    static function validateDate($date, $format = "", $locale="GR")
    {
        if($format == ""){
            switch ($locale) {
                case "GR":
                    $format = "d/m/Y";
                    break;
                case "EN":
                    $format = "Y/m/d";
                    break;

            }            
        }
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    
    
    static function shortDescription($str,$length,$LR = "LEFT")
    {
        $myStr = $str;
        if (strlen($str) > $length){
            switch($LR){
                case "LEFT":
                    $myStr = substr($str,0,$length)." ...";
                    break;
                case "RIGHT":
                    $myStr = substr($str,-1,$length)." ...";
                    break;
            }
        }
        return $myStr;        
    }
    
    static function rsSum($rs,$col) {
        $res = 0;
        for ($i=0;$i<count($rs);$i++) {
            $res += $rs[$i][$col];
        }
        return $res;
    }
    
    static function get_category_path($catId, $conn) {
        $sep = '-';
        $ssql = "SELECT T2.id".
        " FROM ( ".
            "SELECT ".
                "@r AS _id, ".
                "(SELECT @r := parentid FROM CATEGORIES WHERE id = _id) AS parentid, ".
                "@l := @l + 1 AS lvl ".
            "FROM ".
                "(SELECT @r := ".$catId." , @l := 0) vars, ".
                "CATEGORIES h ".
            "WHERE @r <> 0) T1 ".
        "JOIN CATEGORIES T2 ".
        "ON T1._id = T2.id ".
        "ORDER BY T1.lvl DESC ";
        $result = $conn->getRS($ssql);
        $arrPath = array();
        for($i=0;$i<count($result);$i++){
            array_push($arrPath, $result[$i]['id']); 
        }
        $strPath = $sep.implode($sep, $arrPath).$sep;
        return $strPath;
    }
    
    static function ConcatSpecial($str1,$str2,$delimiter) {
        if ($str1=="") {
            return $str2;
        }
        else {
            return $str1 . $delimiter . $str2;
        }
    }
    
    static function GetItemsFromIds($ids, $myTable, $descrField, $idField, $dbo, $delimiter = ", ") {
        
        $str = str_replace(array("[","]"), "", $ids);
        
        $ar = explode(",", $str);
        $str2 = "";
        
        for ($k=0;$k<count($ar);$k++) {
            $criteria = $idField."=".$ar[$k];
            $myItem = func::vlookup($descrField, $myTable, $criteria, $dbo);
            if ($myItem!="") {
                $str2 = func::ConcatSpecial($str2, $myItem, $delimiter);
            }
        }
            
        return $str2;            
        
    }
    
    
    static function rsToTable($rs) {
        echo "<table>";
        for ($i = 0; $i < count($rs); $i++) {
            echo "<tr>";
            $row = $rs[$i];
            for ($k = 0; $k < count($row); $k++) {
                echo "<td>";
                echo $row[$k];
                echo "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        
    }        
    
        
}

class createMyOrder
{
    protected $_myconn, $_rs, $_id, $_fieldOrder, $_mOrder, $_table;
    public function __construct($myconn, $rs, $table, $id, $fieldOrder, $mOrder){
        $this->_myconn = $myconn;
        $this->_rs = $rs;
        $this->_id = $id;
        $this->_fieldOrder = $fieldOrder;
        $this->_mOrder = $mOrder;
        $this->_table = $table;
        
        if($this->_mOrder == 0){
            $rsTemp = $this->_myconn->execSQL("UPDATE ".$this->_table." SET ".$this->_fieldOrder."=? WHERE id=?", array(count($rs),$this->_id));
        }else{
            $i=1;
            if($this->_rs){
                foreach ($this->_rs as $valueId) {
                    if($valueId["id"] != $this->_id){    
                        $rsTemp = $this->_myconn->execSQL("UPDATE ".$this->_table." SET ".$this->_fieldOrder."=? WHERE id=?", array($this->_mOrder+$i,$valueId["id"]));
                        $i++;
                    }
                }
            }
        }
    }
}

class categories_view
{
    protected $_myconn, $_ssql, $_data, $_index, $_parent_id, $_level, $_rs_child_nodes, $_sep; 
    
    public function __construct($myconn, $ssql, $parent_id, $level, $sep="-") {
        $this->_myconn = $myconn;
        $this->_ssql = $ssql;
        $this->_parent_id = $parent_id;
        $this->_level = $level;
        $this->_data = array();
        $this->_index = array();
        $this->_rs_child_nodes = array();
        $this->_sep = $sep;
        
        $rows = $this->_myconn->getRS($this->_ssql);
        
        foreach ($rows as $value) {
            $id = $value["id"];
            $parent_id = $value["parent_id"] === NULL ? "NULL" : $value["parent_id"];
            $this->_data[$id] = $value;
            $this->_index[$parent_id][] = $id;
        }
    }

    /*
     * Recursive top-down tree traversal example:
     * Indent and print child nodes
     */
    function display_child_nodes($parent_id, $level)
    {
        /*
         * Electronics
            -Cameras and Photography
            --Accessories
            --Camcorders
            --Digital Cameras
         */
        $parent_id = $parent_id === NULL ? "NULL" : $parent_id;
        if (isset($this->_index[$parent_id])) {
            foreach ($this->_index[$parent_id] as $id) {
                //echo str_repeat("-|", $level) . $this->_data[$id]["name"]."</br>";
                array_push($this->_rs_child_nodes, str_repeat($this->_sep, $level) . $this->_data[$id]["name"]);
                $this->display_child_nodes($id, $level + 1);
            }
        }
    }
    
    function get_tree($parent_id, $level){
        $this->display_child_nodes($parent_id, $level);
        return $this->_rs_child_nodes;
    }
    
    
    function display_child_nodes_for_combobox($parent_id, $level)
    {
        /*
         * Electronics
            -Cameras and Photography
            --Accessories
            --Camcorders
            --Digital Cameras
         */
        $parent_id = $parent_id === NULL ? "NULL" : $parent_id;
        if (isset($this->_index[$parent_id])) {
            foreach ($this->_index[$parent_id] as $id) {
                $item = array();
                $item["id"] = $this->_data[$id]["id"];
                $item["description"] = str_repeat($this->_sep, $level) . $this->_data[$id]["name"];
                $item["parent_id"] = $this->_data[$id]["parent_id"];
                array_push($this->_rs_child_nodes, $item);
                $this->display_child_nodes_for_combobox($id, $level + 1);
            }
        }
    }
    
    function get_tree_for_combobox($parent_id, $level){
        $this->display_child_nodes_for_combobox($parent_id, $level);
        return $this->_rs_child_nodes;
    }
    
    /*
    * Retrieving nodes using return statement:
    * Get ids of child nodes
    */
   function get_child_nodes2($parent_id)
   {
       /*
        * $children = get_child_nodes2(5); /* TV and Audio */
        /*    echo implode("\n", $children);*/
        
       $children = array();
       $parent_id = $parent_id === NULL ? "NULL" : $parent_id;
       if (isset($this->_index[$parent_id])) {
           foreach ($this->_index[$parent_id] as $id) {
               $children[] = $id;
               $children = array_merge($children, $this->get_child_nodes2($id));
           }
       }
       return $children;
   }
   
	/*
	 * Display parent nodes
	 */
	function display_parent_nodes($id, $withCurrent = FALSE)
	{
		//global $data;
		$current = $this->_data[$id];
		$parent_id = $current["parent_id"] === NULL ? "NULL" : $current["parent_id"];
		$parents = array();
                if($withCurrent){
                    $i=count($parents);
                    $parents[$i]['name'] = $current["name"];
                    $parents[$i]['id'] = $current["id"];
                }
		while (isset($this->_data[$parent_id])) {
                    $i=count($parents);
                    $current = $this->_data[$parent_id];
                    $parent_id = $current["parent_id"] === NULL ? "NULL" : $current["parent_id"];
                    $parents[$i]['name'] = $current["name"];
                    $parents[$i]['id'] = $current["id"];
		}
                
		//echo implode(" > ", array_reverse($parents));
                return array_reverse($parents);
	}
	/*display_parent_nodes(24); /* iPad */
        
        function display_parent_nodes2($id, $withCurrent = FALSE, $level = 0, $sep = " / ")
	{
		//global $data;
		$current = $this->_data[$id];
		$parent_id = $current["parent_id"] === NULL ? "NULL" : $current["parent_id"];
		$parents = array();
                if($withCurrent){
                    $i=count($parents);
                    $parents[$i] = $current["name"];
                }
		while (isset($this->_data[$parent_id])) {
                    $i=count($parents);
                    $current = $this->_data[$parent_id];
                    $parent_id = $current["parent_id"] === NULL ? "NULL" : $current["parent_id"];
                    $parents[$i] = $current["name"];
		}
                
		//return implode(" > ", array_reverse(array_slice($parents, 1)));
                return implode(" > ", array_slice(array_reverse($parents), $level));
	}
	/*display_parent_nodes(24); /* iPad */
   
   
}




class wms {

    //Υπολογισμός αποθέματος για συγκεκριμένο είδος, χρωμα(0) μεγεθος(0) και θέση
    //δεν ενημερώνει την database
    static function inventory($item_id, $size_id, $color_id, $location_id, $dbo) {
        $quantity = "N/A";
        $LastApografiDate = "";

        //vgazoume teleytaia imerominia apografis
        $sql = "SELECT orders.order_date, order_lines.quantity FROM orders INNER JOIN order_lines ON orders.id = order_lines.order_id"
            ." WHERE doc_type = 6 AND order_status = 2 AND "
            ." order_lines.item_id = $item_id AND order_lines.item_size = $size_id AND "
            ." order_lines.item_color = $color_id AND location_id = $location_id "
            ." ORDER BY order_date DESC LIMIT 1";

        //echo $sql;
        $rsLastApografiDate = $dbo->getRS($sql);

        //if(count($rsLastApografiDate)>0){
        if($rsLastApografiDate){
            $quantity = $rsLastApografiDate[0]["quantity"];
            $LastApografiDate = $rsLastApografiDate[0]["order_date"];

            //vgazoume grammes apo parastatika
            $sql = "SELECT order_lines.quantity, order_lines.in_out FROM orders "
                ." INNER JOIN order_lines ON orders.id = order_lines.order_id"
                ." WHERE orders.order_date >= $LastApografiDate AND order_status in (2,6,8) AND doc_type in (1,5,8,17,18) AND "
                ." order_lines.item_id = $item_id AND order_lines.item_size = $size_id AND "
                ." order_lines.item_color = $color_id AND order_lines.location_id = $location_id "
                ." ORDER BY order_date";
            $rsOrdersLines = $dbo->getRS($sql); 
            
            foreach($rsOrdersLines as $orderLine){
                if($orderLine["in_out"]==1){
                    $quantity = $quantity + $orderLine["quantity"];
                }elseif($orderLine["in_out"]==-1){
                    $quantity = $quantity - $orderLine["quantity"];
                }
                
            }
            
        }        
        
        return $quantity;
    }

    //υπολογισμός αποθέματος σε όλες τις θέσεις
    static function inventoryForAllLocation($item_id, $size_id, $color_id, $dbo, $returnZero = FALSE) {
        //ypologizoyme to apouema toy eidos se oles apothikes
        $arQuantity = array();
        $sql = "SELECT * FROM LOCATIONS";
        $rs = $dbo->getRS($sql);


        foreach($rs as $location){     
            $quantity = wms::inventory($item_id, $size_id, $color_id, $location["id"], $dbo);
            if($quantity != "N/A"){
                array_push($arQuantity,$quantity);
            }
        }


        if(count($arQuantity) > 0){
            return number_format(array_sum($arQuantity),2);
        }elseif($returnZero){
            return 0;
        }else{
            return "N/A";
        }
    }


    //ypologizei desmeymeno apothema για παραγγελία
    //an yparxei orderid na min ypologizei paraggelia me ayto to orderid
    static function inventoryLocked($item_id, $size_id, $color_id, $dbo, $orderid=0) {        
        $quantity = 0;
        //an yparxei orderid na min ypologizei paraggelia me ayto to orderid
        $orderidCriteria = $orderid != 0?" orders.id <> $orderid AND ":"";
        $sql = "SELECT orders.id, order_lines.quantity FROM orders INNER JOIN order_lines ON orders.id = order_lines.order_id"
            ." WHERE doc_type = 1 AND order_status = 1 AND $orderidCriteria"
            ." order_lines.item_id = $item_id AND order_lines.item_size = $size_id AND "
            ." order_lines.item_color = $color_id  ";
        
        $rs = $dbo->getRS($sql);
        //echo "<!--inventoryLocked $sql \n-->";

        foreach($rs as $item){
           $quantity = $quantity + $item["quantity"];
        }

        return number_format($quantity,2);
    }

    

    //ypologizei anamenomena eidi apo paraggelies promitheyti σε εκκρεμότητα
    static function inventoryExpected($item_id, $size_id, $color_id, $dbo) {        
        $quantity = 0;
        $sql = "SELECT order_lines.quantity FROM orders INNER JOIN order_lines ON orders.id = order_lines.order_id"
            ." WHERE doc_type = 7 AND order_status = 1 AND "
            ." order_lines.item_id = $item_id AND order_lines.item_size = $size_id AND "
            ." order_lines.item_color = $color_id  ";
        $rs = $dbo->getRS($sql);

        foreach($rs as $item){
            $quantity = $quantity + $item["quantity"];
        }

        $quantity = number_format($quantity,2);        

        return $quantity;
    }

        
    //ενημερώνει την database
    static function updateInventory($item_id, $size_id, $color_id, $location_id, $dbo) {
        $res = "";
        $quantity = self::inventory($item_id, $size_id, $color_id, $location_id, $dbo);
        //tsekarume an iparxei eggrafi ston pinaka inventory
        $invId = func::vlookup("id", "INVENTORY", "item_id=".$item_id." AND size_id=".$size_id." AND color_id=".$color_id." AND location_id=".$location_id, $dbo);
        
        if($quantity != "N/A"){
            if($location_id > 0){
                if($invId == ""){
                    
                    //insert row
                    $res = $dbo->execSQL("INSERT INTO INVENTORY (item_id, size_id, color_id, quantity, location_id) VALUES (?,?,?,?,?)", 
                        array($item_id,$size_id,$color_id,$quantity,$location_id));
                }else{
                    //update row
                    $res = $dbo->execSQL("UPDATE INVENTORY SET item_id=?, size_id=?, color_id=?, quantity=?, location_id=?  WHERE id=?", 
                        array($item_id,$size_id,$color_id,$quantity,$location_id,$invId));
                }
            }

            //update eshop
            //self::updateInventoryEshop($item_id, $size_id, $color_id, $dbo);

        } else {
            $res = $dbo->execSQL("DELETE FROM INVENTORY WHERE id=?", array($invId));
        }

        $res = self::updateItemInventory($item_id, $size_id, $color_id, $dbo);

        return $res;
    }


    //ενημέρωση inventory για όλες τα είδη ενός παραστατικού
    static function updateInventoryByTransaction($order_id, $dbo) {
        $res = "";
        //tsekaroyme an iparxun grammes
        $sql = "SELECT item_id, item_size, item_color, location_id FROM order_lines WHERE order_id=$order_id";
        $rsLines = $dbo->getRS($sql);
        
        foreach($rsLines as $line){
            //enimerosi inventory
            $res = self::updateInventory($line["item_id"], $line["item_size"], $line["item_color"], $line["location_id"], $dbo); 
        }

        return $res;
    }

    //ενημέρωση αρχείου ειδών με απόθεμα
    static function updateItemInventory($item_id, $size_id, $color_id, $dbo) {
        //ypologisume sinoliko apothema kai enimeronume ton pinaka items

        $itemId = func::vlookup("id", "items", "id=".$item_id." AND default_size=".$size_id." AND default_color=".$color_id, $dbo);
        if($itemId > 0){
            //ypologisume sinoliko apothema 
            $totalQuantity = self::inventoryForAllLocation($item_id, $size_id, $color_id, $dbo, TRUE);
            //update item
            $res = $dbo->execSQL("UPDATE items SET inventory=?  WHERE id=?", array($totalQuantity,$itemId));
        }

        return $res;
    }


    //ενημέρωση αρχείου ειδών με δεσμευμένο απόθεμα
    static function updateItemLockedForOrder($item_id, $size_id, $color_id, $dbo) {
        //ypologisume desmeumena enimeronume ton pinaka items

        $itemId = func::vlookup("id", "items", "id=".$item_id." AND default_size=".$size_id." AND default_color=".$color_id, $dbo);
        if($itemId > 0){
            //ypologisume desmeumena
            $quantity = self::inventoryLocked($item_id, $size_id, $color_id, $dbo);
            //update item
            $res = $dbo->execSQL("UPDATE items SET inventory_locked_fororder=?  WHERE id=?", array($quantity,$itemId));
        }

        return $quantity;
    }

    //ενημέρωση αρχείου ειδών με αναμενόμενο απόθεμα
    static function updateItemExpected($item_id, $size_id, $color_id, $dbo) {
        //ypologisume anamenomena enimeronume ton pinaka items

        $itemId = func::vlookup("id", "items", "id=".$item_id." AND default_size=".$size_id." AND default_color=".$color_id, $dbo);
        if($itemId > 0){
            //ypologisume anamenomena
            $quantity = self::inventoryExpected($item_id, $size_id, $color_id, $dbo);
            //update item
            $res = $dbo->execSQL("UPDATE items SET inventory_expected=?  WHERE id=?", array($quantity,$itemId));
        }

        return $res;
    }


    //ενημέρωση αρχείου ειδών με δεσμευμένο απόθεμα
    //ενημέρωση αρχείου ειδών με αναμενόμενο απόθεμα
    //για report στατιστικών
    static function updateItemsExpectedAndLocked($dbo){
        //ipologizoyme kai enimeronoyme ta pedia inventory_locked_fororder kai inventory_expected sto pinaka items
        $expected = 0;
        $locked = 0;

        $sql = "SELECT id, default_size, default_color FROM items";
        $rsitems = $dbo->getRS($sql);

        foreach($rsitems as $item){
            //ypologisume sinoliko apothema 
            $totalQuantity = self::inventoryForAllLocation($item["id"], $item["default_size"], $item["default_color"], $dbo, TRUE);
            //update item
            $res = $dbo->execSQL("UPDATE items SET inventory=?  WHERE id=? AND default_size=? AND default_color=?", 
                array($totalQuantity,$item["id"], $item["default_size"], $item["default_color"]));

            //ypologisume anamenomena
            $quantity = self::inventoryExpected($item["id"], $item["default_size"], $item["default_color"], $dbo);
            //update item
            $res = $dbo->execSQL("UPDATE items SET inventory_expected=?  WHERE id=? AND default_size=? AND default_color=?", 
                array($quantity,$item["id"], $item["default_size"], $item["default_color"]));

            //ypologisume desmeumena
            $quantity = self::inventoryLocked($item["id"], $item["default_size"], $item["default_color"], $dbo);
            //update item
            $res = $dbo->execSQL("UPDATE items SET inventory_locked_fororder=?  WHERE id=? AND default_size=? AND default_color=?", 
                array($quantity,$item["id"], $item["default_size"], $item["default_color"]));

        }
    }


    //ενημέρωση αρχείου ειδών με δεσμευμένο απόθεμα
    //ενημέρωση αρχείου ειδών με αναμενόμενο απόθεμα
    //για report στατιστικών
    //όσα δεν έχουν versions
    static function updateItemsExpectedAndLocked2($dbo){
        //ipologizoyme kai enimeronoyme ta pedia inventory_locked_fororder kai inventory_expected sto pinaka items
        $expected = 0;
        $locked = 0;

        $sql = "SELECT items.id, items.default_size, items.default_color FROM 
                items left outer join item_versions ON items.id = item_versions.product_id   
                WHERE item_versions.product_id IS null order by items.description";
        $rsitems = $dbo->getRS($sql);

        foreach($rsitems as $item){
            //ypologisume sinoliko apothema 
            $totalQuantity = self::inventoryForAllLocation($item["id"], $item["default_size"], $item["default_color"], $dbo, TRUE);
            //update item
            $res = $dbo->execSQL("UPDATE items SET inventory=?  WHERE id=? AND default_size=? AND default_color=?", 
                array($totalQuantity,$item["id"], $item["default_size"], $item["default_color"]));

            //ypologisume anamenomena
            $quantity = self::inventoryExpected($item["id"], $item["default_size"], $item["default_color"], $dbo);
            //update item
            $res = $dbo->execSQL("UPDATE items SET inventory_expected=?  WHERE id=? AND default_size=? AND default_color=?", 
                array($quantity,$item["id"], $item["default_size"], $item["default_color"]));

            //ypologisume desmeumena
            $quantity = self::inventoryLocked($item["id"], $item["default_size"], $item["default_color"], $dbo);
            //update item
            $res = $dbo->execSQL("UPDATE items SET inventory_locked_fororder=?  WHERE id=? AND default_size=? AND default_color=?", 
                array($quantity,$item["id"], $item["default_size"], $item["default_color"]));

        }
    }


    //ενημέρωση item versions με δεσμευμένο απόθεμα
    //ενημέρωση item versions με αναμενόμενο απόθεμα
    //για report στατιστικών
    static function updateItemVersionsExpectedAndLocked($dbo){
        //ipologizoyme kai enimeronoyme ta pedia inventory_locked_fororder kai inventory_expected sto pinaka items
        $expected = 0;
        $locked = 0;

        $sql = "SELECT product_id, color_id, size_id FROM item_versions";
        $rsitemVersions = $dbo->getRS($sql);

        foreach($rsitemVersions as $itemVersion){
            //ypologisume sinoliko apothema 
             
            $totalQuantity = self::inventoryForAllLocation($itemVersion["product_id"], $itemVersion["size_id"], $itemVersion["color_id"], $dbo, TRUE);            
            //update item
            $res = $dbo->execSQL("UPDATE item_versions SET inventory=?  WHERE product_id=? AND size_id=? AND color_id=?", 
                array($totalQuantity,$itemVersion["product_id"], $itemVersion["size_id"], $itemVersion["color_id"]));

            //ypologisume anamenomena
            $quantity = self::inventoryExpected($itemVersion["product_id"], $itemVersion["size_id"], $itemVersion["color_id"], $dbo);
            //update item
            $res = $dbo->execSQL("UPDATE item_versions SET inventory_expected=?  WHERE product_id=? AND size_id=? AND color_id=?", 
                array($quantity,$itemVersion["product_id"], $itemVersion["size_id"], $itemVersion["color_id"]));

            //ypologisume desmeumena
            $quantity = self::inventoryLocked($itemVersion["product_id"], $itemVersion["size_id"], $itemVersion["color_id"], $dbo);
            //update item
            $res = $dbo->execSQL("UPDATE item_versions SET inventory_locked_fororder=?  WHERE product_id=? AND size_id=? AND color_id=?", 
                array($quantity,$itemVersion["product_id"], $itemVersion["size_id"], $itemVersion["color_id"]));

        }
    }

    

    

    
    

}



