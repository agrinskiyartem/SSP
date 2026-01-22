<?php
require_once __DIR__ . '/../includes/init.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$cardId = (int)($_GET['card_id'] ?? 0);
if ($cardId <= 0) {
    echo json_encode(['error' => 'Некорректный идентификатор карты']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT cards.card_id, cards.pan_last4, banks.name AS issuing_bank,
            accounts.balance, accounts.currency, customers.full_name
     FROM cards
     JOIN banks ON banks.bank_id = cards.issuing_bank_id
     JOIN accounts ON accounts.account_id = cards.account_id
     JOIN customers ON customers.customer_id = accounts.customer_id
     WHERE cards.card_id = ?'
);
$stmt->execute([$cardId]);
$card = $stmt->fetch();

if (!$card) {
    echo json_encode(['error' => 'Карта не найдена']);
    exit;
}

echo json_encode([
    'card_id' => $card['card_id'],
    'pan_last4' => $card['pan_last4'],
    'issuing_bank' => $card['issuing_bank'],
    'balance' => $card['balance'],
    'currency' => $card['currency'],
    'full_name' => $card['full_name'],
]);
