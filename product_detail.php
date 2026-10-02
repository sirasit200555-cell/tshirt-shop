<?php
require_once 'config.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pdo = db();

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt_sizes = $pdo->prepare("SELECT size, stock FROM product_sizes WHERE product_id = ?");
$stmt_sizes->execute([$id]);
$sizes_stock = $stmt_sizes->fetchAll(PDO::FETCH_KEY_PAIR); // จะได้อาเรย์เก็บค่า [ 'XS' => 10, 'S' => 0, 'L' => 2, ... ]

// ดึงรูปภาพเพิ่มเติมจากตาราง product_images
$imagesStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ?");
$imagesStmt->execute([$id]);
$productImages = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name'] ?? 'รายละเอียดสินค้า') ?> | SIRASIT SHOP</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header class="top">
        <a class="logo" href="shop.php"><img src="assets/logo.png" alt="SIRASIT SHOP" style="height: 50px; vertical-align: middle;"></a>
<nav class="nav-menu">
    <a href="shop.php" class="nav-link"><i class="fas fa-store"></i> เลือกซื้อสินค้า</a>
    <a href="my_orders.php" class="nav-link"><i class="fas fa-history"></i> ประวัติคำสั่งซื้อ</a>
   <a href="checkout.php" class="nav-link cart-btn"><i class="fas fa-shopping-cart"></i> ตะกร้า <span class="cart-badge"><?php echo $cartCount ?? 0; ?></span></a>
</nav>
    </header>

    <main class="wrap" style="max-width: 1250px; margin: 70px auto;">
        <?php if (!$product): ?>
            <p class="alert">ไม่พบสินค้าที่คุณต้องการ</p>
            <div style="text-align: center; margin-top: 20px;">
                <a class="btn" href="shop.php">กลับไปเลือกซื้อสินค้า</a>
            </div>
        <?php else: ?>
            <div style="display: flex; gap: 40px; background: #fff; padding: 30px; border-radius: 8px; border: 1px solid #e5e7eb; align-items: flex-start; width: 100%;">
    <!-- คอลัมน์ซ้าย: แกลเลอรีรูปภาพ -->
    <div style="flex: 1; text-align: center;">
    <!-- รูปภาพหลัก -->
    <img id="mainImage" src="uploads/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="width: 100%; max-height: 400px; object-fit: contain; border-radius: 6px; margin-bottom: 15px;">

    <!-- รูปภาพย่อย (Thumbnails) ถ้ามีรูปเพิ่มเติม -->
    <?php if (!empty($productImages)): ?>
        <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
            <!-- แสดงรูปหลักอันแรกเป็น Thumbnail แรกด้วย -->
            <img src="uploads/<?= htmlspecialchars($product['image']) ?>" onclick="changeImage(this.src)" style="width: 60px; height: 60px; object-fit: cover; cursor: pointer; border-radius: 4px; border: 2px solid #ccc;">
            
            <!-- วนลูปแสดงรูปภาพเพิ่มเติม -->
            <?php foreach ($productImages as $img): ?>
                <img src="uploads/<?= htmlspecialchars($img['image']) ?>" onclick="changeImage(this.src)" style="width: 60px; height: 60px; object-fit: cover; cursor: pointer; border-radius: 4px; border: 2px solid #ccc;">
            <?php endforeach; ?>
        </div>

        <!-- Script สำหรับเปลี่ยนรูปใหญ่เมื่อคลิกรูปย่อย -->
        <script>
            function changeImage(src) {
                document.getElementById('mainImage').src = src;
            }
        </script>
    <?php endif; ?>
</div>
              <div style="flex: 1;">
            <h2><?= htmlspecialchars($product['name']) ?></h2>
            <p style="font-size: 24px; font-weight: bold; color: #111; margin: 15px 0;">฿<?= number_format($product['price'], 2) ?></p>
            <p style="color: #666; line-height: 1.6; margin-bottom: 20px;">
                <?= nl2br(htmlspecialchars($product['description'] ?? 'ไม่มีรายละเอียดสินค้าเพิ่มเติม')) ?>
            </p>
            
           <!-- ฟอร์มสำหรับเลือกไซส์และเพิ่มลงตะกร้า -->
            <form action="shop.php" method="post">
                <input type="hidden" name="id" value="<?= $product['id'] ?>">

                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; font-size: 16px;">เลือกไซส์</label>
                    <select name="size" id="sizeSelect" required style="width: 100%; padding: 12px; border: 1.5px solid #ccc; border-radius: 6px; font-size: 16px;" onchange="updateStockDisplay()">
                        <option value="" disabled selected>-- เลือกไซส์ --</option>
                        <?php foreach ($sizes_stock as $size => $stock): ?>
                            <option value="<?= htmlspecialchars($size) ?>" data-stock="<?= $stock ?>">
                                ไซส์ <?= htmlspecialchars($size) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- ข้อความแสดงจำนวนสต็อกคงเหลือแบบเรียลไทม์ -->
                <div id="stockDisplay" style="margin-bottom: 20px; font-size: 18px; color: #555; font-weight: 500;">
                    กรุณาเลือกไซส์สินค้า
                </div>

                <!-- ช่องเลือกจำนวน -->
            <div style="margin-bottom: 25px;">
                <label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 16px; color: #333;">จำนวน</label>
                <div style="display: inline-flex; align-items: center; border: 1.5px solid #d1d5db; border-radius: 8px; overflow: hidden; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                <button type="button" onclick="this.parentNode.querySelector('input[type=number]').stepDown()" style="width: 40px; height: 40px; background: #f9fafb; border: none; font-size: 18px; color: #374151; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f9fafb'">-</button>
                <input type="number" name="qty" value="1" min="1" max="99" style="width: 50px; height: 40px; text-align: center; border: none; border-left: 1.5px solid #d1d5db; border-right: 1.5px solid #d1d5db; outline: none; font-size: 16px; font-weight: 600; color: #111; background: #fff;" />
                <button type="button" onclick="this.parentNode.querySelector('input[type=number]').stepUp()" style="width: 40px; height: 40px; background: #f9fafb; border: none; font-size: 18px; color: #374151; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f9fafb'">+</button>
             </div>
        </div>

                <!-- ปุ่มกดต่างๆ อยู่ล่างสุด -->
                <div style="display: flex; gap: 12px; margin-top: 10px;">
    <button type="submit" id="addToCartBtn" style="flex: 1; background: #aeebf3; color: #070707; border: none; padding: 12px 20px; border-radius: 6px; font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s;" title="เพิ่มลงตะกร้า">เพิ่มลงตะกร้า 🛒</button>
    <a href="shop.php" style="flex: 1; text-align: center; background: #e5e7eb; color: #333; text-decoration: none; padding: 12px 20px; border-radius: 6px; font-size: 16px; font-weight: 600; line-height: normal;">กลับหน้าหลัก ↩</a>
</div>
            </form>
        </div>
        <script>
        function updateStockDisplay() {
    const select = document.getElementById('sizeSelect');
    const stockDisplay = document.getElementById('stockDisplay');
    const selectedOption = select.options[select.selectedIndex];
    const addToCartBtn = document.getElementById('addToCartBtn'); // ดึงปุ่มเพิ่มลงตะกร้ามาควบคุม

    if (selectedOption.value === "") {
        stockDisplay.innerText = "กรุณาเลือกไซส์สินค้า";
        stockDisplay.style.color = "#555";
        addToCartBtn.disabled = false;
        addToCartBtn.style.background = "#5a5a5a";
        addToCartBtn.style.cursor = "pointer";
    } else {
        const stock = parseInt(selectedOption.getAttribute('data-stock'));
        
        if (stock === 0) {
            stockDisplay.innerText = "⚠️ สินค้าคงเหลือ: 0 ชิ้น (สินค้าหมด)";
            stockDisplay.style.color = "#dc2626"; // สีแดงเตือน
            
            // ล็อกปุ่ม: ปิดการใช้งานและเปลี่ยนเป็นสีเทา
            addToCartBtn.disabled = true;
            addToCartBtn.style.background = "#d1d5db";
            addToCartBtn.style.cursor = "not-allowed";
        } else {
            stockDisplay.innerText = "สินค้าคงเหลือ: " + stock + " ชิ้น";
            stockDisplay.style.color = "#28a745"; // สีเขียวปกติ
            
            // เปิดใช้งานปุ่มปกติ
            addToCartBtn.disabled = false;
            addToCartBtn.style.background = "#5a5a5a";
            addToCartBtn.style.cursor = "pointer";
        }
    }
}
</script>
    <?php endif; ?>
</main>
</body>
</html>