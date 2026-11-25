<?php
// =====================================================
// ระบบขนส่งจำลอง (ไม่ได้เชื่อมต่อขนส่งจริง ใช้สาธิตให้เห็นขั้นตอนเหมือนจริง)
//  1) ร้านกด "จัดส่งแล้ว"  → ระบบ "จองขนส่ง" ให้อัตโนมัติ และออกเลขพัสดุ (courier_book)
//  2) สถานะพัสดุเดินหน้าเองตามเวลา (courier_timeline) ทุก COURIER_STEP_MINUTES นาที
//  3) พัสดุถึงมือลูกค้า → ออเดอร์เปลี่ยนเป็น "สำเร็จ" อัตโนมัติ (courier_sync)
// =====================================================

// ออกเลขพัสดุ (จำลองการเรียก API ของขนส่ง)
function courier_book(string $company): string {
    $list = shipping_companies();
    $prefix = $list[$company][4] ?? 'TH';
    return $prefix . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
}

// เหตุการณ์ของพัสดุ: [รหัสข้อความ] ตามลำดับ
function courier_event_keys(): array {
    return ['ev_booked', 'ev_picked', 'ev_origin_hub', 'ev_transit', 'ev_dest_hub', 'ev_out', 'ev_delivered'];
}

// สร้างไทม์ไลน์จากเวลาที่ส่งออก: คืน [ ['key'=>..,'label'=>..,'time'=>timestamp|null,'done'=>bool], ... ]
function courier_timeline(?string $shippedAt, ?int $now = null): array {
    $now = $now ?? time();
    $start = $shippedAt ? strtotime($shippedAt) : null;
    $out = [];
    foreach (courier_event_keys() as $i => $k) {
        $ts = $start ? $start + $i * COURIER_STEP_MINUTES * 60 : null;
        $out[] = ['key' => $k, 'label' => t($k), 'time' => $ts, 'done' => $ts !== null && $ts <= $now];
    }
    return $out;
}
function courier_delivered(?string $shippedAt): bool {
    $tl = courier_timeline($shippedAt);
    return (bool)end($tl)['done'];
}

// ถ้าพัสดุส่งถึงแล้ว แต่ออเดอร์ยังเป็น "จัดส่งแล้ว" → เปลี่ยนเป็น "สำเร็จ" (เหมือน webhook จากขนส่ง)
function courier_sync($conn, array $order): array {
    if ($order['status'] === 'shipped' && !empty($order['shipped_at']) && courier_delivered($order['shipped_at'])) {
        $stmt = $conn->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND status = 'shipped'");
        $stmt->bind_param('i', $order['id']);
        $stmt->execute();
        $order['status'] = 'completed';
    }
    return $order;
}

// กล่องแสดงไทม์ไลน์ ใช้ในหน้าออเดอร์ / หน้าติดตามพัสดุ / แอดมิน
function render_timeline(?string $shippedAt): void {
    if (!$shippedAt) return;
    $tl = courier_timeline($shippedAt);
    $lastDone = -1;
    foreach ($tl as $i => $ev) if ($ev['done']) $lastDone = $i;
    echo '<ol class="tracking">';
    foreach (array_reverse($tl, true) as $i => $ev) {
        if (!$ev['done']) continue;
        echo '<li class="' . ($i === $lastDone ? 'now' : '') . '"><span class="t-dot"></span><div><strong>' . e($ev['label']) . '</strong><br><small class="muted">'
            . e(date('d/m/Y H:i', $ev['time'])) . '</small></div></li>';
    }
    echo '</ol>';
}
