<?php
// config.php
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $db_host = 'localhost';
        $db_user = 'u82326';
        $db_pass = '4843119';
        $db_name = 'u82326';
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Внутренняя ошибка сервера']));
        }
    }
    return $pdo;
}