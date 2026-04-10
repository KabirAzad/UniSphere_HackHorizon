<?php
require_once 'includes/config.php';

$pdo->exec("INSERT INTO orders (member_id, store_id, total_price, status) VALUES (1, 1, 100, 'AWAITING_PAYMENT')");
$lastId = $pdo->lastInsertId();

$stmt = $pdo->query("SELECT created_at, NOW(), DATE_SUB(NOW(), INTERVAL 1 MINUTE) FROM orders WHERE id = $lastId");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

$delete_stmt = $pdo->prepare("DELETE FROM orders WHERE status = 'AWAITING_PAYMENT' AND created_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE) AND member_id = ?");
$delete_stmt->execute([1]);

$check = $pdo->query("SELECT id FROM orders WHERE id = $lastId")->fetch();
echo "Order still exists? " . ($check ? "Yes" : "No\nDeleted instantly!");
