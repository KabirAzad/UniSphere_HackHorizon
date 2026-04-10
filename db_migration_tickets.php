<?php
require_once 'includes/config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `tickets` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `ticket_number` VARCHAR(20) NOT NULL UNIQUE,
            `user_id` INT(11) NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `status` ENUM('OPEN', 'CLOSED') DEFAULT 'OPEN',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `ticket_messages` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `ticket_id` INT(11) NOT NULL,
            `sender_type` ENUM('USER', 'ADMIN') NOT NULL,
            `message` TEXT NOT NULL,
            `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo "Tables created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
