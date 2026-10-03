<?php
require 'config.php'; require_login();
$_SESSION['cart'] = $_SESSION['cart'] ?? [];

// ล้างรายการที่ผิดรูปแบบ หรือสินค้าถูกลบออกจากฐานข้อมูลแล้ว (กันเลขตะกร้าค้าง)
foreach ($_SESSION['cart'] as $k => $it) {
    if (!is_array($it) || empty($it['product_id'])) { unset($_SESSION['cart'][$k]); }
}
if ($_SESSION['cart']) {
    $ids = array_values(array_unique(array_map('intval', array_column($_SESSION['cart'], 'product_id'))));
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $stc = db()->prepare("SELECT id FROM products WHERE id IN ($ph)");
    $stc->execute($ids);
    $exist = array_map('intval', $stc->fetchAll(PDO::FETCH_COLUMN));
    foreach ($_SESSION['cart'] as $k => $it) {
        if (!in_array((int)$it['product_id'], $exist, true)) { unset($_SESSION['cart'][$k]); }
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));

$cartCount = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartCount += $item['qty'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $size = $_POST['size'] ?? '';
    $qty = isset($_POST['qty']) ? max(1, (int)$_POST['qty']) : 1;

    $st = db()->prepare("SELECT sizes, name, price, image FROM products WHERE id=?");
    $st->execute([$id]);
    $p = $st->fetch();

    if ($p && in_array($size, explode(',', $p['sizes']), true)) {
        $key = $id . '-' . $size;
        
        $_SESSION['cart'][$key] = [
            'product_id' => $id,
            'name' => $p['name'],
            'price' => $p['price'],
            'image' => $p['image'],
            'size' => $size,
            'qty' => ($_SESSION['cart'][$key]['qty'] ?? 0) + $qty
        ];
    }
    
    // คำขอแบบ AJAX (จากแอนิเมชันลอยลงตะกร้า) -> ตอบ JSON แทนการรีเฟรชหน้า
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
        $count = 0;
        foreach ($_SESSION['cart'] as $it) { $count += $it['qty']; }
        header('Content-Type: application/json');
        echo json_encode(['ok' => (bool)$p, 'count' => $count]);
        exit;
    }

    header('Location: shop.php?page=' . $page . '&added=1');
    exit;
}

// คำนวณจำนวนสินค้าทั้งหมดในตะกร้าจาก Session สำหรับนำไปแสดงผลบนปุ่มตะกร้า
$cartCount = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartCount += $item['qty'];
}

$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    // ถมีการค้นหา ให้นับจำนวนและดึงข้อมูลเฉพาะที่ตรงกับคำค้นหา
    $stmt = db()->prepare("SELECT COUNT(*) FROM products WHERE name LIKE ?");
    $stmt->execute(['%' . $search . '%']);
    $total = (int)$stmt->fetchColumn();
    
    $pages = max(1, ceil($total / PER_PAGE));
    $page = min($page, $pages);
    $off = ($page - 1) * PER_PAGE;
    
    $stmt = db()->prepare("SELECT * FROM products WHERE name LIKE ? ORDER BY id DESC LIMIT " . PER_PAGE . " OFFSET " . $off);
    $stmt->execute(['%' . $search . '%']);
    $items = $stmt->fetchAll();
} else {
    // ถ้าไม่มีการค้นหา ใช้แบบเดิมปกติ
    $total = (int)db()->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $pages = max(1, ceil($total / PER_PAGE));
    $page = min($page, $pages);
    $off = ($page - 1) * PER_PAGE;
    
    $items = db()->query("SELECT * FROM products ORDER BY id DESC LIMIT " . PER_PAGE . " OFFSET " . $off)->fetchAll();
}
?>
<!DOCTYPE html><html lang="th"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>เลือกสินค้า | SIRASIT SHOP</title><link rel="stylesheet" href="assets/style.css"></head>
<style>
  /* กรอบสินค้า 1 ชิ้น = 1 ช่อง */
  .grid form.add-form {
    display: flex; flex-direction: column;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    padding: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.06);
    transition: transform .2s ease, box-shadow .2s ease;
  }
  .grid form.add-form:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,.12); }
  .grid form.add-form .img { border: none !important; box-shadow: none !important; padding: 0 !important; margin: 0 !important; }
  .p-name { margin: 12px 0 4px; min-height: 2.8em; line-height: 1.4; font-size: 15px; font-weight: 600; }
  .p-name a { color: #222; text-decoration: none;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .p-name a:hover { color: #000; text-decoration: underline; }
  .grid form.add-form .p-price { margin: 0 0 12px !important; padding: 0 !important; font-size: 20px !important; font-weight: 700 !important; color: #16a34a !important; }
  /* ===== ไอคอนค้นหาบนเมนูด้านบน ===== */
  .top .nav-search { display: inline-flex; align-items: center; gap: 8px; margin: 0; }
  .top .nav-search input.ns-input {
    width: 0; padding: 0; margin: 0; opacity: 0; border: 1.5px solid transparent; border-radius: 20px;
    background: #fff; font: inherit; font-size: .95rem; height: 38px; box-sizing: border-box;
    transition: width .25s ease, padding .25s ease, opacity .2s ease, border-color .2s ease;
  }
  .top .nav-search.open input.ns-input { width: 260px; max-width: 55vw; padding: 0 16px; opacity: 1; border-color: #080808; }
  .top .nav-search input.ns-input:focus { outline: none; border-color: #080808; box-shadow: 0 0 0 3px rgba(0,0,0,.12); }
  .ns-clear { display: none; font-size: .85rem; font-weight: 700; color: #6b7a90; text-decoration: none; white-space: nowrap; }
  .nav-search.open .ns-clear { display: inline; }
  .ns-clear:hover { color: #c0262b; }
</style>
<body>
<header class="top">
    <a class="logo" href="shop.php">
        <img src="assets/logo.png" alt="SIRASIT SHOP" style="height: 55px; vertical-align: middle;">
    </a>
    <nav>
        <form action="shop.php" method="GET" class="nav-search<?= $search !== '' ? ' open' : '' ?>" id="navSearch" role="search">
            <input type="text" name="search" class="ns-input" id="searchInput" placeholder="พิมพ์ชื่อสินค้าที่ต้องการค้นหา..." value="<?= e($search) ?>" autocomplete="off">
            <?php if ($search !== ''): ?><a href="shop.php" class="ns-clear" title="ล้างการค้นหา">ล้าง</a><?php endif; ?>
            <button type="button" class="nav-btn icon ns-toggle" id="searchToggle" title="ค้นหาสินค้า" aria-label="ค้นหาสินค้า">
                <svg class="nav-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/></svg>
            </button>
        </form>
        <span class="nav-user"><?= nav_icon('user') ?><span class="lbl">สวัสดี <?= e($_SESSION['user']['name']) ?></span></span>
        <a class="nav-btn" href="my_orders.php"><?= nav_icon('orders') ?><span class="lbl">ประวัติคำสั่งซื้อ</span></a>
        <?= mail_nav_link('user') ?>
        <button type="button" class="nav-toggle" id="navToggle" aria-label="เมนู">☰</button>
        <a class="nav-btn primary" href="checkout.php" id="cart-btn"><?= nav_icon('cart') ?><span class="lbl">ตะกร้า</span> (<span id="cart-count"><?= $cartCount; ?></span>)</a>
        <a class="nav-btn icon logout" href="logout.php" title="ออกจากระบบ" aria-label="ออกจากระบบ"><?= nav_icon('logout') ?><span class="lbl">ออกจากระบบ</span></a>
    </nav>
</header>
<main class="wrap">
<!-- โครงสร้างแบบ 3 คอลัมน์: โฆษณาซ้าย | สินค้าตรงกลาง | โฆษณาขวา -->
<div style="display: flex; gap: 20px; margin-top: 20px; align-items: flex-start;">
    
    <!-- ฝั่งซ้าย: พื้นที่โฆษณาซ้าย -->
<?php
// ดึงรูปโฆษณาจากโฟลเดอร์ assets อัตโนมัติ (ไม่ต้องแก้โค้ดเมื่อเพิ่ม/ลบรูป)
// ซ้าย: ไฟล์ชื่อ SD1, SD2, ... หรือ s1, s2, ...   ขวา: ไฟล์ชื่อ U1, U2, ... หรือ sss1, sss2, ...
function ad_images($patterns) {
    $found = [];
    foreach ($patterns as $pat) {
        foreach (glob(__DIR__ . '/assets/' . $pat . '.{png,jpg,jpeg,webp,gif,PNG,JPG,JPEG,WEBP}', GLOB_BRACE) ?: [] as $f) {
            $found[strtolower(basename($f))] = 'assets/' . basename($f);
        }
    }
    ksort($found, SORT_NATURAL);
    return array_values($found);
}
$leftAds  = ad_images(['SD[0-9]*', 's[0-9]*']);
$rightAds = ad_images(['U[0-9]*', 'sss[0-9]*']);
?>
<div style="width: 320px; flex-shrink: 0;">
    <?php if ($leftAds): ?>
    <div style="background: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 6px; text-align: center;">
        <a href="#" target="_blank">
            <img id="promo-banner" src="<?= e($leftAds[0]) ?>" alt="Advertisement" style="width: 100%; height: auto; border-radius: 4px; transition: opacity 0.5s ease-in-out;">
        </a>
    </div>
    <?php endif; ?>
</div>
<?php if (count($leftAds) > 1): ?>
<script>
    const images = <?= json_encode($leftAds) ?>;
    let currentIndex = 0;
    const bannerImg = document.getElementById("promo-banner");
    setInterval(() => {
        bannerImg.style.opacity = 0;
        setTimeout(() => {
            currentIndex = (currentIndex + 1) % images.length;
            bannerImg.src = images[currentIndex];
            bannerImg.style.opacity = 1;
        }, 500);
    }, 5500);
</script>
<?php endif; ?>

   <div style="flex-grow: 1;">
    <?php if (isset($_GET['added'])): ?>
  <div id="toast-alert" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #7eff72; color: #020202; padding: 12px 24px; border-radius: 8px; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-weight: bold; font-size: 14px;">
    ✓ เพิ่มลงตะกร้าเรียบร้อยแล้ว
  </div>
  <script>
    setTimeout(() => {
      const toast = document.getElementById('toast-alert');
      if (toast) { 
        toast.style.transition = 'opacity 0.5s ease'; 
        toast.style.opacity = '0'; 
        setTimeout(() => toast.remove(), 500); 
      }
    }, 3000);
  </script>
<?php endif; ?>
    <?php if (!$items): ?><p class="muted">ยังไม่มีสินค้า</p><?php endif; ?>
    <div class="grid"><!-- ลูปแสดงสินค้า... -->
            <?php foreach ($items as $p): ?>
                <form method="POST" action="" class="add-form">
    <div class="img">
        <?php if ($p['image']): ?>
            <a href="product_detail.php?id=<?= $p['id'] ?>">
                <img src="uploads/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" style="width: 100%; height: 200px; object-fit: cover; border-radius: 4px;">
            </a>
        <?php else: ?>
            <span style="color: #999;">ไม่มีรูป</span>
        <?php endif; ?>
    </div>
                        
                        <div class="p-name">
                            <a href="product_detail.php?id=<?= $p['id'] ?>"><?= e($p['name']) ?></a>
                        </div>
                        <p class="price p-price">฿<?= number_format($p['price'], 2) ?></p>
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <div style="margin-bottom: 10px; text-align: center;">
                        <select name="size" required style="width: 100%; padding: 6px; border: 1px solid #ccc; font-size: 14px;">
                            <option value="" disabled selected>-- เลือกไซส์ --</option>
                            <?php 
                            $sizes = explode(',', $p['sizes']);
                            foreach ($sizes as $sz):
                                $sz = trim($sz);
                                if (!empty($sz)):
                            ?>
                                <option value="<?= e($sz) ?>"><?= e($sz) ?></option>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </select>
                    </div>
                    <div class="btn" style="background: transparent; padding: 0; box-shadow: none;">
                <button type="submit" class="btn" style="width: 100%; padding: 10px 16px; background: #212529; color: #fff; border: none; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: bold; font-size: 14px; transition: background 0.2s;">
             <span>ใส่ตะกร้า</span>
             <span style="font-size: 16px;"></span>
            </button>
            </div>
                </form>
    <?php endforeach; ?>
</div> <!-- ปิด div class="grid" -->
</div> <!-- ปิดกล่องกลาง (flex-grow: 1) -->

<!-- ฝั่งขวา: พื้นที่โฆษณาขวา -->
<div style="width: 320px; flex-shrink: 0;">
    <?php if ($rightAds): ?>
    <div style="background: #fff; border: 1px solid #ddd; padding: 10px; border-radius: 6px; text-align: center;">
        <a href="#" target="_blank">
            <img id="promo-banner-right" src="<?= e($rightAds[0]) ?>" alt="Right Ad" style="width: 100%; height: auto; border-radius: 4px; transition: opacity 0.5s ease-in-out;">
        </a>
    </div>
    <?php endif; ?>
</div>
<?php if (count($rightAds) > 1): ?>
<script>
    const rightImages = <?= json_encode($rightAds) ?>;
    let rightIndex = 0;
    const rightImg = document.getElementById("promo-banner-right");
    setInterval(() => {
        rightImg.style.opacity = 0;
        setTimeout(() => {
            rightIndex = (rightIndex + 1) % rightImages.length;
            rightImg.src = rightImages[rightIndex];
            rightImg.style.opacity = 1;
        }, 500);
    }, 5500);
</script>
<?php endif; ?>


</div>
</main>
<nav class="tabs" aria-label="เลือกหน้า">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?page=<?= $i ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="<?= $i == $page ? 'on' : '' ?>">หน้า <?= $i ?></a>
  <?php endfor; ?>
</nav>

<style>
  /* ===== แถบเลือกหน้า (โทนแดง-ขาว) ===== */
  nav.tabs { background: #fff; border-top: 3px solid #080808; box-shadow: 0 -4px 16px rgba(0,0,0,.08); gap: 10px; padding: 12px; }
  nav.tabs a { min-width: 78px; text-align: center; padding: 8px 20px; border: 1.5px solid #030202; border-radius: 999px; background: #fff; color: #0f0f0f; font-weight: 700; font-size: .95rem; letter-spacing: .02em; text-decoration: none; transition: background .15s, color .15s, border-color .15s, transform .1s, box-shadow .15s; }
  nav.tabs a:hover { background: #080808; border-color: #080808; color: #fff; transform: translateY(-2px); }
  nav.tabs a.on, nav.tabs a.on:hover { background: #c0bebe; border-color: #0f0f0f; color: #030303; box-shadow: 0 4px 10px rgba(8, 5, 5, 0.35); transform: none; }
  .fly-img { position: fixed; z-index: 2000; pointer-events: none; border-radius: 8px; object-fit: cover;
             transition: left .8s cubic-bezier(.5,-0.2,.8,.5), top .8s cubic-bezier(.4,-0.3,.7,.6), width .8s ease, height .8s ease, opacity .8s ease; }
  @keyframes cart-pop { 0%{transform:scale(1)} 40%{transform:scale(1.25)} 100%{transform:scale(1)} }
  #cart-btn.pop { animation: cart-pop .4s ease; }
</style>
<script>
    document.getElementById('navToggle')?.addEventListener('click', function () {
  this.closest('nav').classList.toggle('open');
});
document.querySelectorAll('.add-form').forEach(form => {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.reportValidity()) return;            // ยังไม่ได้เลือกไซส์ -> แจ้งเตือนตามปกติ

    const btn = form.querySelector('button[type="submit"]');
    if (btn.disabled) return;
    btn.disabled = true;

    const img = form.querySelector('.img img');
    const cart = document.getElementById('cart-btn');

    // ส่งข้อมูลเข้าตะกร้าเบื้องหลัง
    const req = fetch('shop.php?page=<?= (int)$page ?>', {
      method: 'POST',
      headers: { 'X-Requested-With': 'fetch' },
      body: new FormData(form)
    }).then(r => r.json());

    // แอนิเมชันรูปลอยไปที่ตะกร้า
    if (img && cart) {
      const a = img.getBoundingClientRect();
      const b = cart.getBoundingClientRect();
      const fly = img.cloneNode();
      fly.className = 'fly-img';
      fly.style.cssText = `left:${a.left}px;top:${a.top}px;width:${a.width}px;height:${a.height}px;opacity:1;`;
      document.body.appendChild(fly);
      fly.getBoundingClientRect();                   // บังคับ reflow ก่อนเริ่ม transition
      fly.style.left = (b.left + b.width / 2 - 20) + 'px';
      fly.style.top = (b.top + b.height / 2 - 20) + 'px';
      fly.style.width = '40px';
      fly.style.height = '40px';
      fly.style.opacity = '0.4';
      await new Promise(r => setTimeout(r, 850));
      fly.remove();
    }

    try {
      const data = await req;
      if (!data.ok) throw new Error('add failed');
      document.getElementById('cart-count').textContent = data.count;   // อัปเดตจำนวน
      cart.classList.remove('pop'); void cart.offsetWidth; cart.classList.add('pop');
    } catch (err) {
      form.submit();                                  // ถ้าพลาด ใช้วิธีเดิม (รีเฟรชหน้า)
      return;
    }
    btn.disabled = false;
  });
});
</script>
<script>
(function () {
  const box = document.getElementById('navSearch');
  const input = document.getElementById('searchInput');
  const btn = document.getElementById('searchToggle');
  btn.addEventListener('click', () => {
    if (!box.classList.contains('open')) {          // ยังปิดอยู่ -> เปิดช่องพิมพ์
      box.classList.add('open');
      setTimeout(() => input.focus(), 50);
    } else if (input.value.trim() !== '') {         // เปิดอยู่และมีข้อความ -> ค้นหา
      box.submit();
    } else {                                        // เปิดอยู่แต่ว่าง -> ปิดกลับ
      box.classList.remove('open');
    }
  });
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && input.value.trim() === '') { box.classList.remove('open'); btn.focus(); }
  });
})();
</script>
<script>window.MAIL={as:'user'};</script>
<script src="assets/mail.js"></script>
</body></html>
