<?php

require_once('php/config.php');
require_once('php/db.php');
require_once('php/dataobjects.php');
require_once('php/utils.php');
require_once('php/start.php');
require_once('php/session.php');

publisher_require_permission('properties');

$propertyId = isset($_POST['property_id']) ? (int)$_POST['property_id'] : (int)($_GET['id'] ?? 0);

if ($propertyId > 0) {
    publisher_set_current_property($dbo, $current_account_id, $propertyId);
}

header('Location: properties.php?selected=1');
exit;
