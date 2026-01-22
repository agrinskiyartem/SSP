<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$isAdmin = is_admin();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        set_flash('error', 'У вас нет прав на изменение справочника.');
        redirect('atms.php');
    }

    verify_csrf();
    $action = $_POST['action'] ?? '';
    $atmId = (int)($_POST['atm_id'] ?? 0);
    $bankId = (int)($_POST['bank_id'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if ($action === 'add' || $action === 'update') {
        if ($bankId <= 0 || $location === '') {
            $errors[] = 'Укажите банк и адрес.';
        }
    }

    if (!$errors) {
        if ($action === 'add') {
            $stmt = $pdo->prepare('INSERT INTO atms (bank_id, location, status) VALUES (?, ?, ?)');
            $stmt->execute([$bankId, $location, $status]);
            set_flash('success', 'Банкомат добавлен.');
        }

        if ($action === 'update' && $atmId > 0) {
            $stmt = $pdo->prepare('UPDATE atms SET bank_id = ?, location = ?, status = ? WHERE atm_id = ?');
            $stmt->execute([$bankId, $location, $status, $atmId]);
            set_flash('success', 'Банкомат обновлён.');
        }

        if ($action === 'delete' && $atmId > 0) {
            $stmt = $pdo->prepare('DELETE FROM atms WHERE atm_id = ?');
            $stmt->execute([$atmId]);
            set_flash('success', 'Банкомат удалён.');
        }

        redirect('atms.php');
    }
}

$editAtm = null;
if ($isAdmin && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM atms WHERE atm_id = ?');
    $stmt->execute([$editId]);
    $editAtm = $stmt->fetch();
}

$banks = $pdo->query('SELECT bank_id, name FROM banks ORDER BY name')->fetchAll();
$atms = $pdo->query('SELECT atms.*, banks.name AS bank_name FROM atms JOIN banks ON banks.bank_id = atms.bank_id ORDER BY atms.atm_id DESC')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="card">
    <h1>Банкоматы</h1>
    <?php if ($errors): ?>
        <div class="alert error"><?= h(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <form method="post" class="form grid" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $editAtm ? 'update' : 'add' ?>">
            <input type="hidden" name="atm_id" value="<?= h($editAtm['atm_id'] ?? 0) ?>">
            <label>
                Банк
                <select name="bank_id" required>
                    <option value="">Выберите банк</option>
                    <?php foreach ($banks as $bank): ?>
                        <option value="<?= h($bank['bank_id']) ?>" <?= ($editAtm && (int)$editAtm['bank_id'] === (int)$bank['bank_id']) ? 'selected' : '' ?>>
                            <?= h($bank['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Адрес
                <input type="text" name="location" required value="<?= h($editAtm['location'] ?? '') ?>">
            </label>
            <label>
                Статус
                <select name="status">
                    <option value="active" <?= ($editAtm['status'] ?? '') === 'active' ? 'selected' : '' ?>>active</option>
                    <option value="inactive" <?= ($editAtm['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>inactive</option>
                </select>
            </label>
            <button class="button" type="submit"><?= $editAtm ? 'Сохранить' : 'Добавить' ?></button>
            <?php if ($editAtm): ?>
                <a class="button ghost" href="atms.php">Отмена</a>
            <?php endif; ?>
        </form>
    <?php else: ?>
        <p class="muted">Справочник доступен только для просмотра.</p>
    <?php endif; ?>

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Банк</th>
                <th>Адрес</th>
                <th>Статус</th>
                <?php if ($isAdmin): ?><th>Действия</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($atms as $atm): ?>
            <tr>
                <td><?= h($atm['atm_id']) ?></td>
                <td><?= h($atm['bank_name']) ?></td>
                <td><?= h($atm['location']) ?></td>
                <td><?= h($atm['status']) ?></td>
                <?php if ($isAdmin): ?>
                    <td class="actions">
                        <a class="button small" href="atms.php?edit=<?= h($atm['atm_id']) ?>">Изменить</a>
                        <form method="post" onsubmit="return confirm('Удалить банкомат?');">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="atm_id" value="<?= h($atm['atm_id']) ?>">
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
