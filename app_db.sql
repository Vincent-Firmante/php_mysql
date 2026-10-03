-- Import this file once (phpMyAdmin > Import, or: mysql -u root -p < app_db.sql)
CREATE DATABASE IF NOT EXISTS app_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE app_db;

CREATE TABLE IF NOT EXISTS profiles (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(60)  NOT NULL,
  role         VARCHAR(60)  NOT NULL,
  interests    VARCHAR(255) NOT NULL DEFAULT '',
  avatar_color CHAR(7)      NOT NULL DEFAULT '#3b6cf6',
  photo        MEDIUMBLOB   NULL,
  photo_mime   VARCHAR(32)  NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO profiles (name, role, interests, avatar_color) VALUES
('Maria Santos', 'UI Designer', 'Typography, Hiking, Film photography', '#e4572e'),
('Jun Reyes', 'Backend Developer', 'PHP, Coffee, Chess', '#2a9d8f');
