-- POTA Activation Tracker Database Schema
CREATE DATABASE IF NOT EXISTS `pota_tracker` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pota_tracker`;

-- Table for user details and roles
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('registered', 'admin') DEFAULT 'registered',
  `is_verified` TINYINT(1) DEFAULT 0,
  `verification_token` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Table for bands managed by administrators
CREATE TABLE IF NOT EXISTS `bands` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Table for modes managed by administrators
CREATE TABLE IF NOT EXISTS `modes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Table for user-defined equipment profiles
CREATE TABLE IF NOT EXISTS `equipment_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `profile_name` VARCHAR(100) NOT NULL,
  `transceiver` VARCHAR(100) NULL,
  `antenna` VARCHAR(100) NULL,
  `power_source` VARCHAR(100) NULL,
  `power_watts` INT NULL,
  `additional_equipment` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table for storing activations data
CREATE TABLE IF NOT EXISTS `activations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `activation_date` DATE NOT NULL,
  `park_reference` VARCHAR(20) NOT NULL,
  `park_name` VARCHAR(150) NOT NULL,
  `qso_count` INT DEFAULT 0,
  `bands` VARCHAR(255) NULL, -- Comma-separated list of checked bands
  `modes` VARCHAR(255) NULL, -- Comma-separated list of checked modes
  `transceiver` VARCHAR(100) NULL,
  `antenna` VARCHAR(100) NULL,
  `power_source` VARCHAR(100) NULL,
  `power_watts` INT NULL,
  `additional_equipment` TEXT NULL,
  `latitude` DECIMAL(9, 6) NULL,
  `longitude` DECIMAL(9, 6) NULL,
  `parking_coords` VARCHAR(50) NULL,
  `parking_conditions` VARCHAR(100) NULL,
  `cell_coverage` VARCHAR(100) NULL,
  `localization_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table for activation images (up to 5 per activation)
CREATE TABLE IF NOT EXISTS `activation_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `activation_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`activation_id`) REFERENCES `activations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table for admin uploaded slider images
CREATE TABLE IF NOT EXISTS `slider_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `image_path` VARCHAR(255) NOT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed default administrator account
-- Password is 'admin' (Bcrypt hash)
INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `is_verified`)
VALUES ('admin', 'admin@pota.app', '$2y$10$8K1w.tM72l0k5xM8jE4U1uF2R4tq8H7fG4eG6Z3I.h7V8M1Kj7gG.', 'admin', 1)
ON DUPLICATE KEY UPDATE `role`='admin';

-- Seed default bands
INSERT INTO `bands` (`name`) VALUES 
('160m'), ('80m'), ('40m'), ('30m'), ('20m'), ('17m'), 
('15m'), ('12m'), ('10m'), ('6m'), ('2m'), ('70cm')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Seed default modes
INSERT INTO `modes` (`name`) VALUES 
('SSB'), ('CW'), ('FT8'), ('FT4'), ('FM'), ('AM'), ('RTTY'), ('PSK')
ON DUPLICATE KEY UPDATE `name`=`name`;
