# Вкладки интерфейса сайта «Банкоматы»

Ниже собраны все вкладки (пункты навигации) сайта и их назначение. Вставки кода показывают, где они объявлены и какие ключевые действия выполняют.

## Навигация и доступность вкладок

Меню формируется в общем шаблоне. В нём видно, что часть вкладок доступна только после входа, а справочники — только администратору:

```php
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
```

## Главная

Вкладка «Главная» показывает приветствие и краткий список возможностей, а также контекст входа пользователя:

```php
<section class="card">
    <h1>Добро пожаловать в прототип «Банкоматы»</h1>
    <?php if ($user): ?>
        <p>Вы вошли как <strong><?= h($user['username']) ?></strong> (роль: <?= h($user['role']) ?>).</p>
    <?php else: ?>
        <p>Для работы войдите в систему.</p>
        <a class="button" href="login.php">Войти</a>
    <?php endif; ?>
</section>
```

## Снятие

Вкладка «Снятие» доступна только авторизованным пользователям и демонстрирует списание с блокировкой счёта:

```php
require_login();

$stmt = $pdo->prepare(
    'SELECT a.account_id, a.balance, c.issuing_bank_id, atm.bank_id AS atm_bank_id
     FROM accounts a
     JOIN cards c ON c.account_id = a.account_id
     JOIN atms atm ON atm.atm_id = ?
     WHERE c.card_id = ?
     FOR UPDATE'
);
```

Здесь же есть форма снятия и кнопка для демонстрации режима без блокировки:

```php
<button class="button" type="submit">Снять</button>
<button class="button" type="submit" formaction="withdraw_unlocked.php">Снять (без блокировки)</button>
```

## Операции

Вкладка «Операции» даёт фильтр по операциям снятия, а также таблицу с деталями. Фильтры временно сохраняются в cookie:

```php
if ($_GET) {
    foreach ($filters as $key => $_) {
        $filters[$key] = trim($_GET[$key] ?? '');
    }
    setcookie('ops_filters', json_encode($filters, JSON_UNESCAPED_UNICODE), time() + 300, '/');
}
```

## Аналитика

Вкладка «Аналитика» собирает агрегированные отчёты — комиссии по банкам, частоту использования банкоматов и динамику операций:

```php
$commissionByBank = $pdo->query(
    'SELECT banks.name AS bank_name, SUM(withdrawals.commission_amount) AS total_commission
     FROM withdrawals
     JOIN atms ON atms.atm_id = withdrawals.atm_id
     JOIN banks ON banks.bank_id = atms.bank_id
     GROUP BY banks.bank_id
     ORDER BY total_commission DESC'
)->fetchAll();
```

## Банки

Вкладка «Банки» — справочник банков. Администратор может добавлять, редактировать и удалять записи, остальные пользователи видят только таблицу:

```php
if (!$isAdmin) {
    set_flash('error', 'У вас нет прав на изменение справочника.');
    redirect('banks.php');
}
```

## Банкоматы

Вкладка «Банкоматы» показывает список банкоматов с привязкой к банку и статусом. Изменения разрешены только администратору:

```php
$atms = $pdo->query(
    'SELECT atms.*, banks.name AS bank_name
     FROM atms
     JOIN banks ON banks.bank_id = atms.bank_id
     ORDER BY atms.atm_id DESC'
)->fetchAll();
```

## Клиенты

Вкладка «Клиенты» ведёт справочник клиентов и даёт отдельную форму «клиент + карта», создающую связанные записи в нескольких таблицах:

```php
$pdo->beginTransaction();
$stmt = $pdo->prepare('INSERT INTO customers (bank_id, full_name) VALUES (?, ?)');
$stmt->execute([$bankId, $fullName]);
$customerId = (int)$pdo->lastInsertId();

$stmt = $pdo->prepare('INSERT INTO accounts (customer_id, balance, currency, status) VALUES (?, ?, ?, ?)');
$stmt->execute([$customerId, $balance, $currency, 'active']);
```

## Карты

Вкладка «Карты» позволяет администратору вести карточный справочник с привязкой к счёту и банку-эмитенту:

```php
$stmt = $pdo->prepare(
    'INSERT INTO cards (account_id, issuing_bank_id, pan_last4, exp_date, status)
     VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([$accountId, $issuingBankId, $panLast4, $expDate, $status]);
```

## Вход

Вкладка «Вход» доступна неавторизованным пользователям. После успешной проверки пароля задаётся сессия и cookie с ролью:

```php
if ($user && password_verify($password, $user['password_hash'])) {
    login_user($user);
    setcookie('atm_last_role', $user['role'], time() + 300, '/');
    setcookie('atm_last_user', $user['username'], time() + 300, '/');
    redirect('index.php');
}
```

## Выход

Вкладка «Выход» завершает текущую сессию и перенаправляет на форму входа:

```php
$_SESSION = [];
session_regenerate_id(true);
set_flash('success', 'Вы вышли из системы.');
redirect('login.php');
```
