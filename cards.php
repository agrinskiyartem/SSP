<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$isAdmin = is_admin();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        set_flash('error', 'У вас нет прав на изменение справочника.');
        redirect('cards.php');
    }

    verify_csrf();
    $action = $_POST['action'] ?? '';
    $cardId = (int)($_POST['card_id'] ?? 0);
    $accountId = (int)($_POST['account_id'] ?? 0);
    $issuingBankId = (int)($_POST['issuing_bank_id'] ?? 0);
    $panLast4 = trim($_POST['pan_last4'] ?? '');
    $expDate = $_POST['exp_date'] ?? '';
    $status = $_POST['status'] ?? 'active';

    if ($action === 'add' || $action === 'update') {
        if ($accountId <= 0 || $issuingBankId <= 0 || $panLast4 === '' || $expDate === '') {
            $errors[] = 'Заполните все поля карты.';
        }
        if (!preg_match('/^[0-9]{4}$/', $panLast4)) {
            $errors[] = 'Последние 4 цифры карты должны быть числом.';
        }
    }

    if (!$errors) {
        if ($action === 'add') {
            $stmt = $pdo->prepare('INSERT INTO cards (account_id, issuing_bank_id, pan_last4, exp_date, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$accountId, $issuingBankId, $panLast4, $expDate, $status]);
            set_flash('success', 'Карта добавлена.');
        }

        if ($action === 'update' && $cardId > 0) {
            $stmt = $pdo->prepare('UPDATE cards SET account_id = ?, issuing_bank_id = ?, pan_last4 = ?, exp_date = ?, status = ? WHERE card_id = ?');
            $stmt->execute([$accountId, $issuingBankId, $panLast4, $expDate, $status, $cardId]);
            set_flash('success', 'Карта обновлена.');
        }

        if ($action === 'delete' && $cardId > 0) {
            $stmt = $pdo->prepare('DELETE FROM cards WHERE card_id = ?');
            $stmt->execute([$cardId]);
            set_flash('success', 'Карта удалена.');
        }

        redirect('cards.php');
    }
}

$editCard = null;
if ($isAdmin && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM cards WHERE card_id = ?');
    $stmt->execute([$editId]);
    $editCard = $stmt->fetch();
}

$accounts = $pdo->query('SELECT accounts.account_id, customers.full_name FROM accounts JOIN customers ON customers.customer_id = accounts.customer_id ORDER BY accounts.account_id DESC')->fetchAll();
$banks = $pdo->query('SELECT bank_id, name FROM banks ORDER BY name')->fetchAll();
$cards = $pdo->query('SELECT cards.*, banks.name AS bank_name, customers.full_name FROM cards JOIN banks ON banks.bank_id = cards.issuing_bank_id JOIN accounts ON accounts.account_id = cards.account_id JOIN customers ON customers.customer_id = accounts.customer_id ORDER BY cards.card_id DESC')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="card">
    <h1>Карты</h1>
    <?php if ($errors): ?>
        <div class="alert error"><?= h(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <form method="post" class="form grid" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $editCard ? 'update' : 'add' ?>">
            <input type="hidden" name="card_id" value="<?= h($editCard['card_id'] ?? 0) ?>">
            <label>
                Счёт клиента
                <select name="account_id" required>
                    <option value="">Выберите счёт</option>
                    <?php foreach ($accounts as $account): ?>
                        <option value="<?= h($account['account_id']) ?>" <?= ($editCard && (int)$editCard['account_id'] === (int)$account['account_id']) ? 'selected' : '' ?>>
                            <?= h($account['account_id']) ?> — <?= h($account['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Банк-эмитент
                <select name="issuing_bank_id" required>
                    <option value="">Выберите банк</option>
                    <?php foreach ($banks as $bank): ?>
                        <option value="<?= h($bank['bank_id']) ?>" <?= ($editCard && (int)$editCard['issuing_bank_id'] === (int)$bank['bank_id']) ? 'selected' : '' ?>>
                            <?= h($bank['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Последние 4 цифры
                <input type="text" name="pan_last4" maxlength="4" required value="<?= h($editCard['pan_last4'] ?? '') ?>">
            </label>
            <label>
                Срок действия
                <input type="date" name="exp_date" required value="<?= h($editCard['exp_date'] ?? '') ?>">
            </label>
            <label>
                Статус
                <select name="status">
                    <option value="active" <?= ($editCard['status'] ?? '') === 'active' ? 'selected' : '' ?>>active</option>
                    <option value="blocked" <?= ($editCard['status'] ?? '') === 'blocked' ? 'selected' : '' ?>>blocked</option>
                </select>
            </label>
            <button class="button" type="submit"><?= $editCard ? 'Сохранить' : 'Добавить' ?></button>
            <?php if ($editCard): ?>
                <a class="button ghost" href="cards.php">Отмена</a>
            <?php endif; ?>
        </form>
    <?php else: ?>
        <p class="muted">Справочник доступен только для просмотра.</p>
    <?php endif; ?>

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Клиент</th>
                <th>Банк-эмитент</th>
                <th>Последние 4 цифры</th>
                <th>Срок</th>
                <th>Статус</th>
                <?php if ($isAdmin): ?><th>Действия</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cards as $card): ?>
            <tr>
                <td><?= h($card['card_id']) ?></td>
                <td><?= h($card['full_name']) ?></td>
                <td><?= h($card['bank_name']) ?></td>
                <td><?= h($card['pan_last4']) ?></td>
                <td><?= h($card['exp_date']) ?></td>
                <td><?= h($card['status']) ?></td>
                <?php if ($isAdmin): ?>
                    <td class="actions">
                        <a class="button small" href="cards.php?edit=<?= h($card['card_id']) ?>">Изменить</a>
                        <form method="post" onsubmit="return confirm('Удалить карту?');">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="card_id" value="<?= h($card['card_id']) ?>">
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
