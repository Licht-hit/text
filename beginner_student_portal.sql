CREATE DATABASE IF NOT EXISTS duyag_2c
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE duyag_2c;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','student','staff') NOT NULL DEFAULT 'student',
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    verification_token CHAR(64) NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add account-verification fields when upgrading an existing users table.
SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role') = 0,
    'ALTER TABLE users ADD COLUMN role ENUM(''admin'',''student'',''staff'') NOT NULL DEFAULT ''student'' AFTER password',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

ALTER TABLE users
    MODIFY role ENUM('admin','student','staff') NOT NULL DEFAULT 'student';

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email') = 0,
    'ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER username',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_verified') = 0,
    'ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'verification_token') = 0,
    'ALTER TABLE users ADD COLUMN verification_token CHAR(64) NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

UPDATE users
SET email = CONCAT('user', id, '@example.invalid')
WHERE email IS NULL OR email = '';

ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL;

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
       AND COLUMN_NAME = 'email' AND NON_UNIQUE = 0) = 0,
    'ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

-- Keep the three original demo accounts available without overwriting existing accounts.
-- Demo passwords: admin/admin123, juan/student123, staff/staff123.
INSERT IGNORE INTO users (full_name, username, email, password, role, email_verified) VALUES
('System Administrator', 'admin', 'admin@example.com', '$2y$12$kyGmVMqZHnhqCyh89Q9jxeVZ1Z9xoBCd4ZX/rIFo3I.laZWK0XJde', 'admin', 1),
('Juan Dela Cruz', 'juan', 'juan@example.com', '$2y$12$8FFlEA0JCh6G5ZhkwCrnxemXYckYSqcGyBGZaSPwaogl6rLAWNA16', 'student', 1),
('Sample Staff', 'staff', 'staff@example.com', '$2y$12$vm4FhfJEM5FBXPaC3j13uehdSj748Ul4FFKaN8/zC9bJsjcbyvvEK', 'staff', 1);

SET @migration_sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
       AND COLUMN_NAME = 'verification_token' AND NON_UNIQUE = 0) = 0,
    'ALTER TABLE users ADD UNIQUE KEY uq_users_verification_token (verification_token)',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
