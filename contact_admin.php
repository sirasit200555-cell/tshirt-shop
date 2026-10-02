<?php
require 'config.php';
require_login();
$uid = (int)($_SESSION['user']['id'] ?? 0);
$err = '';
$act = $_POST['action'] ?? '';

// ===== ส่งจดหมายใหม่ / ตอบกลับ =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($act === 'new') {
        $subject = mb_substr(trim($_POST['subject'] ?? ''), 0, 200);
        $body = mb_substr(trim($_POST['body'] ?? ''), 0, 5000);
        if ($subject === '' || $body === '') {
            $err = 'กรุณากรอกหัวเรื่องและข้อความให้ครบ';
            $_GET['new'] = 1;
        } else {
            db()->prepare("INSERT INTO tickets (user_id, subject) VALUES (?, ?)")->execute([$uid, $subject]);
            $newId = (int)db()->lastInsertId();
            db()->prepare("INSERT INTO ticket_messages (ticket_id, sender, body) VALUES (?, 'customer', ?)")->execute([$newId, $body]);
            header('Location: contact_admin.php?id=' . $newId . '&sent=1'); exit;
        }
    } elseif ($act === 'reply') {
        $rid = (int)($_POST['id'] ?? 0);
        $body = mb_substr(trim($_POST['body'] ?? ''), 0, 5000);
        $chk = db()->prepare("SELECT id FROM tickets WHERE id=? AND user_id=?");
        $chk->execute([$rid, $uid]);
        if ($chk->fetch() && $body !== '') {
            db()->prepare("INSERT INTO ticket_messages (ticket_id, sender, body) VALUES (?, 'customer', ?)")->execute([$rid, $body]);
            db()->prepare("UPDATE tickets SET status='open', updated_at=NOW() WHERE id=?")->execute([$rid]);
        }
        header('Location: contact_admin.php?id=' . $rid); exit;
    }
}

// ===== เลือกว่าจะแสดงหน้าไหน: list (กล่องจดหมาย) / new (เขียนจดหมาย) / thread (เปิดอ่าน) =====
$view = 'list';
$tid = (int)($_GET['id'] ?? 0);
if ($tid) {
    $st = db()->prepare("SELECT * FROM tickets WHERE id=? AND user_id=?");
    $st->execute([$tid, $uid]);
    $ticket = $st->fetch();
    if (!$ticket) { header('Location: contact_admin.php'); exit; }
    $view = 'thread';
    // เปิดอ่านแล้ว = ถือว่าอ่านจดหมายจากแอดมินแล้ว
    db()->prepare("UPDATE ticket_messages SET is_read=1 WHERE ticket_id=? AND sender='admin' AND is_read=0")->execute([$tid]);
    $ms = db()->prepare("SELECT * FROM ticket_messages WHERE ticket_id=? ORDER BY id ASC");
    $ms->execute([$tid]);
    $letters = $ms->fetchAll();
    $lastId = $letters ? (int)end($letters)['id'] : 0;
} elseif (isset($_GET['new'])) {
    $view = 'new';
} else {
    $st = db()->prepare("SELECT t.*,
        (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id=t.id AND m.sender='admin' AND m.is_read=0) AS unread,
        (SELECT body FROM ticket_messages m WHERE m.ticket_id=t.id ORDER BY m.id DESC LIMIT 1) AS last_body
        FROM tickets t WHERE t.user_id=? ORDER BY t.updated_at DESC, t.id DESC");
    $st->execute([$uid]);
    $tickets = $st->fetchAll();
}
$labels = ['customer' => 'คุณ', 'admin' => 'แอดมิน'];
$statusLabels = ['open' => ticket_status_label('open'), 'answered' => ticket_status_label('answered'), 'closed' => ticket_status_label('closed')];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>กล่องจดหมาย | SIRASIT SHOP</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="top">
    <a class="logo" href="shop.php"><img src="assets/logo.png" alt="SIRASIT SHOP" style="height: 55px; vertical-align: middle;"></a>
    <nav>
        <span class="nav-user"><?= nav_icon('user') ?><span class="lbl">สวัสดี <?= e($_SESSION['user']['name']) ?></span></span>
        <a class="nav-btn" href="shop.php"><?= nav_icon('store') ?><span class="lbl">กลับไปเลือกสินค้า</span></a>
        <a class="nav-btn" href="my_orders.php"><?= nav_icon('orders') ?><span class="lbl">ประวัติคำสั่งซื้อ</span></a>
        <?= mail_nav_link('user') ?>
        <a class="nav-btn primary" href="checkout.php"><?= nav_icon('cart') ?><span class="lbl">ตะกร้า</span> (<?= (int)$cartCount ?>)</a>
        <a class="nav-btn icon logout" href="logout.php" title="ออกจากระบบ" aria-label="ออกจากระบบ"><?= nav_icon('logout') ?></a>
    </nav>
</header>

<main class="mb-wrap">
<?php if ($view === 'list'): ?>
    <div class="mb-head">
        <h1 class="mb-title"><?= mail_icon(34) ?> กล่องจดหมาย</h1>
        <a class="mb-btn" href="contact_admin.php?new=1">เขียนจดหมายถึงแอดมิน</a>
    </div>
    <?php if (!$tickets): ?>
        <div class="mb-empty">ยังไม่มีจดหมาย<br>มีปัญหาหรือข้อสงสัย กด "เขียนจดหมายถึงแอดมิน" ได้เลย</div>
    <?php else: ?>
        <ul class="mb-list">
        <?php foreach ($tickets as $t): $isNew = $t['unread'] > 0; ?>
            <li><a class="mb-row<?= $isNew ? ' unread' : '' ?>" href="contact_admin.php?id=<?= (int)$t['id'] ?>">
                <?= mail_icon(30, !$isNew) ?>
                <span class="mb-row-main">
                    <span class="mb-subject"><?= e($t['subject']) ?></span>
                    <span class="mb-preview"><?= e(preg_replace('/\s+/u', ' ', (string)$t['last_body'])) ?></span>
                </span>
                <span class="mb-meta">
                    <span class="mb-chip <?= e($t['status']) ?>"><?= e(ticket_status_label($t['status'])) ?></span><br>
                    <?= e(fmt_time($t['updated_at'])) ?>
                </span>
            </a></li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <script>window.MAIL={as:'user',list:true,unread:<?= mail_unread_user($uid) ?>};</script>

<?php elseif ($view === 'new'): ?>
    <div class="mb-head">
        <h1 class="mb-title"><?= mail_icon(34) ?> เขียนจดหมายถึงแอดมิน</h1>
        <a class="mb-btn ghost" href="contact_admin.php">ยกเลิก</a>
    </div>
    <?php if ($err): ?><p class="mb-error"><?= e($err) ?></p><?php endif; ?>
    <form method="post" class="mb-form">
        <input type="hidden" name="action" value="new">
        <label>หัวเรื่อง<input name="subject" maxlength="200" required placeholder="เช่น สินค้าไม่ตรงปก / สอบถามการจัดส่ง" value="<?= e($_POST['subject'] ?? '') ?>"></label>
        <label>ข้อความ<textarea name="body" required placeholder="อธิบายปัญหาหรือสิ่งที่อยากสอบถาม แอดมินจะตอบกลับในกล่องจดหมายนี้"><?= e($_POST['body'] ?? '') ?></textarea></label>
        <button class="mb-btn" type="submit">ส่งจดหมาย</button>
    </form>

<?php else: ?>
    <p><a href="contact_admin.php">← กลับกล่องจดหมาย</a></p>
    <?php if (isset($_GET['sent'])): ?><p class="mb-ok">ส่งจดหมายถึงแอดมินแล้ว รอคำตอบในกล่องจดหมายนี้</p><?php endif; ?>
    <h1 class="mb-subject-line"><?= e($ticket['subject']) ?></h1>
    <div class="mb-sub">
        <span class="mb-chip <?= e($ticket['status']) ?>" id="mbStatus"><?= e(ticket_status_label($ticket['status'])) ?></span>
        <span>เริ่มเมื่อ <?= e(fmt_time($ticket['created_at'])) ?></span>
    </div>
    <div id="mbLetters">
        <?php foreach ($letters as $m) echo render_letter($m, $labels); ?>
    </div>
    <form method="post" class="mb-form">
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
        <label>ตอบกลับ<textarea name="body" required placeholder="พิมพ์ข้อความตอบกลับแอดมิน"></textarea></label>
        <?php if ($ticket['status'] === 'closed'): ?><p class="muted small">เรื่องนี้ปิดแล้ว การตอบกลับจะเปิดเรื่องอีกครั้ง</p><?php endif; ?>
        <button class="mb-btn" type="submit">ส่งตอบกลับ</button>
    </form>
    <script>window.MAIL={as:'user',ticket:<?= (int)$ticket['id'] ?>,lastId:<?= $lastId ?>,labels:<?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,statusLabels:<?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>};</script>
<?php endif; ?>
</main>
<script src="assets/mail.js"></script>
</body>
</html>
