<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$isAdmin = is_admin();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        set_flash('error', 'У вас нет прав на изменение справочника.');
        redirect('customers.php');
    }

    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'update') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $bankId = (int)($_POST['bank_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');

        if ($bankId <= 0 || $fullName === '') {
            $errors[] = 'Заполните банк и ФИО.';
        }

        if (!$errors) {
            if ($action === 'add') {
                $stmt = $pdo->prepare('INSERT INTO customers (bank_id, full_name) VALUES (?, ?)');
                $stmt->execute([$bankId, $fullName]);
                set_flash('success', 'Клиент добавлен.');
            }

            if ($action === 'update' && $customerId > 0) {
                $stmt = $pdo->prepare('UPDATE customers SET bank_id = ?, full_name = ? WHERE customer_id = ?');
                $stmt->execute([$bankId, $fullName, $customerId]);
                set_flash('success', 'Клиент обновлён.');
            }

            redirect('customers.php');
        }
    }

    if ($action === 'delete') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        if ($customerId > 0) {
            $stmt = $pdo->prepare('DELETE FROM customers WHERE customer_id = ?');
            $stmt->execute([$customerId]);
            set_flash('success', 'Клиент удалён.');
        }
        redirect('customers.php');
    }

    if ($action === 'create_with_card') {
        $bankId = (int)($_POST['bank_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $balance = (float)($_POST['balance'] ?? 0);
        $currency = trim($_POST['currency'] ?? 'RUB');
        $panLast4 = trim($_POST['pan_last4'] ?? '');
        $expDate = $_POST['exp_date'] ?? '';

        if ($bankId <= 0 || $fullName === '' || $balance < 0 || $panLast4 === '' || $expDate === '') {
            $errors[] = 'Заполните все поля формы клиента + карты.';
        }

        if (!preg_match('/^[0-9]{4}$/', $panLast4)) {
            $errors[] = 'Последние 4 цифры карты должны состоять из 4 цифр.';
        }

        if (!$errors) {
            try {
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
                set_flash('success', 'Клиент и карта успешно созданы.');
                redirect('customers.php');
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errors[] = 'Ошибка при создании клиента и карты.';
            }
        }
    }
}

$editCustomer = null;
if ($isAdmin && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE customer_id = ?');
    $stmt->execute([$editId]);
    $editCustomer = $stmt->fetch();
}

$banks = $pdo->query('SELECT bank_id, name FROM banks ORDER BY name')->fetchAll();
$customers = $pdo->query('SELECT customers.*, banks.name AS bank_name FROM customers JOIN banks ON banks.bank_id = customers.bank_id ORDER BY customers.customer_id DESC')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="card">
    <h1>Клиенты</h1>
    <?php if ($errors): ?>
        <div class="alert error"><?= h(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <form method="post" class="form grid" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $editCustomer ? 'update' : 'add' ?>">
            <input type="hidden" name="customer_id" value="<?= h($editCustomer['customer_id'] ?? 0) ?>">
            <label>
                Банк обслуживания
                <select name="bank_id" required>
                    <option value="">Выберите банк</option>
                    <?php foreach ($banks as $bank): ?>
                        <option value="<?= h($bank['bank_id']) ?>" <?= ($editCustomer && (int)$editCustomer['bank_id'] === (int)$bank['bank_id']) ? 'selected' : '' ?>>
                            <?= h($bank['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                ФИО
                <input type="text" name="full_name" required value="<?= h($editCustomer['full_name'] ?? '') ?>">
            </label>
            <button class="button" type="submit"><?= $editCustomer ? 'Сохранить' : 'Добавить' ?></button>
            <?php if ($editCustomer): ?>
                <a class="button ghost" href="customers.php">Отмена</a>
            <?php endif; ?>
        </form>

        <h2>Создать клиента + карту</h2>
        <p class="muted">Форма добавляет данные сразу в связанные таблицы customers, accounts, cards.</p>
        <form method="post" class="form grid" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_with_card">
            <label>
                Банк
                <select name="bank_id" required>
                    <option value="">Выберите банк</option>
                    <?php foreach ($banks as $bank): ?>
                        <option value="<?= h($bank['bank_id']) ?>"><?= h($bank['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                ФИО клиента
                <input type="text" name="full_name" required>
            </label>
            <label>
                Стартовый баланс
                <input type="number" name="balance" min="0" step="0.01" required>
            </label>
            <label>
                Валюта
                <input type="text" name="currency" value="RUB" maxlength="3" required>
            </label>
            <label>
                Последние 4 цифры карты
                <input type="text" name="pan_last4" pattern="\d{4}" maxlength="4" required>
            </label>
            <label>
                Срок действия
                <input type="date" name="exp_date" required>
            </label>
            <button class="button" type="submit">Создать</button>
        </form>
    <?php else: ?>
        <p class="muted">Справочник доступен только для просмотра.</p>
    <?php endif; ?>

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>ФИО</th>
                <th>Банк</th>
                <?php if ($isAdmin): ?><th>Действия</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($customers as $customer): ?>
            <tr>
                <td><?= h($customer['customer_id']) ?></td>
                <td><?= h($customer['full_name']) ?></td>
                <td><?= h($customer['bank_name']) ?></td>
                <?php if ($isAdmin): ?>
                    <td class="actions">
                        <a class="button small" href="customers.php?edit=<?= h($customer['customer_id']) ?>">Изменить</a>
                        <form method="post" onsubmit="return confirm('Удалить клиента?');">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="customer_id" value="<?= h($customer['customer_id']) ?>">
                            <button class="button danger small" type="submit">Удалить</button>
                        </form>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php
require_once __DIR__ . '/includes/footer.php';
?>
