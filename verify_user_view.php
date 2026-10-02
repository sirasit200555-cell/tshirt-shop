<?php
session_start();
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_password = $_POST['admin_password'];
    // กำหนดรหัสผ่านสำหรับยืนยันตัวตน (เปลี่ยนตามต้องการ ที่นี่ตั้งเป็น admin1234)
    if ($admin_password === 'admin1234') {
        $_SESSION['verified_user_view'] = true;
        header("Location: view_users.php");
        exit();
    } else {
        $error = "รหัสผ่านไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ยืนยันตัวตนก่อนดูบัญชีผู้ใช้</title>
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        h2 { margin-bottom: 20px; color: #333; }
        input[type="password"] { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #007bff; color: white; border: none; padding: 10px 15px; width: 100%; border-radius: 4px; font-size: 16px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .back-link { display: block; margin-top: 15px; color: #666; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .error { color: red; margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>🔒 ยืนยันรหัสผ่านแอดมิน</h2>
        <p style="color: #666; font-size: 14px; margin-bottom: 20px;">กรุณากรอกรหัสผ่านเพื่อเข้าถึงข้อมูลบัญชีผู้ใช้</p>
        <?php if (!empty($error)) { echo "<div class='error'>$error</div>"; } ?>
        <form method="POST">
            <input type="password" name="admin_password" placeholder="กรอกรหัสผ่านแอดมิน" required autofocus>
            <button type="submit">ยืนยัน</button>
        </form>
        <a href="admin.php" class="back-link">← กลับสู่หน้าหลักแอดมิน</a>
    </div>
</body>
</html>