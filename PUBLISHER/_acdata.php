<?php

header('Content-Type: application/json; charset=utf-8');
require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');

function fail_json($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function valid_identifier($value) {
    return is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value);
}

function assert_identifier($value, $label) {
    if (!valid_identifier($value)) {
        fail_json("Invalid {$label}.", 400);
    }
    return $value;
}

function assert_safe_column($column) {
    $blocked = [
        'password', 'pass', 'passwd', 'hash', 'token', 'secret', 'apikey', 'api_key',
        'smtp_password', 'smtp_username', 'authorization'
    ];
    if (in_array(strtolower($column), $blocked, true)) {
        fail_json('Column is not allowed.', 403);
    }
    return $column;
}

function table_columns($db, $table) {
    static $cache = [];
    if (!isset($cache[$table])) {
        $rs = $db->getRS("SHOW COLUMNS FROM `{$table}`");
        if (!$rs) {
            fail_json('Table is not allowed.', 400);
        }
        $cache[$table] = array_column($rs, 'Field');
    }
    return $cache[$table];
}

function assert_table_column($db, $table, $column) {
    assert_safe_column($column);
    if (!in_array($column, table_columns($db, $table), true)) {
        fail_json('Column does not exist.', 400);
    }
    return $column;
}

$term = $_GET['term'] ?? '';
if ($term === '***') {
    $term = '';
}

$descrfieldconcatesep = $_GET['descrfieldconcatesep'] ?? '';

$table = assert_identifier($_GET['table'] ?? '', 'table');
$idfield = assert_identifier($_GET['idfield'] ?? '', 'id field');
$descrfield = assert_identifier($_GET['descrfield'] ?? '', 'description field');
$descrfield2 = isset($_REQUEST['descrfield2']) ? assert_identifier($_REQUEST['descrfield2'], 'second description field') : '';

assert_table_column($db1, $table, $idfield);
assert_table_column($db1, $table, $descrfield);
if ($descrfield2 !== '') {
    assert_table_column($db1, $table, $descrfield2);
}

$arrTerms = [$term];
$selectFields = "`{$idfield}`, `{$descrfield}`";
$whereSql = "`{$descrfield}` LIKE CONCAT('%',?,'%')";

if ($descrfield2 !== '') {
    $selectFields .= ", `{$descrfield2}`";
    $whereSql = "({$whereSql} OR `{$descrfield2}` LIKE CONCAT('%',?,'%'))";
    $arrTerms[] = $term;
}

$maxRows = filter_var($_GET['maxrows'] ?? 50, FILTER_VALIDATE_INT);
if ($maxRows === false || $maxRows < 1 || $maxRows > 100) {
    $maxRows = 50;
}

$sql = "SELECT {$selectFields} FROM `{$table}` WHERE {$whereSql} ORDER BY `{$descrfield}` LIMIT {$maxRows}";
$rs = $db1->getRS($sql, $arrTerms);

$result = [];
if ($rs) {
    for ($i = 0; $i < count($rs); $i++) {
        $label = $rs[$i][$descrfield];
        if ($descrfield2 !== '') {
            $label .= $descrfieldconcatesep . $rs[$i][$descrfield2];
        }
        $result[] = [
            'id' => (string)$rs[$i][$idfield],
            'label' => (string)$label
        ];
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
