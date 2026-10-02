<?php
require 'config.php';
// หน้านี้สำหรับแอดมินเท่านั้น (ต้องเข้าสู่ระบบแอดมินที่ admin.php ก่อน)
if (!is_admin()) { header('Location: admin.php'); exit; }

// ===== ตอบกลับ / ปิดเรื่อง / เปิดเรื่องอีกครั้ง =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rid = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    $chk = db()->prepare("SELECT id FROM tickets WHERE id=?");
    $chk->execute([$rid]);
    if ($chk->fetch()) {
        if ($act === 'reply') {
            $body = mb_substr(trim($_POST['body'] ?? ''), 0, 5000);
            if ($body !== '') {
                db()->prepare("INSERT INTO ticket_messages (ticket_id, sender, body) VALUES (?, 'admin', ?)")->execute([$rid, $body]);
                db()->prepare("UPDATE tickets SET status='answered', updated_at=NOW() WHERE id=?")->execute([$rid]);
            }
        } elseif ($act === 'close') {
            db()->prepare("UPDATE tickets SET status='closed', updated_at=NOW() WHERE id=?")->execute([$rid]);
        } elseif ($act === 'reopen') {
            db()->prepare("UPDATE tickets SET status='open', updated_at=NOW() WHERE id=?")->execute([$rid]);
        }
    }
    header('Location: admin_messages.php?id=' . $rid); exit;
}

$tid = (int)($_GET['id'] ?? 0);
$view = 'list';
if ($tid) {
    $st = db()->prepare("SELECT t.*, u.name AS customer_name FROM tickets t LEFT JOIN users u ON u.id=t.user_id WHERE t.id=?");
    $st->execute([$tid]);
    $ticket = $st->fetch();
    if (!$ticket) { header('Location: admin_messages.php'); exit; }
    $view = 'thread';
    $cname = $ticket['customer_name'] ?: ('ลูกค้า #' . $ticket['user_id']);
    // เปิดอ่านแล้ว = ถือว่าอ่านจดหมายจากลูกค้าแล้ว
    db()->prepare("UPDATE ticket_messages SET is_read=1 WHERE ticket_id=? AND sender='customer' AND is_read=0")->execute([$tid]);
    $ms = db()->prepare("SELECT * FROM ticket_messages WHERE ticket_id=? ORDER BY id ASC");
    $ms->execute([$tid]);
    $letters = $ms->fetchAll();
    $lastId = $letters ? (int)end($letters)['id'] : 0;
} else {
    $f = $_GET['f'] ?? 'all';
    $where = ''; $params = [];
    if (in_array($f, ['open', 'answered', 'closed'], true)) { $where = 'WHERE t.status=?'; $params[] = $f; }
    else { $f = 'all'; }
    $st = db()->prepare("SELECT t.*, u.name AS customer_name,
        (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id=t.id AND m.sender='customer' AND m.is_read=0) AS unread,
        (SELECT body FROM ticket_messages m WHERE m.ticket_id=t.id ORDER BY m.id DESC LIMIT 1) AS last_body
        FROM tickets t LEFT JOIN users u ON u.id=t.user_id $where
        ORDER BY (unread > 0) DESC, t.updated_at DESC, t.id DESC");
    $st->execute($params);
    $tickets = $st->fetchAll();
}
$statusLabels = ['open' => ticket_status_label('open'), 'answered' => ticket_status_label('answered'), 'closed' => ticket_status_label('closed')];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>กล่องจดหมายลูกค้า | แอดมิน</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="top">
    <a class="logo" href="admin.php">Admin</a>
    <nav>
        <a class="nav-btn" href="admin.php"><?= nav_icon('shirt') ?><span class="lbl">จัดการสินค้า</span></a>
        <a class="nav-btn" href="shop.php"><?= nav_icon('store') ?><span class="lbl">ดูหน้าร้าน</span></a>
        <?= mail_nav_link('admin') ?>
        <a class="nav-btn icon logout" href="admin.php?logout=1" title="ออกจากระบบ" aria-label="ออกจากระบบ"><?= nav_icon('logout') ?></a>
    </nav>
</header>

<main class="mb-wrap">
<?php if ($view === 'list'): ?>
    <div class="mb-head">
        <h1 class="mb-title"><?= mail_icon(34) ?> กล่องจดหมายลูกค้า</h1>
    </div>
    <div class="mb-filter">
        <?php foreach (['all' => 'ทั้งหมด', 'open' => 'รอตอบ', 'answered' => 'ตอบแล้ว', 'closed' => 'ปิดเรื่องแล้ว'] as $k => $lab): ?>
            <a href="admin_messages.php?f=<?= $k ?>" class="<?= $f === $k ? 'on' : '' ?>"><?= $lab ?></a>
        <?php endforeach; ?>
    </div>
    <?php if (!$tickets): ?>
        <div class="mb-empty">ไม่มีจดหมายในหมวดนี้</div>
    <?php else: ?>
        <ul class="mb-list">
        <?php foreach ($tickets as $t): $isNew = $t['unread'] > 0; ?>
            <li><a class="mb-row<?= $isNew ? ' unread' : '' ?>" href="admin_messages.php?id=<?= (int)$t['id'] ?>">
                <?= mail_icon(30, !$isNew) ?>
                <span class="mb-row-main">
                    <span class="mb-subject"><?= e($t['subject']) ?></span>
                    <span class="mb-preview">จาก <?= e($t['customer_name'] ?: ('ลูกค้า #' . $t['user_id'])) ?> — <?= e(preg_replace('/\s+/u', ' ', (string)$t['last_body'])) ?></span>
                </span>
                <span class="mb-meta">
                    <span class="mb-chip <?= e($t['status']) ?>"><?= e(ticket_status_label($t['status'])) ?></span><br>
                    <?= e(fmt_time($t['updated_at'])) ?>
                </span>
            </a></li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <script>window.MAIL={as:'admin',list:true,unread:<?= mail_unread_admin() ?>};</script>

<?php else:
    $labels = ['customer' => $cname, 'admin' => 'แอดมิน (คุณ)']; ?>
    <p><a href="admin_messages.php">← กลับกล่องจดหมาย</a></p>
    <h1 class="mb-subject-line"><?= e($ticket['subject']) ?></h1>
    <div class="mb-sub">
        <span class="mb-chip <?= e($ticket['status']) ?>" id="mbStatus"><?= e(ticket_status_label($ticket['status'])) ?></span>
        <span>จาก <?= e($cname) ?> · เริ่มเมื่อ <?= e(fmt_time($ticket['created_at'])) ?></span>
    </div>
    <div id="mbLetters">
        <?php foreach ($letters as $m) echo render_letter($m, $labels); ?>
    </div>
    <form method="post" class="mb-form">
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
        <label>ตอบกลับลูกค้า<textarea name="body" required placeholder="พิมพ์ข้อความตอบกลับ"></textarea></label>
        <button class="mb-btn" type="submit">ส่งตอบกลับ</button>
    </form>
    <form method="post" class="mb-close">
        <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
        <?php if ($ticket['status'] === 'closed'): ?>
            <button class="mb-btn ghost" name="action" value="reopen" type="submit">เปิดเรื่องอีกครั้ง</button>
        <?php else: ?>
            <button class="mb-btn ghost" name="action" value="close" type="submit">ปิดเรื่องนี้</button>
        <?php endif; ?>
    </form>
    <script>window.MAIL={as:'admin',ticket:<?= (int)$ticket['id'] ?>,lastId:<?= $lastId ?>,labels:<?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,statusLabels:<?= json_encode($statusLabels, JSON_UNESCAPED_UNICODE) ?>};</script>
<?php endif; ?>
</main>
<script src="assets/mail.js"></script>
</body>
</html>
