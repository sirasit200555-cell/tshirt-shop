<?php
require 'config.php'; require_login();
$cart = $_SESSION['cart'] ?? [];
if (isset($_GET['remove'])) { unset($_SESSION['cart'][$_GET['remove']]); header('Location: checkout.php'); exit; }

$lines = []; $sum = 0;
foreach ($cart as $key => $item) {
    // ตรวจสอบว่า item เป็นอาเรย์หรือไม่ (ถ้าเป็นโครงสร้างใหม่)
    if (is_array($item)) {
        $id = $item['product_id'] ?? 0;
        $size = $item['size'] ?? '';
        $qty = $item['qty'] ?? 1;
    } else {
        // เผื่อกรณีข้อมูลเก่าที่เป็นตัวเลขจำนวนธรรมดา
        list($id, $size) = explode('-', $key, 2);
        $qty = $item;
    }

    $st = db()->prepare("SELECT * FROM products WHERE id=?"); $st->execute([$id]);
    if ($p = $st->fetch()) {
        $lines[] = [
            'key' => $key,
            'product_id' => $id,
            'name' => $p['name'],
            'size' => $size,
            'price' => $p['price'],
            'image' => $p['image'],
            'qty' => $qty
        ];
        $sum += $p['price'] * $qty;
    } else {
        // สินค้านี้ไม่มีในฐานข้อมูลแล้ว -> เอาออกจากตะกร้า ไม่ให้เลขค้าง
        unset($_SESSION['cart'][$key]);
    }
}

$done = false; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lines) {
    $f = trim($_POST['fullname'] ?? ''); $ph = trim($_POST['phone'] ?? ''); $ad = trim($_POST['address'] ?? '');
    $pay = $_POST['payment'] ?? '';
    if ($f == '' || $ph == '' || $ad == '' || !in_array($pay, ['qr','cod'], true)) {
        $err = 'กรุณากรอกข้อมูลให้ครบ';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // 1. ตรวจสอบสต็อกสินค้าทุกรายการในตะกร้าก่อนทำการบันทึก
            $outOfStock = false;
            $outOfStockName = '';
            foreach ($lines as $item) {
                $stmtStock = $pdo->prepare("SELECT stock FROM product_sizes WHERE product_id = ? AND size = ?");
                $stmtStock->execute([$item['product_id'], $item['size']]);
                $row = $stmtStock->fetch();
                $currentStock = $row ? (int)$row['stock'] : 0;
                if ($currentStock < $item['qty']) {
                    $outOfStock = true;
                    $outOfStockName = $item['name'] . ' (ไซส์ ' . $item['size'] . ')';
                    break;
                }
            }

            if ($outOfStock) {
                $pdo->rollBack();
                $err = 'ขออภัย สินค้า ' . $outOfStockName . ' มีจำนวนสต็อกไม่เพียงพอ';
            } else {
                // 2. บันทึกคำสั่งซื้อหลัก
                $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, fullname, phone, address, payment, total) VALUES (?,?,?,?,?,?)");
                $stmtOrder->execute([$_SESSION['user']['id'], $f, $ph, $ad, $pay, $sum]);
                $oid = $pdo->lastInsertId();

                // 3. เตรียมคำสั่ง SQL สำหรับตัดสต็อกและบันทึกรายการ
                $updStock = $pdo->prepare("UPDATE product_sizes SET stock = stock - ? WHERE product_id = ? AND size = ?");
                $ins = $pdo->prepare("INSERT INTO order_items (order_id, product_name, size, price, qty) VALUES (?, ?, ?, ?, ?)");

                foreach ($lines as $item) {
                    $ins->execute([$oid, $item['name'], $item['size'], $item['price'], $item['qty']]);
                    $updStock->execute([$item['qty'], $item['product_id'], $item['size']]);
                }

                $pdo->commit();
                $_SESSION['cart'] = [];
                $done = $oid;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $err = 'Error: ' . $e->getMessage();
        }
    }
}

$qrFile = file_exists(__DIR__.'/assets/qr.png.jpg') ? 'assets/qr.png.jpg' : null;
?>
<!DOCTYPE html>
<html lang="th"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ชำระเงิน | SIRASIT SHOP</title><link rel="stylesheet" href="assets/style.css">
<style>
  .ok-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 8px 28px rgba(0,0,0,.07);padding:36px 32px;margin-top:24px;text-align:center}
  .ok-check{width:84px;height:84px;margin:0 auto 16px;border-radius:50%;background:#dff5e8;display:grid;place-items:center;animation:ok-pop .5s cubic-bezier(.2,1.4,.4,1) both}
  .ok-check svg{width:46px;height:46px;stroke:#16a34a;stroke-width:3;fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:50;stroke-dashoffset:50;animation:ok-draw .5s .35s ease forwards}
  @keyframes ok-pop{0%{transform:scale(.3);opacity:0}100%{transform:scale(1);opacity:1}}
  @keyframes ok-draw{to{stroke-dashoffset:0}}
  .ok-card h2{margin:0 0 6px;font-size:1.7rem}
  .ok-sub{color:#6b7280;margin:0 0 18px}
  .ok-no{display:inline-block;background:#f3f4f6;border:1px dashed #9ca3af;border-radius:999px;padding:6px 18px;font-weight:800;font-size:1.1rem;letter-spacing:.03em;margin-bottom:22px}
  .ok-box{text-align:left;background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:14px 18px;margin-bottom:14px}
  .ok-box h4{margin:0 0 8px;font-size:.8rem;color:#6b7280;text-transform:uppercase;letter-spacing:.06em}
  .ok-row{display:flex;justify-content:space-between;gap:12px;padding:6px 0;font-size:.95rem}
  .ok-row+.ok-row{border-top:1px solid #eceef1}
  .ok-row .sz{color:#6b7280;font-size:.85rem}
  .ok-total{display:flex;justify-content:space-between;border-top:2px solid #111;margin-top:6px;padding-top:10px;font-weight:800;font-size:1.1rem}
  .ok-total span:last-child{color:#16a34a}
  .ok-note{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:10px;padding:10px 14px;font-size:.9rem;text-align:left;margin-bottom:14px}
  .ok-actions{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
  .ok-actions a{flex:1;min-width:150px;text-align:center;padding:13px 18px;border-radius:10px;font-weight:700;text-decoration:none;border:2px solid #111;transition:background .15s,color .15s}
  .ok-actions .pri{background:#111;color:#fff}.ok-actions .pri:hover{background:#333}
  .ok-actions .sec{background:#fff;color:#111}.ok-actions .sec:hover{background:#111;color:#fff}
</style></head>
<body>
<header class="top"><a class="logo" href="shop.php"><img src="assets/logo.png" alt="" style="height: 50px; vertical-align: middle;"></a></header>
<main class="wrap narrow">
<?php if ($done): ?>
    <div class="ok-card">
        <div class="ok-check"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
        <h2>สั่งซื้อสำเร็จ</h2>
        <p class="ok-sub">ขอบคุณที่ไว้วางใจ SIRASIT SHOP เราได้รับคำสั่งซื้อของคุณแล้ว</p>
        <div class="ok-no">คำสั่งซื้อ #<?= (int)$done ?></div>

        <div class="ok-box">
            <h4>รายการสินค้า</h4>
            <?php foreach ($lines as $item): ?>
                <div class="ok-row">
                    <span><?= e($item['name']) ?> <span class="sz">(ไซส์ <?= e($item['size']) ?>) × <?= (int)$item['qty'] ?></span></span>
                    <span><?= number_format($item['price'] * $item['qty'], 2) ?></span>
                </div>
            <?php endforeach; ?>
            <div class="ok-total"><span>ยอดรวมทั้งสิ้น</span><span><?= number_format($sum, 2) ?> บาท</span></div>
        </div>

        <div class="ok-box">
            <h4>จัดส่งถึง</h4>
            <div style="font-weight:700"><?= e($f) ?> · <?= e($ph) ?></div>
            <div style="color:#4b5563;margin-top:2px"><?= nl2br(e($ad)) ?></div>
            <div style="margin-top:8px;font-size:.9rem">ชำระเงินโดย: <b><?= $pay === 'qr' ? 'สแกนจ่าย (QR)' : 'จ่ายปลายทาง' ?></b></div>
        </div>

        <?php if ($pay === 'qr'): ?>
            <div class="ok-note">กรุณาโอนเงินตามยอดรวม แล้วแจ้งชำระกับทางร้านเพื่อให้เราเตรียมจัดส่งได้ไวขึ้น</div>
        <?php endif; ?>

        <div class="ok-actions">
            <a class="pri" href="order_tracking.php?id=<?= (int)$done ?>">ติดตามสถานะคำสั่งซื้อ</a>
            <a class="sec" href="shop.php">เลือกสินค้าต่อ</a>
        </div>
    </div>
<?php elseif (empty($lines)): ?>
    <h2>ตะกร้าว่าง</h2><a class="btn" href="shop.php">เลือกสินค้า</a>
<?php else: ?>
    <h2>สรุปรายการ</h2>
    <table class="tbl" style="width: 100%; border-collapse: separate; border-spacing: 0 8px;">
    <?php foreach ($lines as $item): ?>
        <tr style="background-color: #f9fafb; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <!-- คอลัมน์รูปภาพสินค้า (มีลิงก์คลิกไปดูรายละเอียด) -->
            <td style="padding: 12px; width: 60px; text-align: center; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                <?php if (!empty($item['image'])): ?>
                    <a href="product_detail.php?id=<?= e($item['product_id'] ?? '') ?>">
                        <img src="uploads/<?= e($item['image']) ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #e5e7eb;">
                    </a>
                <?php else: ?>
                    <div style="width: 50px; height: 50px; background-color: #e5e7eb; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #6b7280;">ไม่มีรูป</div>
                <?php endif; ?>
            </td>
            <!-- คอลัมน์ชื่อสินค้าและไซส์ -->
            <td style="padding: 12px; vertical-align: middle;">
                <div style="font-weight: 600; color: #1f2937; font-size: 15px;"><?= e($item['name']) ?></div>
                <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">
                    ไซส์: <span style="font-weight: 500; color: #374151;"><?= e($item['size']) ?></span> | จำนวน: <span style="font-weight: 500; color: #374151;"><?= e($item['qty']) ?></span> ชิ้น
                </div>
            </td>
            <!-- คอลัมน์ราคารวม -->
            <td style="padding: 12px; text-align: right; vertical-align: middle; font-weight: 600; color: #1f2937; font-size: 15px;">
                <?= number_format($item['price'] * $item['qty'], 2) ?> บาท
            </td>
            <!-- คอลัมน์ปุ่มลบ -->
            <td style="padding: 12px; text-align: center; vertical-align: middle; width: 50px; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                <a href="?remove=<?= e($item['key']) ?>" style="color: #ef4444; text-decoration: none; font-size: 16px; padding: 6px 10px; border-radius: 4px;" title="ลบสินค้า">🗑️</a>
            </td>
        </tr>
    <?php endforeach; ?>
    <!-- แถวสรุปยอดรวมทั้งหมด -->
    <tr style="border-top: 2px solid #e5e7eb;">
        <td colspan="2" style="padding: 15px 10px; font-weight: bold; font-size: 16px; color: #1f2937;">รวมทั้งสิ้น</td>
        <td colspan="2" style="padding: 15px 10px; text-align: right; font-weight: bold; font-size: 18px; color: #10b981;">
            <?= number_format($sum, 2) ?> บาท
        </td>
    </tr>
</table>

    <h2 style="margin-top: 20px;">ที่อยู่จัดส่ง</h2>
    <?php if (!empty($err)): ?>
    <div class="error"><?= $err ?></div>
    <?php endif; ?>
    <form method="post">
        <label>ชื่อ-นามสกุล<input name="fullname" required value="<?= e($_POST['fullname'] ?? '') ?>"></label>
        <label>เบอร์โทร<input name="phone" required value="<?= e($_POST['phone'] ?? '') ?>"></label>
        <label>ที่อยู่<textarea name="address" rows="3" required><?= e($_POST['address'] ?? '') ?></textarea></label>
        
        <h2>วิธีชำระเงิน</h2>
        <label class="opt"><input type="radio" name="payment" value="qr" required onclick="qr.hidden=false"> สแกนจ่าย (QR)</label>
        <div id="qr" hidden class="qrbox">
        <?php if ($qrFile): ?><img src="<?= $qrFile ?>" alt="QR ชำระเงิน"><?php else: ?><p class="muted">ยังไม่มีรูป QR วางไฟล์ qr.png ไว้ในโฟลเดอร์ assets/</p><?php endif; ?>
        <p class="muted small">โอนตามยอดรวม แล้วแจ้งชำระกับร้าน</p>
        <div style="text-align: center; margin-top: 15px; border-top: 1px dashed #ccc; padding-top: 10px; font-size: 14px; color: #333;">
            <b>รายการที่ต้องชำระ:</b>
            <ul style="list-style: none; padding: 0; margin: 5px 0;">
            <?php foreach ($lines as $item): ?>
                <li>- <?= htmlspecialchars($item['name']) ?> (ไซส์ <?= htmlspecialchars($item['size']) ?>) × <?= $item['qty'] ?> = <?= number_format($item['price'] * $item['qty'], 2) ?> บาท</li>
            <?php endforeach; ?>
            </ul>
            <b>ยอดโอนรวมทั้งสิ้น: <span style="color: #d9534f; font-size: 16px;"><?= number_format($sum, 2) ?></span> บาท</b>
        </div>
        </div>
        
        <label class="opt"><input type="radio" name="payment" value="cod" onclick="qr.hidden=true"> จ่ายปลายทาง</label>
        <button type="submit" style="width: 100%; padding: 14px 20px; font-size: 16px; font-weight: 600; background-color: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: background 0.2s; margin-top: 15px;"> ยืนยันสั่งซื้อ <?= number_format($sum, 2) ?> บาท</button>
    </form>
<?php endif; ?>
</main></body></html>