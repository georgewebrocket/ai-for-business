<?php

header('Content-Type: text/html; charset=utf-8');
require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');

function fail_html($message, $status = 400) {
    http_response_code($status);
    echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function valid_identifier($value) {
    return is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value);
}

function assert_identifier($value, $label) {
    if (!valid_identifier($value)) {
        fail_html("Invalid {$label}.", 400);
    }
    return $value;
}

function assert_safe_column($column) {
    $blocked = [
        'password', 'pass', 'passwd', 'hash', 'token', 'secret', 'apikey', 'api_key',
        'smtp_password', 'smtp_username', 'authorization'
    ];
    if (in_array(strtolower($column), $blocked, true)) {
        fail_html('Column is not allowed.', 403);
    }
    return $column;
}

function table_columns($db, $table) {
    static $cache = [];
    if (!isset($cache[$table])) {
        $rs = $db->getRS("SHOW COLUMNS FROM `{$table}`");
        if (!$rs) {
            fail_html('Table is not allowed.', 400);
        }
        $cache[$table] = array_column($rs, 'Field');
    }
    return $cache[$table];
}

function assert_table_column($db, $table, $column) {
    assert_safe_column($column);
    if (!in_array($column, table_columns($db, $table), true)) {
        fail_html('Column does not exist.', 400);
    }
    return $column;
}

$term = $_REQUEST['term'] ?? '';
if ($term === '***') {
   $term = '';
}

$table = assert_identifier($_REQUEST['table'] ?? '', 'table');
$idField = assert_identifier($_REQUEST['idField'] ?? '', 'id field');
$descrField = assert_identifier($_REQUEST['descrField'] ?? '', 'description field');
$descrField2 = isset($_REQUEST['descrField2']) ? assert_identifier($_REQUEST['descrField2'], 'second description field') : '';

assert_table_column($db1, $table, $idField);
assert_table_column($db1, $table, $descrField);
if ($descrField2 !== '') {
    assert_table_column($db1, $table, $descrField2);
}

$params = [$term];
$selectFields = "`{$idField}`, `{$descrField}`";
$whereSql = "`{$descrField}` LIKE CONCAT('%',?,'%')";

if ($descrField2 !== '') {
    $selectFields .= ", `{$descrField2}`";
    $whereSql = "({$whereSql} OR `{$descrField2}` LIKE CONCAT('%',?,'%'))";
    $params[] = $term;
}

$sql = "SELECT {$selectFields} FROM `{$table}` WHERE {$whereSql} ORDER BY `{$descrField}` LIMIT 50";
$rs = $db1->getRS($sql, $params);

echo '<ul>';
if ($rs) {
    for ($i = 0; $i < count($rs) && $i < 50; $i++) {
        $id = $rs[$i][$idField];
        $descr = $rs[$i][$descrField];
        if ($descrField2 !== '') {
            $descr2 = $rs[$i][$descrField2];
            $val = $descr . ' | ' . $descr2;
            echo '<li data-id="' . e($id) . '" data-val="' . e($val) . '">' . e($val) . '</li>';
        } else {
            echo '<li data-id="' . e($id) . '" data-val="' . e($descr) . '">' . e($descr) . '</li>';
        }
    }
}
echo '</ul>';

