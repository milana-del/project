<?php
// admin_panel.php
session_start();
require_once 'config.php';

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
$stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE login = ?");
$stmt->execute([$auth_login]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($auth_pass, $admin['password_hash'])) {
    header('WWW-Authenticate: Basic realm="Chase Atlantic Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<!DOCTYPE html><html><head><title>Неверный логин или пароль</title></head><body style="background:#121212; color:white; text-align:center; padding-top:50px;"><h1>❌ Неверный логин или пароль!</h1><p>Попробуйте ещё раз.</p></body></html>';
    exit;
}

// Обработка ответа на сообщение
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_message') {
    $message_id = (int)$_POST['message_id'];
    $reply_text = trim($_POST['reply_text']);
    if (!empty($reply_text)) {
        $stmt = $pdo->prepare("UPDATE fan_messages SET admin_reply = ?, reply_date = NOW() WHERE id = ?");
        $stmt->execute([$reply_text, $message_id]);
        $success = "✅ Ответ сохранён!";
    } else {
        $error = "Текст ответа не может быть пустым.";
    }
}

// Обработка изменения статуса заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    $allowed = ['new', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (in_array($new_status, $allowed)) {
        $stmt = $pdo->prepare("UPDATE fan_orders SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        $success = "✅ Статус заказа №$order_id обновлён!";
    } else {
        $error = "Недопустимый статус.";
    }
}

// Получаем список всех сообщений с данными пользователя
$messages = $pdo->query("
    SELECT m.*, u.login, u.full_name, u.email 
    FROM fan_messages m
    JOIN fan_users u ON m.user_id = u.id
    ORDER BY m.created_at DESC
")->fetchAll();

// Получаем список всех заказов с данными пользователя
$orders = $pdo->query("
    SELECT o.*, u.login, u.full_name, u.email 
    FROM fan_orders o
    JOIN fan_users u ON o.user_id = u.id
    ORDER BY o.order_date DESC
")->fetchAll();
foreach ($orders as &$order) {
    $order['items'] = json_decode($order['items_json'], true);
}
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
        body { background: var(--darker-bg); padding-top: 30px; }
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .section-card { background: var(--dark-bg); border-radius: 20px; padding: 25px; margin-bottom: 40px; }
        .section-title { font-size: 1.8rem; margin-bottom: 20px; border-left: 5px solid var(--primary-color); padding-left: 15px; }
        .message-card, .order-card { background: rgba(255,255,255,0.05); border-radius: 15px; padding: 15px; margin-bottom: 15px; transition: 0.3s; }
        .message-card:hover, .order-card:hover { background: rgba(138,43,226,0.1); }
        .reply-form textarea { width: 100%; padding: 10px; background: rgba(0,0,0,0.3); border: 1px solid var(--primary-color); border-radius: 10px; color: white; }
        .reply-btn, .status-btn { background: var(--primary-color); border: none; color: white; padding: 5px 15px; border-radius: 20px; cursor: pointer; transition: 0.3s; }
        .reply-btn:hover, .status-btn:hover { background: var(--secondary-color); }
        .status-select { padding: 5px; border-radius: 10px; background: #333; color: white; border: 1px solid var(--primary-color); }
        .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .back-link { background: #333; padding: 8px 20px; border-radius: 30px; color: white; text-decoration: none; }
        .back-link:hover { background: var(--primary-color); color: white; }
        .reply-text { background: rgba(76,175,80,0.1); padding: 10px; border-left: 3px solid #4caf50; margin-top: 10px; }
    </style>
</head>
<body>
<div class="admin-container">
    <div class="admin-header">
        <h1>🔧 Админ-панель Chase Atlantic</h1>
        <a href="index.html" class="back-link">← На главную</a>
    </div>
    <p>Вы вошли как <strong><?= htmlspecialchars($auth_login) ?></strong></p>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- Раздел 1: Обращения пользователей -->
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
                            <strong>📎 Ваш ответ:</strong><br>
                            <?= nl2br(htmlspecialchars($msg['admin_reply'])) ?><br>
                            <small>от <?= date('d.m.Y H:i', strtotime($msg['reply_date'])) ?></small>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" class="reply-form mt-3">
                        <input type="hidden" name="action" value="reply_message">
                        <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                        <textarea name="reply_text" rows="2" placeholder="Напишите ответ пользователю..."><?= htmlspecialchars($msg['admin_reply'] ?? '') ?></textarea>
                        <button type="submit" class="reply-btn mt-2">📨 Отправить ответ</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Раздел 2: Заказы пользователей -->
    <div class="section-card">
        <h2 class="section-title">🛍 Заказы пользователей</h2>
        <?php if (empty($orders)): ?>
            <p>Нет заказов.</p>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div><strong>Заказ №<?= $order['id'] ?></strong> – <?= date('d.m.Y H:i', strtotime($order['order_date'])) ?></div>
                    <div><strong>Пользователь:</strong> <?= htmlspecialchars($order['full_name']) ?> (<?= htmlspecialchars($order['login']) ?>)</div>
                    <div><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></div>
                    <div><strong>Сумма:</strong> <?= number_format($order['total_amount'], 0, '.', ' ') ?> ₽</div>
                    <div><strong>Текущий статус:</strong> 
                        <span class="badge bg-<?= 
                            $order['status'] == 'new' ? 'secondary' : 
                            ($order['status'] == 'processing' ? 'primary' : 
                            ($order['status'] == 'shipped' ? 'info' : 
                            ($order['status'] == 'delivered' ? 'success' : 'danger'))) ?>">
                            <?= $order['status'] ?>
                        </span>
                    </div>
                    <details>
                        <summary>Состав заказа</summary>
                        <ul>
                            <?php foreach ($order['items'] as $item): ?>
                                <li><?= htmlspecialchars($item['name']) ?> x <?= $item['quantity'] ?> – <?= number_format($item['price'] * $item['quantity'], 0, '.', ' ') ?> ₽</li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                    
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
                        <button type="submit" class="status-btn ms-2">🔄 Обновить статус</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>