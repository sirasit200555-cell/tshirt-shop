<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

$id = $_GET['id'] ?? 0;
$order = null;
$items = [];

if ($id > 0) {
    try {
        // เรียกใช้งานผ่านฟังก์ชัน db() ตาม config.php
        $pdo = db();
        
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $stmt_items = $pdo->prepare("SELECT oi.*, (SELECT p.image FROM products p WHERE p.name = oi.product_name ORDER BY p.id DESC LIMIT 1) AS image FROM order_items oi WHERE oi.order_id = ?");
            $stmt_items->execute([$id]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตามสถานะคำสั่งซื้อ | SIRASIT SHOP</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .stt{display:inline-flex;align-items:center;gap:8px;font-weight:700;vertical-align:middle}
        .stt.wait{color:#1565c0}
        .stt.ship{color:#e8730c}
        .stt.done{color:#16a34a}
        .stt.cancel{color:#d12a2f}
        .stt svg{flex:none}
        .stt.ship svg{animation:truck-move 1.2s ease-in-out infinite}
        @keyframes truck-move{0%,100%{transform:translateX(-2px)}50%{transform:translateX(3px)}}
        .it{display:flex;align-items:center;gap:12px}
        .it img,.it .noimg{width:64px;height:64px;border-radius:8px;border:1px solid #e5e7eb;object-fit:cover;flex:none;background:#f3f4f6}
        .it .noimg{display:grid;place-items:center;font-size:11px;color:#9ca3af}
        .it .sz{color:#6b7280;font-size:.85rem}
        .money{color:#16a34a;font-weight:800}
    </style>
</head>
<body>
    <header class="top">
        <a class="logo" href="shop.php"><img src="assets/logo.png" alt="SIRASIT SHOP" style="height: 50px; vertical-align: middle;"></a>
        <nav><a href="shop.php">กลับไปเลือกสินค้า</a></nav>
    </header>
    <main class="wrap narrow">
        <h2>ติดตามสถานะคำสั่งซื้อ</h2>
        
        <?php if (isset($db_error)): ?>
            <p class="alert">เกิดข้อผิดพลาดฐานข้อมูล: <?= htmlspecialchars($db_error) ?></p>
        <?php elseif (!$order): ?>
            <p class="alert">ไม่พบข้อมูลคำสั่งซื้อหมายเลข #<?= $id ?></p>
            <div style="margin-top: 20px; text-align: center;">
                <a class="btn" href="shop.php">กลับสู่หน้าหลัก</a>
            </div>
        <?php else: ?>
            <div style="background: #f9fafb; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 20px;">
                <p><strong>หมายเลขคำสั่งซื้อ:</strong> #<?= $order['id'] ?></p>
                <p><strong>ชื่อ-นามสกุล:</strong> <?= htmlspecialchars($order['fullname'] ?? '-') ?></p>
                <p><strong>เบอร์โทรศัพท์:</strong> <?= htmlspecialchars($order['phone'] ?? '-') ?></p>
                <p><strong>ที่อยู่จัดส่ง:</strong> <?= nl2br(htmlspecialchars($order['address'] ?? '-')) ?></p>
               <p><strong>วิธีชำระเงิน:</strong> 
        <?php 
            $paymentMethod = $order['payment'] ?? '-';
            if ($paymentMethod == 'cod') {
             echo '<strong>เก็บเงินปลายทาง</strong>';
            } elseif ($paymentMethod == 'qr') {
                 echo '<strong>QR Code</strong>';
            } else {
                 echo '<strong>' . htmlspecialchars($paymentMethod) . '</strong>';
            }
        ?>
        </p>
                <p><strong>สถานะคำสั่งซื้อ:</strong>
                <?php
                    $st = trim((string)($order['status'] ?? ''));
                    if ($st === '') $st = 'รอดำเนินการ (กำลังเตรียมจัดส่ง)';
                    $truck = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true"><path d="M2 6h11v10H2z"/><path d="M13 9h4.5L21 12.5V16h-8"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg>';
                    $check = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
                    if (mb_strpos($st, 'ยกเลิก') === 0) {
                        echo '<span class="stt cancel">' . htmlspecialchars($st) . '</span>';
                    } elseif (mb_strpos($st, 'จัดส่งสำเร็จ') === 0) {
                        echo '<span class="stt done">' . $check . htmlspecialchars($st) . '</span>';
                    } elseif (mb_strpos($st, 'ของจัดส่งแล้ว') === 0 || mb_strpos($st, 'กำลังจัดส่ง') === 0) {
                        echo '<span class="stt ship">' . $truck . htmlspecialchars($st) . '</span>';
                    } else {
                        echo '<span class="stt wait">' . htmlspecialchars($st) . '</span>';
                    }
                ?>
                </p>
                <p><strong>วันที่สั่งซื้อ:</strong> <?= htmlspecialchars($order['created_at'] ?? '-') ?></p>
            </div>

            <h3>รายการสินค้า</h3>
            <table class="tbl">
                <tr>
                    <th>สินค้า</th>
                    <th class="r">จำนวน</th>
                    <th class="r">ราคาต่อหน่วย</th>
                    <th class="r">ราคารวม</th>
                </tr>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="it">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($item['image']) ?>" alt="">
                                <?php else: ?>
                                    <div class="noimg">ไม่มีรูป</div>
                                <?php endif; ?>
                                <div>
                                    <div><?= htmlspecialchars($item['product_name'] ?? $item['name'] ?? 'สินค้า') ?></div>
                                    <?php if (!empty($item['size'])): ?><div class="sz">ไซส์ <?= htmlspecialchars($item['size']) ?></div><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="r"><?= $item['qty'] ?? 1 ?></td>
                        <td class="r"><?= number_format($item['price'] ?? 0, 2) ?></td>
                        <td class="r"><?= number_format(($item['price'] ?? 0) * ($item['qty'] ?? 1), 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center;">ไม่พบรายการสินค้าในคำสั่งซื้อนี้</td></tr>
                <?php endif; ?>
                <tr class="sum">
                    <td colspan="3">ยอดรวมทั้งสิ้น</td>
                    <td class="r"><span class="money"><?= number_format($order['total'] ?? 0, 2) ?></span> บาท</td>
                </tr>
            </table>

            <div style="margin-top: 20px; text-align: center;">
                <a class="btn" href="shop.php">เลือกซื้อสินค้าเพิ่มเติม</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>