# Карта требований → фрагменты кода и пояснения

> В каждом пункте приведён фрагмент из проекта и краткое описание, зачем он используется.

## 1. Динамические страницы PHP + HTML, CSS, JavaScript
```php
<link rel="stylesheet" href="assets/style.css">
...
<script src="assets/app.js"></script>
```
Страницы рендерятся PHP (например, `index.php`, `withdraw.php`) и подключают единый CSS и JS. CSS отвечает за стили интерфейса, а JS — за динамику (AJAX и клиентскую валидацию).

## 2. Несколько таблиц в MySQL (БД)
```sql
CREATE TABLE banks (
  bank_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE withdrawals (
  withdrawal_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  atm_id INT NOT NULL,
  card_id INT NOT NULL,
  account_id INT NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  commission_amount DECIMAL(14,2) NOT NULL,
  total_debit DECIMAL(14,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_withdrawals_atm
    FOREIGN KEY (atm_id) REFERENCES atms(atm_id)
) ENGINE=InnoDB;
```
В `schema.sql` описаны связанные таблицы (банки, клиенты, карты, операции), что обеспечивает хранение данных и связи между сущностями.

## 3. Home-страница с меню и авторизацией
```php
<nav class="nav">
    <a href="index.php">Главная</a>
    <?php if ($user): ?>
        <a href="withdraw.php">Снятие</a>
        <a href="operations.php">Операции</a>
        <a href="analytics.php">Аналитика</a>
        <?php if (is_admin()): ?>
            <a href="banks.php">Банки</a>
            <a href="customers.php">Клиенты</a>
        <?php endif; ?>
        <a href="logout.php">Выход</a>
    <?php else: ?>
        <a href="login.php">Вход</a>
    <?php endif; ?>
</nav>
```
Главная страница и общий хедер показывают меню и пункты, доступные после авторизации. Если пользователь не вошёл — отображается только ссылка на вход.

## 4. Два типа пользователей и разные права
```php
function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'admin';
}
```
Роль хранится в сессии; `admin` получает права на CRUD-операции, остальные (например, `operator`) — только просмотр и операции снятия.

## 5. Ввод данных минимум в 2 связанные таблицы
```php
$pdo->beginTransaction();

$stmt = $pdo->prepare('INSERT INTO customers (bank_id, full_name) VALUES (?, ?)');
$stmt->execute([$bankId, $fullName]);
$customerId = (int)$pdo->lastInsertId();

$stmt = $pdo->prepare('INSERT INTO accounts (customer_id, balance, currency, status) VALUES (?, ?, ?, ?)');
$stmt->execute([$customerId, $balance, $currency, 'active']);
$accountId = (int)$pdo->lastInsertId();

$stmt = $pdo->prepare('INSERT INTO cards (account_id, issuing_bank_id, pan_last4, exp_date, status) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$accountId, $bankId, $panLast4, $expDate, 'active']);

$pdo->commit();
```
Форма «Создать клиента + карту» одновременно вставляет записи в связанные таблицы customers → accounts → cards в одной транзакции.

## 6. Вывод данных по разным запросам (JOIN по связанным таблицам)
```php
$sql = 'SELECT w.withdrawal_id, w.amount, w.commission_amount, w.total_debit, w.created_at,
               atms.location AS atm_location, atm_bank.name AS atm_bank,
               cards.pan_last4, card_bank.name AS card_bank,
               customers.full_name
        FROM withdrawals w
        JOIN atms ON atms.atm_id = w.atm_id
        JOIN banks atm_bank ON atm_bank.bank_id = atms.bank_id
        JOIN cards ON cards.card_id = w.card_id
        JOIN banks card_bank ON card_bank.bank_id = cards.issuing_bank_id
        JOIN accounts ON accounts.account_id = w.account_id
        JOIN customers ON customers.customer_id = accounts.customer_id
        WHERE 1=1';
```
Отчёт по операциям строится по JOIN-запросу, который объединяет операции, карты, банкоматы и клиентов. Отдельные запросы аналитики показывают агрегаты по банкам/банкоматам.

## 7. Асинхронный запрос (AJAX)
```js
const response = await fetch(`ajax/card_info.php?card_id=${encodeURIComponent(cardId)}`);
const data = await response.json();
```
При выборе карты выполняется AJAX-запрос, который возвращает информацию о банке-эмитенте и балансе, не перезагружая страницу.

## 8. SQL-скрипт создания и начального заполнения БД
```sql
CREATE DATABASE IF NOT EXISTS atm_coursework
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
...
INSERT INTO banks (name) VALUES
  ('Банк Северный'),
  ('Банк Восточный'),
  ('Банк Центральный');
```
`schema.sql` содержит создание базы, таблиц и стартовые данные, позволяющие сразу проверить все функции.

## 9. Триггер (хранимая процедура/триггер)
```sql
CREATE TRIGGER trg_withdrawals_calc_commission
BEFORE INSERT ON withdrawals
FOR EACH ROW
BEGIN
  ...
  SET NEW.commission_amount = ROUND(NEW.amount * commission_rate, 2);
  SET NEW.total_debit = NEW.amount + NEW.commission_amount;
END
```
Триггер автоматически считает комиссию и итоговое списание перед вставкой операции снятия.

## 10. Разбиение скриптов на модули PHP
```php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
```
Общий код вынесен в модули (`includes/init.php`, `auth.php`, `functions.php`, `header.php`, `footer.php`) и подключается через `require_once`.

## 11. Сессия 2 минуты + предупреждение
```php
if ($inactiveSeconds > 120) {
    $_SESSION = [];
    session_regenerate_id(true);
    set_flash('warning', 'Сессия ... истекла из-за неактивности.');
    return;
}

if ($inactiveSeconds > 90) {
    $_SESSION['session_warning'] = true;
}
```
При неактивности более 2 минут сессия завершается, за 30 секунд до окончания выводится предупреждение, дальше пользователь работает как неавторизованный.

## 12. Cookie между сеансами (несколько минут)
```php
setcookie('atm_last_role', $user['role'], time() + 300, '/');
setcookie('atm_last_user', $user['username'], time() + 300, '/');
```
Cookie сохраняют последнюю роль/логин на 5 минут и используются при повторном заходе, чтобы подсказать логин.

## 13. Использование методов GET и POST
```php
<form method="get" class="form grid" novalidate>
...
<form method="post" class="form" novalidate>
```
GET используется для фильтров операций, POST — для логина и CRUD/операций (создание клиентов, снятие средств).

## 14. Обработка ошибок пользователя
```php
if ($atmId <= 0 || $cardId <= 0 || $amount <= 0) {
    $errors[] = 'Заполните все поля и укажите сумму больше 0.';
}
```
При некорректном вводе формируются сообщения об ошибках, которые показываются пользователю.

## 15. Использование стандартного интерфейса (цвета, элементы управления)
```css
body {
    margin: 0;
    font-family: "Segoe UI", sans-serif;
    background: var(--bg);
    color: var(--text);
}
```
Интерфейс выдержан в едином стиле с типичными контролами, нейтральной цветовой гаммой и системным шрифтом Segoe UI.

## 16. «Украшательства» HTML
```css
.card {
    background: var(--card);
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
}
```
Карточный дизайн, тени и аккуратные отступы улучшают визуальное восприятие страниц.

## 17. Демонстрация конкурентного доступа (блокировка чтения/записи)
```php
$stmt = $pdo->prepare(
    'SELECT a.account_id, a.balance, c.issuing_bank_id, atm.bank_id AS atm_bank_id
     FROM accounts a
     JOIN cards c ON c.account_id = a.account_id
     JOIN atms atm ON atm.atm_id = ?
     WHERE c.card_id = ?
     FOR UPDATE'
);
```
В `withdraw.php` используется `SELECT ... FOR UPDATE`, что блокирует строку счёта. Для сравнения есть `withdraw_unlocked.php`, где блокировка отсутствует — это демонстрирует разницу поведения при параллельных запросах.

## 18. Дублирующая проверка на сервере (защита от модификации клиентского кода)
```js
if (amountInput && Number(amountInput.value) <= 0) {
    event.preventDefault();
    alert('Сумма должна быть больше 0.');
}
```
```php
if ($atmId <= 0 || $cardId <= 0 || $amount <= 0) {
    $errors[] = 'Заполните все поля и укажите сумму больше 0.';
}
```
Клиентская проверка суммы есть в JS, но на сервере выполняется повторная валидация, так что подмена JS не обходит ограничения.

## 19. План (сценарий) демонстрации функций
```
## 1. Вход и роли
1. Требование: вход разными ролями — войти под admin...
2. Требование: вход разными ролями — выйти и войти под operator...
3. Требование: ограничение прав — под operator попытаться открыть banks.php...
```
Подробный сценарий демонстрации всех функций и требований приведён в `report.md` (раздел «Сценарий демонстрации»).
