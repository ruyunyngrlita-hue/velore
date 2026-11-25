<?php
// =====================================================
// โปรโมชั่นหน้าแรก (ไทย/อังกฤษ) : แก้ข้อความ/ลิงก์ตรงนี้ได้เลย (เพิ่ม-ลบรายการได้)
// 'guest_only' => true  = แสดงเฉพาะคนที่ยังไม่ล็อกอิน
// 'link' เป็นหน้าในเว็บ เช่น 'register.php', 'index.php?cat=1', 'index.php?sort=price_asc'
// =====================================================
$en = current_lang() === 'en';
return [
    [
        'tag'        => 'MEMBER',
        'title'      => $en ? 'Join for free' : 'สมัครสมาชิกฟรี',
        'text'       => $en ? 'Save your name, phone and delivery address to order again in a few clicks, view history and track every order.'
                            : 'บันทึกชื่อ เบอร์โทร และที่อยู่จัดส่งไว้ สั่งซื้อครั้งต่อไปได้ในไม่กี่คลิก พร้อมดูประวัติและติดตามสถานะคำสั่งซื้อ',
        'link'       => 'register.php',
        'cta'        => $en ? 'Sign up' : 'สมัครสมาชิก',
        'guest_only' => true,
    ],
    [
        'tag'        => 'PAYMENT',
        'title'      => $en ? 'Two ways to pay' : 'ชำระเงินได้ 2 วิธี',
        'text'       => $en ? 'Pay by bank transfer (attach your slip online) or cash on delivery, whichever suits you.'
                            : 'เลือกโอนเงินผ่านบัญชีธนาคาร พร้อมแนบสลิปออนไลน์ หรือเก็บเงินปลายทางชำระเมื่อได้รับสินค้า สะดวกตามที่คุณต้องการ',
        'link'       => 'index.php#products',
        'cta'        => $en ? 'Shop now' : 'เลือกซื้อเลย',
    ],
    [
        'tag'        => 'FREE SHIPPING',
        'title'      => $en ? 'Free shipping' : 'ส่งฟรีเมื่อซื้อครบ',
        'text'       => $en ? 'Spend ' . number_format(FREE_SHIPPING_MIN) . ' baht or more and we ship for free with our courier partners.'
                            : 'ซื้อครบ ' . number_format(FREE_SHIPPING_MIN) . ' บาท ส่งฟรี จัดส่งผ่านขนส่งพันธมิตร พร้อมเลขพัสดุให้ติดตาม',
        'link'       => 'index.php?sort=price_asc#products',
        'cta'        => $en ? 'See prices' : 'ดูราคาเริ่มต้น',
    ],
];
