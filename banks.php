<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$errors = [];
$isAdmin = is_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        set_flash('error', 'У вас нет прав на изменение справочника.');
        redirect('banks.php');
    }

    verify_csrf();

    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $bankId = (int)($_POST['bank_id'] ?? 0);

    if ($action === 'add' || $action === 'update') {
        if ($name === '') {
            $errors[] = 'Название банка обязательно.';
        }
    }

    if (!$errors) {
        if ($action === 'add') {
            $stmt = $pdo->prepare('INSERT INTO banks (name) VALUES (?)');
            $stmt->execute([$name]);
            set_flash('success', 'Банк добавлен.');
        }

        if ($action === 'update' && $bankId > 0) {
            $stmt = $pdo->prepare('UPDATE banks SET name = ? WHERE bank_id = ?');
            $stmt->execute([$name, $bankId]);
            set_flash('success', 'Банк обновлён.');
        }

        if ($action === 'delete' && $bankId > 0) {
            $stmt = $pdo->prepare('DELETE FROM banks WHERE bank_id = ?');
            $stmt->execute([$bankId]);
            set_flash('success', 'Банк удалён.');
        }

        redirect('banks.php');
    }
}

$editBank = null;
if ($isAdmin && isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM banks WHERE bank_id = ?');
    $stmt->execute([$editId]);
    $editBank = $stmt->fetch();
}

$banks = $pdo->query('SELECT * FROM banks ORDER BY name')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="card">
    <h1>Банки</h1>
    <?php if ($errors): ?>
        <div class="alert error"><?= h(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <form method="post" class="form inline" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $editBank ? 'update' : 'add' ?>">
            <input type="hidden" name="bank_id" value="<?= h($editBank['bank_id'] ?? 0) ?>">
            <label>
                Название
                <input type="text" name="name" required value="<?= h($editBank['name'] ?? '') ?>">
            </label>
            <button class="button" type="submit"><?= $editBank ? 'Сохранить' : 'Добавить' ?></button>
            <?php if ($editBank): ?>
                <a class="button ghost" href="banks.php">Отмена</a>
            <?php endif; ?>
        </form>
    <?php else: ?>
        <p class="muted">Справочник доступен только для просмотра.</p>
    <?php endif; ?>

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Название</th>
                <?php if ($isAdmin): ?><th>Действия</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($banks as $bank): ?>
            <tr>
                <td><?= h($bank['bank_id']) ?></td>
                <td><?= h($bank['name']) ?></td>
                <?php if ($isAdmin): ?>
                    <td class="actions">
                        <a class="button small" href="banks.php?edit=<?= h($bank['bank_id']) ?>">Изменить</a>
                        <form method="post" onsubmit="return confirm('Удалить банк?');">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="bank_id" value="<?= h($bank['bank_id']) ?>">
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
