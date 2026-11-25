<?php
require_once __DIR__ . '/includes/config.php';
$en = current_lang() === 'en';
$pageTitle = t('privacy');
$metaDesc = $en ? 'VELORE privacy policy under Thailand\'s PDPA: what data we collect, why, and your rights'
               : 'นโยบายความเป็นส่วนตัวของ VELORE ตาม พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล (PDPA) ข้อมูลที่เก็บ วัตถุประสงค์ และสิทธิ์ของคุณ';
require __DIR__ . '/includes/header.php';
?>

<article class="policy">
<?php if ($en): ?>
    <h1>Privacy Policy</h1>
    <p class="muted">Under Thailand's Personal Data Protection Act B.E. 2562 (PDPA) · Effective <?= date('d/m/Y') ?></p>

    <h2>1. Data we collect</h2>
    <p>Your name, email, phone number, delivery address and map pin (if you set one), your password (stored encrypted and never readable), your order history, and payment slips you upload.</p>

    <h2>2. Why we use it</h2>
    <ul>
        <li>To create and manage your member account</li>
        <li>To process orders, verify payments, deliver goods and tell you the status</li>
        <li>To contact you about your order or our service</li>
        <li>To improve the website (for example, aggregated visit statistics)</li>
    </ul>

    <h2>3. Sharing</h2>
    <p>We do not sell or trade your data. It is shared with courier companies only as needed to deliver your order, or when required by law.</p>

    <h2>4. How long we keep it</h2>
    <p>For as long as your account is active, and for the period the law requires for commercial and accounting records.</p>

    <h2>5. Your rights</h2>
    <p>You may request access, correction, deletion, restriction or objection regarding your personal data. You can edit your basic details yourself on <a href="<?= BASE_URL ?>/account.php">My account</a>.</p>

    <h2>6. Cookies and analytics</h2>
    <p>This site uses cookies to keep you logged in and remember your cart<?= GA_MEASUREMENT_ID !== '' ? ' and may use Google Analytics to measure anonymous visit statistics' : '' ?>.</p>

    <h2>7. Security</h2>
    <p>We use safeguards such as password encryption, protection against forged form submissions, limits on repeated failed logins, and private storage for payment slips.</p>

    <h2>8. Contact</h2>
    <p>Questions or requests: <a href="mailto:<?= e(SHOP_EMAIL) ?>"><?= e(SHOP_EMAIL) ?></a> or call <?= e(SHOP_PHONE) ?> (<a href="<?= BASE_URL ?>/contact.php">all contact channels</a>)</p>
<?php else: ?>
    <h1>นโยบายความเป็นส่วนตัว (Privacy Policy)</h1>
    <p class="muted">ตาม พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562 (PDPA) · มีผลตั้งแต่ <?= date('d/m/Y') ?></p>

    <h2>1. ข้อมูลที่เราเก็บ</h2>
    <p>ชื่อ-นามสกุล, อีเมล, เบอร์โทรศัพท์, ที่อยู่จัดส่งและตำแหน่งหมุดแผนที่ (ถ้าปักไว้), รหัสผ่าน (เก็บแบบเข้ารหัส ไม่สามารถอ่านกลับได้), ประวัติการสั่งซื้อ และสลิปโอนเงินที่คุณแนบ</p>

    <h2>2. วัตถุประสงค์ในการใช้ข้อมูล</h2>
    <ul>
        <li>สร้างและจัดการบัญชีสมาชิก</li>
        <li>ดำเนินการตามคำสั่งซื้อ จัดส่งสินค้า และแจ้งสถานะ</li>
        <li>ติดต่อกลับเรื่องการสั่งซื้อหรือการให้บริการ</li>
        <li>ปรับปรุงเว็บไซต์และบริการ (เช่น สถิติการเข้าชมแบบรวม)</li>
    </ul>

    <h2>3. การเปิดเผยข้อมูล</h2>
    <p>เราไม่ขายหรือแลกเปลี่ยนข้อมูลของคุณ ข้อมูลจะถูกส่งให้ผู้ให้บริการขนส่งเท่าที่จำเป็นต่อการจัดส่งเท่านั้น หรือเมื่อกฎหมายกำหนด</p>

    <h2>4. ระยะเวลาเก็บข้อมูล</h2>
    <p>เก็บไว้ตราบเท่าที่บัญชีของคุณยังใช้งานอยู่ และตามระยะเวลาที่กฎหมายกำหนดสำหรับเอกสารทางการค้าและบัญชี</p>

    <h2>5. สิทธิ์ของเจ้าของข้อมูล</h2>
    <p>คุณมีสิทธิ์ขอเข้าถึง แก้ไข ลบ ระงับการใช้ หรือคัดค้านการใช้ข้อมูลส่วนบุคคลของคุณ แก้ไขข้อมูลพื้นฐานได้เองที่หน้า <a href="<?= BASE_URL ?>/account.php">ข้อมูลของฉัน</a></p>

    <h2>6. คุกกี้และการวิเคราะห์การใช้งาน</h2>
    <p>เว็บไซต์ใช้คุกกี้เพื่อจดจำการเข้าสู่ระบบและตะกร้าสินค้า<?= GA_MEASUREMENT_ID !== '' ? ' และอาจใช้ Google Analytics เพื่อวิเคราะห์สถิติการเข้าชมแบบไม่ระบุตัวตน' : '' ?></p>

    <h2>7. การรักษาความปลอดภัย</h2>
    <p>เรามีมาตรการป้องกัน เช่น เข้ารหัสรหัสผ่าน ป้องกันการส่งฟอร์มปลอม จำกัดการเข้าสู่ระบบผิดซ้ำ ๆ และเก็บสลิปโอนเงินแบบไม่เปิดสาธารณะ</p>

    <h2>8. ติดต่อเรา</h2>
    <p>หากมีคำถามหรือต้องการใช้สิทธิ์ ติดต่อได้ที่ <a href="mailto:<?= e(SHOP_EMAIL) ?>"><?= e(SHOP_EMAIL) ?></a> หรือโทร <?= e(SHOP_PHONE) ?> (<a href="<?= BASE_URL ?>/contact.php">ช่องทางติดต่อทั้งหมด</a>)</p>
<?php endif; ?>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
