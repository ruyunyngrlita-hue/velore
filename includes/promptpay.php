<?php
// =====================================================
// QR พร้อมเพย์ (PromptPay) แบบระบุยอดเงิน ตามมาตรฐาน EMVCo / ธนาคารแห่งประเทศไทย
// ตั้งค่าเบอร์/เลขบัตรของร้านที่ includes/config.php → PROMPTPAY_ID
// =====================================================
require_once __DIR__ . '/qr.php';

function promptpay_crc16(string $s): string {
    $crc = 0xFFFF;
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        $crc ^= ord($s[$i]) << 8;
        for ($b = 0; $b < 8; $b++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

function promptpay_payload(string $id, float $amount): string {
    $id = preg_replace('/\D/', '', $id);
    $f = fn(string $tag, string $val) => $tag . str_pad((string)strlen($val), 2, '0', STR_PAD_LEFT) . $val;

    if (strlen($id) >= 13) {                  // เลขบัตรประชาชน / เลขผู้เสียภาษี 13 หลัก
        $target = $f('02', substr($id, 0, 13));
    } else {                                  // เบอร์โทร 10 หลัก → 0066 + 9 หลักหลัง
        $phone = '0066' . substr(ltrim($id, '0'), -9);
        $target = $f('01', $phone);
    }
    $merchant = $f('00', 'A000000677010111') . $target;

    $data = $f('00', '01')
          . $f('01', $amount > 0 ? '12' : '11')       // 12 = QR ใช้ครั้งเดียวระบุยอด
          . $f('29', $merchant)
          . $f('58', 'TH')
          . $f('53', '764');
    if ($amount > 0) $data .= $f('54', number_format($amount, 2, '.', ''));
    $data .= '6304';
    return $data . promptpay_crc16($data);
}

// แสดงกล่อง QR พร้อมเพย์ (ใช้ในหน้าสั่งซื้อสำเร็จ / หน้าออเดอร์)
function render_promptpay_box(float $amount): void {
    if (PROMPTPAY_ID === '') return;
    $svg = QrCode::svg(promptpay_payload(PROMPTPAY_ID, $amount), 'PromptPay QR');
    ?>
    <div class="qr-box">
        <div class="qr-img"><?= $svg ?></div>
        <div class="qr-text">
            <strong><?= e(t('qr_title')) ?></strong>
            <p><?= e(t('qr_steps')) ?></p>
            <p class="qr-amount"><?= money($amount) ?></p>
            <p class="muted" style="margin:0"><?= e(PROMPTPAY_NAME) ?></p>
        </div>
    </div>
    <?php
}
