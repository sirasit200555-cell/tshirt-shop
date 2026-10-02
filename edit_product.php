<?php
// edit_product.php
require 'config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ดึงข้อมูลสินค้าเดิม
$stmt = db()->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    die("ไม่พบสินค้าที่ต้องการแก้ไข");
}

$allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

// จัดการเมื่อกดบันทึกการแก้ไข
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $description = $_POST['description'] ?? '';

    // ----- รูปภาพหลัก (ถ้ามีการเปลี่ยน) -----
    $image = $product['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed_ext, true)) {
            $image = 'prod_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/uploads/' . $image);
        }
    }

    // ----- อัปเดตข้อมูลสินค้า -----
    $updateStmt = db()->prepare("UPDATE products SET name = ?, price = ?, description = ?, image = ? WHERE id = ?");
    $updateStmt->execute([$name, $price, $description, $image, $id]);

    // ----- รูปภาพเพิ่มเติม: ลบรูปที่ติ๊กเลือก -----
    if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
        $selStmt = db()->prepare("SELECT image FROM product_images WHERE product_id = ? AND image = ?");
        $delStmt = db()->prepare("DELETE FROM product_images WHERE product_id = ? AND image = ?");
        foreach ($_POST['delete_images'] as $delImg) {
            $delImg = basename((string)$delImg);
            $selStmt->execute([$id, $delImg]);
            if ($selStmt->fetch()) {
                $delStmt->execute([$id, $delImg]);
                $path = __DIR__ . '/uploads/' . $delImg;
                if (is_file($path)) { @unlink($path); }
            }
        }
    }

    // ----- รูปภาพเพิ่มเติม: เพิ่มรูปใหม่ -----
    if (!empty($_FILES['additional_images']['name'][0])) {
        $insImg = db()->prepare("INSERT INTO product_images (product_id, image) VALUES (?, ?)");
        foreach ($_FILES['additional_images']['name'] as $i => $fileName) {
            if ($_FILES['additional_images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $subExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($subExt, $allowed_ext, true)) continue;
            $subImg = uniqid('sub.') . '.' . $subExt;
            if (move_uploaded_file($_FILES['additional_images']['tmp_name'][$i], __DIR__ . '/uploads/' . $subImg)) {
                $insImg->execute([$id, $subImg]);
            }
        }
    }

    // ----- สต็อกแต่ละไซส์ -----
    $all_sizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    foreach ($all_sizes as $sz) {
        $stock_val = isset($_POST['stock_' . $sz]) ? max(0, (int)$_POST['stock_' . $sz]) : 0;

        $checkSizeStmt = db()->prepare("SELECT COUNT(*) FROM product_sizes WHERE product_id = ? AND size = ?");
        $checkSizeStmt->execute([$id, $sz]);
        if ($checkSizeStmt->fetchColumn() > 0) {
            db()->prepare("UPDATE product_sizes SET stock = ? WHERE product_id = ? AND size = ?")->execute([$stock_val, $id, $sz]);
        } else {
            db()->prepare("INSERT INTO product_sizes (product_id, size, stock) VALUES (?, ?, ?)")->execute([$id, $sz, $stock_val]);
        }
    }

    header("Location: admin.php");
    exit;
}

// รูปภาพเพิ่มเติมที่มีอยู่
$imgStmt = db()->prepare("SELECT image FROM product_images WHERE product_id = ?");
$imgStmt->execute([$id]);
$extraImages = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

// สต็อกเดิม
$stmtSizes = db()->prepare("SELECT size, stock FROM product_sizes WHERE product_id = ?");
$stmtSizes->execute([$id]);
$existing_stocks = $stmtSizes->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขสินค้า</title>
    <style>
        body { font-family: sans-serif; background: #faf8f8; padding: 20px; }
        .form-container { max-width: 650px; margin: 0 auto; background: #fff; padding: 70px; border: 1px solid #ddd; }
        h2 { margin-top: 0; font-size: 30px; }
        .form-group { margin-bottom: 10px; }
        label { display: block; margin-bottom: 25px; font-weight: bold; font-size: 16px; }
        input[type="text"], input[type="number"], input[type="file"] { width: 100%; padding: 12px; box-sizing: border-box; border: 1px solid #ccc; font-size: 15px; }
        .btn-submit { background: #000; color: #fff; padding: 20px 30px; border: none; cursor: pointer; font-weight: bold; margin-top: 10px; }
        .btn-submit:hover { background: #333; }
        .back-link { display: inline-block; margin-bottom: 30px; color: #333; text-decoration: none; font-size: 20px; }
        .extra-grid { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 14px; }
        .extra-item { width: 90px; text-align: center; font-size: 12px; font-weight: normal; margin: 0; cursor: pointer; }
        .extra-item img { width: 90px; height: 90px; object-fit: cover; border: 1px solid #ddd; display: block; margin-bottom: 4px; transition: opacity .15s, border-color .15s; }
        .extra-item input { margin-right: 4px; }
        .extra-item:has(input:checked) img { opacity: .35; border-color: #d62828; }
        .extra-item:has(input:checked) span { color: #d62828; font-weight: bold; }
    </style>
</head>
<body>

<div class="form-container">
    <a href="admin.php" class="back-link">&larr; กลับหน้าจัดการ</a>
    <h2>แก้ไขสินค้า</h2>

    <form action="" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label>ชื่อสินค้า</label>
            <input type="text" name="name" value="<?= e($product['name']) ?>" required>
        </div>

        <div class="form-group">
            <label>ราคา (บาท)</label>
            <input type="number" step="0.01" name="price" value="<?= e($product['price']) ?>" required>
        </div>

        <div class="form-group">
            <label>รูปสินค้า</label>
            <?php if (!empty($product['image'])): ?>
                <div style="margin-bottom: 8px;">
                    <img src="uploads/<?= e($product['image']) ?>" alt="" width="60" style="vertical-align: middle; border: 1px solid #ddd;">
                    <span style="font-size: 12px; color: #666;">(รูปปัจจุบัน)</span>
                </div>
            <?php endif; ?>
            <input type="file" name="image" accept="image/*">
        </div>

        <div class="form-group">
            <label>รูปภาพเพิ่มเติม</label>
            <?php if ($extraImages): ?>
                <div class="extra-grid">
                    <?php foreach ($extraImages as $ex): ?>
                        <label class="extra-item">
                            <img src="uploads/<?= e($ex) ?>" alt="">
                            <input type="checkbox" name="delete_images[]" value="<?= e($ex) ?>"><span>ลบรูปนี้</span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div style="font-size: 12px; color: #666; margin-bottom: 8px; font-weight: normal;">ติ๊ก "ลบรูปนี้" เพื่อลบรูปเดิม และเลือกไฟล์ด้านล่างเพื่อเพิ่มรูปใหม่ (กดบันทึกสินค้าเพื่อยืนยัน)</div>
            <?php else: ?>
                <div style="font-size: 12px; color: #666; margin-bottom: 8px; font-weight: normal;">(ยังไม่มีรูปภาพเพิ่มเติม)</div>
            <?php endif; ?>
            <input type="file" name="additional_images[]" accept="image/*" multiple>
        </div>

        <div class="form-group">
            <label>รายละเอียดสินค้า</label>
            <textarea name="description" rows="4" style="width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; font-family: inherit;" placeholder="กรอกรายละเอียดสินค้า..."><?= e($product['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label>จัดการสต็อกแต่ละไซส์</label>
            <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                <?php foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL'] as $sz): ?>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span style="font-weight: bold; min-width: 25px;"><?= $sz ?></span>
                        <input type="number" name="stock_<?= $sz ?>" value="<?= (int)($existing_stocks[$sz] ?? 0) ?>" min="0" style="padding: 6px; width: 60px;">
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn-submit" style="margin-top: 25px;">บันทึกสินค้า</button>
        </div>
    </form>
</div>

</body>
</html>
