-- UniSphere: Campus Hyperlocal Delivery & Reward Ecosystem
-- MySQL Database Schema

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:30";

-- CREATE DATABASE IF NOT EXISTS UniSphere;
-- USE UniSphere;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('MEMBER', 'STORE', 'RIDER') NOT NULL DEFAULT 'MEMBER',
  `points` DECIMAL(10,2) DEFAULT '0.00',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. UniStores Table
CREATE TABLE IF NOT EXISTS `stores` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `store_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `category` VARCHAR(50),
  `is_active` TINYINT(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Products Table
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `store_id` INT(11) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `price` DECIMAL(10,2) NOT NULL,
  `image_url` VARCHAR(255) DEFAULT 'assets/images/default_product.jpg',
  `category` VARCHAR(50),
  `is_available` TINYINT(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `member_id` INT(11) NOT NULL,
  `store_id` INT(11) NOT NULL,
  `rider_id` INT(11) DEFAULT NULL,
  `status` ENUM('AWAITING_PAYMENT', 'PENDING_VERIFICATION', 'CONFIRMED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED', 'CANCELLED') DEFAULT 'AWAITING_PAYMENT',
  `total_price` DECIMAL(10,2) NOT NULL,
  `otp` VARCHAR(6) DEFAULT NULL,
  `checkpoint` VARCHAR(100) DEFAULT 'Store',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`member_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`),
  FOREIGN KEY (`rider_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Order Items (to allow multiple products per order if needed, but for MVP keep it simple)
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `quantity` INT(11) DEFAULT '1',
  `price` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Payments Table
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `order_id` INT(11) NOT NULL,
  `transaction_id` VARCHAR(100) NOT NULL,
  `screenshot_url` VARCHAR(255) NOT NULL,
  `status` ENUM('PENDING', 'VERIFIED', 'REJECTED') DEFAULT 'PENDING',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Rider Locations (Live Tracking)
CREATE TABLE IF NOT EXISTS `rider_locations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `rider_id` INT(11) NOT NULL,
  `order_id` INT(11) NOT NULL,
  `lat` DECIMAL(10,8) NOT NULL,
  `lng` DECIMAL(11,8) NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rider_order` (`rider_id`, `order_id`),
  FOREIGN KEY (`rider_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Redemptions Table
CREATE TABLE IF NOT EXISTS `redemptions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `reward_name` VARCHAR(100) NOT NULL,
  `points_cost` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;
