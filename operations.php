<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$filters = [
    'bank_id' => '',
    'atm_id' => '',
    'card_id' => '',
    'date_from' => '',
    'date_to' => '',
];

if ($_GET) {
    foreach ($filters as $key => $_) {
        $filters[$key] = trim($_GET[$key] ?? '');
    }
    setcookie('ops_filters', json_encode($filters, JSON_UNESCAPED_UNICODE), time() + 300, '/');
} elseif (!empty($_COOKIE['ops_filters'])) {
    $cookieFilters = json_decode($_COOKIE['ops_filters'], true);
    if (is_array($cookieFilters)) {
        $filters = array_merge($filters, array_intersect_key($cookieFilters, $filters));
    }
}

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
$params = [];

if ($filters['bank_id'] !== '') {
    $sql .= ' AND atm_bank.bank_id = ?';
    $params[] = (int)$filters['bank_id'];
}
if ($filters['atm_id'] !== '') {
    $sql .= ' AND atms.atm_id = ?';
    $params[] = (int)$filters['atm_id'];
}
if ($filters['card_id'] !== '') {
    $sql .= ' AND cards.card_id = ?';
    $params[] = (int)$filters['card_id'];
}
if ($filters['date_from'] !== '') {
    $sql .= ' AND w.created_at >= ?';
    $params[] = $filters['date_from'] . ' 00:00:00';
}
if ($filters['date_to'] !== '') {
    $sql .= ' AND w.created_at <= ?';
    $params[] = $filters['date_to'] . ' 23:59:59';
}

$sql .= ' ORDER BY w.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$operations = $stmt->fetchAll();

$banks = $pdo->query('SELECT bank_id, name FROM banks ORDER BY name')->fetchAll();
$atms = $pdo->query('SELECT atm_id, location FROM atms ORDER BY location')->fetchAll();
$cards = $pdo->query('SELECT card_id, pan_last4 FROM cards ORDER BY card_id')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="card">
    <h1>Операции</h1>
    <form method="get" class="form grid" novalidate>
        <label>
            Банк банкомата
            <select name="bank_id">
                <option value="">Все</option>
                <?php foreach ($banks as $bank): ?>
                    <option value="<?= h($bank['bank_id']) ?>" <?= ($filters['bank_id'] !== '' && (int)$filters['bank_id'] === (int)$bank['bank_id']) ? 'selected' : '' ?>>
                        <?= h($bank['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Банкомат
            <select name="atm_id">
                <option value="">Все</option>
                <?php foreach ($atms as $atm): ?>
                    <option value="<?= h($atm['atm_id']) ?>" <?= ($filters['atm_id'] !== '' && (int)$filters['atm_id'] === (int)$atm['atm_id']) ? 'selected' : '' ?>>
                        <?= h($atm['location']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Карта
            <select name="card_id">
                <option value="">Все</option>
                <?php foreach ($cards as $card): ?>
                    <option value="<?= h($card['card_id']) ?>" <?= ($filters['card_id'] !== '' && (int)$filters['card_id'] === (int)$card['card_id']) ? 'selected' : '' ?>>
                        **** <?= h($card['pan_last4']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Дата с
            <input type="date" name="date_from" value="<?= h($filters['date_from']) ?>">
        </label>
        <label>
            Дата по
            <input type="date" name="date_to" value="<?= h($filters['date_to']) ?>">
        </label>
        <button class="button" type="submit">Применить</button>
    </form>
    <p class="muted">Фильтры сохраняются в cookie на 5 минут.</p>

    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Клиент</th>
                <th>Карта</th>
                <th>Банк карты</th>
                <th>Банкомат</th>
                <th>Банк банкомата</th>
                <th>Сумма</th>
                <th>Комиссия</th>
                <th>Всего списано</th>
                <th>Дата</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($operations as $op): ?>
            <tr>
                <td><?= h($op['withdrawal_id']) ?></td>
                <td><?= h($op['full_name']) ?></td>
                <td>**** <?= h($op['pan_last4']) ?></td>
                <td><?= h($op['card_bank']) ?></td>
                <td><?= h($op['atm_location']) ?></td>
                <td><?= h($op['atm_bank']) ?></td>
                <td><?= h(number_format($op['amount'], 2, '.', ' ')) ?></td>
                <td><?= h(number_format($op['commission_amount'], 2, '.', ' ')) ?></td>
                <td><?= h(number_format($op['total_debit'], 2, '.', ' ')) ?></td>
                <td><?= h($op['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php
require_once __DIR__ . '/includes/footer.php';
?>
