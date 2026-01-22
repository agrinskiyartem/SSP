<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/header.php';
?>
<section class="card">
    <h1>Добро пожаловать в прототип «Банкоматы»</h1>
    <p>Проект демонстрирует базовые операции, справочники, аналитику и контроль конкурентного доступа.</p>
    <?php if ($user): ?>
        <p>Вы вошли как <strong><?= h($user['username']) ?></strong> (роль: <?= h($user['role']) ?>).</p>
    <?php else: ?>
        <p>Для работы войдите в систему.</p>
        <a class="button" href="login.php">Войти</a>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Возможности</h2>
    <ul>
        <li>CRUD для банков, банкоматов, клиентов и карт (только администратор).</li>
        <li>Снятие наличных с расчётом комиссии.</li>
        <li>Просмотр операций и аналитика.</li>
        <li>Демонстрация транзакций и блокировок.</li>
    </ul>
</section>
<?php
require_once __DIR__ . '/includes/footer.php';
?>
