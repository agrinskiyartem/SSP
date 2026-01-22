<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$commissionByBank = $pdo->query(
    'SELECT banks.name AS bank_name, SUM(withdrawals.commission_amount) AS total_commission
     FROM withdrawals
     JOIN atms ON atms.atm_id = withdrawals.atm_id
     JOIN banks ON banks.bank_id = atms.bank_id
     GROUP BY banks.bank_id
     ORDER BY total_commission DESC'
)->fetchAll();

$atmUsage = $pdo->query(
    'SELECT atms.location, banks.name AS bank_name, COUNT(withdrawals.withdrawal_id) AS ops_count
     FROM atms
     JOIN banks ON banks.bank_id = atms.bank_id
     LEFT JOIN withdrawals ON withdrawals.atm_id = atms.atm_id
     GROUP BY atms.atm_id
     ORDER BY ops_count DESC'
)->fetchAll();

$opsDynamics = $pdo->query(
    'SELECT DATE(created_at) AS op_date, COUNT(*) AS ops_count, SUM(total_debit) AS total_amount
     FROM withdrawals
     GROUP BY DATE(created_at)
     ORDER BY op_date DESC'
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<section class="card">
    <h1>Аналитика</h1>

    <h2>Сумма комиссий по банкам (банк банкомата)</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Банк</th>
                <th>Сумма комиссий</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($commissionByBank as $row): ?>
            <tr>
                <td><?= h($row['bank_name']) ?></td>
                <td><?= h(number_format($row['total_commission'] ?? 0, 2, '.', ' ')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Частота использования банкоматов</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Банкомат</th>
                <th>Банк</th>
                <th>Количество операций</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($atmUsage as $row): ?>
            <tr>
                <td><?= h($row['location']) ?></td>
                <td><?= h($row['bank_name']) ?></td>
                <td><?= h($row['ops_count']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Динамика операций по дням</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Количество операций</th>
                <th>Сумма списаний</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($opsDynamics as $row): ?>
            <tr>
                <td><?= h($row['op_date']) ?></td>
                <td><?= h($row['ops_count']) ?></td>
                <td><?= h(number_format($row['total_amount'] ?? 0, 2, '.', ' ')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php
require_once __DIR__ . '/includes/footer.php';
?>
