<?php
// api.php
require_once 'config.php';
session_start();

header('Content-Type: application/json; charset=UTF-8');

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
// Ожидаем пути вида /api/messages, /api/messages/123, /api/orders, /api/profile
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path_parts = explode('/', trim($path, '/'));
$resource = $path_parts[0] ?? '';
$id = isset($path_parts[1]) && is_numeric($path_parts[1]) ? (int)$path_parts[1] : null;

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

function validate_message_data($data, &$errors) {
    $errors = [];
    // full_name
    $full_name = trim($data['name'] ?? '');
    if (empty($full_name) || !preg_match('/^[а-яА-Яa-zA-Z\s]+$/u', $full_name) || strlen($full_name) > 150) {
        $errors['name'] = 'Имя должно содержать только буквы и пробелы (макс. 150 символов).';
    }
    // email
    $email = trim($data['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email.';
    }
    // phone (необязательный, но если указан – формат)
    $phone = trim($data['phone'] ?? '');
    if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]{6,20}$/', $phone)) {
        $errors['phone'] = 'Телефон: 6–20 цифр, разрешены +, -, (, ), пробел.';
    }
    // subject – необязательно, но если есть – не более 255
    $subject = trim($data['subject'] ?? '');
    if (strlen($subject) > 255) {
        $errors['subject'] = 'Тема не более 255 символов.';
    }
    // message
    $message = trim($data['message'] ?? '');
    if (empty($message)) {
        $errors['message'] = 'Сообщение не может быть пустым.';
    } elseif (strlen($message) > 5000) {
        $errors['message'] = 'Сообщение не более 5000 символов.';
    }
    // privacy (согласие)
    if (empty($data['privacy'])) {
        $errors['privacy'] = 'Необходимо согласиться с обработкой данных.';
    }
    return empty($errors);
}

// ---------- Авторизация по сессии ----------
$user_id = $_SESSION['fan_user_id'] ?? null;

// ---------- Обработка маршрутов ----------
try {
    if ($method === 'POST' && $resource === 'messages') {
        // Получаем данные из JSON или FormData (для fallback)
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input && $_POST) $input = $_POST;
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Нет данных']);
            exit;
        }
        $errors = [];
        if (!validate_message_data($input, $errors)) {
            http_response_code(400);
            echo json_encode(['errors' => $errors]);
            exit;
        }

        $pdo->beginTransaction();
        try {
            // Если пользователь не авторизован – создаём нового
            if (!$user_id) {
                $login = generate_unique_login($pdo);
                $plain_password = generate_password();
                $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
                $full_name = trim($input['name']);
                $email = trim($input['email']);
                $phone = trim($input['phone'] ?? '');

                $stmt = $pdo->prepare("INSERT INTO fan_users (login, password_hash, full_name, email, phone) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$login, $password_hash, $full_name, $email, $phone]);
                $user_id = $pdo->lastInsertId();
                $new_user = true;
            } else {
                // Обновим контактные данные пользователя (имя, email, телефон) – по желанию
                $stmt = $pdo->prepare("UPDATE fan_users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([trim($input['name']), trim($input['email']), trim($input['phone'] ?? ''), $user_id]);
                $new_user = false;
            }

            // Сохраняем сообщение
            $stmt = $pdo->prepare("INSERT INTO fan_messages (user_id, name, email, subject, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                trim($input['name']),
                trim($input['email']),
                trim($input['subject'] ?? ''),
                trim($input['message'])
            ]);

            $pdo->commit();

            $response = ['success' => true, 'message' => 'Сообщение успешно отправлено!'];
            if ($new_user) {
                $response['login'] = $login;
                $response['password'] = $plain_password;
            }
            echo json_encode($response);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Ошибка сохранения: ' . $e->getMessage()]);
        }
        exit;
    }

    elseif ($method === 'PUT' && $resource === 'messages' && $id) {
        // Только для авторизованных пользователей
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['error' => 'Требуется авторизация']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Нет данных']);
            exit;
        }
        // Проверяем, что сообщение принадлежит этому пользователю
        $stmt = $pdo->prepare("SELECT user_id FROM fan_messages WHERE id = ?");
        $stmt->execute([$id]);
        $msg = $stmt->fetch();
        if (!$msg || $msg['user_id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['error' => 'Нет прав на редактирование']);
            exit;
        }

        // Валидация (можно менять тему и текст)
        $subject = trim($input['subject'] ?? '');
        $message = trim($input['message'] ?? '');
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
        $stmt->execute([$subject, $message, $id]);
        echo json_encode(['success' => true, 'message' => 'Сообщение обновлено']);
        exit;
    }

    elseif ($method === 'POST' && $resource === 'orders') {
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['error' => 'Требуется авторизация']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['items']) || !isset($input['total'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Некорректные данные заказа']);
            exit;
        }
        $items = $input['items'];
        $total = (float)$input['total'];
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

    elseif ($method === 'GET' && $resource === 'profile') {
        if (!$user_id) {
            http_response_code(401);
            echo json_encode(['error' => 'Требуется авторизация']);
            exit;
        }
        // Получаем данные пользователя
        $stmt = $pdo->prepare("SELECT id, login, full_name, email, phone, created_at FROM fan_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'Пользователь не найден']);
            exit;
        }
        // Получаем его сообщения
        $stmt = $pdo->prepare("SELECT id, subject, message, created_at, updated_at FROM fan_messages WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $messages = $stmt->fetchAll();
        // Получаем заказы
        $stmt = $pdo->prepare("SELECT id, total_amount, items_json, status, order_date FROM fan_orders WHERE user_id = ? ORDER BY order_date DESC");
        $stmt->execute([$user_id]);
        $orders = $stmt->fetchAll();
        foreach ($orders as &$order) {
            $order['items'] = json_decode($order['items_json'], true);
            unset($order['items_json']);
        }
        echo json_encode([
            'user' => $user,
            'messages' => $messages,
            'orders' => $orders
        ]);
        exit;
    }

    else {
        http_response_code(404);
        echo json_encode(['error' => 'Неверный маршрут']);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера: ' . $e->getMessage()]);
}