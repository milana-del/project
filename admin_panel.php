<?php
session_start();
require_once 'config.php';

// CSRF токен для форм редактирования
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// HTTP Basic Authentication
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Chase Atlantic Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<!DOCTYPE html><html><head><title>Доступ запрещён</title></head><body style="background:#121212; color:white; text-align:center; padding-top:50px;"><h1>🔒 Доступ запрещён</h1><p>Введите логин и пароль администратора.</p></body></html>';
    exit;
}

$auth_login = $_SERVER['PHP_AUTH_USER'];
$auth_pass  = $_SERVER['PHP_AUTH_PW'];

$pdo = getDB();

// Проверка администратора (таблица admin, логин admin, пароль milana)
$stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE login = ?");
$stmt->execute([$auth_login]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($auth_pass, $admin['password_hash'])) {
    header('WWW-Authenticate: Basic realm="Chase Atlantic Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<!DOCTYPE html><html><head><title>Неверный логин или пароль</title></head><body style="background:#121212; color:white; text-align:center; padding-top:50px;"><h1>❌ Неверный логин или пароль!</h1><p>Попробуйте ещё раз.</p></body></html>';
    exit;
}

// --- Обработка действий ---
$success_msg = '';
$error_msg = '';

// 1. Ответ на сообщение (fan_messages)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_message') {
    $message_id = (int)$_POST['message_id'];
    $reply_text = trim($_POST['reply_text']);
    if (!empty($reply_text)) {
        // Проверяем существование колонок admin_reply и reply_date, если нет – добавляем
        try {
            $stmt = $pdo->prepare("UPDATE fan_messages SET admin_reply = ?, reply_date = NOW() WHERE id = ?");
            $stmt->execute([$reply_text, $message_id]);
            $success_msg = " Ответ сохранён!";
        } catch (PDOException $e) {
            // Если колонок нет, добавляем их
            $pdo->exec("ALTER TABLE fan_messages ADD COLUMN admin_reply TEXT DEFAULT NULL AFTER message");
            $pdo->exec("ALTER TABLE fan_messages ADD COLUMN reply_date TIMESTAMP NULL DEFAULT NULL AFTER admin_reply");
            $stmt = $pdo->prepare("UPDATE fan_messages SET admin_reply = ?, reply_date = NOW() WHERE id = ?");
            $stmt->execute([$reply_text, $message_id]);
            $success_msg = " Ответ сохранён (поля добавлены автоматически)!";
        }
    } else {
        $error_msg = "Текст ответа не может быть пустым.";
    }
}

// 2. Обновление статуса заказа (fan_orders)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    $allowed = ['new', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (in_array($new_status, $allowed)) {
        $stmt = $pdo->prepare("UPDATE fan_orders SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        $success_msg = " Статус заказа №$order_id обновлён!";
    } else {
        $error_msg = "Недопустимый статус.";
    }
}

// 3. Редактирование пользователя (fan_users)
$edit_user_id = 0;
$edit_user = [];
$edit_errors = [];

if (isset($_GET['edit_user'])) {
    $edit_user_id = (int)$_GET['edit_user'];
    $stmt = $pdo->prepare("SELECT * FROM fan_users WHERE id = ?");
    $stmt->execute([$edit_user_id]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit_user) {
        $error_msg = "Пользователь не найден.";
        $edit_user_id = 0;
    }
}

// 4. Сохранение редактирования пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Ошибка CSRF. Обновите страницу.');
    }
    $id = (int)$_POST['user_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $has_error = false;
    if (empty($full_name) || !preg_match('/^[а-яА-Яa-zA-Z\s]+$/u', $full_name) || strlen($full_name) > 150) {
        $edit_errors['full_name'] = 'ФИО должно содержать только буквы и пробелы (макс. 150 символов).';
        $has_error = true;
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $edit_errors['email'] = 'Введите корректный email.';
        $has_error = true;
    }
    if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]{6,20}$/', $phone)) {
        $edit_errors['phone'] = 'Телефон: 6–20 цифр, разрешены +, -, (, ), пробел.';
        $has_error = true;
    }

    if ($has_error) {
        $edit_user = ['id' => $id, 'full_name' => $full_name, 'email' => $email, 'phone' => $phone];
        $edit_user_id = $id;
        $error_msg = "Исправьте ошибки в форме.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE fan_users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $phone, $id]);
            $success_msg = " Данные пользователя обновлены!";
            $edit_user_id = 0;
        } catch (Exception $e) {
            $error_msg = "Ошибка сохранения: " . $e->getMessage();
        }
    }
}

// 5. Удаление пользователя ( сначала сообщения и заказы, потом пользователь)
if (isset($_GET['delete_user'])) {
    $id = (int)$_GET['delete_user'];
    try {
        $pdo->prepare("DELETE FROM fan_messages WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM fan_orders WHERE user_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM fan_users WHERE id = ?")->execute([$id]);
        $success_msg = "🗑 Пользователь удалён (со всеми сообщениями и заказами).";
    } catch (Exception $e) {
        $error_msg = "Ошибка удаления: " . $e->getMessage();
    }
}

// --- Загрузка данных для отображения ---
// Все сообщения с данными пользователя
$messages = $pdo->query("
    SELECT m.*, u.login, u.full_name, u.email 
    FROM fan_messages m 
    JOIN fan_users u ON m.user_id = u.id 
    ORDER BY m.created_at DESC
")->fetchAll();

// Проверяем существование таблицы fan_orders
$orders_exist = false;
$orders = [];
try {
    $orders = $pdo->query("
        SELECT o.*, u.login, u.full_name, u.email 
        FROM fan_orders o 
        JOIN fan_users u ON o.user_id = u.id 
        ORDER BY o.order_date DESC
    ")->fetchAll();
    $orders_exist = true;
} catch (PDOException $e) {
    
}

// Все пользователи
$users = $pdo->query("SELECT id, login, full_name, email, phone, created_at FROM fan_users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | Chase Atlantic</title>
    <link rel="icon" href="assets/maini.ico" type="image/ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700;900&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background: #0a0a0a; padding: 20px; }
        .admin-container { max-width: 1400px; margin: 0 auto; }
        .tabs { display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 1px solid #333; }
        .tab-btn { background: none; border: none; color: #aaa; font-size: 1.2rem; font-weight: 600; padding: 10px 25px; cursor: pointer; transition: 0.3s; }
        .tab-btn.active { color: var(--primary-color); border-bottom: 3px solid var(--primary-color); }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .section-card { background: #1a1a1a; border-radius: 20px; padding: 25px; margin-bottom: 30px; }
        .section-title { font-size: 1.5rem; margin-bottom: 20px; border-left: 4px solid var(--primary-color); padding-left: 15px; }
        .message-card, .order-card, .user-card { background: rgba(255,255,255,0.05); border-radius: 15px; padding: 15px; margin-bottom: 15px; }
        .reply-text { background: rgba(76,175,80,0.1); padding: 10px; border-left: 3px solid #4caf50; margin-top: 10px; }
        .admin-edit-form { background: #252525; border: 1px solid var(--primary-color); border-radius: 20px; padding: 20px; margin-bottom: 30px; }
        .field-error { color: #ffaa66; font-size: 0.8rem; display: block; margin-top: 4px; }
        .btn-save { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
        .table { color: white; }
        .table th { background: #2a2a2a; }
        .status-select { background: #333; color: white; border: 1px solid var(--primary-color); border-radius: 8px; padding: 5px; }
    </style>
</head>
<body>
<div class="admin-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1> Админ-панель Chase Atlantic</h1>
        <a href="index.html" class="btn btn-secondary">На главную</a>
    </div>
    <p>Вы вошли как <strong><?= htmlspecialchars($auth_login) ?></strong></p>

    <?php if ($success_msg): ?>
        <div class="alert alert-success"><?= $success_msg ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger"><?= $error_msg ?></div>
    <?php endif; ?>

    <!-- Вкладки -->
    <div class="tabs">
        <button class="tab-btn active" data-tab="tab1">📬 Обращения и заказы</button>
        <button class="tab-btn" data-tab="tab2">👥 Пользователи</button>
    </div>

    <!-- Вкладка 1: Обращения + Заказы -->
    <div id="tab1" class="tab-pane active">
        <!-- Обращения -->
        <div class="section-card">
            <h2 class="section-title">📬 Обращения пользователей</h2>
            <?php if (empty($messages)): ?>
                <p>Нет обращений.</p>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message-card">
                        <div><strong>От:</strong> <?= htmlspecialchars($msg['full_name']) ?> (<?= htmlspecialchars($msg['login']) ?>)</div>
                        <div><strong>Email:</strong> <?= htmlspecialchars($msg['email']) ?></div>
                        <div><strong>Тема:</strong> <?= htmlspecialchars($msg['subject'] ?: '—') ?></div>
                        <div><strong>Сообщение:</strong><br><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                        <div><strong>Дата:</strong> <?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></div>
                        <?php if (!empty($msg['admin_reply'])): ?>
                            <div class="reply-text">
                                <strong>📎 Ваш ответ:</strong><br><?= nl2br(htmlspecialchars($msg['admin_reply'])) ?><br>
                                <small><?= date('d.m.Y H:i', strtotime($msg['reply_date'])) ?></small>
                            </div>
                        <?php endif; ?>
                        <form method="post" class="mt-3">
                            <input type="hidden" name="action" value="reply_message">
                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                            <textarea name="reply_text" rows="2" placeholder="Напишите ответ..." class="form-control"><?= htmlspecialchars($msg['admin_reply'] ?? '') ?></textarea>
                            <button type="submit" class="btn btn-primary mt-2">📨 Отправить ответ</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Заказы -->
        <div class="section-card">
            <h2 class="section-title">🛍 Заказы пользователей</h2>
            <?php if (!$orders_exist): ?>
                <p>Таблица заказов (fan_orders) не найдена или отсутствует. Заказы не отображаются.</p>
            <?php elseif (empty($orders)): ?>
                <p>Нет заказов.</p>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div><strong>Заказ №<?= $order['id'] ?></strong> – <?= date('d.m.Y H:i', strtotime($order['order_date'])) ?></div>
                        <div><strong>Пользователь:</strong> <?= htmlspecialchars($order['full_name']) ?> (<?= htmlspecialchars($order['login']) ?>)</div>
                        <div><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></div>
                        <div><strong>Сумма:</strong> <?= number_format($order['total_amount'], 0, '.', ' ') ?> ₽</div>
                        <div><strong>Состав заказа:</strong> 
                            <?php 
                                $items = json_decode($order['items_json'], true);
                                if ($items) {
                                    echo '<ul>';
                                    foreach ($items as $item) {
                                        echo '<li>' . htmlspecialchars($item['name']) . ' x ' . $item['quantity'] . ' – ' . number_format($item['price'] * $item['quantity'], 0, '.', ' ') . ' ₽</li>';
                                    }
                                    echo '</ul>';
                                } else {
                                    echo '—';
                                }
                            ?>
                        </div>
                        <div><strong>Текущий статус:</strong> 
                            <span class="badge bg-<?= 
                                $order['status'] == 'new' ? 'secondary' : 
                                ($order['status'] == 'processing' ? 'primary' : 
                                ($order['status'] == 'shipped' ? 'info' : 
                                ($order['status'] == 'delivered' ? 'success' : 'danger'))) ?>">
                                <?= $order['status'] ?>
                            </span>
                        </div>
                        <form method="post" class="mt-2">
                            <input type="hidden" name="action" value="update_order_status">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <select name="status" class="status-select">
                                <option value="new" <?= $order['status'] == 'new' ? 'selected' : '' ?>>Новый</option>
                                <option value="processing" <?= $order['status'] == 'processing' ? 'selected' : '' ?>>В обработке</option>
                                <option value="shipped" <?= $order['status'] == 'shipped' ? 'selected' : '' ?>>Отправлен</option>
                                <option value="delivered" <?= $order['status'] == 'delivered' ? 'selected' : '' ?>>Доставлен</option>
                                <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Отменён</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-warning"> Обновить статус</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Вкладка 2: Пользователи (редактирование, удаление) -->
    <div id="tab2" class="tab-pane">
        <div class="section-card">
            <h2 class="section-title">👥 Управление пользователями</h2>

            <!-- Форма редактирования (если выбран пользователь) -->
            <?php if ($edit_user_id > 0 && !empty($edit_user)): ?>
                <div class="admin-edit-form">
                    <h3>Редактирование пользователя: <?= htmlspecialchars($edit_user['login']) ?></h3>
                    <form method="post">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <div class="form-group">
                            <label>ФИО *</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($edit_user['full_name'] ?? '') ?>" class="form-control">
                            <?php if (isset($edit_errors['full_name'])): ?>
                                <span class="field-error"><?= $edit_errors['full_name'] ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($edit_user['email'] ?? '') ?>" class="form-control">
                            <?php if (isset($edit_errors['email'])): ?>
                                <span class="field-error"><?= $edit_errors['email'] ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>Телефон</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($edit_user['phone'] ?? '') ?>" class="form-control">
                            <?php if (isset($edit_errors['phone'])): ?>
                                <span class="field-error"><?= $edit_errors['phone'] ?></span>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-save"> Сохранить изменения</button>
                        <a href="admin_panel.php" class="btn btn-secondary ms-2">Отмена</a>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Таблица пользователей -->
            <?php if (empty($users)): ?>
                <p>Нет зарегистрированных пользователей.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Логин</th>
                                <th>ФИО</th>
                                <th>Email</th>
                                <th>Телефон</th>
                                <th>Дата регистрации</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['login']) ?></td>
                                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['phone'] ?: '—') ?></td>
                                    <td><?= date('d.m.Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <a href="admin_panel.php?edit_user=<?= $user['id'] ?>" class="btn btn-sm btn-warning">Редактировать</a>
                                        <a href="admin_panel.php?delete_user=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Удалить пользователя <?= htmlspecialchars($user['login']) ?> и все его сообщения и заказы?')">Удалить</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Переключение вкладок
    const tabs = document.querySelectorAll('.tab-btn');
    const panes = document.querySelectorAll('.tab-pane');
    tabs.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.dataset.tab;
            tabs.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            panes.forEach(pane => pane.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
        });
    });
</script>
</body>
</html>