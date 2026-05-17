<?php

//require_once 'utils.php';



/*
EXAMPLE
$gridUsers = new datagrid("gridAccounts", $conn,  
		"SELECT * FROM USERS ORDER BY username", 
		array("fullname","username","password","active","userprofile"), 
		array("NAME","USERNAME","PASSWORD","ACTIVE","PROFILE"),
		$ltoken, 0, 
		TRUE,"edituser.php","edit", 
		TRUE,"deluser.php","del"
		);
$gridUsers->set_colWidths(array("90%","150","150","50","50","30","30"));
$gridUsers->get_datagrid();
*/
class datagrid
{
    protected $_id, $_sql, $_fields, $_headers, $_edit, $_del, $_rowsPerPage, $_key;    
    protected $_gridheader, $_gridrows, $_rs;
    protected $_editUrl, $_delUrl;
    protected $_editLabel, $_delLabel;
    protected $_ltoken;
    protected $_tree, $_addNode, $_addNodeUrl, $_addLabel, $_parentKey, $_table;
    protected $_hasheaders;
    protected $_controlColWidths, $_colWidths;
    protected $_getsubgridpage;
    protected $_colsFormat;
    protected $_locale;
    protected $_conn;
    protected $_selectRow, $_selectLabel;
    protected $_footerAR, $_gridfooter;
    
    protected $_cellstyles;
    protected $_headerDescriptions;
    protected $_editimg, $_delimg;
    protected $_editTitle, $_delTitle;
    protected $_popupHeight;
    protected $_popupHeightEdit;
    protected $_popupWidthEdit;
    protected $_popupHeightDelete;
    protected $_editIcon, $_delIcon, $_addNodeIcon, $_nodeOpenIcon;


    public function __construct($id, $conn, $sql, $fields, $headers, $ltoken="l=gr", 
            $rowsPerPage=0, 
            $edit=FALSE, $editUrl="", $editLabel = "edit", 
            $del=FALSE, $delUrl="",  $delLabel = "del", 
            $key="id", $locale="GR") {
        $this->_id = $id;
        $this->_conn = $conn;
        $this->_sql = $sql;
        $this->_fields = $fields;
        $this->_headers = $headers;
        
        $this->_edit = $edit;
        $this->_editUrl = $editUrl;
        $this->_editLabel = $editLabel;
        
        $this->_del = $del;
        $this->_delUrl = $delUrl;
        $this->_delLabel = $delLabel;
        
        $this->_rowsPerPage = $rowsPerPage;
        $this->_key = $key;                
        $this->_ltoken = $ltoken;
        $this->_tree = FALSE;
        $this->_hasheaders = TRUE;
        $this->_controlColWidths = FALSE;
        $this->_getsubgridpage = "";
        $this->_locale = $locale;
        
        $this->_editIcon = "";
        $this->_delIcon = "";
        $this->_addNodeIcon = "";
        $this->_nodeOpenIcon = "";
                
        if ($this->_sql!="") {
            $this->_rs = $this->_conn->getRS($this->_sql);
        }
       
        $this->_cellstyles = $this->_rs;
        if ($this->_cellstyles) {
            for ($i=0;$i<count($this->_cellstyles);$i++) {
                for ($k=0;$k<count($this->_fields);$k++ ) {
                    $this->_cellstyles[$i][$this->_fields[$k]] = "";
                }
            }
        }
        
        
        $this->_selectRow = FALSE;
        $this->_selectLabel = "select";
        
        $this->CreateFooter();
        
        $this->_headerDescriptions = "";
        $this->_editimg = "open.png";
        $this->_delimg = "del.png";
        
        $this->_editTitle = "Επισκόπηση/επεξεργασία εγγραφής";
        $this->_delTitle = "Delete Record";
        $this->_popupHeight = 700;
        
    }
    
    public function set_editIcon($editIcon) {
        $this->_editIcon = $editIcon;
    }
    	
    public function set_delIcon($delIcon) {
        $this->_delIcon = $delIcon;
    }
	
    public function set_addNodeIcon($addNodeIcon) {
        $this->_addNodeIcon = $addNodeIcon;
    }
	
    public function set_nodeOpenIcon($nodeOpenIcon) {
        $this->_nodeOpenIcon = $nodeOpenIcon;
    }
    
    
    private function CreateFooter() {
        for ($i=0;$i<count($this->_fields);$i++) {
            $this->_footerAR[$this->_fields[$i]] = "";
        }
    }
    
    public function col_sum($colKey) {
        $myNr = 0;
        for ($i=0;$i<count($this->_rs);$i++) {
            $myNr += $this->_rs[$i][$colKey];
        }
        $this->_footerAR[$colKey] = $myNr;
    }
    
    public function setFooter($val) {
        $this->_footerAR = $val;
    }
    
    public function setFooterCol($col, $val) {
        $this->_footerAR[$col] = $val;
        //echo  $this->_footerAR[$col];
    }
    
    //public function set_edit($editUrl,$editLabel="Edit",$editImg="open.png",$editTitle = "EDIT RECORD", $popupHeight = 700, $popupWidth = 700) {
    public function set_edit($editUrl,$editLabel="Edit",$editImg="open.png",$editTitle = "EDIT RECORD", $popupHeight = 700, $popupWidth=1000) {
        $this->_edit = TRUE;
        $this->_editUrl = $editUrl;
        $this->_editLabel = $editLabel;
        $this->_editimg = $editImg;
        //$editTitle = str_replace("??", $this->_id, $editTitle);
        $this->_editTitle = $editTitle;
        $this->_popupHeightEdit = $popupHeight;
        $this->_popupWidthEdit = $popupWidth;
    }
    
    public function set_del($delUrl,$delLabel="Delete",$delImg="del.png",$delTitle = "DELETE RECORD", $popupHeight = 700) {
        $this->_del = TRUE;
        $this->_delUrl = $delUrl;
        $this->_delLabel = $delLabel;
        $this->_delimg = $delImg;
        $this->_delTitle = $delTitle;
        $this->_popupHeightDelete = $popupHeight;
    }
    
    public function set_TreeParams($addNode=FALSE, $addNodeUrl="", $addLabel="add", $parentKey="parentid", $table = "", $getsubgridpage = "") {
        $this->_tree = TRUE;
        $this->_addNode = $addNode;
        $this->_addNodeUrl = $addNodeUrl;
        $this->_addLabel = $addLabel;
        $this->_parentKey = $parentKey;
        $this->_table = $table;
        $this->_getsubgridpage = $getsubgridpage;
    }
    
    public function set_select($label="select") {
        $this->_selectRow = TRUE;
        $this->_selectLabel = $label;
    }
    
    public function get_rs(){
        return $this->_rs;
    }
    
    public function set_rs($val){
        $this->_rs = $val; 
    }
    
    public function set_key($val){
        $this->_key = $val; 
    }
    
    public function set_hasheaders($val) {
        $this->_hasheaders = $val;
    }
    
    public function set_colWidths($val) {
        $this->_controlColWidths = TRUE;
        $this->_colWidths = $val;
        
    }
    
    public function set_colsFormat($arr) {
        $this->_colsFormat = $arr;
    }
    
    
    public function set_headerDescriptions($arr) {
        $this->_headerDescriptions = $arr;
    }
    
    
    public function set_locale($locale) {
        $this->_locale = $locale;
    }
    
    public function col_vlookup($colid, $coldescr, $table, $field, $conn, $idField="id") {
        for ($i=0;$i<count($this->_rs);$i++) {
            $this->_rs[$i][$coldescr] = func::vlookup($field, $table, "$idField=".$this->_rs[$i][$colid], $conn);
        }
    }
    
    public function col_bgcolor($colid, $coldescr, $table, $field, $conn, $idField="id") {
        for ($i=0;$i<count($this->_cellstyles);$i++) {
            $bgcolor = func::vlookup($field, $table, "$idField=".$this->_rs[$i][$colid], $conn);
            //echo $bgcolor;
            $this->_cellstyles[$i][$coldescr] = "background-color: $bgcolor ";
            
        }
    }
    
    public function get_cellstyles(){
        return $this->_cellstyles;
    }
	
    public function col_func($colid, $coldescr, $sfunction, $factor="??") {
            for ($i=0;$i<count($this->_rs);$i++) {
                if ($this->_rs[$i][$colid]!="") {
                    $this->_rs[$i][$coldescr] = str_replace($factor,$this->_rs[$i][$colid],$sfunction);
                }
            }
    }
    
    public function removeDuplicates($colid) {
        $prev = "";
        for ($i=0;$i<count($this->_rs);$i++) {
            if ($this->_rs[$i][$colid]==$prev) {
                $this->_rs[$i][$colid] = "";
            }
            else {
               $prev = $this->_rs[$i][$colid];
            }
        }
    }
    
    public function GetItemsFromIds($conn, $colid, $myTable, $idField = "id", $descrField="description") {
        for ($i=0;$i<count($this->_rs);$i++) {
            $str = str_replace(array("[","]"), "", $this->_rs[$i][$colid]);
            //echo $str;
            $ar = explode(",", $str);
            $str2 = "";
            //if (count($ar)>0) {
                //$str2 = "<ul class=\"list\">";
                for ($k=0;$k<count($ar);$k++) {
                    $criteria = $idField."=".$ar[$k];
                    $myItem = func::vlookup($descrField, $myTable, $criteria, $conn);
                    //echo $myItem;
                    if ($myItem!="") {
                        $str2 = func::ConcatSpecial($str2, "- ".$myItem, "<br/>");
                    }
                }
                //$str2 .= "</ul>";
            //}
            $this->_rs[$i][$colid] = $str2;            
        }
    }
    
    
    public function GetItemsFromIdsRS($colid, $rs, $descrField="description") {
        for ($i=0;$i<count($this->_rs);$i++) {
            $str = str_replace(array("[","]"), "", $this->_rs[$i][$colid]);
            $ar = explode(",", $str);
            $str2 = "";            
            for ($k=0;$k<count($ar);$k++) {                
                $myItem = func::vlookupRS($descrField, $rs, $ar[$k]);
                if ($myItem!="") {
                    $str2 = func::ConcatSpecial($str2, "- ".$myItem, "<br/>");
                }
            }
                
            $this->_rs[$i][$colid] = $str2;            
        }
    }
    
    
    
    public function GetColCount($colid, $val, $factor="equals") {
        $MyCount = 0;
        for ($i=0;$i<count($this->_rs);$i++) {            
            switch ($factor) {
                case "equals": $criteria = $this->_rs[$i][$colid]==$val; break;
                case "gt": $criteria = $this->_rs[$i][$colid]>$val; break;
                case "gte": $criteria = $this->_rs[$i][$colid]>=$val; break;
                case "lt":  $criteria = $this->_rs[$i][$colid]<$val;  break;
                case "lte":  $criteria = $this->_rs[$i][$colid]<=$val;  break;
                case "nn":  $criteria = $this->_rs[$i][$colid]!="";  break;
                default : $criteria = $this->_rs[$i][$colid]==$val; break;
                    
            }
            if ($criteria) {
                $MyCount++;
            }
        }
        return $MyCount;
    }
    
    
    private function createGridHeader() {
        
        if ($this->_controlColWidths) {
            $col=0;
            if ($this->_tree) {
                $this->_gridheader = "<th width=\"".$this->_colWidths[$col]."\">.</th>";
                $col++;
            }
            for ($i=0;$i<count($this->_headers);$i++) {
                if ($this->_headerDescriptions != "") {
                    $this->_gridheader .= "<th title=".$this->_headerDescriptions[$col].
                            " width=\"".$this->_colWidths[$col]."\">".$this->_headers[$i]."</th>";
                }
                else {
                    $this->_gridheader .= "<th width=\"".$this->_colWidths[$col]."\">".$this->_headers[$i]."</th>";
                }                
                $col++;
            }
            if ($this->_edit) {
                $this->_gridheader .= "<th width=\"".$this->_colWidths[$col]."\">".$this->_editLabel."</th>";
                $col++;
            }
            if ($this->_del) {
                $this->_gridheader .= "<th width=\"".$this->_colWidths[$col]."\">".$this->_delLabel."</th>";
                $col++;
            }
            if ($this->_addNode) {
                $this->_gridheader .= "<th width=\"".$this->_colWidths[$col]."\">".$this->_addLabel."</th>";
                $col++;
            } 
            if ($this->_selectRow) {
                $this->_gridheader .= "<th width=\"".$this->_colWidths[$col]."\">".$this->_selectLabel."</th>";
                $col++;
            }
        }
        else {        
            if ($this->_tree) {
                $this->_gridheader = "<th>.</th>";
            }
            for ($i=0;$i<count($this->_headers);$i++) {
                $this->_gridheader .= "<th>".$this->_headers[$i]."</th>";
            }
            if ($this->_edit) {

                $this->_gridheader .= "<th>".$this->_editLabel."</th>";
            }
            if ($this->_del) {
                $this->_gridheader .= "<th>".$this->_delLabel."</th>";
            }
            if ($this->_addNode) {
                $this->_gridheader .= "<th>".$this->_addLabel."</th>";
            } 
            if ($this->_selectRow) {
                $this->_gridheader .= "<th>".$this->_selectLabel."</th>";
            }
        }
        
    }
    
    private function createGridRows() {
        $tdclass = "odd";
        $cols = 0;
        if ($this->_rs) {
            for ($i=0;$i<count($this->_rs);$i++) {
                $this->_gridrows .= "<tr id=\"".$this->_rs[$i][$this->_key]."\">";
                if ($this->_tree) {
                    $this->_gridrows .= "<td class=\"".$tdclass."\">";            
                    //*****
                    if ($this->_rs[$i]["nodes"]==1) {
                        $this->_gridrows .= "<a onclick=\"ExpandTree(this, '".$this->_getsubgridpage."');return false;\" id=\"a".$this->_rs[$i][$this->_key]."\" class=\"inline-button tree\" href=\"#\">";
                        //$this->_gridrows .= "+"; //..............
                        if($this->_nodeOpenIcon == ""){
                            $this->_gridrows .= "+"; //..............
                        }else{
                            $this->_gridrows .= $this->_nodeOpenIcon;
                        }
                        $this->_gridrows .= "</a>";
                    }
                    $cols++;
                    $this->_gridrows .= "</td>";
                }
                for ($k=0;$k<count($this->_fields);$k++) {
                    $align = "left";
                    if ($this->_colsFormat && ($this->_colsFormat[$k]=="CURRENCY" 
                            || $this->_colsFormat[$k]=="PERCENTAGE")) {
                        $align = "right";
                    }
                    if ($this->_colsFormat && $this->_colsFormat[$k]=="NR") {
                        $align = "right";
                    }
                    $curField = $this->_fields[$k];
                    $tdId = $curField."-".$i;

                    $cellStyle = "";
                    if ($this->_cellstyles[$i][$curField]!="") {
                        $cellStyle = $this->_cellstyles[$i][$curField];
                        $cellStyle = "style=\"$cellStyle\""; 
                    }

                    $this->_gridrows .= "<td $cellStyle id=\"$tdId\" align=\"".$align."\" class=\"".$tdclass." ".$curField."\">";                
                    $cellval = "";
                    if ($this->_colsFormat) {
                        $cellval = func::format($this->_rs[$i][$curField], $this->_colsFormat[$k], $this->_locale);
                    }
                    else {
                        $cellval = $this->_rs[$i][$curField];
                    }
                    $this->_gridrows .= $cellval;
                    $this->_gridrows .= "</td>";
                    $cols++;
                }
                if ($this->_edit) {
                    $this->_gridrows .= "<td style=\"\" class=\"".$tdclass."\">"; 
                    if (strpos($this->_editUrl, "?")!=FALSE) {
                        $editurl = $this->_editUrl."&id=".$this->_rs[$i][$this->_key]."&".$this->_ltoken;
                    }
                    else {
                        $editurl = $this->_editUrl."?id=".$this->_rs[$i][$this->_key]."&".$this->_ltoken;
                    }

                    $editTitle = str_replace("??", $this->_rs[$i][$this->_key],  $this->_editTitle);

                    if ($this->_rs[$i][$this->_key]>0) {
                        //$this->_gridrows .= "<a style=\"cursor:pointer\" class=\"modalBtn\"  data-title=\"$editTitle\" data-href=\"".$editurl."\" data-height=\"$this->_popupHeightEdit\" data-width=\"$this->_popupWidthEdit\" >";
                        $this->_gridrows .= "<a style=\"cursor:pointer\" class=\"modalBtn\"  data-title=\"$editTitle\" data-href=\"".$editurl."\" data-height=\"$this->_popupHeightEdit\" data-width=\"$this->_popupWidthEdit\" >";

                        //$this->_gridrows .= "<img src=\"img/".$this->_editimg."\"/>"; //..............
                        $this->_gridrows .= '<span class="glyphicon glyphicon-edit"></span>';
                        $this->_gridrows .= "</a>";
                    }

                    $this->_gridrows .= "</td>";

                    $cols++;
                }
                if ($this->_del) {
                    $this->_gridrows .= "<td style=\"text-align:left\" class=\"".$tdclass."\">";
                    $delurl = $this->_delUrl."?id=".$this->_rs[$i][$this->_key];
                    $delTitle = str_replace("??", $this->_rs[$i][$this->_key],  $this->_delTitle);
                    if ($this->_rs[$i][$this->_key]>0) {
                        $this->_gridrows .= 
                                "<a style=\"cursor:pointer\" class=\"modalBtn\" data-title=\""
                                . "$delTitle\" data-href=\"".$delurl."\" data-height=\"$this->_popupHeightDelete\" >";
                        $this->_gridrows .= '<span class="glyphicon glyphicon-remove"></span>';
                        $this->_gridrows .= "</a>";
                    }
                    $this->_gridrows .= "</td>";
                    $cols++;
                }
                if ($this->_addNode) {
                    $this->_gridrows .= "<td style=\"text-align:left\" class=\"".$tdclass."\">";
                    $addNodeUrl = $this->_addNodeUrl."?id=".$this->_rs[$i][$this->_key];
                    $this->_gridrows .= "<a data-toggle=\"modal\" class=\"modalButton\" data-target=\"#myModal\" href=\"".$addNodeUrl."\">";
                    //$this->_gridrows .= "<img src=\"img/add.png\"/>"; //..............
                    $this->_gridrows .= '<span class="glyphicon glyphicon-plus"></span>';
                    $this->_gridrows .= "</a></td>";
                    $cols++;
                }

                if ($this->_selectRow) {
                    $this->_gridrows .= "<td style=\"text-align:center\" class=\"".$tdclass."\">";              
                    $this->_gridrows .= "<input class=\"checkrow\" type=\"checkbox\" name=\"chkRow".$i."\" value=\"".$this->_rs[$i][$this->_key]."\" /></td>"; /*Giorgos*/
    //                $this->_gridrows .= "<input type=\"checkbox\" name=\"chkRow[]\" value=\"1\" /></td>";  /*Dimitrios*/
                    $cols++;
                }

                $this->_gridrows .= "</tr>";

                //*****
                if ($this->_tree) {
                    $this->_gridrows .= "<tr><td style=\"padding:0px\" colspan=\"".$cols."\"><div id=\"subgrid".$this->_rs[$i][$this->_key]."\" class=\"subgrid\"></div></td></tr>";
                }

                //allagi row class / for coloring purposes
                if ($tdclass=="odd") { $tdclass="even"; }
                else { $tdclass="odd";}
            }
        }
        if ($this->_hasheaders) {
            $this->_gridrows .= "<tfoot><tr><td class=\"table-footer\" colspan=\"".$cols."\"></td></tr>";
        }
    }
    
    
    private function CreateGridFooter() {
        if ($this->_footerAR!=NULL) {
            $this->_gridfooter = "<tr>";
            $keys = array_keys($this->_footerAR);
            for ($i=0;$i<count($this->_footerAR);$i++) {
                $this->_gridfooter .= '<td class="table-footer">'.func::format($this->_footerAR[$keys[$i]],"CURRENCY").'</td>';
                //********************
            }
            $this->_gridfooter .= "</tr></tfoot>";
        }
    }
    

    
    public function get_datagrid() {
        $this->createGridHeader();
        $this->createGridRows();
        $this->CreateGridFooter();
        
        echo "<div class=\"datagrid-container\">";
        echo '<table id="'.$this->_id.'" class="table" cellspacing="1">';
        echo "<thead>"; 
        if ($this->_hasheaders) {           
            echo $this->_gridheader; 
        }else{}
        
        echo "</thead><tbody>";
        echo $this->_gridrows; 
        if ($this->_hasheaders) {
            echo $this->_gridfooter;
        }
        echo '</tbody></table>';
        echo "</div>"; 
        
    }

    
    public function get_reverse_datagrid() {
        $id = $this->_id;
        echo "<table id=\"$id\" class=\"table\">";
        for ($i=0; $i<count($this->_headers); $i++) {
            echo "<tr>";
            echo "<td>".$this->_headers[$i]."</td>";
            for ($k=0; $k<count($this->_rs); $k++) {
                if ($this->_colsFormat) {
                    $val = func::format($this->_rs[$k][$this->_fields[$i]], $this->_colsFormat[$i]);
                }
                else {
                    $val = $this->_rs[$k][$this->_fields[$i]];
                }
                echo "<td>$val</td>";
            }            
            echo "</tr>";
        }
        echo "</table>";
    }
    
    
}


class dateTimePicker
{

    protected $_id, $_label, $_val, $_placeholder, $_format, $_locale, $_class, $_type;
    protected $_disabled;
    protected $_extraAttr;

    public function __construct($id, $label, $val, $placeholder="") {
        $this->_id = $id;
        $this->_label = $label;
        $this->_val = $val;
        $this->_placeholder = $placeholder;
        $this->_type = 'text';
        $this->_disabled = FALSE;
    }

    public function set_disabled() {
        $this->_disabled = TRUE;
    }

    public function set_locale($locale) {
        $this->_locale = $locale;
    }

    public function set_extraAttr($attr) {
        $this->_extraAttr = $attr;
    }
    
    public function set_class($class) {
        $this->_class = $class;
    }

    public function getControl() {
               
        $input = $this->getControlSimple();
        echo '<div class="col-4 col-sm-12">'.$this->_label.'</div>' .            
            '<div class="col-8 col-sm-12">'.$input.'</div>';

    }

    public function getControlSimple() {
        $id = $this->_id;
        $val = $this->_val;
        $valDate = substr($val,6,2) . "/" . substr($val,4,2) . "/" . substr($val,0,4);
        $valTime = substr($val,8,2) . ":" . substr($val,10,2);

        $control = "<div id=\"$id-container\">"; 
        $control .= "<input id=\"$id-date\" name=\"$id-date\" class=\"datepicker form-control\" type=\"text\" value=\"$valDate\" style=\"display:inline-block; width:120px\" autocomplete=\"off\" />";
        $control .= "<input id=\"$id-time\" name=\"$id-time\" class=\"form-control\" type=\"time\" value=\"$valTime\" style=\"display:inline-block; width:120px\" />";
        $control .= "</div>";

        return $control;

    }

    static function getValue($post, $controlId, $locale="GR") {
        $postDate = $post["$controlId-date"];
        $postTime = $post["$controlId-time"];
        $myDate = func::dateTo14str($postDate, "/", $locale);
        $myDate = substr($myDate, 0, 8);
        $myTime = str_replace(":", "", $postTime);
        return $myDate . $myTime . "00";
    }
    

}



/*
FORMAT DATE > class = datepicker (+javascript)
*/
class textbox
{
    protected $_id, $_label, $_val, $_multiline, $_placeholder, $_format, $_locale, $_class, $_type;
    protected $_disabled;
    protected $_extraAttr;
    protected $_multilineRows;


    public function __construct($id, $label, $val, $placeholder="", $multiline=FALSE) {
        $this->_id = $id;
        $this->_label = $label;
        $this->_val = $val;
        $this->_multiline = $multiline;
        $this->_placeholder = $placeholder;
        $this->_type = 'text';
        $this->_disabled = FALSE;
        $this->_multilineRows = 5;
    }
    
    public function set_format($type) {
        $this->_format = $type;
        
        if ($type=="DATE") {
            if ($this->_class == "") {
                $this->_class = "datepicker";
            }            
            if ($this->_placeholder == "") {
                $this->_placeholder = "ΗΗ/ΜΜ/ΕΕΕΕ";
            }
            
        }
    }
    
    public function set_disabled() {
        $this->_disabled = TRUE;
    }
    
    public function set_multiline() {
        $this->_multiline = TRUE;
    }
    
    public function set_multilineRows($rowsRr) {
        $this->_multilineRows = $rowsRr;
    }
    
    //text, password, hidden
    public function set_type($TextType="text") {
        $this->_type = $TextType;
    }
    
    public function set_locale($locale) {
        $this->_locale = $locale;
    }
    
    public function set_extraAttr($attr) {
        $this->_extraAttr = $attr;
    }
    
    public function set_class($class) {
        $this->_class = $class;
    }

    public function get_Textbox() {
        //        $input = "";
        //        $val = "";
        //        if ($this->_format!="") {
        //            $val = func::format($this->_val, $this->_format, $this->_locale);
        //        }
        //        else {
        //            $val = $this->_val;
        //        }       
        //        
        //        switch ($this->_multiline) {
        //            case FALSE:
        //                $input = '<input class="'.$this->_class.'" type="'.$this->_type.'" name="'.$this->_id.'" id="'.$this->_id.'" value="'.$val.'" placeholder="'.$this->_placeholder.'" />';
        //                break;
        //            case TRUE:
        //                $input = '<textarea class="'.$this->_class.'" name="'.$this->_id.'" id="'.$this->_id.'" cols="" rows="10" placeholder="'.$this->_placeholder.'">'.$val.'</textarea>';
        //                break;
        //            default:
        //        }        
        $input = $this->textboxSimple();
        echo '<div class="col-4 col-sm-12">'.$this->_label.'</div>            
            <div class="col-8 col-sm-12">'.$input.'</div>';
    }
    
    public function textboxSimple() {
        $val = "";
        $extraAttr = $this->_extraAttr;
        if ($this->_format!="") {
            $val = func::format($this->_val, $this->_format, $this->_locale);
        }
        else {
            $val = $this->_val;
        } 
        
        //$autocomplete = $this->_format=="DATE"? "autocomplete=\"off\"": "";
        $autocomplete = "autocomplete=\"off\"";
        
        switch ($this->_multiline) {
            case FALSE:
                if ($this->_disabled) {
                    return '<input '.$extraAttr.' class="form-control '.$this->_class.'" type="'.$this->_type.'" name="'.$this->_id.'" id="'.$this->_id.'" value="'.$val.'" placeholder="'.$this->_placeholder.'" readonly="readonly" />';
                }
                else {
                    return '<input '.$extraAttr.' class="form-control '.$this->_class.'" type="'.$this->_type.'" name="'.$this->_id.'" id="'.$this->_id.'" value="'.$val.'" placeholder="'.$this->_placeholder.'" '.$autocomplete.' />';
                }
            case TRUE:
                if ($this->_disabled) {
                    return '<textarea '.$extraAttr.' class="form-control '.$this->_class.'" name="'.$this->_id.'" id="'.$this->_id.'" cols="" rows="'.$this->_multilineRows.'" placeholder="'.$this->_placeholder.'" readonly="readonly">'.$val.'</textarea>';
                }
                else {
                    return '<textarea '.$extraAttr.' class="form-control '.$this->_class.'" name="'.$this->_id.'" id="'.$this->_id.'" cols="" rows="'.$this->_multilineRows.'" placeholder="'.$this->_placeholder.'">'.$val.'</textarea>';
                }
            default:
        }
    }
    
    //GET DATA FROM TEXTBOX
    //RETURNS 20140708000000
    static function getDate($val, $locale="GR") {
        if ($val=="") {
            return "";
        }
        else {
            return func::dateTo14str($val, "/", $locale);
        }
    }
    
    static function getCurrency($val, $locale="GR") {
        return func::CurrencyToNr($val, $locale);
    }
	
	
    
    //func::CurrencyToNr($_POST['txtCharge'], config::$locale)
}



class password
{
    
    protected $_id, $_label, $_val;
    
    public function __construct($id, $label, $val) {
        $this->_id = $id;
        $this->_label = $label;
        $this->_val = $val;
    }
    
    public function getControl() {
        
        $myTextbox = new textbox($this->_id, $this->_label, "");
        $myTextbox->set_type("password");
        echo $myTextbox->textboxSimple();
        
    }
    
    public function getFullControl() {
        $label = $this->_label;
        echo "<div class=\"col-4 col-sm-12\">$label</div><div class=\"col-8 col-sm-12\">";
        $this->getControl();
        echo "</div>";
        
    }
    
    static function getVal($postArray, $controlId) {
        return $postArray[$controlId];
    }
    
    
    
}



//add this to header
//<script src="//cdn.tinymce.com/4/tinymce.min.js"></script>
class richTextbox
{
    
    protected $_id, $_label, $_val, $_class;
    
    public function __construct($id, $label, $val) {
        $this->_id = $id;
        $this->_label = $label;
        $this->_val = $val;        
    }
    
    public function setClass($val) {
        $this->_class = $val;
    }
    
    public function getControl() {
        $controlId = $this->_id;
        $myTextbox = new textbox($controlId, $this->_label, $this->_val, "", TRUE);
        echo $myTextbox->textboxSimple();

        $myAppDomain = app::$app_domain;
        
        echo <<<EOT
        
        <script>
        var publisherEditorTheme = window.PublisherTheme ? window.PublisherTheme.get() : (document.documentElement.getAttribute('data-theme') || 'light');
        tinymce.init({
          selector: '#$controlId',
            relative_urls : false,
            remove_script_host : false,
            entities: 'raw',
            entity_encoding: 'raw',
            convert_urls : true,
            plugins: 'code, link, lists, image, table',
            toolbar: 'undo redo | styleselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | code',
            image_advtab: true,
            skin: publisherEditorTheme === 'dark' ? 'oxide-dark' : 'oxide',
            content_css: publisherEditorTheme === 'dark' ? ['dark', 'https://{$myAppDomain}/admin/css/editor-styles.css'] : ['default', 'https://{$myAppDomain}/admin/css/editor-styles.css'],
            setup: function(editor) {
                editor.on('init', function() {
                    if (window.PublisherTheme) {
                        window.PublisherTheme.refresh();
                    }
                });
            }
        });
        </script>
        
EOT;
        
    }
    
    
    public function getFullControl() {
        $label = $this->_label;
        echo "<div class=\"col-4 col-sm-12\">$label</div><div class=\"col-8 col-sm-12\">";
        $this->getControl();
        echo "</div>";
        
    }
    
    
}


class button
{
    protected $_id, $_type, $_label, $_method;
    protected $_hintText;
    protected $_styleClass;
    //TYPE=submit, button
    
    public function __construct($id, $label, $type="submit" ) {
        $this->_id = $id;
        $this->_label = $label;
        $this->_type = $type; 
        $this->_hintText = "";
        $this->_styleClass = "btn-success";
    }
    
    public function set_method($val) {
        $this->_method = $val;
    }
    
    public function set_styleClass($val) {
        $this->_styleClass = $val;
    }
    
    public function set_hintText($val) {
        $this->_hintText = $val;
    }
    
    public function get_button() {
        
        $control = $this->get_button_simple();
        
        echo '<div class="col-4 col-sm-12"></div>            
            <div class="col-8 col-sm-12">' .
            $control .
            '</div>';        
    }
    
    public function get_button_simple() {
        
        $title = "";
        if ($this->_hintText!="") {
            $title = "title=\"".$this->_hintText."\"";
        }
        
        switch ($this->_type) {
            case "submit":
                return '<input class="btn '.$this->_styleClass.'" '.$title.' name="'.$this->_id.'" id="'.$this->_id.'" type="'.$this->_type.'" value="'.$this->_label.'" />'; 
                break;
            case "close-update":
                return "<input class=\"btn\" '.$title.' name=\"$this->_id\" id=\"$this->_id\"  onclick=\"window.parent.location.reload(false);\" type=\"button\" value=\"Close & update\" />";
                break;
            default :
                return "<input class=\"btn btn-default\" '.$title.' name=\"$this->_id\" id=\"$this->_id\" onclick=\"".$this->_method."\" type=\"button\" value=\"".$this->_label."\" />";
                break;
        }  
        
    }
    
    
}



class comboBox
{
    protected $_id, $_sql, $_idField, $_descField, $_currentId, $_label;
    protected $_rs;
    protected $_disabled;
    protected $_dontSelect;
    protected $_zerochoice;
    protected $_count;
    
    public function __construct($id, $conn, $sql, $idField="id", $descField="description", $currentId=0, $label="") {
        $this->_id = $id;        
        $this->_idField = $idField;
        $this->_descField = $descField;
        $this->_currentId = $currentId;
        $this->_label = $label;
        
        if ($sql!="" && strpos($sql, "ORDER BY")==FALSE) {
            $sql .= " ORDER BY " . $descField;
        }
        $this->_sql = $sql;
        if ($sql!="") {
            $this->_rs = $conn->getRS($sql);
        }
        $this->_disabled = FALSE;
        $this->_dontSelect = "...";
        $this->_zerochoice = true;
    }
    
    public function setRs($rs) {
        $this->_rs = $rs;
    }
    
    public function setCurrentId($val) {
        $this->_currentId = $val;
    }
    
    public function setLabel($val) {
        $this->_label = $val;
    }
    
    public function set_zerochoice($val) {
        $this->_zerochoice = $val;
    }
    
    
    public function set_disabled() {
        $this->_disabled = TRUE;
    }
    
    public function set_dontSelect($val) {
        $this->_dontSelect = $val;
    }
    
    
    public function get_comboBox() {
        
        $control = $this->comboBox_simple();
        echo '<div class="col-4 col-sm-12">'.$this->_label.'</div>            
            <div class="col-8 col-sm-12">';
        echo $control;
        echo "</div>";
    }
    
    public function comboBox_simple() {
        $control = "<select class=\"form-control\" name=\"" . $this->_id . "\" id=\"" . $this->_id . "\" ";
        
        if ($this->_disabled == TRUE) {
            $control .= " disabled ";
        }
        $control .= " >";
        
        if ($this->_zerochoice) {
            $control .= "<option value=\"0\" >$this->_dontSelect</option>"; ////
        }
        if ($this->_rs) {
            for ($i=0;$i<count($this->_rs);$i++){             
                if ($this->_idField!="") {
                    $cur_id = $this->_rs[$i][$this->_idField];
                }
                else {
                    $cur_id = $this->_rs[$i][0];
                }
                if ($this->_descField!="") {
                    $cur_descr = $this->_rs[$i][$this->_descField];
                }
                else {
                    $cur_descr = $this->_rs[$i][1];
                }            
                if ($cur_id == $this->_currentId) {
                    $control .= "<option value=\"" . $cur_id . "\" selected=\"selected\">" . $cur_descr . "</option>";
                }
                else {
                    $control .= "<option value=\"" . $cur_id . "\" >" . $cur_descr . "</option>";
                }
            }
        }
        $control .= "</select>";
        return $control;
        
    }
    
    public function getCount() {
        return count($this->_rs);
    }
    
}



class comboBox_multiselect
{
    protected $_id, $_sql, $_idField, $_descField, $_currentId, $_label;
    protected $_rs;
    protected $_disabled;
    protected $_extraAttr;
    protected $_dontSelect;
    protected $_zerochoice;
    
    public function __construct($id, $conn, $sql, $idField="id", $descField="description", $currentId=0, $label="") {
        $this->_id = $id;        
        $this->_idField = $idField;
        $this->_descField = $descField;
        $this->_currentId = $currentId;
        $this->_label = $label;
        $this->_multiselect = FALSE;
        
        if (strpos($sql, "ORDER BY")==FALSE) {
            $sql .= " ORDER BY " . $descField;
        }
        $this->_sql = $sql;
        if ($this->_sql!="") {
            $this->_rs = $conn->getRS($sql);
        }
        $this->_disabled = FALSE;        
        $this->_dontSelect = "...";
        $this->_zerochoice = true;
    }
    
    public function set_zerochoice($val) {
        $this->_zerochoice = $val;
    }
    public function set_enableNoChoice($val) {
        $this->_zerochoice = $val;
    }
    
    public function set_dontSelect($val) {
        $this->_dontSelect = $val;
    }
    public function set_noChoiceDescription($val) {
        $this->_dontSelect = $val;
    }
    
    public function set_rs($rs) {
        $this->_rs = $rs;
    }    
    
    public function set_disabled() {
        $this->_disabled = TRUE;
    }
    
    public function set_extraAttr($attr) {
        $this->_extraAttr = $attr;
    }
    
    public function set_multiselect($multiselect) {
        $this->_multiselect = $multiselect;
    }
    
    public function get_comboBox() {
        
        $control = $this->comboBox_simple();
        echo '<div class="col-4 col-sm-12">'.$this->_label.'</div>            
            <div class="col-8 col-sm-12">';
        echo $control;
        echo "</div>";
    }
    
    static function get_ids($array_ids, $sep=",", $begin_sep_item="[", $end_sep_item="]"){
        //create string [15],[32],[45]
        if(is_array($array_ids)){
            foreach ($array_ids as $key => $value) {
                $array_ids[$key] = $begin_sep_item . $value . $end_sep_item;
            }
            $result = implode($sep, $array_ids);
        }
        else{
            $result = FALSE;
        }
        return $result;    
    }
    
    static function set_current_ids($str_ids, $sep=",", $begin_sep_item="[", $end_sep_item="]"){
        //create array([0]=>"[15]",[1]=>"[32]"[2]=>"[45]" ...)
        if($str_ids != ""){
            $str_ids = str_replace(array($begin_sep_item, $end_sep_item), "", $str_ids);
            $result = explode($sep, $str_ids);
        }
        else{
            $result = FALSE;
        }
        return $result;
    }
    
    public function comboBox_simple() {
        $id = str_replace("[]", "", $this->_id); // multiselect
        if($this->_multiselect){
            // multiselect
            $this->_extraAttr = "multiple=\"multiple\"";
        }
        $control = "<select ".$this->_extraAttr." name=\"" . $this->_id . "\" id=\"" . $id . "\" ";
        
        if ($this->_disabled == TRUE) {
            $control .= " disabled ";
        }
        $control .= " >";
        
        if ($this->_zerochoice) {
            $control .= "<option value=\"0\" >$this->_dontSelect</option>"; ////
        }
        
        if(!is_array($this->_currentId)){$this->_currentId = array($this->_currentId);}
        for ($i=0;$i<count($this->_rs);$i++){             
            $cur_id = $this->_rs[$i][$this->_idField];
            $cur_descr = $this->_rs[$i][$this->_descField];            
           
            $arr_cur_id_index = in_array($cur_id, $this->_currentId);
            if ($arr_cur_id_index) {
                    $control .= "<option value=\"" . $cur_id . "\" selected=\"selected\">" . $cur_descr . "</option>";
            }
            else {
                $control .= "<option value=\"" . $cur_id . "\" >" . $cur_descr . "</option>";
            }
           
        }
        $control .= "</select>";
        return $control;
        
    }
}



class checkbox
{
    protected $_id, $_label, $_val, $_locale, $_class, $_type; 
    protected $_disabled;
    
    public function __construct($id, $label, $val) {
        $this->_id = $id;
        $this->_label = $label;
        $this->_val = $val;
        $this->_type = "checkbox";
        $this->_disabled = FALSE;
        
    }
    
    public function set_disabled() {
        $this->_disabled = TRUE;
    }
    
    public function set_type($checkboxType="checkbox") {
        $this->_type = $checkboxType;
    }
    
    public function set_locale($locale) {
        $this->_locale = $locale;
    }
    
    public function set_class($class) {
        $this->_class = $class;
    }

    public function get_Checkbox() {
        //        $input = "";
        //        $isCheked = "";
        //        $isdisabled = "";
        //        if($this->_disabled){$isdisabled = ' disabled = "disabled"';}
        //        switch ($this->_val){
        //            case 1:
        //                $isCheked = ' checked = "checked"';
        //                break;
        //            case 0:
        //                $isCheked = "";
        //                break;
        //            default :
        //                $isCheked = "";
        //                break;
        //        }
        
        $input = $this->CheckBoxSimple();
        echo '<div class="col-4 col-sm-12">'.$this->_label.'</div>            
            <div class="col-8 col-sm-12">'.$input.'</div>';
    }
    
    public function CheckBoxSimple() {
        $input = "";
        $isCheked = "";
        $isdisabled = "";
        if($this->_disabled){$isdisabled = ' disabled = "disabled"';}
        switch ($this->_val){
            case 1:
                $isCheked = ' checked = "checked"';
                break;
            case 0:
                $isCheked = "";
                break;
            default :
                $isCheked = "";
                break;
        }
        
        $input = '<input class="'.$this->_class.'" type="'.$this->_type.'" name="'.$this->_id.'" id="'.$this->_id.'" value="1"'.$isCheked.' '.$isdisabled.'>';
        return $input;
    }
    
    static function getVal($val) {
        if ($val==1) {
            return 1;
        } else {
            return 0;
        }
    }
    
    static function getVal2($postAr, $name) {
        if (array_key_exists ($name, $postAr)) {
            //echo '1';
            return $postAr[$name];
        }
        else {
            //echo '0';
            return 0;
        }
    }
    
    
}



class hr
{
    public function get_hr() {
        echo '<div style="clear: both"></div>
            <hr style="height: 0px; border: none; border-bottom: 1px dashed rgb(200,200,200); width: 95%; float: left;"></hr>';
    }
}



class radiobutton
{
    protected $_id, $_label, $_val, $_locale, $_class, $_type, $_checked, $_rbname; 
    
    public function __construct($id, $label, $val, $rbName, $checked) {
        $this->_id = $id;
        $this->_label = $label;
        $this->_val = $val;
        $this->_type = "radio";
        $this->_rbname = $rbName;
        $this->_checked = $checked;
        
    }
    
    public function set_type($radioButtonType="radio") {
        $this->_type = $radioButtonType;
    }
    
    public function set_locale($locale) {
        $this->_locale = $locale;
    }
    
    public function set_class($class) {
        $this->_class = $class;
    }

    public function get_Radiobutton() {
        $input = "";
        $val = $this->_val;
        $label = $this->_label;
        $rbName = $this->_rbname;
        $checked = $this->_checked;
        for($i = 0;$i < count($val);$i++){
            $input = '<input class="'.$this->_class.'" type="'.$this->_type.'" name="'.$this->_rbname.'" id="'.$this->_id.$i.'" value="'.$val[$i].'" '.$checked[$i].'>';
            echo '<div class="col-6"><label for="'.$this->_id.$i.'">'.$label[$i].'</label></div>            
                    <div class="col-6">'.$input.'</div>';
        }        

    }
    
    
}


class autocomplete
{    
    protected $_id, $_hiddenid, $_table, $_id_field, $_descr_field, $_strval, $_idval, $_label, $_dbo;
    
    public function __construct($id, $table, $idval, $dbo) {
        $this->_id = $id;
        $this->_hiddenid = "h_" . $this->_id;
        $this->_table = $table;
        $this->_id_field = "id";
        $this->_descr_field = "description";
        if ($idval=="") {$idval=0;}
        $this->_idval = $idval;
        $this->_dbo = $dbo;
        
    }
    
    public function set_id_field($val) {
        $this->_id_field = $val;
    }
    
    public function set_descr_field($val) {
        $this->_descr_field = $val;
    }
    
    public function set_hiddenid($val) {
        $this->_hiddenid = $val;
    }
    
    public function set_label($val) {
        $this->_label = $val;
    }
    
    private function getControl() {
        $this->_strval = func::vlookup($this->_descr_field, 
                $this->_table, $this->_id_field."=".$this->_idval, $this->_dbo);        
        $res = "<input id=\"".$this->_id."\" type=\"text\" class=\"form-control\" name=\"".
                $this->_id."\" value=\"".$this->_strval."\" />";
        $res .=  "<input type=\"hidden\" name=\"".$this->_hiddenid."\" id=\"".
                $this->_hiddenid."\" value=\"".$this->_idval."\" />";
        return $res;
        
    }
    
    private function getScript() {
        return "<script>
        $(function() {
            $( '#".$this->_id."' ).autocomplete(
            {
                source:'_acdata.php?table=".$this->_table.
                "&idfield=".$this->_id_field.
                "&descrfield=".$this->_descr_field."',
                minLength:3,
                select: function( event, ui ) {
                $( '#".$this->_hiddenid."' ).val( ui.item.id ).trigger('change');                
                }
            })

        });
        </script>";
    }
    
    public function getAutocompleteSimple() {
        echo $this->getScript();
        echo $this->getControl();
    }
    
    public function getAutocomplete() {
        echo $this->getScript();
        echo "<div class=\"col-4 col-sm-12\">".$this->_label."</div>";
        echo "<div class=\"col-8 col-sm-12\">";
        echo $this->getControl();
        echo "</div>";
    }
    
    
    
}




class autocomplete2
{    
    protected $_id, $_hiddenid, $_table, $_id_field, $_descr_field, $_strval, $_idval, $_label, $_dbo;
    protected $_styleClass;
    protected $_placeholder;
    protected $_max_rows, $_descr_field_2, $_descr_field_3, $_descr_field_4, $_sql; 
    protected $_getscript;
    protected $_descr_field_concate_sep;
    protected $_extraAttr;


    public function __construct($id, $table, $idval, $dbo, $getscript = TRUE) {
        $this->_id = $id;
        $this->_hiddenid = "h_" . $this->_id;
        $this->_table = $table;
        $this->_id_field = "id";
        $this->_descr_field = "description";
        if ($idval=="") {$idval=0;}
        $this->_idval = $idval;
        $this->_dbo = $dbo;
        $this->_max_rows = 0;
        $this->_getscript = $getscript;
        $this->_sql = "";
        $this->_descr_field_concate_sep = " | ";
        $this->_extraAttr = "";
    }
    
    public function set_extraAttr($attr) {
        $this->_extraAttr = $attr;
    }
    
    public function set_sql($val) {
        $this->_sql = $val;
    }
    
    public function set_id_field($val) {
        $this->_id_field = $val;
    }
    
    public function set_max_rows($val) {
        $this->_max_rows = $val;
    }
    
    public function set_placeholder($val) {
        $this->_placeholder = $val;
    }
    
    public function set_descr_field($val) {
        $this->_descr_field = $val;
    }
    
    public function set_descr_field_2($val) {
        $this->_descr_field_2 = $val;
    }
    
    public function set_descr_field_3($val) {
        $this->_descr_field_3 = $val;
    }
    
    public function set_descr_field_4($val) {
        $this->_descr_field_4 = $val;
    }
    
    public function set_hiddenid($val) {
        $this->_hiddenid = $val;
    }
    
    public function set_label($val) {
        $this->_label = $val;
    }
    
    public function set_styleClass($val) {
        $this->_styleClass = $val;
    }
    
    public function set_text($val) {
        $this->_strval = $val;
    }
    
    public function set_descr_field_concate_sep($val) {
        $this->_descr_field_concate_sep = $val;
    }
    
    private function getControl() {
        if ($this->_strval=="") {
            $this->_strval = func::vlookup($this->_descr_field, 
                $this->_table, $this->_id_field."=".$this->_idval, $this->_dbo);
            if ($this->_descr_field_2!="") {
                $this->_strval = func::vlookup("CONCAT(".$this->_descr_field.",'".$this->_descr_field_concate_sep."',".$this->_descr_field_2.")", 
                $this->_table, $this->_id_field."=".$this->_idval, $this->_dbo);
            }
        }
        $res = "<input ".$this->_extraAttr." class=\"".$this->_styleClass." form-control\" id=\"".$this->_id."\" type=\"text\" name=\"".
                $this->_id."\" value=\"".$this->_strval."\" placeholder=\"".$this->_placeholder."\" autocomplete=\"off\" />";
        $res .=  "<input type=\"hidden\" name=\"".$this->_hiddenid."\" id=\"".
                $this->_hiddenid."\" value=\"".$this->_idval."\" />";
        
        
        $controlId = $this->_id;
        $res .= "<div id=\"$controlId-select\" class=\"ac-select\" style=\"display:none\"></div>";
        
        return $res;
        
    }
    
    private function getScript_old() {
        $link = "_acdata.php?table=".$this->_table.
                "&idfield=".$this->_id_field.
                "&descrfield=".$this->_descr_field.
                "&sql=".$this->_sql.
                "&descrfieldconcatesep=".$this->_descr_field_concate_sep;
        if ($this->_max_rows>0) {
            $link .= "&maxrows=".$this->_max_rows;
        }
        if ($this->_descr_field_2!="") {
            $link .= "&descrfield2=".$this->_descr_field_2;
        }
        if ($this->_descr_field_3!="") {
            $link .= "&descrfield3=".$this->_descr_field_3;
        }
        if ($this->_descr_field_4!="") {
            $link .= "&descrfield4=".$this->_descr_field_4;
        }
        
    //        return "<script>
    //        $(function() {
    //			$( '#".$this->_id."' ).autocomplete(
    //            {
    //                source:'$link',
    //                minLength:3,
    //                select: function( event, ui ) {
    //                $( '#".$this->_hiddenid."' ).val( ui.item.id ).trigger('change');                
    //                }
    //            })
    //			$( '#".$this->_id."' ).keyup(function() {
    //				$( '#".$this->_hiddenid."' ).val('0');
    //			});			
    //
    //        });
    //        </script>";
        
        return "<script>
        $(function() {
            
			$( '#".$this->_id."' ).autocomplete(
            {
                source:'$link',
                minLength:3,
                select: function( event, ui ) {
                $( '#".$this->_hiddenid."' ).val( ui.item.id ).trigger('change');                
                }
            })
					

        });
        </script>";
        
    //        return "<script>
    //        $(function() {
    //            $( '#".$this->_id."' ).autocomplete(
    //            {
    //                source:'_acdata.php?table=".$this->_table.
    //                "&idfield=".$this->_id_field.
    //                "&descrfield=".$this->_descr_field."',
    //                minLength:3,
    //                select: function( event, ui ) {
    //                $( '#".$this->_hiddenid."' ).val( ui.item.id ).trigger('change');                
    //                }
    //            })
    //
    //        });
    //        </script>";
        
    }
    
    
    
    private function getScript() {
        
        $controlId = $this->_id;

        $table = $this->_table; 
        $idField = $this->_id_field;
        $descrField = $this->_descr_field;
		
		$descrField2SearchStr = "";
		if ($this->_descr_field_2 != "") {
			$descrField2 = $this->_descr_field_2;
			$descrField2SearchStr = ", descrField2: '$descrField2'";
		}
        
$script = <<<EOT
<script>

$(function() {
    


$( '#$controlId' ).keyup(function() {
    var term = $(this).val();
    
    if (term.length>=3) {
        $.post("_acdata3.php",  
            {
                term: term, 
                table : '$table',
                idField: '$idField',
                descrField: '$descrField'
				$descrField2SearchStr
                
            },  
            function(data){ 
                //console.log(data);				
                $("#$controlId-select").html(data);
                $("#$controlId-select").show();
                
                $( '#$controlId-select li' ).click(function() {
                    var id = $(this).data('id');
                    var val = $(this).data('val');
                    
                    $('#h_$controlId').val(id); 
                    $('#h_$controlId').trigger('change');
                    $('#$controlId').val(val);
                    $("#$controlId-select").hide();
					console.log('..//..');
                });
                
            });
    }
    else {
        $("#$controlId-select").html('');
        $("#$controlId-select").hide();
    }
});

});
            
</script>

EOT;

        return $script;
        
    }
    
    
    
    
    public function getAutocompleteSimple() {
        if ($this->_getscript) {
            echo $this->getScript();
        }
        echo $this->getControl();
    }
    
    public function getAutocomplete() {
        if ($this->_getscript) {
            echo $this->getScript();
        }
        echo "<div class=\"col-4 col-sm-12\">".$this->_label."</div>";
        echo "<div class=\"col-8 col-sm-12\">";
        echo $this->getControl();
        echo "</div>";
    }
    
    
    
}




class selectList
{
    protected $_id, $_table, $_id_field, $_descr_field, $_val, $_label, $_dbo, $_criteria;
    
    public function __construct($id, $table, $val, $dbo) {
        $this->_id = $id;
        $this->_table = $table;
        $this->_id_field = "id";
        $this->_descr_field = "description";
        $this->_val = $val;
        $this->_dbo = $dbo;        
    }
    
    public function set_criteria($val) {
        $this->_criteria = $val;
    }
    
    public function set_label($val) {
        $this->_label = $val;
    }
    
    public function set_idField($val) {
        $this->_id_field = $val;
    }
    
    public function set_descrField($val) {
        $this->_descr_field = $val;
    }
    
    //example
    //$ids = selectList::getVal("l_id", $_POST);
    public static function getVal($id, $data) {
        $str = "";
        foreach($data as $key => $value){
            //echo strpos($key,$id)." / ";
            //echo $key."-".$value." / ";
            if (strpos($key,$id)!==false && strpos($key,$id)==0) {
                $ar = explode("___", $key);
                $str = func::ConcatSpecial($str, "[".$ar[count($ar)-1]."]", ",");
            }
        }
        return $str;

    }
    
    public function getList() {
        //show list
        
        echo '<div style="clear:both"></div>';
        echo '<div class="col-4 col-sm-12"><h3>'.$this->_label.'</h3></div><div class="col-8 col-sm-12">';
        
        echo $this->getSimpleList();
        
        echo '<div style="clear:both"></div>';
        echo '</div>';
        echo '</div>';
    }
    
    public function getSimpleList() {
        //get rs
        $sql = "SELECT * FROM $this->_table";
        if ($this->_criteria!="") {
            $sql .= " WHERE $this->_criteria";
        }
        $sql .= " ORDER BY $this->_descr_field ";
        
        $rs = $this->_dbo->getRS($sql);
                
        $str = '<div id="'.$this->_id.'">';
                        
        for ($i=0;$i<count($rs);$i++) {
            $descr = $rs[$i][$this->_descr_field];
            $str .= '<div class="col-11 col-sm-12">'.$descr.'</div>';
            
            $chkid = $this->_id . "_chk___" . $rs[$i][$this->_id_field];
            $checked = "";
            if (strpos($this->_val, "[".$rs[$i][$this->_id_field]."]")!==false) {
                $checked = 'checked="checked"';
            }
            $chk = '<input type="checkbox" name="'.$chkid.'" id="'.$chkid.'" '.$checked.' />';
            $str .= '<div class="col-1 col-sm-12">'.$chk.'</div>';
            $str .= '<div style="clear:both;height:0px"></div>';
        }
        
        return $str;
        
        
    }
    
    public function getListCondensed() {
        echo '<div style="clear:both"></div>';
        echo '<div class="col-6" style="padding-top:10px">'.$this->_label.'</div><div class="col-6">';               
        echo $this->getSimpleList();
        echo '<div style="clear:both"></div>';
        echo '</div>';
        echo '</div>';
    }
    
    //emfanizw sto control selectlist 
    //ektos apo ta shops sta opoia einai o xristis manager
    //kai ta shops pou einai energa gia afti tin katigoria
    //etsi wste na min xa8oun an o xristis kanei save
    public static function AddOptions($myList, $extraList) {
        $ar_extraList = explode(",", $extraList);
        $myNewList = $myList;
        for ($i=0;$i<count($ar_extraList);$i++) { 
            if ($ar_extraList[$i]!="") {
                if (strpos($myList, $ar_extraList[$i])===FALSE) {
                    $myNewList .= ",".$ar_extraList[$i];
                }
            }
        }
        return $myNewList;
    }

}



/*class selectTreeList
{
    protected $_id, $_table, $_id_field, $_descr_field, $_val, $_label, $_dbo, $_criteria;

    public function __construct($id, $table, $val, $dbo) {
        $this->_id = $id;
        $this->_table = $table;
        $this->_id_field = "id";
        $this->_descr_field = "description";
        $this->_val = $val;
        $this->_dbo = $dbo;
             
    }

    public function set_criteria($val) {
        $this->_criteria = $val;
    }
    
    public function set_label($val) {
        $this->_label = $val;
    }
    
    public function set_idField($val) {
        $this->_id_field = $val;
    }
    
    public function set_descrField($val) {
        $this->_descr_field = $val;
    }

    private function getCategories() {
        $sql = "SELECT {$this->_id_field}, {$this->_descr_field}, parent_id FROM {$this->_table}";
        if (!empty($this->_criteria)) {
            $sql .= " WHERE {$this->_criteria}";
        }
        $sql .= " ORDER BY parent_id, {$this->_id_field}";
        return $this->_dbo->getRS($sql);
        
    }

    private function buildCategoryHierarchy($categories, $parentId = 0) {
        $branch = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] == $parentId) {
                $children = self::buildCategoryHierarchy($categories, $category['id']);
                if ($children) {
                    $category['children'] = $children;
                }
                $branch[] = $category;
            }
        }
        return $branch;
    }

    private function renderCategories($categories, $savedCategories = [], $prefix = '') {
        foreach ($categories as $category) {
            $isChecked = in_array($category['id'], $savedCategories) ? 'checked' : '';
            echo '<li>';
            echo '<label>';
            echo '<input type="checkbox" name="'.$this->_id.'[]" value="' . $category['id'] . '" ' . $isChecked . '> ';
            echo $prefix . htmlspecialchars($category['description']);
            echo '</label>';
    
            if (!empty($category['children'])) {
                echo '<ul>';
                self::renderCategories($category['children'], $savedCategories, $prefix . '- ');
                echo '</ul>';
            }
            echo '</li>';
        }
    }

    public function getList() {
        $categories = self::getCategories();
        //var_dump($categories);

        $hierarchy = self::buildCategoryHierarchy($categories);
        //var_dump($hierarchy);

        $savedCategoriesString = $this->_val;
        $savedCategories = explode(',', $savedCategoriesString);

        echo "<div id=\"{$this->_id}\" class=\"select-tree-list\"><ul>";
        self::renderCategories($hierarchy, $savedCategories);
        echo "</ul>";

        echo <<<EOT
        <style>
            .select-tree-list ul {
                list-style:none;
                padding-left:30px;
            }
            .select-tree-list {
                border:1px solid #ccc;
                height:300px;
                overflow-y:scroll;
                padding-top:5px;
            }
            .select-tree-list input[type=checkbox] {
                margin:0px 10px 0px 0px !important;
                vertical-alignment:middle;
            }
        </style>
EOT;

    }

    //$data = $_POST...
    public static function getVal($control_id, $data) {
        $selectedCategories = $data[$control_id] ?? [];
        return implode(', ', $selectedCategories);
    }


}*/


class selectTreeList
{
    protected $_id, $_table, $_id_field, $_descr_field, $_val, $_label, $_dbo, $_criteria, $_type;

    public function __construct($id, $table, $val, $dbo, $type = "CHECKBOX") {
        $this->_id = $id;
        $this->_table = $table;
        $this->_id_field = "id";
        $this->_descr_field = "description";
        $this->_val = $val; // For CHECKBOX: "[1],[2],[3]", For NUMBERS: '{"1": 2, "3": 5}'
        $this->_dbo = $dbo;
        $this->_type = strtoupper($type); // Ensure type is uppercase
    }

    public function set_criteria($val) {
        $this->_criteria = $val;
    }

    public function set_label($val) {
        $this->_label = $val;
    }

    public function set_idField($val) {
        $this->_id_field = $val;
    }

    public function set_descrField($val) {
        $this->_descr_field = $val;
    }

    private function getCategories() {
        $sql = "SELECT {$this->_id_field}, {$this->_descr_field}, parent_id FROM {$this->_table} ORDER BY parent_id, {$this->_descr_field}";
        return $this->_dbo->getRS($sql);
    }

    private function buildCategoryHierarchy($categories, $parentId = 0) {
        $branch = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] == $parentId) {
                $children = self::buildCategoryHierarchy($categories, $category['id']);
                if ($children) {
                    $category['children'] = $children;
                }
                $branch[] = $category;
            }
        }
        return $branch;
    }

    private function renderCategories($categories, $savedValues = [], $prefix = '') {
        //var_dump($categories);
        foreach ($categories as $category) {
            echo '<li>';
            //echo '<label>';
            //echo htmlspecialchars($prefix . $category['description']) . ': ';

            if ($this->_type === "CHECKBOX") {
                // Render checkbox
                $isChecked = in_array($category['id'], $savedValues) ? 'checked' : '';
                echo '<label>';
                echo '<input type="checkbox" name="'.$this->_id.'[]" value="[' . $category['id'] . ']" ' . $isChecked . '>';
                echo htmlspecialchars($prefix . $category['description']);
                echo '</label>';
            } elseif ($this->_type === "NUMBERS") {
                // Render number input
                echo "<span>";
                echo htmlspecialchars($prefix . $category['description']);
                $value = isset($savedValues[$category['id']]) ? htmlspecialchars($savedValues[$category['id']]) : '';
                echo '<input type="number" name="'.$this->_id.'[' . $category['id'] . ']" value="' . $value . '" min="0" step="any">';
                echo "</span>";
            }

            // echo '</label>';

            if (!empty($category['children'])) {
                echo '<ul>';
                self::renderCategories($category['children'], $savedValues, $prefix . '- ');
                echo '</ul>';
            }
            echo '</li>';
        }
    }

    public function getList() {
        $categories = self::getCategories();
        
        $hierarchy = self::buildCategoryHierarchy($categories);

        //var_dump($hierarchy);

        // Parse saved values based on type
        if ($this->_type === "CHECKBOX") {
            // Split the values by `],[` and trim surrounding brackets
            // preg_match_all('/\[(\d+)\]/', $this->_val, $matches);
            // $savedValues = array_map(function ($id) {
            //     return "$id";
            // }, $matches[1]);           
            $savedValues = $this->_val;
            $savedValues = str_replace('[','',$savedValues);
            $savedValues = str_replace(']','',$savedValues);
            $savedValues = explode(',',$savedValues);

        } elseif ($this->_type === "NUMBERS") {
            $savedValues = json_decode($this->_val, true) ?? [];
        } else {
            $savedValues = [];
        }

        // echo "<ul>";
        // self::renderCategories($hierarchy, $savedValues);
        // echo "</ul>";

        echo "<div id=\"{$this->_id}\" class=\"select-tree-list\"><ul>";
        self::renderCategories($hierarchy, $savedValues);
        echo "</ul></div>";

        echo <<<EOT
        <style>
            .select-tree-list ul {
                list-style:none;
                padding-left:30px;
            }
            .select-tree-list {
                border:1px solid #ccc;
                height:300px;
                overflow-y:scroll;
                padding-top:5px;
            }
            .select-tree-list input[type=checkbox] {
                margin:0px 10px 0px 0px !important;
                vertical-alignment:middle;
            }
            .select-tree-list ul li span {
                display:grid;
                grid-template-columns:1fr 100px;
                gap:10px;
                font-weight:bold;
                border-bottom:1px dotted #ccc;
            }
            .select-tree-list ul li span input {
                width:100%;
            }
        </style>
EOT;


    }

    public static function getVal($control_id, $data, $type="CHECKBOX") {
        /*if (isset($data[$control_id])) {
            if (is_array($data[$control_id])) {
                
                $selected = array_map(function ($id) {
                    return "$id";
                }, $data[$control_id]);
                return implode(",", $selected); // Store as "[1],[2],[3]"
            }
            return "";
        }
        return '';*/
        if ($type === "CHECKBOX") {
            return implode(",", $data[$control_id]);
        }
        elseif ($type === "NUMBERS") {
            return json_encode($data[$control_id]);
        }
    }
}





/*
<script src="js/evol-colorpicker.min.js" type="text/javascript" charset="utf-8"></script>        
<link href="css/evol-colorpicker.css" rel="stylesheet" type="text/css">
*/
class textboxColor
{
    protected $_id, $_label, $_val, $_placeholder, $_class, $_strColors;
    protected $_hideButton, $_history, $_hidePalette, $_displayIndicator, $_defaultPalette;


    public function __construct($id, $label, $val, $strColors="", $placeholder="") {
        $this->_id = $id;
        $this->_label = $label;
        $this->_val = $val;
        $this->_placeholder = $placeholder;
        $this->_strColors = $strColors;        
        $this->_hideButton = "false";
        $this->_history = "false";
        $this->_hidePalette = FALSE;
        $this->_displayIndicator = "true";
        $this->_defaultPalette = "theme";
    }
    
    public function set_defaultPalette($val) {
        //Possible values are "theme" or "web".
        $this->_defaultPalette = $val;
    }
    
    public function set_class($class) {
        $this->_class = $class;
    }
    
    public function set_hideButton($val) {
        if($val){
            $this->_hideButton = "true";            
        }elseif (!$val) {
            $this->_hideButton = "false";
        }        
    }
    
    public function set_history($val) {
        if($val){
            $this->_history = "true";            
        }elseif (!$val) {
            $this->_history = "false";
        } 
    }
    
    public function set_displayIndicator($val) {
        if($val){
            $this->_displayIndicator = "true";            
        }elseif (!$val) {
            $this->_displayIndicator = "false";
        } 
    }
    
    public function set_hidePalette($val) {
        $this->_hidePalette = $val;
    }

    public function get_TextboxColor() {
        $input = $this->textboxColorSimple();
        echo $this->getScript();
        echo $this->getCSS();
        echo '<div class="col-4 col-sm-12">'.$this->_label.'</div>            
            <div class="col-8 col-sm-12"><div class="myColorPicker">'.$input.'</div></div>';
    }
    
    private function getScript() {
        $strHidePalette = "";
        
        $customTheme = $this->_strColors!=""? "customTheme: [".$this->_strColors."],": "";
        
        
        if($this->_hidePalette){
            $strHidePalette = "$(\"#".$this->_id."\").colorpicker(\"hidePalette\");";
        }
        return "<script type=\"text/javascript\">".
             "$(document).ready(function() {".
                 "$(\"#".$this->_id."\").colorpicker({".
                     $customTheme .
                     "history: ".$this->_history.",".
                     "displayIndicator: ".$this->_displayIndicator.",".
                     "defaultPalette: \"".$this->_defaultPalette."\",".
                     "hideButton: ".$this->_hideButton.
                 "});".
                $strHidePalette.
             "});".
         "</script>";
    }
    
    private function getCSS() {
        $str = "<style>.myColorPicker .evo-cp-wrap{".
                "width: 100%;".
                "}</style>";
        if($this->_hideButton == "false"){
            $str = "<style>.myColorPicker .form-control{".
                "width: 80%;".
                "}</style>";
        }
        return $str;
    }
    
    
    public function textboxColorSimple() {
        return '<input class="form-control '.$this->_class.'" name="'.$this->_id.'" id="'.$this->_id.'" value="'.$this->_val.'" autocomplete="off" placeholder="'.$this->_placeholder.'" />';
        
        
    }
}




class dateFromTo
{
    
    protected $_id, $_date1, $_date2, $_locale, $_label;
    
    public function __construct($id, $date1="", $date2="", $locale="GR") {
        $this->_id = $id;
        $this->_date1 = $date1;
        $this->_date2 = $date2;
        $this->_locale = $locale;
    }
    
    
    public function set_label($val) {
        $this->_label = $val;
    }
    
    
    public function getControl() {
        
        $date1Control = new textbox($this->_id . "_d1", "FROM", $this->_date1);
        $date1Control->set_locale($this->_locale);
        $date1Control->set_format("DATE");
        echo $date1Control->textboxSimple();
        
        echo " - ";
        
        $date2Control = new textbox($this->_id . "_d2", "FROM", $this->_date2);
        $date2Control->set_locale($this->_locale);
        $date2Control->set_format("DATE");
        echo $date2Control->textboxSimple();        
        
    }
    
    static function getVals($post, $controlId, $locale="GR") {
        $val1date = $post[$controlId . '_d1'];
        $val2date = $post[$controlId . '_d2'];
        if ($val2date=="") {
            $val2date = $val1date;
        }
        
        $val1 = ""; $val2 = "";
        if ($val1date!="") {
            $val1 = textbox::getDate($val1date, $locale);        
            $val2 = textbox::getDate($val2date, $locale);
            $val2 = substr($val2, 0, 8) . "235959"; 
        }
        
        return array($val1, $val2);
        
    }
    
}






class fileControl
{
    protected $_id, $_filePath, $_label, $_host, $_w, $_h;
    protected $_baseurl, $_init_folder, $_script_folder_depth;

    public function __construct($id, $filePath, $label, 
        $baseurl, $init_folder, 
        $script_folder_depth=2, $img_width=50, $img_height=50) {

        $this->_id = $id;
        $this->_filePath = $filePath;
        $this->_label = $label;
        $this->_baseurl = $baseurl;
        $this->_init_folder = $init_folder;
        $this->_script_folder_depth = $script_folder_depth;
        $this->_w = $img_width;
        $this->_h = $img_height;
    }

    public function setThumbDimensions($w, $h) {
        $this->_w = $w;
        $this->_h = $h;
    }

    public static function fileThumb($thumbId, $filePath, $w=50, $h=50) {
        
        if ($filePath=="") {
            return "<span style=\"font-size:50px\"><span class=\"fa fa-ban\"></span></span>";
        }
        
        $arPath = explode("/", $filePath);
        $fileName = $arPath[count($arPath) - 1];
        $arFileName = explode(".", $fileName);
        $fileExt = $arFileName[count($arFileName) - 1];
        $thumb = "";
        
        
        switch ($fileExt) {
            case "jpg":
            case "JPG":
            case "jpeg":
            case "JPEG":
            case "png":
            case "PNG":
            case "gif":
            case "GIF":
            case "webp":
            case "svg":
                $thumb = "<div id=\"$thumbId\" style=\"width:{$w}px; height:{$h}px; background-image:url($filePath); background-size:contain; background-position:center; background-repeat:no-repeat; background-color:#ddd; margin-bottom:10px\"></div>";
                break;
            
            case "doc":
            case "docx":
                $thumb = "<span style=\"font-size:50px\"><span class=\"fa fa-file-word-o\"></span></span>";
                break;
            
            case "xls":
            case "xlsx":
                $thumb = "<span style=\"font-size:50px\"><span class=\"fa fa-file-excel-o\"></span></span>";
                break;
            
            case "pdf":
                $thumb = "<span style=\"font-size:50px\"><span class=\"fa fa-file-pdf-o\"></span></span>";
                break;
            
            case "zip":
                $thumb = "<span style=\"font-size:50px\"><span class=\"fa fa-file-archive-o\"></span></span>";
                break;
            
            default:
                $thumb = "<span style=\"font-size:30px\"><span class=\"fa fa-file-text-o \"></span></span>";                
                break;
                
        }

        return "<a href=\"$filePath\" target=\"_blank\">$thumb</a><br/><br/>";
        
    }

    public function getControl() {
        $textboxId = $this->_id . "_txt";
        $imageId = $this->_id . "_img";

        $control_id = $this->_id;
        
        $myTextBox = new textbox($textboxId, "", $this->_filePath);
        echo $myTextBox->textboxSimple();

        echo <<<EOT
        
        <a id="btn-{$this->_id}" data-title="Add file" data-height="600" data-width="900" data-href="filemanager/?baseurl={$this->_baseurl}&currentfolder={$this->_init_folder}&scriptfolderdepth={$this->_script_folder_depth}&parentelement={$this->_id}" class="btn btn-primary btn-filecontrol">Add/edit</a>
        <br/><br/>      
EOT;


        if ($this->_filePath!="") {
                     
            echo $this->fileThumb($imageId, $this->_filePath, $this->_w, $this->_h);    
        }

        echo <<<EOT
        

        <script>
        $(function() {
        
        $("#btn-{$this->_id}").click(function() {
            $("#popup-{$this->_id}").show();
        });

        $("#btn-close-popup-{$this->_id}").click(function() {
            $("#popup-{$this->_id}").hide();
        });

        });
        </script>

        <div id="popup-{$this->_id}" style="display:none;position:fixed;top:0px;left:0px;right:0px;bottom:0px;background-color:#fff;z-index:9999">
            <iframe src="filemanager/?baseurl={$this->_baseurl}&currentfolder={$this->_init_folder}&scriptfolderdepth={$this->_script_folder_depth}&parentelement={$this->_id}" style="width:100%;height:100%;border:1px solid #ccc" ></iframe>
            
            <span id="btn-close-popup-{$this->_id}" style="position:absolute;top:20px;right:20px;padding:10px 20px;background-color:#fff;color:000;z-index:9999;border:1px solid #ccc;border-radius:10px;cursor:pointer;">❌ Close</span>

        </div>

EOT;


        


    }


    public function getFullControl() {
        echo "<div class=\"col-4 col-sm-12\">$this->_label</div>";
        echo "<div class=\"col-8 col-sm-12\">";
        $this->getControl();
        echo "</div>";
    }


}




class fileControlV1
{
    
    protected $_id, $_filePath, $_label, $_host, $_w, $_h;
    
    public function __construct($id, $filePath, $label, $host) {
        $this->_id = $id;
        $this->_filePath = $filePath;
        $this->_label = $label;
        $this->_host = $host;
        $this->_w = 50;
        $this->_h = 50;
    }
    
    
    public function setThumbDimensions($w, $h) {
        $this->_w = $w;
        $this->_h = $h;
    }
    
    
    public function getControl() {
        $textboxId = $this->_id . "_txt";
        $imageId = $this->_id . "_img";
        
        $myTextBox = new textbox($textboxId, "", $this->_filePath);
        echo $myTextBox->textboxSimple();
        
        $myHost = $this->_host;        
        echo <<<EOT
        
        <a data-title="Add file" data-height="600" data-width="900" data-href="$myHost/elfinder/elfinder.php?mode=featuredimage&host=$myHost&textbox=$textboxId&image=$imageId" class="btn btn-primary modalBtn">Add/edit</a>       
EOT;
         echo "<br/><br/>"; 
         
         echo $this->fileThumb($imageId, $this->_filePath, $this->_w, $this->_h);
         
        //if ($this->_filePath!="") {
            //echo "<a href=\"$this->_filePath\" target=\"_blank\"><div id=\"$imageId\" style=\"width:150px; height:150px; background-image:url($this->_filePath); background-size:contain; background-position:center; background-repeat:no-repeat; background-color:#ddd\"></div></a>";
        //}
        
    }
    
    public static function fileThumb($thumbId, $filePath, $w=50, $h=50) {
        
        if ($filePath=="") {
            return "<span style=\"font-size:50px\"><span class=\"fa fa-ban\"></span></span>";
        }
        
        $arPath = explode("/", $filePath);
        $fileName = $arPath[count($arPath) - 1];
        $arFileName = explode(".", $fileName);
        $fileExt = $arFileName[count($arFileName) - 1];
        $thumb = "";
        
        
        switch ($fileExt) {
            case "jpg":
            case "JPG":
            case "jpeg":
            case "JPEG":
            case "png":
            case "PNG":
            case "gif":
            case "GIF":
                $thumb = "<div id=\"$thumbId\" style=\"width:{$w}px; height:{$h}px; background-image:url($filePath); background-size:contain; background-position:center; background-repeat:no-repeat; background-color:#ddd; margin-bottom:10px\"></div>";
                break;
            
            case "doc":
            case "docx":
                $thumb = "<span style=\"font-size:50px\"><span class=\"fa fa-file-word-o\"></span></span>";
                break;
            
            case "xls":
            case "xlsx":
                $thumb = "<span style=\"font-size:50px\"><span class=\"fa fa-file-excel-o\"></span></span>";
                break;
            
            case "pdf":
                $thumb = "<span style=\"font-size:50px\"><span class=\"fa fa-file-pdf-o\"></span></span>";
                break;
            
            case "zip":
                $thumb = "<span style=\"font-size:50px\"><span class=\"fa fa-file-archive-o\"></span></span>";
                break;
            
            default:
                $thumb = "<span style=\"font-size:30px\"><span class=\"fa fa-file-text-o \"></span></span>";                
                break;
                
        }
        
        return "<a href=\"$filePath\" target=\"_blank\">$thumb</a>";
        
    }
    
    
    public function getFullControl() {
        echo "<div class=\"col-4 col-sm-12\">$this->_label</div>";
        echo "<div class=\"col-8 col-sm-12\">";
        $this->getControl();
        echo "</div>";
    }
    
}







class yesNoAll {
    
    protected $_id, $_label, $_val;

    public function __construct($id, $label, $val) {
        $this->_id = $id;
        $this->_label = $label;
        $this->_val = $val;
    }

    public function yesNoAll_simple() {
        $control = "<select class=\"form-control\" name=\"" . $this->_id . "\" id=\"" . $this->_id . "\" >";
        $selectedYes = $this->_val==1? "selected": "";  
        $control .= "<option $selectedYes value=\"1\">Yes</option>";
        $selectedNo = $this->_val==0? "selected": "";     
        $control .= "<option $selectedNo value=\"0\">No</option>"; 
        $selectedAll = $this->_val==-1? "selected": "";       
        $control .= "<option $selectedAll value=\"-1\">All</option>";
        $control .= "</select>";
        return $control;
    }

    public function get_control() {
        
        $control = $this->yesNoAll_simple();
        echo '<div class="col-4 col-sm-12 col-sm-12">'.$this->_label.'</div>            
            <div class="col-8 col-sm-12 col-sm-12">';
        echo $control;
        echo "</div>";
    }

}










class CONTROL
{
    
    static function getControl($controlId, $dbo, $item, $post, $fields, $fieldTypes, $fieldNames, $fieldAttributes, $fullControl = FALSE, $locale="GR") {
        
        $controlIndex = -1;
        //find control position in array
        for ($i = 0; $i < count($fields); $i++) {
            if ($fields[$i] == $controlId) {
                $controlIndex = $i;                
            }
        }
        if ($controlIndex==-1) {
            return FALSE;
        }
        
        //$item = $this->_item;
        $property = $controlId;
        
        $styleDiv = $item? "": "display:inline-block";
                
        echo "<div id=\"$property-container\" style=\"$styleDiv\">";
        switch ($fieldTypes[$controlIndex]) {
            case "ID":
                $val = $item? $item->get_id(): 0; 
                $myTextBox = new textbox($fields[$controlIndex], 
                        $fieldNames[$controlIndex], $val);
                $myTextBox->set_disabled();
                if ($fullControl) { $myTextBox->get_Textbox(); }
                else { echo $myTextBox->textboxSimple(); }
                
                break;
                
            case "integer":                
            case "text":
                $postVal = $post? $post[$property]: "";
                $val = $item? $item->$property(): $postVal; 
                //echo $item->$property();
                $myTextBox = new textbox($fields[$controlIndex], 
                        $fieldNames[$controlIndex], $val);
                if ($fieldAttributes[$fields[$controlIndex]]['READONLY']=="readonly") {
                    $myTextBox->set_disabled();
                }
                if ($fullControl) { $myTextBox->get_Textbox(); }
                else { echo $myTextBox->textboxSimple(); }
                break;
                
            case "password":
                //only on form
                $val = ""; //dont show password (hash)
                $myPassword = new password($fields[$controlIndex], 
                        $fieldNames[$controlIndex], $val);
                if ($fullControl) { $myPassword->getFullControl(); }
                else { echo $myPassword->getControl(); }
                break;
                
            case "hidden":
                $postVal = $post? $post[$property]: "0";
                $val = $item? $item->$property(): $postVal; 
                $myTextBox = new textbox($fields[$controlIndex], 
                        $fieldNames[$controlIndex], $val);
                $myTextBox->set_type("hidden");
                if ($fullControl) { $myTextBox->get_Textbox(); }
                else { echo $myTextBox->textboxSimple(); }
                break;

            case "currency":
                $postVal = $post? $post[$property]: 0;
                $val = $item? $item->$property(): $postVal;
                //echo $val;
                $myTextBox = new textbox($fields[$controlIndex], 
                        $fieldNames[$controlIndex], $val);                
                $myTextBox->set_format("CURRENCY");
                $myTextBox->set_locale($locale);
                if ($fieldAttributes[$fields[$controlIndex]]['READONLY']=="readonly") {
                    $myTextBox->set_disabled();
                }
//                echo "<pre>";
//                var_dump($myTextBox);
//                echo "</pre>";
                if ($fullControl) { $myTextBox->get_Textbox(); }
                else { echo $myTextBox->textboxSimple(); }
                break;
			
			case "currency3":
                $postVal = $post? $post[$property]: 0;
                $val = $item? $item->$property(): $postVal;
                //echo $val;
                $myTextBox = new textbox($fields[$controlIndex], 
                        $fieldNames[$controlIndex], $val);                
                $myTextBox->set_format("CURRENCY3");
                $myTextBox->set_locale($locale);
                if ($fieldAttributes[$fields[$controlIndex]]['READONLY']=="readonly") {
                    $myTextBox->set_disabled();
                }
                if ($fullControl) { $myTextBox->get_Textbox(); }
                else { echo $myTextBox->textboxSimple(); }
                break;
			

            case "date":
                //echo "date";
                $postVal = $post? $post[$property]: "";
                $val = $item? $item->$property(): $postVal; 
                $myTextBox = new textbox($fields[$controlIndex], 
                        $fieldNames[$controlIndex], $val);
                $myTextBox->set_locale($locale);
                $myTextBox->set_format("DATE");
                if ($fieldAttributes[$fields[$controlIndex]]['READONLY']=="readonly") {
                    $myTextBox->set_disabled();
                }
                if ($fullControl) { $myTextBox->get_Textbox(); }
                else { echo $myTextBox->textboxSimple(); }
                break;
            
            case "datetime":
                $postVal = $post? $post[$property]: "";
                $val = $item? $item->$property(): $postVal; 
                $myDateTime = new dateTimePicker($fields[$controlIndex], 
                    $fieldNames[$controlIndex], $val);
                $myDateTime->set_locale($locale);
                if ($fieldAttributes[$fields[$controlIndex]]['READONLY']=="readonly") {
                    $myDateTime->set_disabled();
                }
                if ($fullControl) { $myDateTime->getControl(); }
                else { echo $myDateTime->getControlSimple(); }
                break;

            case "textarea":
                $postVal = $post? $post[$property]: "";
                $val = $item? $item->$property(): $postVal; 
                $myTextBox = new textbox($fields[$controlIndex], 
                        $fieldNames[$controlIndex], $val);
                $myTextBox->set_multiline();
                if ($fieldAttributes[$fields[$controlIndex]]['READONLY']=="readonly") {
                    $myTextBox->set_disabled();
                }
                if ($fullControl) { $myTextBox->get_Textbox(); }
                else { echo $myTextBox->textboxSimple(); }
                break;

            case "hidden-area":
                $postVal = $post? $post[$property]: "";
                $val = $item? $item->$property(): $postVal;
                $val = htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
                echo '<textarea class="form-control hidden-area" name="' . $fields[$controlIndex] . '" id="' . $fields[$controlIndex] . '" style="opacity:0;height:0;min-height:0;overflow:hidden;padding:0;border:0;margin:0;" rows="1">' . $val . '</textarea>';
                break;
                
            case "richtextbox":
                $postVal = $post? $post[$property]: "";
                $val = $item? $item->$property(): $postVal; 
                $myTextBox = new richTextbox($fields[$controlIndex], 
                        $fieldNames[$controlIndex], $val);
                //disabled...
                if ($fullControl) { $myTextBox->getFullControl(); }
                else { $myTextBox->getControl(); }
                break;

            case "checkbox":
                $postVal = $post? $post[$property]: 0;
                $val = $item? $item->$property(): $postVal;
                $myCheckBox = new checkbox($fields[$controlIndex], 
                        $fieldNames[$controlIndex], $val);
                if ($fieldAttributes[$fields[$controlIndex]]['READONLY']=="readonly") {
                    $myCheckBox->set_disabled();
                }
                if ($fullControl) { $myCheckBox->get_Checkbox(); }
                else { echo $myCheckBox->CheckBoxSimple(); }
                
                break;

            case "selectTreeList":
                $postVal = $post? $post[$property]: 0;
                $val = $item? $item->$property(): $postVal;
                $mySelectTreeList = new selectTreeList($fields[$controlIndex], $fieldAttributes[$fields[$controlIndex]]['TABLE'], $val, $dbo,$fieldAttributes[$fields[$controlIndex]]['TYPE']);
                $mySelectTreeList->getList();
                break;

            case "combobox":
                $postVal = $post? $post[$property]: 0;
                $val = $item? $item->$property(): $postVal;
                $myArray = $fieldAttributes[$fields[$controlIndex]];
                $sql = $myArray['SQL'];
                $idField = $myArray['ID-FIELD'];
                $descField = $myArray['DESC-FIELD'];
                $myRS = array_key_exists('RS', $myArray)? $myArray['RS']: FALSE;
                //var_dump($myRS);
                $myComboBox = new comboBox($fields[$controlIndex], $dbo, 
                        $sql, $idField, $descField, 
                        $val, $fieldNames[$controlIndex]);
                if ($myRS) {
                    $myComboBox->setRS($myRS);
                }
                if ($fieldAttributes[$fields[$controlIndex]]['READONLY']=="readonly") {
                    $myComboBox->set_disabled();
                }
                if ($fullControl) { $myComboBox->get_comboBox(); }
                else { echo $myComboBox->comboBox_simple(); }
                
                break;

            case "yesnoall":
                $postVal = $post? $post[$property]: 0;
                $val = $item? $item->$property(): $postVal;
                $myYesNoAll = new yesNoAll($fields[$controlIndex], 
                    $fieldNames[$controlIndex], $val);
                if ($fullControl) { $myYesNoAll->get_control(); }
                else { echo $myYesNoAll->yesNoAll_simple(); }
                break;
                
                
            case "color":
                $postVal = $post? $post[$property]: "";
                $val = $item? $item->$property(): $postVal; 
                //echo $item->$property();
                $myColorBox = new textboxColor($fields[$controlIndex], 
                        $fieldNames[$controlIndex], $val);
                if ($fieldAttributes[$fields[$controlIndex]]['READONLY']=="readonly") {
                    $myColorBox->set_disabled();
                }
                if ($fullControl) { $myColorBox->get_TextboxColor(); }
                else { echo $myColorBox->textboxColorSimple(); }
                break;
                                
                
            /*case "combobox_multiselect":
                $postVal = $post? $post[$property]: 0;
                $val = $item? $item->$property(): $postVal;
                $myArray = $fieldAttributes[$fields[$controlIndex]];
                $sql = $myArray['SQL'];
                $idField = $myArray['ID-FIELD'];
                $descField = $myArray['DESC-FIELD'];
                $myComboBoxM = new comboBox_multiselect($fields[$controlIndex], $dbo, 
                        $sql, $idField, $descField, 
                        $val, $fieldNames[$controlIndex]);
                if ($fieldAttributes[$fields[$controlIndex]]['READONLY']=="readonly") {
                    $myComboBoxM->set_disabled();
                }
                if ($fullControl) { $myComboBoxM->get_comboBox(); }
                else { echo $myComboBoxM->comboBox_simple(); }
                
                break;*/
                

            case "autocomplete":
                $postVal = $post? $post[$property]: "";
                $postValH = $postVal==""? 0: $post["h_".$property];
                                
                $val = $item? $item->$property(): $postValH;
                $myArray = $fieldAttributes[$fields[$controlIndex]];
                $table = $myArray['TABLE'];
                $idField = $myArray['ID-FIELD'];
                $descField = $myArray['DESC-FIELD'];
                $descField2 = $myArray['DESC-FIELD-2'];
                $descField3 = $myArray['DESC-FIELD-3'];
                
                $myAutocomplete = new autocomplete2($fields[$controlIndex], $table, $val, $dbo);
                $myAutocomplete->set_id_field($idField);
                $myAutocomplete->set_descr_field($descField);
                
                if ($descField2!="") {
                    $myAutocomplete->set_descr_field_2($descField2);
                }
                if ($descField3!="") {
                    $myAutocomplete->set_descr_field_3($descField3);
                }
                
                $myAutocomplete->set_label($fieldNames[$controlIndex]);
                if ($fullControl) {
                    $myAutocomplete->getAutocomplete();
                }
                else {
                    $myAutocomplete->getAutocompleteSimple();
                }
                
                break;

            case "datefromto":
                //only for search
                $postVal1 = $post? $post[$property . "_d1"]: "";
                $postVal2 = $post? $post[$property . "_d2"]: "";
                $myDateFromTo = new dateFromTo($fields[$controlIndex], $postVal1, $postVal2, $locale);
                $myDateFromTo->getControl();
                break;

            case "filecontrol":
                //only for item form
                $val = $item? $item->$property(): "";
                $myArray = $fieldAttributes[$fields[$controlIndex]];
                //$host = $myArray['HOST'];
                $thumbWidth = 0; $thumbHeight = 0;
                if (array_key_exists("WIDTH", $myArray) ) {
                    $thumbWidth = $myArray['WIDTH'];
                }
                else {
                    $thumbWidth = 50;
                }
                if (array_key_exists("HEIGHT", $myArray) ) {
                    $thumbHeight = $myArray['HEIGHT'];
                }
                else {
                    $thumbHeight = 50;
                }

                $baseurl = $myArray['BASEURL'];
                $init_folder = $myArray['INIT-FOLDER'];
                $script_folder_depth = $myArray['SCRIPT-FOLDER-DEPTH'];
                                
                $myFileControl = new fileControl($fields[$controlIndex], $val, $fieldNames[$controlIndex], 
                $baseurl,$init_folder,$script_folder_depth);
                if ($thumbWidth>0 && $thumbHeight>0) {
                    $myFileControl->setThumbDimensions($thumbWidth, $thumbHeight);
                }
                
                if ($fullControl) {
                    $myFileControl->getFullControl();
                }
                else {
                    $myFileControl->getControl();
                }
                
                break;
            
            
            default:
                break;
        }
        echo "<div style=\"clear:both\"></div>";
        echo "</div>";

        if ($fieldAttributes[$fields[$controlIndex]]['AFTER']!="") {
            echo "<div class=\"col-4\"></div><div class=\"col-8\">" . $fieldAttributes[$fields[$controlIndex]]['AFTER'] . "</div>"  ;
        }
        
    }
    
    
    
}



class ITEMCONTROL
{
    
    protected $_item, $_fields, $_fieldTypes, $_fieldNames, $_filename, $_locale, $_dbo;
    protected $_fieldAttributes;
    protected $_canSave, $_canDelete;
    protected $_actionParams;
    //FieldTypes: text, textarea, integer, currency, date, combobox, check, autocomplete
    
    
    public function __construct($dbo, $item, $fields, $fieldTypes, $fieldNames, 
            $filename = "", $canSave = TRUE, $canDelete = TRUE, $locale="GR") {
        $this->_item = $item;
        $this->_fields = $fields;
        $this->_fieldTypes = $fieldTypes;
        $this->_fieldNames = $fieldNames;
        $this->_filename = $filename; 
        $this->_actionParams = "";  
        $this->_canSave = $canSave;
        $this->_canDelete = $canDelete;
        $this->_locale = $locale;
        $this->_dbo = $dbo;
        
        for ($i = 0; $i < count($this->_fields); $i++) {
            $this->_fieldAttributes[$this->_fieldNames[$i]] = "";
        }
        
        
    }

    public function setFields($array) {
        for ($i=0;$i<count($array);$i++) {
            $option = $array[$i];
            $this->_fields[$i] = $option[0];
            $this->_fieldTypes[$i] = $option[1];
            $this->_fieldNames[$i] = $option[2];
            if (!isset($this->_fieldAttributes[$this->_fields[$i]]) || !is_array($this->_fieldAttributes[$this->_fields[$i]])) {
                $this->_fieldAttributes[$this->_fields[$i]] = ["READONLY" => "", "AFTER" => ""];
            }
        }
    }
    
    
    
    /*example
    //combobox
    $itemControl->setFieldAttr("item_unit", 
        array("SQL"=> "SELECT id, description FROM item_units", 
            "ID-FIELD" => "id", "DESC-FIELD" => "description")
        );     
    */
    public function setFieldAttr($fieldName, $arrayVal) {
        if (is_array($arrayVal)) {
            $arrayVal += ["READONLY" => "", "AFTER" => ""];
        }
        $this->_fieldAttributes[$fieldName] = $arrayVal;
    }
    
    public function setFormActionParams($val) {
        $this->_actionParams = $val;
    }
    
    public function getControl($controlId, $fullControl = FALSE, $locale="GR") {        
        
        //var_dump($this->_item);
        
        CONTROL::getControl($controlId, $this->_dbo, $this->_item, FALSE, 
                    $this->_fields, 
                    $this->_fieldTypes, 
                    $this->_fieldNames, 
                    $this->_fieldAttributes, 
                    $fullControl,
                    $locale);        
        
    }
    
    
    
    
    
    
    public function submitButton($label = "Αποθήκευση", $fullControl = FALSE) {
        
        $btnSubmit = new button("submit-button", $label);
        if ($fullControl) {
            $btnSubmit->get_button();
        }
        else {
            echo $btnSubmit->get_button_simple();
        }       
        
    }
    
    
    
    public function deleteButton($controlId = "delete-button", $label = "Διαγραφή", 
            $textConfirm = "Θέλετε να διαγραφεί η τρέχουσα εγγραφή;", $labelConfirm = "Ναι, διάγραψέ την!", 
            $labelCancel = "Όχι, το μετάνιωσα.") {
        
        $page = $this->_filename;
        $itemId = $this->_item->get_id();
        
        echo "<div style=\"display:inline-block; position:relative\">";
        echo "<input id=\"$controlId\" type=\"button\" class=\"btn btn-danger\" value=\"$label\" />";
        echo "<div id=\"$controlId-confirm\" style=\"position:absolute; top:50px; left:0px; background:#fff; box-shadow:0px 0px 5px #ccc; padding:10px; display:none\">";
        echo "<p>$textConfirm</p>";
        echo "<p style=\"white-space: nowrap;\">"
                . "<a id=\"$controlId-confirm-button\" href=\"$page?id=$itemId&delete=1\" "
                . "class=\"btn btn-danger\">$labelConfirm</a> &nbsp; ";
        echo "<span id=\"$controlId-confirm-hide\" class=\"btn btn-primary\">$labelCancel</span>";
        echo "</p></div></div>";
        
        //script
        echo <<<EOT
        
        <script>
        
        $(function() {
            
            $('#$controlId').click(function() {
                $('#$controlId-confirm').show();                
            });
                
            $('#$controlId-confirm-hide').click(function() {
                $('#$controlId-confirm').hide();                
            });
        
        });        
        
        </script>
        
        
EOT;
        
    }
    
    
    
    public function SaveItem($post) {
        
        if ($post) {
            //$item = $this->_item;
            //echo "saving...";
            for ($i = 0; $i < count($this->_fields); $i++) {
                if ($this->_fields[$i]!="id" 
                        && $this->_fieldAttributes[$this->_fields[$i]]["READONLY"]!="readonly") {
                    //echo "saving....";
                    $val = "";
                    $property = $this->_fields[$i];

                            //echo $property . "-" . $this->_fieldTypes[$i]. "<br/>";

                    switch ($this->_fieldTypes[$i]) {
                        case "text":
                        case "hidden":                        
                        case "textarea":
                        case "hidden-area":
                        case "richtextbox":
                        case "integer":
                        case "combobox":
                        case "color":
                            $val = $post[$property];
                            break;
                        
                        case "password":
                            $postVal = $post[$property];
                            $val = "";
                            if ($postVal!="") {
                                $val = password::getVal($post, $property);
                            }
                            break;
                        
                        case "autocomplete":
                            $val = $post["h_" . $property];
                            //echo "autocomplete-val = " . $val;
                            break;

                        case "currency":
                            $val = textbox::getCurrency($post[$property], $this->_locale);
                            $val = $val==""? 0: $val;
                            break;
							
						case "currency3":
                            $val = textbox::getCurrency($post[$property], $this->_locale);
                            $val = $val==""? 0: $val;
                            break;

                        case "date":
                            $val = textbox::getDate($post[$property], $this->_locale);
                            break;

                        case "datetime":
                            $val = dateTimePicker::getValue($post, $property, $this->_locale);
                            break;

                        case "checkbox":
                            $val = checkbox::getVal2($post, $property);
                            break;

                        case "selectTreeList":
                            $val = selectTreeList::getVal($property, $post, $this->_fieldAttributes[$this->_fields[$i]]["TYPE"]);
                            //$val = str_replace(".", ",", $val);
                            break;
                        
                        case "filecontrol":
                            $val = $post[$property . "_txt"];

                        default:
                            break;
                    }

                    if ($this->_fieldTypes[$i]=="password") {
                        if ($val!="") {
                            $this->_item->$property($val);
                        }                        
                    }
                    else {
                        $this->_item->$property($val);
                    }
                     
                }
                
            }
            
            $this->_item->Savedata();
            
            return $this->_item->get_id();
        }
        else {
            return "";
        }
    }
    
    
    public function DeleteItem($get) {
        
        if ($get) {         
            if (isset($get['delete']) && $get['delete']==1) {                
                $this->_item->Delete();
                return 1;
            }
            else {
                return 0;
            }
        } 
        return 0;
        
    }
    
    
    public function ViewItem($saveResult = "", $delResult = 0, 
            $formid="item-form", $formMethod = "post") {
        
        $formAction = $this->_filename;        
        $item = $this->_item;
        $id = $item->get_id();
        echo "<!--".$saveResult."///-->";
        if ($saveResult!=="") { 
            echo "<!--///-->";
            $err = $saveResult>0? 0: 1;
            $msg = $saveResult>0? "Data was saved": "An error occured. Please try again";
            switch($err) {
                case 0: $alertType = "alert-success"; break;
                case 1: $alertType = "alert-danger"; break;
                default: break;
            }
            
            echo <<<EOT
            
            <div class="alert $alertType">
            <a href="#" class="close" data-dismiss="alert">&times;</a>
            $msg
            </div>                    
EOT;
        
        }
        
        if ($delResult==1) {
            
            $msg = "Item was deleted.";
            
            echo <<<EOT
            
            <div class="alert alert-success">
            <a href="#" class="close" data-dismiss="alert">&times;</a>
            $msg
            </div>
                
EOT;
            
        }
        else {
            $actionParams = $this->_actionParams!=""? "&" . $this->_actionParams: "";
        
            echo "<form id=\"$formid\" class=\"item-form\" action=\"$formAction?id=$id$actionParams\" method=\"$formMethod\">";

            for ($i = 0; $i < count($this->_fields); $i++) {
                $property = $this->_fields[$i];                
                $this->getControl($property, TRUE);           

            }

            echo "<div class=\"spacer-20\"></div>";

            echo "<div class=\"col-4 col-sm-12 col-sm-12\"></div><div class=\"col-8 col-sm-12 col-sm-12\">";
            if ($this->_canSave) { $this->submitButton(); }
            echo "&nbsp;";
            if ($this->_canDelete && $id>0) { $this->deleteButton(); }
            echo "</div>";

            echo "<div class=\"spacer-50\"></div>";

            echo "</form>";

            echo <<<EOT

            <script>

                $(function() {

                    $("#submit-button").click(function() {
                        parent.SetDataRefresh(1);
                    });

                    $("#delete-button-confirm-button").click(function() {
                        parent.SetDataRefresh(1);
                    });

                });

            </script>

EOT;
        
    }
    
    }
    
}







class LISTCONTROL
{
    
    protected $_fields, $_fieldTypes, $_fieldNames, $_filename, $_itemFilename, $_locale, $_dbo, $_sql;
    protected $_canAdd, $_canView;
    protected $_fieldAttributes;
    protected $_rsPage, $_nrOfRows, $_rs;
    protected $_itemName;
    protected $_sumcols;
    protected $_datagrid;
    protected $_selectRow, $_selectLabel;


    protected $_searchFields, $_searchFieldTypes, $_searchFieldNames, $_searchFieldAttributes;
    
    public function __construct($dbo, $sql, $fields, $fieldTypes, $fieldNames, 
            $filename="", $itemFilename="", $itemName = "",
            $canAdd = TRUE, $canView = TRUE,
            $locale="GR") {
        $this->_fields = $fields;
        $this->_fieldTypes = $fieldTypes;
        $this->_fieldNames = $fieldNames;
        $this->_filename = $filename;
        $this->_itemFilename = $itemFilename;
        $this->_itemName = $itemName;
        $this->_canAdd = $canAdd;
        $this->_canView = $canView;
        $this->_locale = $locale;
        $this->_dbo = $dbo;
        $this->_sql = $sql;
        $this->_selectRow = FALSE;
        $this->_selectLabel = "select";
        
        for ($i = 0; $i < count($this->_fields); $i++) {
            $this->_fieldAttributes[$this->_fields[$i]] = "";
        }
    }

    public function get_datagrid() {
        return $this->_datagrid;
    }
    public function set_datagrid($new_grid) {
        $this->_datagrid = $new_grid;
    }

    public function setFields($array) {
        for ($i=0;$i<count($array);$i++) {
            $option = $array[$i];
            $this->_fields[$i] = $option[0];
            $this->_fieldTypes[$i] = $option[1];
            $this->_fieldNames[$i] = $option[2];
        }
    }

    public function set_sumcols($array) {
        $this->_sumcols = $array;
    }

    public function set_select($label = "select") {
        $this->_selectRow = TRUE;
        $this->_selectLabel = $label;
    }
    
    
    /*
    SQL / ID-FIELD / DESC-FIELD (combobox)
     */
    public function setFieldAttr($field, $arrayVal) {
        $this->_fieldAttributes[$field] = $arrayVal;
        return $this;
    }
    
    public function setSearch($searchFields, $searchFieldTypes, $searchFieldNames) {
        
        $this->_searchFields = $searchFields;
        $this->_searchFieldTypes = $searchFieldTypes;
        $this->_searchFieldNames = $searchFieldNames;
        
        for ($i = 0; $i < count($this->_searchFields); $i++) {
            $this->_searchFieldAttributes[$this->_searchFields[$i]] = "";
        }
        
    }
    
    /*
    examples
    SEARCH-TYPE = STARTS-WITH, ANY, EXACT (description)
    SQL / ID-FIELD / DESC-FIELD (combobox)
    */
    public function setSearchFieldAttr($field, $arrayVal) {
        $this->_searchFieldAttributes[$field] = $arrayVal;
        return $this;
    }
    
    
    /*
    Αυτή η μέθοδος παίρνει ως παράμετρο την array $_POST ή $_GET
    και διαμορφώνει το sql του LISTCONTROL
     */
    public function SearchList($get, $updateRS = TRUE, 
            $pagination = TRUE, $orderBy = "", $nrOfRows = 50) {
        
        $currentPage = (isset($get) && isset($get['page'])) ? $get['page'] : 0;
        
        $orderBy = $orderBy==""? "": " ORDER BY " . $orderBy;
        
        if ($get) {
            if (strpos($this->_sql, "WHERE")==FALSE) {
                $this->_sql .= " WHERE id>0 ";
            }            
            
            for ($i = 0; $i < count($this->_searchFields); $i++) {
                $searchField = $this->_searchFields[$i];                
                //var_dump($this->_searchFieldAttributes[$searchField]);
                $criteria = array_key_exists('CRITERIA', $this->_searchFieldAttributes[$searchField])? 
                        $this->_searchFieldAttributes[$searchField]['CRITERIA']: "";                
                
                switch ($this->_searchFieldTypes[$i]) {
                    case "integer":
                        $val = $get[$searchField];
                        if ($val!="") {
                            $criteria = $criteria!=""? 
                                str_replace('[val]', $val, $criteria)
                                : " AND $searchField = $val ";
                            $this->_sql .= $criteria;
                        }
                        break;
                    
                    case "text":
                        $val = $get[$searchField];
                        if ($val!="") {
                            switch ($this->_searchFieldAttributes[$searchField]['SEARCH-TYPE']) {
                                case "EXACT":
                                    $criteria = $criteria!=""? 
                                        str_replace('[val]', $val, $criteria)
                                        : " AND $searchField LIKE '$val' ";
                                    $this->_sql .= $criteria;
                                    break;
                                case "STARTS-WITH":
                                    $criteria = $criteria!=""? 
                                        str_replace('[val]', $val, $criteria)
                                        : " AND $searchField LIKE '$val%' ";
                                    $this->_sql .= $criteria;
                                    break;
                                case "ANY":
                                    $criteria = $criteria!=""? 
                                        str_replace('[val]', $val, $criteria)
                                        : " AND $searchField LIKE '%$val%' ";
                                    $this->_sql .= $criteria;
                                    break;
                                default:
                                    $criteria = $criteria!=""? 
                                        str_replace('[val]', $val, $criteria)
                                        : " AND $searchField LIKE '%$val%' ";
                                    $this->_sql .= $criteria;
                            }                            
                        }
                        break;
                    
                    case "combobox":
                        $val = $get[$searchField];
                        if ($val!=0) {
                            $criteria = $criteria!=""? 
                                str_replace('[val]', $val, $criteria)
                                : " AND $searchField = $val ";
                            $this->_sql .= $criteria; 
                        }
                        break;

                    case "yesnoall":
                        $val = $get[$searchField];
                        if ($val>=0) {
                            $criteria = $criteria!=""? 
                                str_replace('[val]', $val, $criteria)
                                : " AND $searchField = $val ";
                            $this->_sql .= $criteria; 
                        }
                            break;
                    
                    case "date":
                        $val = $get[$searchField];
                        if ($val!="") {
                            $val = textbox::getDate($get[$searchField], $this->_locale);
                            $criteria = $criteria!=""? 
                                str_replace('[val]', $val, $criteria)
                                : " AND $searchField LIKE '$val' ";
                            $this->_sql .= $criteria;
                        }                        
                        break;
                    
                    case "datefromto":
                        $val1 = $get[$searchField . "_d1"];
                        //echo $val1;
                        if ($val1!="") {
                            //echo "....";
                            $arVal = dateFromTo::getVals($get, $searchField, $this->_locale);
                            $date1 = $arVal[0];
                            $date2 = $arVal[1];
                            
                            if ($criteria!="") {
                                $criteria = str_replace('[val1]', $date1, $criteria);
                                $criteria = str_replace('[val2]', $date2, $criteria);
                            }
                            else {
                                $criteria = " AND ($searchField >= '$date1' AND $searchField<= '$date2') ";
                            }
                            
                            $this->_sql .= $criteria;
                        }
                        break;
                        
                    case "checkbox":
                        $val = checkbox::getVal2($get, $searchField);
                        //echo $val;
                        //echo $criteria;
                        $criteria = $criteria!=""? 
                                str_replace('[val]', $val, $criteria)
                                : " AND $searchField = $val ";
                        $this->_sql .= $criteria;
                        break;
                        
                    case "autocomplete":
                        $valTxt = $get? $get[$searchField]: "";
                        $val = $valTxt==""? 0: $get["h_".$searchField];
                        
                        //$val = $get["h_".$searchField];
                        if ($val!=0) {
                            $criteria = $criteria!=""? 
                                str_replace('[val]', $val, $criteria)
                                : " AND $searchField = $val ";
                            $this->_sql .= $criteria; 
                        }
                        break;
                                   
                    
                    default:
                        break;
                }
                
            }
            
        }
        
                
        if ($pagination) {
            
            $link = $this->_filename;
                                    
            for ($i = 0; $i < count($this->_searchFields); $i++) {
                
                if ($this->_searchFieldTypes[$i]=="datefromto") {
                    $linkCriteria = $this->_searchFields[$i] . "_d1="  . $_GET[$this->_searchFields[$i] . "_d1"] 
                       . "&" . $this->_searchFields[$i] . "_d2="  . $_GET[$this->_searchFields[$i] . "_d2"] ;
                }
                elseif ($this->_searchFieldTypes[$i]=="autocomplete") {
                    $linkCriteria = $this->_searchFields[$i] . "="  . $_GET[$this->_searchFields[$i]] 
                       . "&" . "h_" . $this->_searchFields[$i] . "="  . $_GET["h_" . $this->_searchFields[$i]] ;
                }
                elseif ($this->_searchFieldTypes[$i]=="checkbox") {
                    $linkCriteria = $this->_searchFields[$i] . "=" . checkbox::getVal2($_GET, $this->_searchFields[$i]);
                }
                else {
                    $linkCriteria = $this->_searchFields[$i] . "=" . $_GET[$this->_searchFields[$i]];
                }
                
                if ($i==0) {
                    $link .= "?" . $linkCriteria;
                }
                else {
                    $link .= "&" . $linkCriteria;
                }
            }
            $link = $link == $this->_filename? $link . "?page=": $link . "&page=";
            
            //echo $this->_sql;
            
            $this->_rsPage = new RS_PAGE(
                    $this->_dbo, 
                    $this->_sql, 
                    implode(",",$this->_fields), 
                    $orderBy, $nrOfRows, 
                    $currentPage, NULL, 
                    $link);
            
            /*echo "<!--"; 
            var_dump($this->_rsPage);
            echo "-->"; */           
                        
            $this->_rs = $this->_rsPage->getRS();
            
            $this->_nrOfRows = $nrOfRows;
            
        }
        elseif ($updateRS) {
            $this->_rs = $this->_dbo->getRS($this->_sql . " $orderBy");
        }        
        
        
        echo "<!--". $this->_sql . "-->";
        
        
    }
    
    
    public function getRS() {
        return $this->_rs;
    }
    
    public function setRS($rs) {
        $this->_rs = $rs;
    }
    
    public function getSQL() {
        return $this->_sql;
    }
    
    
    public function getControl($controlId, $data, $fullControl = FALSE) {        
        
        CONTROL::getControl($controlId, $this->_dbo, FALSE, $data, 
                    $this->_searchFields, 
                    $this->_searchFieldTypes, 
                    $this->_searchFieldNames, 
                    $this->_searchFieldAttributes, 
                    $fullControl);        
        
    }
    
    
    
    public function searchForm($formId = "search-form", $method="get", $label="Search" ) {
        
        $page = $this->_filename;
        
        $data = $method=="get"? $_GET: $_POST;
        
        echo "<form id=\"$formId\" action=\"$page\" method=\"$method\" style=\"margin-bottom:10px\"  >";
        
        for ($i = 0; $i < count($this->_searchFields); $i++) {
            $controlId = $this->_searchFields[$i];
            if ($this->_searchFieldTypes[$i]=="separator") {
                echo "<br/>";
            }
            else {
                echo "<div style=\"display:inline-block; position:relative\">";
                echo $this->_searchFieldNames[$i] . "&nbsp;";
                $this->getControl($controlId, $data);
                echo "&nbsp;&nbsp;";
                echo "</div>";
            }
            

        } 
        
        $btnSubmit = new button("submit-button", $label);
        echo "&nbsp;&nbsp;" . $btnSubmit->get_button_simple();
        
        echo "</form>";
        
        
    }
    
    
    
    public function addNewButton($page, $label = "Προσθήκη") {
        
        echo "<a class=\"modalBtn btn btn-primary\" "
            . "data-href=\"$page?id=0\" "
            . " data-title=\"Νέα εγγραφή\" >$label</a>";
        
    }

    public function prepare_datagrid($editLabel = "Άνοιγμα", $rowsPerPage = 50, $editPopupWidth=1000, $editPopupHeight=700, $addPosition="TOP", $gridName = "grid") {
        $editUrl = $this->_itemFilename;
        
        if ($this->_canAdd && $addPosition=="TOP") {
            $this->addNewButton($editUrl);
        }
        
        $edit = $this->_canView;
        
        $grid = new datagrid("$gridName", 
                $this->_dbo, 
                "", 
                $this->_fields, 
                $this->_fieldNames, 
                "l=" . $this->_locale, 
                $rowsPerPage);
        $grid->set_rs($this->_rs);
        if ($edit) {
            $grid->set_edit($editUrl, $editLabel, "", $this->_itemName, $editPopupHeight, $editPopupWidth);
        }
        if ($this->_selectRow) {
            $grid->set_select($this->_selectLabel);
        }
        
        $colsFormat = array();
        for ($i = 0; $i < count($this->_fieldTypes); $i++) {
            switch ($this->_fieldTypes[$i]) {
                case "text":
                case "combobox":
                case "":
                    $colsFormat[$i] = "";
                    break;
                case "currency":
                    $colsFormat[$i] = "CURRENCY";
                    break;
				case "currency3":
                    $colsFormat[$i] = "CURRENCY3";
                    break;
                case "date":
                    $colsFormat[$i] = "DATE";
                    break;

                case "yesno":
                    $colsFormat[$i] = "YESNO";
                    break;
					
				case "checkbox":
                    $colsFormat[$i] = "CHECK";
                    break;

                default:
                    break;
            }
        }
        
        $grid->set_colsFormat($colsFormat);

        //COLUMNS with SUMS
        if ($this->_sumcols) {
            foreach($this->_sumcols as $colKey) {
                $grid->col_sum($colKey);
            }
        }

        $this->_datagrid = $grid;
        
        //$grid->get_datagrid();

    }
    
    public function ViewList($editLabel = "Άνοιγμα", $rowsPerPage = 50, $editPopupWidth=1000, $editPopupHeight=700, $addPosition="TOP", $gridName = "grid") {
        
        //search form
        if ($this->_searchFields) {
            $this->searchForm();
        }
        
        
        $editUrl = $this->_itemFilename;
        
        if ($this->_canAdd && $addPosition=="TOP") {
            $this->addNewButton($editUrl);
        }
        
        $edit = $this->_canView;
        
        $grid = new datagrid("$gridName", 
                $this->_dbo, 
                "", 
                $this->_fields, 
                $this->_fieldNames, 
                "l=" . $this->_locale, 
                $rowsPerPage);
        $grid->set_rs($this->_rs);
        if ($edit) {
            $grid->set_edit($editUrl, $editLabel, "", $this->_itemName, $editPopupHeight, $editPopupWidth);
        }
        if ($this->_selectRow) {
            $grid->set_select($this->_selectLabel);
        }
        
        $colsFormat = array();
        for ($i = 0; $i < count($this->_fieldTypes); $i++) {
            switch ($this->_fieldTypes[$i]) {
                case "text":
                case "combobox":
                case "":
                    $colsFormat[$i] = "";
                    break;
                case "currency":
                    $colsFormat[$i] = "CURRENCY";
                    break;
				case "currency3":
                    $colsFormat[$i] = "CURRENCY3";
                    break;
                case "date":
                    $colsFormat[$i] = "DATE";
                    break;

                case "yesno":
                    $colsFormat[$i] = "YESNO";
                    break;
					
				case "checkbox":
                    $colsFormat[$i] = "CHECK";
                    break;

                default:
                    break;
            }
        }
        
        $grid->set_colsFormat($colsFormat);

        //COLUMNS with SUMS
        if ($this->_sumcols) {
            foreach($this->_sumcols as $colKey) {
                $grid->col_sum($colKey);
            }
        }
        
        $grid->get_datagrid();
        
        if ($this->_canAdd && $addPosition=="BOTTOM") {
            $this->addNewButton($editUrl);
        }
        
        if ($this->_rsPage && $this->_rs) {
            echo '<div class="pagination">';
            
            if ($this->_rsPage->getCount() > $this->_nrOfRows){
                echo $this->_rsPage->getFirst();
                echo $this->_rsPage->getPrev();
                echo $this->_rsPage->getPageLinks();
                echo $this->_rsPage->getNext();                                
                echo $this->_rsPage->getLast();
            }
            
            echo '</div>';
        }
        
    }
    
    
    public function refreshScript() {
        
        $page = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        
        echo <<<EOT
        
        <script>
        
        function refresh() {
            window.location.href = "$page";
        }
        
        </script>
        
EOT;
        
    }
    
    
}
