<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['fan_user_id'])) {
    header('Location: profile.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($login) || empty($password)) {
        $error = 'Заполните оба поля.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id, password_hash FROM fan_users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['fan_user_id'] = $user['id'];
            header('Location: profile.php');
            exit;
        } else {
            $error = 'Неверный логин или пароль.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход | Chase Atlantic Fan</title>
    <link rel="icon" href="assets/maini.ico" type="image/ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700;900&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-container {
            max-width: 500px;
            margin: 100px auto;
            background: rgba(30,30,30,0.9);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
        }
        .back-link { margin-top: 30px; }
        .back-link a { color: var(--primary-color); text-decoration: none; }
        .error-message { background: rgba(244,67,54,0.2); color: #f44336; padding: 10px; border-radius: 10px; margin-bottom: 20px; }
    </style>
</head>
<body style="background: var(--darker-bg);">
<div class="container">
    <div class="login-container">
        <h2 style="margin-bottom: 20px;">Вход в личный кабинет</h2>
        <p>Введите логин и пароль, полученные при отправке формы</p>
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>Логин</label>
                <input type="text" name="login" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="submit-btn" style="margin-top: 20px;">Войти</button>
        </form>
        <div class="back-link">
            <a href="index.html">← Вернуться на главную</a>
        </div>
    </div>
</div>
</body>
</html>