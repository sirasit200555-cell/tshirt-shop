<?php
require_once 'config.php';
require_login();
$pdo = db();
$userId = $_SESSION['user']['id'];

// ตรวจสอบและเพิ่มคอลัมน์อัตโนมัติหากยังไม่มี เพื่อป้องกัน Error
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN status VARCHAR(50) DEFAULT 'รอดำเนินการ (กำลังเตรียมจัดส่ง)'");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN cancel_reason TEXT DEFAULT NULL");
} catch (PDOException $e) {}

// จัดการการยกเลิกคำสั่งซื้อเมื่อถูกส่ง POST มา
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $orderId = (int)$_POST['cancel_order_id'];
    $reason = trim($_POST['cancel_reason'] ?? 'อื่นๆ');
    if (!empty($_POST['other_reason']) && $reason === 'อื่นๆ') {
        $reason = 'อื่นๆ: ' . trim($_POST['other_reason']);
    }

    $chk = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $chk->execute([$orderId, $userId]);
    $ord = $chk->fetch();

    if ($ord) {
        $currentStatus = $ord['status'] ?? 'รอดำเนินการ';
        if (!str_contains($currentStatus, 'ยกเลิก')) {
            $upd = $pdo->prepare("UPDATE orders SET status = 'ยกเลิกคำสั่งซื้อแล้ว', cancel_reason = ? WHERE id = ?");
            $upd->execute([$reason, $orderId]);
        }
    }
    header('Location: my_orders.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติคำสั่งซื้อ | SIRASIT SHOP</title>
    <link rel="stylesheet" href="assets/style.css">
    <script>
        function openCancelModal(orderId) {
            document.getElementById('modal-order-id').value = orderId;
            document.getElementById('cancel-modal').style.display = 'flex';
        }
        function closeCancelModal() {
            document.getElementById('cancel-modal').style.display = 'none';
        }
    </script>
</head>
<body>
    <header class="top">
        <a class="logo" href="shop.php"><img src="assets/logo.png" alt="SIRASIT SHOP" style="height: 55px; vertical-align: middle;"></a>
        <nav>
            <span class="muted">สวัสดี <?= e($_SESSION['user']['name']) ?></span>
            <a href="shop.php">เลือกซื้อสินค้า</a>
            <a href="my_orders.php" style="color: #353434; text-decoration: none; font-weight: bold;">ประวัติคำสั่งซื้อ</a>
            <a class="btn small" href="checkout.php">ตะกร้า</a>
            <a href="logout.php">ออกจากระบบ</a>
        </nav>
    </header>

    <main class="wrap" style="max-width: 1100px; margin: 40px auto;">
        <h2 style="margin-bottom: 20px;">ประวัติคำสั่งซื้อของฉัน</h2>
        <?php if (empty($orders)): ?>
            <p style="text-align: center; margin-top: 40px; color: #666;">คุณยังไม่มีประวัติคำสั่งซื้อ</p>
            <div style="text-align: center; margin-top: 20px;">
                <a class="btn" href="shop.php">ไปเลือกซื้อสินค้ากันเลย</a>
            </div>
        <?php else: ?>
            <table class="tbl" style="margin-top: 20px; width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>เลขออร์เดอร์</th>
                        <th>ชื่อผู้รับ</th>
                        <th>เบอร์โทร</th>
                        <th>วิธีชำระเงิน</th>
                        <th class="r">ยอดรวม (บาท)</th>
                        <th>วันที่สั่งซื้อ</th>
                        <th>สถานะ</th>
                        <th style="text-align: center;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $ord): ?>
                        <?php 
                            $statusText = $ord['status'] ?? 'รอดำเนินการ (กำลังเตรียมจัดส่ง)';
                            $cancelReason = $ord['cancel_reason'] ?? '';
                        ?>
                        <tr>
                            <td>#<?= $ord['id'] ?></td>
                            <td><?= htmlspecialchars($ord['fullname']) ?></td>
                            <td><?= htmlspecialchars($ord['phone']) ?></td>
                            <td><?= htmlspecialchars($ord['payment']) ?></td>
                            <td class="r"><?= number_format($ord['total'], 2) ?></td>
                            <td><?= htmlspecialchars($ord['created_at']) ?></td>
                            <td>
                                <?= htmlspecialchars($statusText) ?>
                                <?php if (!empty($cancelReason)): ?>
                                    <br><small style="color: #e11d48;">(เหตุผล: <?= htmlspecialchars($cancelReason) ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                    <a href="order_tracking.php?id=<?= $ord['id'] ?>" class="btn" style="padding: 5px 10px; font-size: 13px; text-decoration: none; background: #2563eb; color: #fff;">ดูสถานะ</a>
                                    
                                    <?php if (!str_contains($statusText, 'ยกเลิก') && !str_contains($statusText, 'จัดส่งแล้ว')): ?>
                                        <button type="button" class="btn" onclick="openCancelModal(<?= $ord['id'] ?>)" style="padding: 5px 10px; font-size: 13px; background: #dc2626; color: #fff; border: none; cursor: pointer; border-radius: 4px;">ยกเลิก</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>

    <!-- Modal สำหรับเลือกเหตุผลการยกเลิก -->
    <div id="cancel-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
        <div style="background: #fff; padding: 30px; border-radius: 8px; width: 400px; max-width: 90%;">
            <h3>เลือกเหตุผลในการยกเลิกคำสั่งซื้อ</h3>
            <form method="post" style="margin-top: 15px;">
                <input type="hidden" name="cancel_order_id" id="modal-order-id">
                
                <div style="margin-bottom: 10px;">
                    <label><input type="radio" name="cancel_reason" value="เปลี่ยนใจ, สั่งสินค้าผิด" checked> เปลี่ยนใจ / สั่งสินค้าผิด</label>
                </div>
                <div style="margin-bottom: 10px;">
                    <label><input type="radio" name="cancel_reason" value="พบราคาถูกกว่าที่อื่น"> พบราคาถูกกว่าที่อื่น</label>
                </div>
                <div style="margin-bottom: 10px;">
                    <label><input type="radio" name="cancel_reason" value="ต้องการเปลี่ยนที่อยู่จัดส่ง/ไซส์"> ต้องการเปลี่ยนที่อยู่จัดส่ง / ไซส์</label>
                </div>
                <div style="margin-bottom: 15px;">
                    <label><input type="radio" name="cancel_reason" value="อื่นๆ"> อื่นๆ</label>
                    <input type="text" name="other_reason" placeholder="ระบุเหตุผลเพิ่มเติม..." style="width: 100%; margin-top: 5px; padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeCancelModal()" style="padding: 8px 16px; background: #e5e7eb; border: none; border-radius: 4px; cursor: pointer;">ปิด</button>
                    <button type="submit" style="padding: 8px 16px; background: #dc2626; color: #fff; border: none; border-radius: 4px; cursor: pointer;">ยืนยันยกเลิกออร์เดอร์</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>