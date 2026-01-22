<?php
$flash = get_flash();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Банкоматы — учебный прототип</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container">
        <div class="logo">ATM Coursework</div>
        <nav class="nav">
            <a href="index.php">Главная</a>
            <?php if ($user): ?>
                <a href="withdraw.php">Снятие</a>
                <a href="operations.php">Операции</a>
                <a href="analytics.php">Аналитика</a>
                <?php if (is_admin()): ?>
                    <a href="banks.php">Банки</a>
                    <a href="atms.php">Банкоматы</a>
                    <a href="customers.php">Клиенты</a>
                    <a href="cards.php">Карты</a>
                <?php endif; ?>
                <a href="logout.php">Выход</a>
            <?php else: ?>
                <a href="login.php">Вход</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container">
    <?php if (!empty($_SESSION['session_warning'])): ?>
        <div class="alert warning">Ваша сессия истечёт через ~30 секунд при отсутствии активности.</div>
    <?php endif; ?>

    <?php foreach ($flash as $type => $messages): ?>
        <?php foreach ($messages as $message): ?>
            <div class="alert <?= h($type) ?>"><?= h($message) ?></div>
        <?php endforeach; ?>
    <?php endforeach; ?>
