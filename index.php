<?php
require_once './vendor/autoload.php';
require_once 'catalogApi.php';

$DB = new Db('localhost', '', 'test_api', 'root', '');

try {
    $api = new catalogApi($DB);
    echo $api->run();
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
