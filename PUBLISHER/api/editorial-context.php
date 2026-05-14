<?php

require_once('../php/config.php');
require_once('../php/db.php');
require_once('../php/dataobjects.php');
require_once('../php/utils.php');
require_once('../php/start.php');
require_once('../php/session.php');
require_once('../php/editorial-context.php');

header('Content-Type: application/json; charset=utf-8');

publisher_require_permission('content');
publisher_require_property();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_GET;
}

$accountId = (int)$current_account_id;
$propertyId = (int)$current_property_id;
$days = isset($input['days']) ? (int)$input['days'] : 180;
$limit = isset($input['limit']) ? (int)$input['limit'] : 120;

echo json_encode(
    editorial_context_get($dbo, $accountId, $propertyId, [
        'days' => $days,
        'limit' => $limit,
    ]),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
);
