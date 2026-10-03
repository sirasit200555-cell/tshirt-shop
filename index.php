<?php
require 'config.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? ''); $pass = $_POST['password'] ?? '';
    if (($_POST['mode'] ?? '') === 'register') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '' || !preg_match('/^[A-Za-z0-9_]{3,30}$/', $username) || strlen($pass) < 6) {
            $err = 'ชื่อผู้ใช้ต้องเป็นตัวอักษรอังกฤษ ตัวเลข หรือ _ ยาว 3-30 ตัว และรหัสผ่านอย่างน้อย 6 ตัวอักษร';
        } else {
            try {
                db()->prepare("INSERT INTO users (name,username,password) VALUES (?,?,?)")
                    ->execute([$name, $username, password_hash($pass, PASSWORD_DEFAULT)]);
                $_SESSION['user'] = ['id' => db()->lastInsertId(), 'name' => $name];
                header('Location: shop.php'); exit;
            } catch (PDOException $ex) { $err = 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว'; }
        }
    } else {
        $st = db()->prepare("SELECT * FROM users WHERE username=?"); $st->execute([$username]);
        $u = $st->fetch();
        if ($u && password_verify($pass, $u['password'])) {
            $_SESSION['user'] = ['id' => $u['id'], 'name' => $u['name']];
            header('Location: shop.php'); exit;
        }
        $err = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
$reg = ($_POST['mode'] ?? '') === 'register' || isset($_GET['register']);
?>
<!DOCTYPE html><html lang="th"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>เข้าสู่ระบบ | SIRASIT SHOP</title><link rel="stylesheet" href="assets/style.css"></head>
<body class="login-body">
<main class="login-box">
  <h1 class="logo">
    <a href="index.php"><img src="assets/logo.png" alt="SIRASIT SHOP"></a>
  </h1>
  <h2 class="login-title"><?= $reg ? 'สมัครสมาชิก' : 'เข้าสู่ระบบ' ?></h2>
  <?php if ($err): ?><p class="alert"><?= e($err) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="mode" value="<?= $reg ? 'register' : 'login' ?>">
    <?php if ($reg): ?><label>ชื่อที่แสดง<input name="name" required></label><?php endif; ?>
    <label>ชื่อผู้ใช้<input name="username" required autocomplete="username"></label>
    <label>รหัสผ่าน<input type="password" name="password" required></label>
    <button class="btn"><?= $reg ? 'สมัครและเข้าสู่ระบบ' : 'เข้าสู่ระบบ' ?></button>
  </form>
  <p class="muted"><?= $reg ? '<a href="index.php">มีบัญชีแล้ว? เข้าสู่ระบบ</a>' : '<a href="?register=1">ยังไม่มีบัญชี? สมัครสมาชิก</a>' ?></p>
</main></body></html>