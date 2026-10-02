<?php
// ไม่ต้องเรียก session_start() ซ้ำ เพราะใน config.php มีให้อยู่แล้ว
include 'config.php';

// ตรวจสอบว่าผ่านการยืนยันตัวตนมาหรือยัง
if (!isset($_SESSION['verified_user_view']) || $_SESSION['verified_user_view'] !== true) {
    header("Location: verify_user_view.php");
    exit();
}

// ใช้ฟังก์ชัน db() ตามมาตรฐานโครงสร้างโปรเจกต์ของคุณ
try {
    $stmt = db()->query("SELECT * FROM users ORDER BY id DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการบัญชีผู้ใช้ (Admin)</title>
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8f9fa; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        h2 { color: #333; margin-top: 0; }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #dee2e6; text-align: left; }
        th { background-color: #343a40; color: white; }
        tr:hover { background-color: #f1f3f5; }
        .btn-back { background-color: #6c757d; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-size: 14px; }
        .btn-back:hover { background-color: #5a6268; }
        .badge { background-color: #e2e3e5; color: #383d41; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-family: monospace; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-flex">
            <h2>👥 รายชื่อบัญชีผู้ใช้งานระบบ</h2>
            <a href="admin.php" class="btn-back">← กลับหน้าแอดมิน</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>Username</th>
                    <th>Password (Hashed)</th>
                    <th>วันที่สมัคร</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><span class="badge"><?php echo htmlspecialchars($row['password']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['created_at'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #777;">ไม่พบข้อมูลผู้ใช้งานในระบบ</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>