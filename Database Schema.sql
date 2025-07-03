--
-- Mass Mailer Database Schema
--
-- This SQL script defines the necessary tables for the Mass Mailer plugin's
-- core functionality, specifically for lists and subscribers.
--
-- @package Mass_Mailer
-- @subpackage Database
--

-- Table for Mailing Lists
CREATE TABLE IF NOT EXISTS `mm_lists` (
    `list_id` INT AUTO_INCREMENT PRIMARY KEY,
    `list_name` VARCHAR(255) NOT NULL UNIQUE,
    `list_description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for Subscribers
CREATE TABLE IF NOT EXISTS `mm_subscribers` (
    `subscriber_id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `first_name` VARCHAR(100),
    `last_name` VARCHAR(100),
    `status` ENUM('subscribed', 'unsubscribed', 'pending', 'bounced') DEFAULT 'pending',
    `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Linking Table for Many-to-Many relationship between Lists and Subscribers
-- A subscriber can be in multiple lists, and a list can have multiple subscribers.
CREATE TABLE IF NOT EXISTS `mm_list_subscriber_rel` (
    `list_id` INT NOT NULL,
    `subscriber_id` INT NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active', -- Status specific to this list relationship
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`list_id`, `subscriber_id`), -- Composite primary key
    FOREIGN KEY (`list_id`) REFERENCES `mm_lists`(`list_id`) ON DELETE CASCADE,
    FOREIGN KEY (`subscriber_id`) REFERENCES `mm_subscribers`(`subscriber_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

