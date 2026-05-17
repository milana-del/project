<?php
// api.php
require_once 'config.php';
session_start();

header('Content-Type: application/json; charset=UTF-8');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
if (!$input && $_POST) $input = $_POST;

$action = $input['action'] ?? $_GET['action'] ?? '';

$pdo = getDB();

// ---------- Вспомогательные функции ----------
function generate_unique_login($pdo) {
    do {
        $login = 'fan_' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
        $stmt = $pdo->prepare("SELECT id FROM fan_users WHERE login = ?");
        $stmt->execute([$login]);
    } while ($stmt->fetch());
    return $login;
}

function generate_password($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

// ---------- Регистрация ----------
if ($method === 'POST' && $action === 'register') {
    $full_name = trim($input['full_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $privacy = isset($input['privacy']) ? true : false;

    $errors = [];
    if (empty($full_name) || !preg_match('/^[а-яА-Яa-zA-Z\s]+$/u', $full_name) || strlen($full_name) > 150) {
        $errors['full_name'] = 'Имя должно содержать только буквы и пробелы (макс. 150 символов).';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email.';
    }
    if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]{6,20}$/', $phone)) {
        $errors['phone'] = 'Телефон: 6–20 цифр, разрешены +, -, (, ), пробел.';
    }
    if (!$privacy) {
        $errors['privacy'] = 'Необходимо согласиться с обработкой данных.';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $login = generate_unique_login($pdo);
        $plain_password = generate_password();
        $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO fan_users (login, password_hash, full_name, email, phone) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$login, $password_hash, $full_name, $email, $phone]);
        $user_id = $pdo->lastInsertId();

        $_SESSION['fan_user_id'] = $user_id;
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'login' => $login,
            'password' => $plain_password,
            'message' => 'Регистрация прошла успешно!'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка регистрации: ' . $e->getMessage()]);
    }
    exit;
}

// ---------- Вход ----------
if ($method === 'POST' && $action === 'login') {
    $login = trim($input['login'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($login) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Заполните оба поля.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, password_hash FROM fan_users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['fan_user_id'] = $user['id'];
        echo json_encode(['success' => true, 'message' => 'Вход выполнен']);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Неверный логин или пароль']);
    }
    exit;
}

// ---------- Проверка авторизации ----------
$user_id = $_SESSION['fan_user_id'] ?? null;

if ($method === 'GET' && $action === 'profile') {
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'Не авторизован']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, login, full_name, email, phone, created_at FROM fan_users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        session_destroy();
        http_response_code(401);
        echo json_encode(['error' => 'Пользователь не найден']);
        exit;
    }
    echo json_encode(['user' => $user]);
    exit;
}

// ---------- Далее только для авторизованных ----------
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

// ---------- Отправка сообщения ----------
if ($method === 'POST' && $action === 'message') {
    $subject = trim($input['subject'] ?? '');
    $message = trim($input['message'] ?? '');
    $privacy = isset($input['privacy']) ? true : false;

    $errors = [];
    if (strlen($subject) > 255) $errors['subject'] = 'Тема не более 255 символов.';
    if (empty($message)) $errors['message'] = 'Сообщение не может быть пустым.';
    if (strlen($message) > 5000) $errors['message'] = 'Сообщение не более 5000 символов.';
    if (!$privacy) $errors['privacy'] = 'Необходимо согласиться с обработкой данных.';

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        exit;
    }

    // Получаем данные пользователя для сохранения (имя, email)
    $stmt = $pdo->prepare("SELECT full_name, email FROM fan_users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    $stmt = $pdo->prepare("INSERT INTO fan_messages (user_id, name, email, subject, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $user['full_name'], $user['email'], $subject, $message]);

    echo json_encode(['success' => true, 'message' => 'Сообщение отправлено']);
    exit;
}

// ---------- Редактирование сообщения ----------
if ($method === 'PUT' && $action === 'update_message') {
    $message_id = (int)($input['id'] ?? 0);
    $subject = trim($input['subject'] ?? '');
    $message = trim($input['message'] ?? '');

    if (!$message_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID сообщения не указан']);
        exit;
    }

    // Проверяем принадлежность
    $stmt = $pdo->prepare("SELECT user_id FROM fan_messages WHERE id = ?");
    $stmt->execute([$message_id]);
    $msg = $stmt->fetch();
    if (!$msg || $msg['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Нет прав на редактирование']);
        exit;
    }

    $errors = [];
    if (strlen($subject) > 255) $errors['subject'] = 'Тема не более 255 символов.';
    if (empty($message)) $errors['message'] = 'Сообщение не может быть пустым.';
    if (strlen($message) > 5000) $errors['message'] = 'Сообщение не более 5000 символов.';

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE fan_messages SET subject = ?, message = ? WHERE id = ?");
    $stmt->execute([$subject, $message, $message_id]);
    echo json_encode(['success' => true, 'message' => 'Сообщение обновлено']);
    exit;
}

// ---------- Оформление заказа ----------
if ($method === 'POST' && $action === 'order') {
    $items = $input['items'] ?? [];
    $total = (float)($input['total'] ?? 0);

    if (empty($items) || $total <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Корзина пуста']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO fan_orders (user_id, total_amount, items_json) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $total, json_encode($items)]);
    echo json_encode(['success' => true, 'order_id' => $pdo->lastInsertId()]);
    exit;
}

// Если ничего не подошло
http_response_code(404);
echo json_encode(['error' => 'Неизвестное действие']);