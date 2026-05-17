<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['fan_user_id'])) {
    header('Location: fan_login.php');
    exit;
}

$pdo = getDB();
$user_id = $_SESSION['fan_user_id'];

// Выход
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.html');
    exit;
}

// Загружаем данные пользователя, сообщения и заказы через API (или напрямую)
// Для простоты используем прямой запрос к БД, но можно и через cURL к api.php
$stmt = $pdo->prepare("SELECT id, login, full_name, email, phone, created_at FROM fan_users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    session_destroy();
    header('Location: fan_login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, subject, message, created_at, updated_at FROM fan_messages WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$messages = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, total_amount, items_json, status, order_date FROM fan_orders WHERE user_id = ? ORDER BY order_date DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();
foreach ($orders as &$order) {
    $order['items'] = json_decode($order['items_json'], true);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мой профиль | Chase Atlantic Fan</title>
    <link rel="icon" href="assets/maini.ico" type="image/ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700;900&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .profile-container {
            background: var(--dark-bg);
            border-radius: 20px;
            padding: 30px;
            margin: 30px auto;
        }
        .section-title-small {
            font-size: 1.5rem;
            margin: 30px 0 20px;
            border-left: 4px solid var(--primary-color);
            padding-left: 15px;
        }
        .message-card, .order-card {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .message-card .date, .order-card .date {
            color: var(--gray-text);
            font-size: 0.85rem;
        }
        .edit-msg-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.9rem;
        }
        .modal-edit {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .modal-edit.active {
            display: flex;
        }
        .modal-edit .modal-content {
            background: var(--dark-bg);
            max-width: 500px;
            width: 90%;
            border-radius: 20px;
            padding: 20px;
        }
        .logout-link {
            display: inline-block;
            margin-top: 20px;
            color: #ff6b6b;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="profile-container">
        <div class="d-flex justify-content-between align-items-center">
            <h1>👋 <?= htmlspecialchars($user['full_name']) ?></h1>
            <a href="?logout=1" class="logout-link">Выйти</a>
        </div>
        <p><strong>Логин:</strong> <?= htmlspecialchars($user['login']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
        <p><strong>Телефон:</strong> <?= htmlspecialchars($user['phone'] ?: 'не указан') ?></p>
        <p><strong>На сайте с:</strong> <?= date('d.m.Y', strtotime($user['created_at'])) ?></p>

        <h2 class="section-title-small">📬 Мои обращения</h2>
        <?php if (empty($messages)): ?>
            <p>Пока нет отправленных сообщений.</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message-card" data-id="<?= $msg['id'] ?>">
                    <div class="d-flex justify-content-between">
                        <strong><?= htmlspecialchars($msg['subject'] ?: 'Без темы') ?></strong>
                        <button class="edit-msg-btn" onclick="openEditModal(<?= $msg['id'] ?>, '<?= htmlspecialchars($msg['subject']) ?>', `<?= htmlspecialchars($msg['message']) ?>`)">✏️ Редактировать</button>
                    </div>
                    <div class="date"><?= date('d.m.Y H:i', strtotime($msg['created_at'])) ?></div>
                    <div class="message-text mt-2"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2 class="section-title-small">🛍 Мои заказы</h2>
        <?php if (empty($orders)): ?>
            <p>Вы ещё не оформляли заказы через калькулятор.</p>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div><strong>Заказ №<?= $order['id'] ?></strong> – <?= date('d.m.Y', strtotime($order['order_date'])) ?></div>
                    <div>Сумма: <strong><?= number_format($order['total_amount'], 0, '.', ' ') ?> ₽</strong></div>
                    <div>Статус: <?= $order['status'] ?></div>
                    <details>
                        <summary>Состав заказа</summary>
                        <ul>
                            <?php foreach ($order['items'] as $item): ?>
                                <li><?= htmlspecialchars($item['name']) ?> x <?= $item['quantity'] ?> – <?= number_format($item['price'] * $item['quantity'], 0, '.', ' ') ?> ₽</li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="mt-4">
            <a href="index.html" class="back-link">← Вернуться на главную</a>
        </div>
    </div>
</div>

<!-- Модальное окно редактирования сообщения -->
<div id="editModal" class="modal-edit">
    <div class="modal-content">
        <h3>Редактировать сообщение</h3>
        <form id="editForm">
            <div class="form-group">
                <label>Тема</label>
                <input type="text" name="subject" id="editSubject" class="form-control">
            </div>
            <div class="form-group">
                <label>Сообщение</label>
                <textarea name="message" id="editMessage" rows="4" class="form-control" required></textarea>
            </div>
            <input type="hidden" id="editId">
            <button type="submit" class="submit-btn mt-3">Сохранить</button>
            <button type="button" class="btn btn-secondary mt-2" onclick="closeEditModal()">Отмена</button>
        </form>
    </div>
</div>

<script>
    let currentMessageId = null;
    function openEditModal(id, subject, message) {
        currentMessageId = id;
        document.getElementById('editId').value = id;
        document.getElementById('editSubject').value = subject;
        document.getElementById('editMessage').value = message;
        document.getElementById('editModal').classList.add('active');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
        currentMessageId = null;
    }
    document.getElementById('editForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('editId').value;
        const subject = document.getElementById('editSubject').value;
        const message = document.getElementById('editMessage').value;
        try {
            const res = await fetch('/api/messages/' + id, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ subject, message })
            });
            const data = await res.json();
            if (res.ok) {
                alert('Сообщение обновлено!');
                location.reload();
            } else {
                alert('Ошибка: ' + (data.error || data.errors?.message || 'Не удалось обновить'));
            }
        } catch (err) {
            alert('Ошибка сети');
        }
        closeEditModal();
    });
    window.onclick = function(event) {
        const modal = document.getElementById('editModal');
        if (event.target === modal) closeEditModal();
    }
</script>
</body>
</html>