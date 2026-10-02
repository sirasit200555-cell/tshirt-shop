<?php
// ===== ตั้งค่าการเชื่อมต่อ (XAMPP ค่าเริ่มต้น) =====
define('DB_HOST', 'localhost');
define('DB_NAME', 'tshirt_shop');
define('DB_USER', 'root');
define('DB_PASS', '');
// รหัสผ่านแอดมิน (เปลี่ยนได้ตรงนี้)
define('ADMIN_PASSWORD', 'admin1234');
define('PER_PAGE', 8);

session_start();

function db() {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        ensure_mail_tables($pdo);
    }
    return $pdo;
}

// ===== ระบบกล่องจดหมาย (ทิกเก็ต) : สร้างตารางให้อัตโนมัติถ้ายังไม่มี =====
function ensure_mail_tables($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (
      id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, subject VARCHAR(200) NOT NULL,
      status ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX (user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_messages (
      id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL,
      sender ENUM('customer','admin') NOT NULL, body TEXT NOT NULL,
      is_read TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX (ticket_id), FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function is_admin() { return !empty($_SESSION['admin']); }
function fmt_time($ts) { return date('d/m/Y H:i', strtotime($ts)); }
function ticket_status_label($s) {
    return ['open' => 'รอตอบ', 'answered' => 'ตอบแล้ว', 'closed' => 'ปิดเรื่องแล้ว'][$s] ?? $s;
}
// จำนวนจดหมายที่ยังไม่ได้อ่าน
function mail_unread_admin() {
    return (int)db()->query("SELECT COUNT(*) FROM ticket_messages WHERE sender='customer' AND is_read=0")->fetchColumn();
}
function mail_unread_user($uid) {
    $st = db()->prepare("SELECT COUNT(*) FROM ticket_messages m JOIN tickets t ON t.id=m.ticket_id
                         WHERE t.user_id=? AND m.sender='admin' AND m.is_read=0");
    $st->execute([(int)$uid]);
    return (int)$st->fetchColumn();
}
// ไอคอนซองจดหมาย (ปิด = ยังไม่อ่าน, เปิด = อ่านแล้ว)
function mail_icon($px = 22, $open = false) {
    $path = $open
        ? '<path d="M3 9.5l9-6 9 6V19a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19z"/><path d="M3 9.5l9 6 9-6"/>'
        : '<rect x="2.5" y="4.5" width="19" height="15" rx="1.5"/><path d="M3 6.5l9 7 9-7"/>';
    return '<svg class="mb-ico" width="'.$px.'" height="'.$px.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">'.$path.'</svg>';
}
// ออเดอร์ถูกยกเลิกหรือไม่ (สถานะขึ้นต้นด้วยคำว่า "ยกเลิก")
function order_is_cancelled($status) {
    return mb_strpos(trim((string)$status), 'ยกเลิก') === 0;
}
// ข้อความสถานะคำสั่งซื้อ (ยกเลิก = ตัวหนังสือสีแดง, รอดำเนินการ = สีฟ้า)
function order_status_badge($status) {
    $s = trim((string)$status);
    if (order_is_cancelled($s))                return '<span class="st-cancel">ถูกยกเลิกแล้ว</span>';
    if (mb_strpos($s, 'จัดส่งสำเร็จ') === 0)  return '<span class="st done">จัดส่งสำเร็จ</span>';
    if (mb_strpos($s, 'ของจัดส่งแล้ว') === 0) return '<span class="st ship">จัดส่งแล้ว</span>';
    return '<span class="st wait">รอดำเนินการ</span>';
}
// ไอคอนปุ่มเมนูด้านบน
function nav_icon($name) {
    $paths = [
        'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'orders' => '<path d="M9 3h6v3H9z"/><path d="M7 4.5H6a2 2 0 0 0-2 2V20a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6.5a2 2 0 0 0-2-2h-1"/><path d="M9 12h6M9 16h6"/>',
        'store'  => '<path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/>',
        'cart'   => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 7H6"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'users'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 15c2.5.6 4 2.3 4 5"/>',
        'box'    => '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        'shirt'  => '<path d="M8 3L3 6l2 4 2-1v12h10V9l2 1 2-4-5-3a4 4 0 0 1-8 0z"/>',
    ];
    return '<svg class="nav-ico" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">'.($paths[$name] ?? '').'</svg>';
}
// ปุ่มซองจดหมายบนแถบเมนู พร้อมเลขแจ้งเตือน
function mail_nav_link($as) {
    $n = $as === 'admin' ? mail_unread_admin() : mail_unread_user($_SESSION['user']['id'] ?? 0);
    $href = $as === 'admin' ? 'admin_messages.php' : 'contact_admin.php';
    return '<a class="mb-nav" href="'.$href.'" title="กล่องจดหมาย">'.mail_icon(22).'<span class="lbl">กล่องจดหมาย</span>'
         . '<span class="mb-badge" data-mail-badge'.($n > 0 ? '' : ' hidden').'>'.$n.'</span></a>';
}
// จดหมาย 1 ฉบับ (ต้องหน้าตาตรงกับที่ assets/mail.js สร้าง)
function render_letter($m, $labels) {
    return '<article class="mb-letter mb-from-'.e($m['sender']).'" data-id="'.(int)$m['id'].'">'
         . '<header class="mb-letter-head"><span>'.e($labels[$m['sender']] ?? $m['sender']).'</span>'
         . '<time>'.e(fmt_time($m['created_at'])).'</time></header>'
         . '<div class="mb-body">'.e($m['body']).'</div></article>';
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function require_login() {
    if (empty($_SESSION['user'])) { header('Location: index.php'); exit; }
}
// คำนวณจำนวนสินค้าทั้งหมดในตะกร้า
$cartCount = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        if (is_array($item)) {
            $cartCount += $item['qty'] ?? 0;
        } else {
            $cartCount += $item; // รองรับข้อมูลเก่าที่เป็นตัวเลข
        }
    }
}

