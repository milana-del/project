<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'method' => $_SERVER['REQUEST_METHOD']]);