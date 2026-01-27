<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$errors = [];
$successData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $atmId = (int)($_POST['atm_id'] ?? 0);
    $cardId = (int)($_POST['card_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);

    if ($atmId <= 0 || $cardId <= 0 || $amount <= 0) {
        $errors[] = 'Заполните все поля и укажите сумму больше 0.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // DEMO: без блокировки — отсутствие SELECT ... FOR UPDATE может привести к гонкам и некорректным списаниям.
            $stmt = $pdo->prepare(
                'SELECT a.account_id, a.balance, c.issuing_bank_id, atm.bank_id AS atm_bank_id
                 FROM accounts a
                 JOIN cards c ON c.account_id = a.account_id
                 JOIN atms atm ON atm.atm_id = ?
                 WHERE c.card_id = ?'
            );
            $stmt->execute([$atmId, $cardId]);
            $row = $stmt->fetch();

            if (!$row) {
                throw new RuntimeException('Карта или банкомат не найдены.');
            }

            $commissionRate = ((int)$row['issuing_bank_id'] === (int)$row['atm_bank_id']) ? 0 : 0.012;
            $commission = round($amount * $commissionRate, 2);
            $total = $amount + $commission;

            if ($row['balance'] < $total) {
                $pdo->rollBack();
                $errors[] = 'Недостаточно средств на счёте.';
            } else {
                $stmt = $pdo->prepare('UPDATE accounts SET balance = balance - ? WHERE account_id = ?');
                $stmt->execute([$total, $row['account_id']]);

                $stmt = $pdo->prepare('INSERT INTO withdrawals (atm_id, card_id, account_id, amount, commission_amount, total_debit) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$atmId, $cardId, $row['account_id'], $amount, $commission, $total]);

                $pdo->commit();
                $successData = [
                    'commission' => $commission,
                    'total' => $total,
                    'message' => 'Операция выполнена (БЕЗ БЛОКИРОВКИ — DEMO).',
                ];
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Ошибка выполнения операции: ' . $e->getMessage();
        }
    }
}

$atms = $pdo->query('SELECT atms.atm_id, atms.location, banks.name AS bank_name FROM atms JOIN banks ON banks.bank_id = atms.bank_id WHERE atms.status = "active" ORDER BY atms.location')->fetchAll();
$cards = $pdo->query('SELECT cards.card_id, cards.pan_last4, customers.full_name FROM cards JOIN accounts ON accounts.account_id = cards.account_id JOIN customers ON customers.customer_id = accounts.customer_id WHERE cards.status = "active" ORDER BY customers.full_name')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="card">
    <h1>Снятие наличных</h1>
    <p class="muted">Комиссия 0% при совпадении банка-эмитента и банка банкомата, иначе 1.2%.</p>

    <?php if ($errors): ?>
        <div class="alert error"><?= h(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <?php if ($successData): ?>
        <div class="alert success">
            <?= h($successData['message']) ?>
            Комиссия: <?= h(number_format($successData['commission'], 2, '.', ' ')) ?>, всего списано: <?= h(number_format($successData['total'], 2, '.', ' ')) ?>.
        </div>
    <?php endif; ?>

    <form method="post" class="form grid" novalidate data-withdraw-form>
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label>
            Банкомат
            <select name="atm_id" required data-ajax-atm>
                <option value="">Выберите банкомат</option>
                <?php foreach ($atms as $atm): ?>
                    <option value="<?= h($atm['atm_id']) ?>"><?= h($atm['location']) ?> (<?= h($atm['bank_name']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Карта
            <select name="card_id" required data-ajax-card>
                <option value="">Выберите карту</option>
                <?php foreach ($cards as $card): ?>
                    <option value="<?= h($card['card_id']) ?>"><?= h($card['full_name']) ?> • **** <?= h($card['pan_last4']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Сумма
            <input type="number" name="amount" min="1" step="0.01" required>
        </label>
        <button class="button" type="submit" formaction="withdraw.php">Снять</button>
        <button class="button" type="submit">Снять (без блокировки)</button>
    </form>
    <p class="muted">Демо: кнопка без блокировки может приводить к некорректным списаниям при одновременных запросах.</p>

    <div class="info" id="card-info">
        <strong>Информация по карте:</strong>
        <p>Выберите карту, чтобы увидеть банк-эмитент и баланс счёта.</p>
    </div>
</section>
<?php
require_once __DIR__ . '/includes/footer.php';
?>
