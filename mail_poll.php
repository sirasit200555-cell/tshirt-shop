<?php
// ตอบกลับเป็น JSON ให้หน้าเว็บถามซ้ำทุกไม่กี่วินาที (จำนวนจดหมายใหม่ + ข้อความใหม่ในทิกเก็ต)
require 'config.php';
session_write_close();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$asAdmin = ($_GET['as'] ?? '') === 'admin';
$uid = (int)($_SESSION['user']['id'] ?? 0);
if (($asAdmin && !is_admin()) || (!$asAdmin && !$uid)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$out = ['unread' => 0, 'messages' => [], 'status' => null];

if (isset($_GET['ticket'])) {
    $tid = (int)$_GET['ticket'];
    $after = (int)($_GET['after'] ?? 0);
    if ($asAdmin) {
        $st = db()->prepare("SELECT id, status FROM tickets WHERE id=?");
        $st->execute([$tid]);
    } else {
        $st = db()->prepare("SELECT id, status FROM tickets WHERE id=? AND user_id=?");
        $st->execute([$tid, $uid]);
    }
    $t = $st->fetch();
    if (!$t) { http_response_code(404); echo json_encode(['error' => 'not found']); exit; }

    $other = $asAdmin ? 'customer' : 'admin';
    $ms = db()->prepare("SELECT id, sender, body, created_at FROM ticket_messages WHERE ticket_id=? AND id>? ORDER BY id ASC");
    $ms->execute([$tid, $after]);
    foreach ($ms->fetchAll() as $m) {
        $out['messages'][] = ['id' => (int)$m['id'], 'sender' => $m['sender'], 'body' => $m['body'], 'time' => fmt_time($m['created_at'])];
    }
    // คนที่เปิดอ่านเธรดอยู่ถือว่าอ่านจดหมายของอีกฝั่งแล้ว
    db()->prepare("UPDATE ticket_messages SET is_read=1 WHERE ticket_id=? AND sender=? AND is_read=0")->execute([$tid, $other]);
    $out['status'] = $t['status'];
}

$out['unread'] = $asAdmin ? mail_unread_admin() : mail_unread_user($uid);
echo json_encode($out, JSON_UNESCAPED_UNICODE);
