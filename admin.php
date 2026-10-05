<?php
require 'config.php';
if (isset($_GET['logout'])) { unset($_SESSION['admin']); header('Location: admin.php'); exit; }
$err = '';
if (isset($_POST['admin_pass'])) {
    if (hash_equals(ADMIN_PASSWORD, $_POST['admin_pass'])) { $_SESSION['admin'] = true; header('Location: admin.php'); exit; }
    $err = 'รหัสผ่านแอดมินไม่ถูกต้อง';
}
$isAdmin = !empty($_SESSION['admin']);
$msg = '';
$msgType = ''; // success = เพิ่มสินค้า (เขียว), delete = ลบสินค้า (เหลือง), error = กรอกไม่ครบ (แดง)
$openOrders = false;

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'add') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock_input = $_POST['stock'] ?? [];
        $active_sizes = [];
        foreach ($stock_input as $sz => $qty) {
            if (intval($qty) > 0) {
                $active_sizes[] = $sz;
            }
        }
        $sizes = implode(',', $active_sizes);
        $img = null;

        // อัปโหลดรูปภาพหลัก
        if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $img = uniqid('p.').'.'.$ext;
                move_uploaded_file($_FILES['image']['tmp_name'], __DIR__.'/uploads/'.$img);
            }
        }

        if ($name === '' || $price <= 0 || empty($active_sizes)) {
            $msg = 'กรุณากรอกชื่อ ราคา และเลือกไซส์อย่างน้อย 1 ไซส์'; $msgType = 'error';
        } else {
            // บันทึกสินค้าหลักลงตาราง products
            $stmt = db()->prepare("INSERT INTO products (name,price,image,sizes,description) VALUES (?,?,?,?,?)");
            $stmt->execute([$name, $price, $img, $sizes, '']);

            // ดึง ID ของสินค้าเพิ่งเพิ่มล่าสุด
            $productId = db()->lastInsertId();

            // บันทึกจำนวนสต็อกลงตาราง product_sizes
            $stmtSize = db()->prepare("INSERT INTO product_sizes (product_id, size, stock) VALUES (?,?,?)");
            foreach ($stock_input as $sz => $qty) {
                $q = intval($qty);
                if ($q > 0) {
                    $stmtSize->execute([$productId, $sz, $q]);
                }
            }

            // อัปโหลดรูปภาพเพิ่มเติม
            if (!empty($_FILES['images']['name'][0])) {
                foreach ($_FILES['images']['name'] as $i => $fileName) {
                    if ($_FILES['images']['error'][$i] === 0) {
                        $subExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        if (in_array($subExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                            $subImg = uniqid('sub.').'.'.$subExt;
                            move_uploaded_file($_FILES['images']['tmp_name'][$i], __DIR__.'/uploads/'.$subImg);
                            $subStmt = db()->prepare("INSERT INTO product_images (product_id, image) VALUES (?,?)");
                            $subStmt->execute([$productId, $subImg]);
                        }
                    }
                }
            }
            $msg = 'เพิ่มสินค้าและสต็อกสำเร็จแล้ว '; $msgType = 'success';
        }
    } 
    else if ($act === 'delete') {
        $productId = (int)$_POST['id'];
        
        $st = db()->prepare("SELECT image FROM products WHERE id=?");
        $st->execute([$productId]);
        if ($r = $st->fetch()) { 
            if (!empty($r['image'])) {
                @unlink(__DIR__.'/uploads/'.$r['image']); 
            }
        }
        
        db()->prepare("DELETE FROM product_images WHERE product_id=?")->execute([$productId]);
        db()->prepare("DELETE FROM product_sizes WHERE product_id=?")->execute([$productId]);
        db()->prepare("DELETE FROM products WHERE id=?")->execute([$productId]);
        
        $msg = 'ลบสินค้าแล้ว'; $msgType = 'delete';
    } 
    else if ($act === 'delete_order') {
        $orderId = (int)$_POST['id'];
        $st = db()->prepare("SELECT status FROM orders WHERE id=?");
        $st->execute([$orderId]);
        $row = $st->fetch();
        // ลบได้เฉพาะออเดอร์ที่ถูกยกเลิกแล้วเท่านั้น
        if ($row && order_is_cancelled($row['status'])) {
            db()->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$orderId]);
            db()->prepare("DELETE FROM orders WHERE id=?")->execute([$orderId]);
            $msg = 'ลบออเดอร์ #' . $orderId . ' แล้ว';
        } else {
            $msg = 'ลบได้เฉพาะออเดอร์ที่ถูกยกเลิกแล้วเท่านั้น';
        }
        $openOrders = true;
    }
    else if ($act === 'update_status') {
        $orderId = (int)$_POST['id'];
        $newStatus = $_POST['status'] ?? '';

        $stmt = db()->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);

        $msg = 'อัปเดตสถานะคำสั่งซื้อแล้ว ✓';
        $openOrders = true;
    }
}
?>
<!DOCTYPE html><html lang="th"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>หลังบ้าน | Sirasit SHOP</title><link rel="stylesheet" href="assets/style.css"></head>
<body>
    <?php if (!$isAdmin): ?>
<main class="login-box">
    <h1 class="logo">หลังบ้าน</h1>
    <?php if ($err): ?><p class="alert"><?= e($err) ?></p><?php endif; ?>
    <form action="admin.php" method="post">
        <label>รหัสผ่านแอดมิน <input type="password" name="admin_pass" required autofocus></label>
        <button type="submit" class="btn">เข้าสู่ระบบแอดมิน</button>
    </form>
    <p class="muted small"><a href="index.php">กลับหน้าร้าน</a></p>
</main>
<?php else: ?>
<?php
$products = db()->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
$orders = db()->query("SELECT * FROM orders ORDER BY id DESC LIMIT 20")->fetchAll();
// นับทุกออเดอร์ที่ "ยังไม่จัดส่ง" (ทุกสถานะที่ไม่ใช่จัดส่งแล้ว / จัดส่งสำเร็จ / ยกเลิก)
$pending = (int)db()->query("SELECT COUNT(*) FROM orders
    WHERE status IS NULL OR status = ''
       OR (status NOT LIKE 'ของจัดส่งแล้ว%' AND status NOT LIKE 'จัดส่งสำเร็จ%' AND status NOT LIKE 'ยกเลิก%')")->fetchColumn();
?>
<header class="top">
    <a class="logo" href="admin.php">Admin</a>
    <nav>
    <a class="nav-btn blue" href="verify_user_view.php"><?= nav_icon('users') ?><span class="lbl">ดูบัญชีผู้ใช้</span></a>
    <button type="button" id="open-orders" class="nav-btn green"><?= nav_icon('box') ?><span class="lbl">คำสั่งซื้อ</span><?php if ($pending > 0): ?><span class="mb-badge"><?= $pending ?></span><?php endif; ?></button>
    <a class="nav-btn" href="shop.php"><?= nav_icon('store') ?><span class="lbl">ดูหน้าร้าน</span></a>
    <?= mail_nav_link('admin') ?>
    <a class="nav-btn icon logout" href="admin.php?logout=1" title="ออกจากระบบ" aria-label="ออกจากระบบ"><?= nav_icon('logout') ?></a>
</nav>
</header>
<main class="wrap">
<style>
  /* กล่องแจ้งผลด้านบนฟอร์ม: เพิ่ม = เขียว, ลบ = เหลือง, ผิดพลาด = แดง */
  .flash { display: flex; align-items: center; gap: 12px; padding: 14px 18px; margin-bottom: 20px; border-radius: 8px; border: 1px solid; font-weight: 600; }
  .flash-ico { flex: none; width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; font-size: 14px; font-weight: 800; background: rgba(255,255,255,.7); }
  .flash-success { background: #d1fae5; border-color: #10b981; color: #065f46; }
  .flash-delete  { background: #fff3cd; border-color: #ffa41a; color: #856404; }
  .flash-error   { background: #fdecec; border-color: #e5484d; color: #a12a2a; }
  /* ปุ่มเพิ่มสินค้า */
  .add-submit { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; margin-top: 8px; padding: 15px 20px; border: none; border-radius: 10px; background: #10b981; color: #fff; font: inherit; font-size: 1.2rem; font-weight: 800; letter-spacing: .02em; cursor: pointer; box-shadow: 0 4px 12px rgba(16,185,129,.35); transition: background .15s, transform .1s, box-shadow .15s; }
  .add-submit:hover { background: #0b936a; box-shadow: 0 6px 16px rgba(16,185,129,.45); transform: translateY(-1px); }
  .add-submit:active { transform: translateY(1px); box-shadow: 0 2px 6px rgba(16,185,129,.35); }
  .add-submit:focus-visible { outline: 3px solid #065f46; outline-offset: 3px; }
  /* ฟอร์มเพิ่มสินค้าอยู่กึ่งกลาง ความกว้างพอดี */
  .add-wrap { max-width: 720px; margin: 0 auto; }
  .add-wrap h2 { text-align: center; }
  .add-wrap .addform { width: 100% !important; max-width: none !important; box-sizing: border-box; padding: 24px 28px; }
  .add-wrap .addform input:not([type="number"]), .add-wrap .addform textarea { width: 100%; box-sizing: border-box; }
  .add-wrap .sizes-stock-group > div { justify-content: center; }
  /* หน้าต่างคำสั่งซื้อ */
  .modal-back { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 3000; display: flex; align-items: flex-start; justify-content: center; padding: 40px 16px; overflow-y: auto; }
  .modal-back[hidden] { display: none; }
  .modal-box { background: #fff; border-radius: 12px; width: min(1280px, 100%); padding: 20px 24px 24px; box-shadow: 0 20px 50px rgba(0,0,0,.3); }
  .modal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
  #close-orders { border: none; background: #f3f4f6; width: 34px; height: 34px; border-radius: 50%; font-size: 16px; cursor: pointer; }
  #close-orders:hover { background: #e5e7eb; }
</style>
<!-- หน้าต่างคำสั่งซื้อ (เปิดจากปุ่ม "คำสั่งซื้อ" ด้านบน) -->
<div id="orders-modal" class="modal-back" <?= $openOrders ? '' : 'hidden' ?>>
  <div class="modal-box">
    <div class="modal-head">
      <h2 style="margin: 0;">คำสั่งซื้อล่าสุด</h2>
      <button type="button" id="close-orders" aria-label="ปิด">✕</button>
    </div>
    <?php if ($openOrders && !empty($msg)): ?>
      <div style="background:#d1fae5;color:#065f46;padding:10px 14px;border-radius:6px;margin-bottom:12px;font-weight:500;"><?= e($msg) ?></div>
    <?php endif; ?>
    <table class="tbl orders-tbl">
        <tr>
            <th style="text-align: center;">เลขที่ออเดอร์</th>
            <th style="text-align: center;">สถานะ</th>
            <th style="text-align: left;">ข้อมูลลูกค้า / ที่อยู่</th>
            <th style="text-align: center;">การชำระเงิน</th>
            <th class="r">ยอดรวม</th>
            <th style="text-align: center !important;">จัดการ</th>
        </tr>
        <?php foreach ($orders as $o): $cancelled = order_is_cancelled($o['status'] ?? ''); ?>
        <tr>
            <td style="text-align: center;">#<?= $o['id'] ?></td>
            <td style="text-align: center;"><?= order_status_badge($o['status'] ?? '') ?></td>
            <td><?= e($o['fullname']) ?> · <?= e($o['phone']) ?><br><span class="muted small"><?= e($o['address']) ?></span></td>
            <td style="text-align: center;"><?= $o['payment'] === 'qr' ? 'สแกนจ่าย' : 'จ่ายปลายทาง' ?></td>
            <td class="r"><?= number_format($o['total'], 2) ?></td>
            <td style="text-align: center; white-space: nowrap;">
                <?php if (!$cancelled): ?>
                <form method="post" style="display: inline-block; margin-right: 5px;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?= $o['id'] ?>">
        <select name="status" style="padding: 4px 12px; border-radius: 4px; border: 1px solid #ccc;">
            <?php foreach (['รอดำเนินการ (กำลังเตรียมจัดส่ง)', 'ของจัดส่งแล้ว (รอของประมาณ 4-5 วัน)', 'จัดส่งสำเร็จ'] as $st): ?>
                <option value="<?= e($st) ?>" <?= (($o['status'] ?? '') === $st) ? 'selected' : '' ?>><?= e($st) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" style="padding: 6px 12px; font-size: 13px; font-weight: 500; background-color: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">💾 บันทึก</button>
    </form>
                <?php endif; ?>
    <a href="order_tracking.php?id=<?= $o['id'] ?>" target="_blank" style="padding: 6px 12px; font-size: 13px; font-weight: 500; background-color: #3b82f6; color: white; border-radius: 6px; text-decoration: none; display: inline-block; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"> ดูรายละเอียด</a>
                <?php if ($cancelled): ?>
                <form method="post" onsubmit="return confirm('ลบออเดอร์ #<?= (int)$o['id'] ?> ออกถาวรใช่หรือไม่?')" style="display: inline-block; margin: 0 0 0 5px;">
        <input type="hidden" name="action" value="delete_order">
        <input type="hidden" name="id" value="<?= $o['id'] ?>">
        <button type="submit" style="padding: 6px 12px; font-size: 13px; font-weight: 500; background-color: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">🗑 ลบ</button>
    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
        <tr><td class="muted" colspan="6">ยังไม่มีคำสั่งซื้อ</td></tr>
        <?php endif; ?>
    </table>
  </div>
</div>

    
    <div class="add-wrap">
    <h2>เพิ่มสินค้า</h2>
    <form method="post" enctype="multipart/form-data" class="addform">
        <?php if (!empty($msg) && !$openOrders): ?>
        <div class="flash flash-<?= e($msgType ?: 'delete') ?>" role="status">
            <span class="flash-ico"><?= $msgType === 'success' ? '✓' : ($msgType === 'error' ? '!' : '🗑') ?></span>
            <span><?= e($msg) ?></span>
        </div>
        <?php endif; ?>
        <!-- ฟิลด์อื่นๆ ต่อไป... -->
        <input type="hidden" name="action" value="add">
        <label>ชื่อสินค้า<input name="name" required></label>
        <label>ราคา (บาท)<input type="number" name="price" step="0.01" min="1" required></label>
        <label>รูปสินค้า<input type="file" name="image" accept="image/*"></label>
        <label>รูปภาพเพิ่มเติม (เลือกได้หลายรูป) <input type="file" name="images[]" accept="image/*" multiple></label>
        <label>รายละเอียดสินค้า</label>
    <textarea name="description" rows="4" style="width: 100%; padding: 8px; margin-top: 5px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit;" placeholder="กรอกรายละเอียดสินค้า เช่น เนื้อผ้า, คุณสมบัติ หรือข้อมูลเพิ่มเติม..."></textarea>
        <div class="sizes-stock-group" style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">กำหนดจำนวนสต็อกแต่ละไซส์:</label>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL'] as $s): ?>
                    <div style="background: #f8f9fa; padding: 8px; border: 1px solid #ddd; border-radius: 4px; text-align: center;">
                        <label style="font-weight: bold; display: block; margin-bottom: 3px;"><?= $s ?></label>
                        <input type="number" name="stock[<?= $s ?>]" min="0" value="0" placeholder="0" style="width: 50px; text-align: center; padding: 4px;">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="add-submit"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>เพิ่มสินค้า</button>
    </form>
    </div>

    <h2>สินค้าทั้งหมด (<?= count($products) ?>)</h2>
    <table class="tbl">
        <?php foreach ($products as $p): ?>
        <tr>
            <td class="thumb"><?php if ($p['image']): ?><img src="uploads/<?= e($p['image']) ?>" alt=""><?php endif; ?></td>
            <td>
                <?= e($p['name']) ?><br>
               <span class="muted small">
        <?php
        $stmtSize = db()->prepare("SELECT size, stock FROM product_sizes WHERE product_id = ?");
        $stmtSize->execute([$p['id']]);
        $sizes = $stmtSize->fetchAll(PDO::FETCH_ASSOC);
        
        $stockText = [];
        foreach ($sizes as $s) {
            $stockText[] = $s['size'] . ': ' . $s['stock'];
        }
        echo (!empty($stockText) ? implode(' | ', $stockText) : 'ยังไม่ได้กำหนด');
        ?>
        | ราคา: <?= number_format($p['price'], 2) ?> บาท
    </span>
            </td>
            <td style="text-align: right; white-space: nowrap;">
                <a href="edit_product.php?id=<?= $p['id'] ?>" style="padding: 6px 12px; font-size: 13px; font-weight: 500; background-color: #0bb3f5; color: white; border-radius: 6px; text-decoration: none; display: inline-block; margin-right: 5px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s;"> แก้ไข</a>
    <form method="post" onsubmit="return confirm('ต้องการลบสินค้านี้ใช่หรือไม่?')" style="display: inline-block; margin: 0;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button type="submit" style="padding: 6px 12px; font-size: 13px; font-weight: 500; background-color: #ef4444; color: white; border: none; border-radius: 6px; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s;">🗑️ ลบ</button>
    </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>


</main>
<script>
(function () {
  const modal = document.getElementById('orders-modal');
  const open  = document.getElementById('open-orders');
  const close = document.getElementById('close-orders');
  if (!modal || !open) return;
  const show = () => { modal.hidden = false; document.body.style.overflow = 'hidden'; };
  const hide = () => { modal.hidden = true;  document.body.style.overflow = ''; };
  open.addEventListener('click', show);
  close.addEventListener('click', hide);
  modal.addEventListener('mousedown', e => { if (e.target === modal) hide(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') hide(); });
  if (!modal.hidden) document.body.style.overflow = 'hidden';
})();
</script>
<script>window.MAIL={as:'admin'};</script>
<script src="assets/mail.js"></script>
<?php endif; ?>
</body>
</html>
