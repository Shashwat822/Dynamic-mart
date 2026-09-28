CREATE DATABASE IF NOT EXISTS dynamic_mart CHARACTER SET utf8mb4;
USE dynamic_mart;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  icon VARCHAR(50) DEFAULT 'fa-tag',
  parent_id INT DEFAULT NULL
);

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  description VARCHAR(500),
  price DECIMAL(10,2),
  old_price DECIMAL(10,2),
  discount DECIMAL(5,2),
  image VARCHAR(300),
  category_id INT,
  stock INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);