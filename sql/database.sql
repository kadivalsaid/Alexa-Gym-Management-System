-- =========================================================
-- Alexa Gym Management System - Fresh Clean Database
-- Database Name: gym_management
-- Description: Clean database with Plans, Trainers, and Admin Account only.
--              No members, no test attendance, no test payments.
--
-- Default Credentials:
--   Admin Email:    admin@gym.com
--   Admin Password: Admin786
-- =========================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- CREATE DATABASE IF NOT EXISTS `gym_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `gym_management`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Drop existing tables if re-importing
-- --------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `contact_messages`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `feedback`;
DROP TABLE IF EXISTS `tickets`;
DROP TABLE IF EXISTS `workout_assignments`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `memberships`;
DROP TABLE IF EXISTS `members`;
DROP TABLE IF EXISTS `trainers`;
DROP TABLE IF EXISTS `plans`;
DROP TABLE IF EXISTS `users`;

-- --------------------------------------------------------
-- 1. Table: users
-- Only Admin account created. Zero members.
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  `google_id` VARCHAR(100) DEFAULT NULL,
  `profile_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'banned') NOT NULL DEFAULT 'active',
  `remember_token` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin seed account (Plain text password: Admin786)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`) VALUES
(1, 'Admin Officer', 'admin@gym.com', 'Admin786', 'admin', 'active', NOW());

-- --------------------------------------------------------
-- 2. Table: plans (Gym Membership Plans for Website & Members)
-- --------------------------------------------------------
CREATE TABLE `plans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `duration_months` INT UNSIGNED NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `description` TEXT DEFAULT NULL,
  `features` TEXT DEFAULT NULL,
  `is_popular` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `plans` (`id`, `name`, `duration_months`, `price`, `description`, `features`, `is_popular`, `created_at`) VALUES
(1, 'Basic Membership', 1, 999.00, 'Ideal for beginners seeking general gym floor and cardio equipment access.', 'Full Gym Floor Access\nStandard Cardio Equipment\nLocker Room & Shower Access\nFree Fitness Assessment', 0, NOW()),
(2, 'Standard Pro', 3, 2499.00, 'Our most popular quarterly plan for consistent fitness gains and coach guidance.', 'All Basic Features Included\nCardio & Strength Training Zone\n1 Personal Trainer Consultation\nCustomized Nutrition Guidelines\nFree Steam Bath (2x/month)', 1, NOW()),
(3, 'Premium VIP', 12, 7999.00, 'Ultimate all-inclusive 365-day package with dedicated personal coach guidance.', 'All Gym Facilities & Group Classes\nDedicated Personal Coach Support\nPersonalized Workout & Diet Chart\nUnlimited Sauna & Steam Bath\nComplimentary Gym Kit & Shaker Bottle\nPriority Support & Guest Passes', 0, NOW()),
(4, 'Half-Yearly Elite', 6, 4999.00, 'Six months of intense conditioning with regular progress tracking.', 'Full gym & cross-fit area access\nBody composition analysis monthly\nGroup aerobic & HIIT classes', 0, NOW());

-- --------------------------------------------------------
-- 3. Table: trainers (Coaches for Gym Website & Member Guidance)
-- --------------------------------------------------------
CREATE TABLE `trainers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `specialization` VARCHAR(100) DEFAULT NULL,
  `experience` VARCHAR(50) DEFAULT '3+ Years Experience',
  `bio` TEXT DEFAULT NULL,
  `photo` VARCHAR(255) DEFAULT NULL,
  `salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `joined_on` DATE DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `trainers` (`id`, `full_name`, `email`, `phone`, `specialization`, `experience`, `bio`, `salary`, `joined_on`, `created_at`) VALUES
(1, 'Vikram Sharma', 'vikram.trainer@gym.com', '9876543210', 'Bodybuilding & Strength', '6+ Years Experience', 'Certified IFBB Pro trainer specialized in hypertrophy, progressive overload, and competition prep.', 45000.00, '2022-01-15', NOW()),
(2, 'Priya Patel', 'priya.trainer@gym.com', '9823456781', 'Yoga & Functional Fitness', '5+ Years Experience', 'Certified yoga instructor and mobility specialist focusing on core strength, agility, and injury prevention.', 38000.00, '2022-04-10', NOW()),
(3, 'Rohit Verma', 'rohit.trainer@gym.com', '9811223344', 'Cardio & Crossfit', '4+ Years Experience', 'CrossFit Level-2 certified coach driven to improve cardiovascular endurance and athletic agility.', 35000.00, '2023-08-01', NOW()),
(4, 'Neha Roy', 'neha.trainer@gym.com', '9844556677', 'Diet & Weight Loss', '5+ Years Experience', 'Clinical nutritionist and weight-loss mentor helping members achieve sustainable transformations.', 40000.00, '2023-11-20', NOW());

-- --------------------------------------------------------
-- 4. Table: members (Clean - 0 Rows)
-- --------------------------------------------------------
CREATE TABLE `members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `gender` ENUM('male', 'female', 'other') NOT NULL DEFAULT 'male',
  `dob` DATE DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `plan_id` INT UNSIGNED DEFAULT NULL,
  `trainer_id` INT UNSIGNED DEFAULT NULL,
  `join_date` DATE NOT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_members_user` (`user_id`),
  KEY `fk_members_plan` (`plan_id`),
  KEY `fk_members_trainer` (`trainer_id`),
  CONSTRAINT `fk_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_members_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_members_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Table: memberships (Clean - 0 Rows)
-- --------------------------------------------------------
CREATE TABLE `memberships` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `status` ENUM('active', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_memberships_member` (`member_id`),
  KEY `fk_memberships_plan` (`plan_id`),
  CONSTRAINT `fk_memberships_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_memberships_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Table: payments (Clean - 0 Rows)
-- --------------------------------------------------------
CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `method` VARCHAR(50) NOT NULL DEFAULT 'upi',
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `paid_on` DATE NOT NULL,
  `status` ENUM('completed', 'pending', 'failed') NOT NULL DEFAULT 'completed',
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_payments_member` (`member_id`),
  CONSTRAINT `fk_payments_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Table: attendance (Clean - 0 Rows)
-- --------------------------------------------------------
CREATE TABLE `attendance` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` INT UNSIGNED NOT NULL,
  `attend_date` DATE NOT NULL,
  `check_in` TIME NOT NULL,
  `check_out` TIME DEFAULT NULL,
  `status` ENUM('present', 'absent', 'late') NOT NULL DEFAULT 'present',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_member_date` (`member_id`, `attend_date`),
  KEY `fk_attendance_member` (`member_id`),
  CONSTRAINT `fk_attendance_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
CREATE TABLE `workout_assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` INT UNSIGNED DEFAULT NULL,
  `category` ENUM('Chest', 'Back', 'Shoulders', 'Arms', 'Legs', 'Cardio') NOT NULL DEFAULT 'Chest',
  `exercise_name` VARCHAR(150) NOT NULL,
  `sets` INT UNSIGNED NOT NULL DEFAULT 3,
  `reps` VARCHAR(50) NOT NULL DEFAULT '10-12',
  `duration` VARCHAR(50) DEFAULT '15 mins',
  `trainer_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_workout_member` (`member_id`),
  CONSTRAINT `fk_workout_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standard Gym Workout Exercises (Assigned by Trainers for Members)
INSERT INTO `workout_assignments` (`id`, `member_id`, `category`, `exercise_name`, `sets`, `reps`, `duration`, `trainer_notes`, `created_at`) VALUES
(1, NULL, 'Chest', 'Barbell Incline Bench Press', 4, '8-10', '15 mins', 'Keep elbows at a 45-degree angle; control eccentric phase.', NOW()),
(2, NULL, 'Chest', 'Flat Dumbbell Press', 3, '10-12', '12 mins', 'Deep stretch at bottom, squeeze chest at top.', NOW()),
(3, NULL, 'Chest', 'Cable Chest Flyes', 3, '12-15', '10 mins', 'Constant tension, focus on chest contraction.', NOW()),
(4, NULL, 'Back', 'Deadlift (Conventional)', 4, '6-8', '20 mins', 'Keep spine neutral, brace core throughout the lift.', NOW()),
(5, NULL, 'Back', 'Wide Grip Lat Pulldown', 3, '10-12', '12 mins', 'Pull to upper chest, drive with elbows.', NOW()),
(6, NULL, 'Back', 'Seated Cable Row', 3, '10-12', '10 mins', 'Squeeze shoulder blades together at peak.', NOW()),
(7, NULL, 'Shoulders', 'Standing Overhead Dumbbell Press', 4, '8-10', '12 mins', 'Full extension without locking out elbows aggressively.', NOW()),
(8, NULL, 'Shoulders', 'Lateral Dumbbell Raises', 4, '12-15', '10 mins', 'Lead with elbows, avoid using body momentum.', NOW()),
(9, NULL, 'Arms', 'Barbell Bicep Curls', 3, '10-12', '10 mins', 'Keep elbows fixed at sides; squeeze biceps at peak.', NOW()),
(10, NULL, 'Arms', 'Triceps Rope Pushdown', 3, '12-15', '10 mins', 'Flay rope apart at bottom for full tricep contraction.', NOW()),
(11, NULL, 'Legs', 'Barbell Back Squats', 4, '8-10', '20 mins', 'Hit parallel depth, drive through midfoot.', NOW()),
(12, NULL, 'Legs', 'Leg Press Machine', 3, '10-12', '12 mins', 'Never lock knees at full extension.', NOW()),
(13, NULL, 'Cardio', 'HIIT Treadmill Sprints', 6, '30s sprint / 60s walk', '15 mins', 'Warm up for 3 mins before starting sprint intervals.', NOW());

-- --------------------------------------------------------
-- 9. Table: tickets (Clean - 0 Rows)
-- --------------------------------------------------------
CREATE TABLE `tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` INT UNSIGNED NOT NULL,
  `subject` VARCHAR(150) NOT NULL,
  `category` ENUM('Billing', 'Equipment', 'Trainer', 'Locker', 'General') NOT NULL DEFAULT 'General',
  `priority` ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium',
  `description` TEXT NOT NULL,
  `admin_reply` TEXT DEFAULT NULL,
  `status` ENUM('Pending', 'In Progress', 'Resolved') NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_tickets_member` (`member_id`),
  CONSTRAINT `fk_tickets_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 10. Table: feedback (Clean - 0 Rows)
-- --------------------------------------------------------
CREATE TABLE `feedback` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `comment` TEXT NOT NULL,
  `is_approved` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_feedback_member` (`member_id`),
  CONSTRAINT `fk_feedback_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 11. Table: notifications
-- Structure ready; contains 1 initial notification for Admin.
-- --------------------------------------------------------
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info', 'success', 'warning', 'danger') NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notifications_user` (`user_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 'Welcome to your Gym Portal! 🏋️‍♂️', 'Your fresh gym database has been successfully initialized. You can now register members and manage gym plans.', 'success', 0, NOW());

-- --------------------------------------------------------
-- 12. Table: contact_messages (Clean - 0 Rows)
-- --------------------------------------------------------
CREATE TABLE `contact_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('unread', 'read', 'replied') NOT NULL DEFAULT 'unread',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 13. Table: password_resets (Clean - 0 Rows)
-- --------------------------------------------------------
CREATE TABLE `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(100) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reset_email` (`email`),
  KEY `idx_reset_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 14. Table: membership_requests
-- Staging table for member plan requests awaiting Admin verification & approval
-- --------------------------------------------------------
CREATE TABLE `membership_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `gender` ENUM('male', 'female', 'other') NOT NULL DEFAULT 'male',
  `dob` DATE DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `plan_id` INT UNSIGNED NOT NULL,
  `trainer_id` INT UNSIGNED DEFAULT NULL,
  `payment_method` VARCHAR(30) NOT NULL DEFAULT 'upi',
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `admin_note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_requests_user` (`user_id`),
  KEY `fk_requests_plan` (`plan_id`),
  CONSTRAINT `fk_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_requests_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
