<?php
// เปิดครั้งเดียว: http://localhost/tshirt-shop/install.php
$pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS tshirt_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE tshirt_shop");
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL,
  price DECIMAL(10,2) NOT NULL, image VARCHAR(255) DEFAULT NULL,
  sizes VARCHAR(100) NOT NULL DEFAULT 'S,M,L,XL',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
  fullname VARCHAR(150) NOT NULL, phone VARCHAR(30) NOT NULL, address TEXT NOT NULL,
  payment ENUM('qr','cod') NOT NULL, total DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
$pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL,
  product_name VARCHAR(200) NOT NULL, size VARCHAR(10) NOT NULL,
  price DECIMAL(10,2) NOT NULL, qty INT NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE)");

// ระบบกล่องจดหมาย (ทิกเก็ตลูกค้า-แอดมิน)
$pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, subject VARCHAR(200) NOT NULL,
  status ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS ticket_messages (
  id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL,
  sender ENUM('customer','admin') NOT NULL, body TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (ticket_id), FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() == 0) {
    $st = $pdo->prepare("INSERT INTO products (name, price) VALUES (?, ?)");
    for ($i = 1; $i <= 30; $i++) $st->execute(["เสื้อยืดตัวอย่าง $i", 199 + ($i % 5) * 50]);
}
echo "ติดตั้งเสร็จแล้ว ✔ <a href='index.php'>ไปหน้าล็อกอิน</a>";