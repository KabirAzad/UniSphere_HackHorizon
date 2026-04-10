<?php
require_once 'includes/config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `store_reviews` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `store_id` INT(11) NOT NULL,
            `user_id` INT(11) NOT NULL,
            `rating` INT(1) NOT NULL,
            `review` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_store_user_review` (`store_id`, `user_id`),
            FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    echo "Reviews table created successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
