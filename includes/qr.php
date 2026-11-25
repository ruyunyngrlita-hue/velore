<?php
// =====================================================
// ตัวสร้าง QR Code (เขียนเอง ไม่ต้องใช้ไลบรารี/อินเทอร์เน็ต) ใช้ระดับแก้ไขข้อผิดพลาด M, รองรับข้อความไม่เกิน ~210 ไบต์
// ใช้: QrCode::svg('ข้อความ')  → ได้โค้ด SVG
// =====================================================
final class QrCode
{
    // version => [EC codewords ต่อบล็อก, จำนวนบล็อกกลุ่ม1, data cw กลุ่ม1, จำนวนบล็อกกลุ่ม2, data cw กลุ่ม2]
    private const EC = [
        1 => [10, 1, 16, 0, 0], 2 => [16, 1, 28, 0, 0], 3 => [26, 1, 44, 0, 0], 4 => [18, 2, 32, 0, 0],
        5 => [24, 2, 43, 0, 0], 6 => [16, 4, 27, 0, 0], 7 => [18, 4, 31, 0, 0], 8 => [22, 2, 38, 2, 39],
        9 => [22, 3, 36, 2, 37], 10 => [26, 4, 43, 1, 44],
    ];
    private const ALIGN = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
        7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private static array $exp = [];
    private static array $log = [];

    private static function initGf(): void
    {
        if (self::$exp) return;
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11D;
        }
        for ($i = 255; $i < 512; $i++) self::$exp[$i] = self::$exp[$i - 255];
    }

    private static function gfMul(int $a, int $b): int
    {
        return ($a === 0 || $b === 0) ? 0 : self::$exp[self::$log[$a] + self::$log[$b]];
    }

    private static function rsEncode(array $data, int $ecLen): array
    {
        $gen = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j] ^= $coef;
                $next[$j + 1] ^= self::gfMul($coef, self::$exp[$i]);
            }
            $gen = $next;
        }
        $rem = array_fill(0, $ecLen, 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($rem);
            $rem[] = 0;
            if ($factor !== 0) {
                for ($i = 0; $i < $ecLen; $i++) {
                    $rem[$i] ^= self::gfMul($gen[$i + 1], $factor);
                }
            }
        }
        return $rem;
    }

    private static function dataCapacity(int $v): int
    {
        [, $b1, $d1, $b2, $d2] = self::EC[$v];
        return $b1 * $d1 + $b2 * $d2;
    }

    /** คืนเมทริกซ์ [แถว][คอลัมน์] ของ 0/1 */
    public static function matrix(string $text): array
    {
        self::initGf();
        $bytes = array_values(unpack('C*', $text) ?: []);
        $n = count($bytes);
        $version = 0;
        for ($v = 1; $v <= 10; $v++) {
            $bits = 4 + ($v <= 9 ? 8 : 16) + 8 * $n;
            if ((int)ceil($bits / 8) <= self::dataCapacity($v)) { $version = $v; break; }
        }
        if ($version === 0) throw new RuntimeException('QR data too long');

        // ---- bit stream ----
        $bitStr = '0100' . str_pad(decbin($n), $version <= 9 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach ($bytes as $b) $bitStr .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        $capBits = self::dataCapacity($version) * 8;
        $bitStr .= str_repeat('0', min(4, $capBits - strlen($bitStr)));
        $bitStr .= str_repeat('0', (8 - strlen($bitStr) % 8) % 8);
        $cw = [];
        for ($i = 0; $i < strlen($bitStr); $i += 8) $cw[] = bindec(substr($bitStr, $i, 8));
        for ($pad = 0xEC; count($cw) < self::dataCapacity($version); $pad = ($pad === 0xEC ? 0x11 : 0xEC)) $cw[] = $pad;

        // ---- แบ่งบล็อก + Reed-Solomon + สลับ ----
        [$ecLen, $b1, $d1, $b2, $d2] = self::EC[$version];
        $blocks = []; $ecs = []; $pos = 0;
        for ($i = 0; $i < $b1 + $b2; $i++) {
            $len = $i < $b1 ? $d1 : $d2;
            $blk = array_slice($cw, $pos, $len);
            $pos += $len;
            $blocks[] = $blk;
            $ecs[] = self::rsEncode($blk, $ecLen);
        }
        $final = [];
        $maxLen = max($d1, $d2);
        for ($i = 0; $i < $maxLen; $i++) foreach ($blocks as $blk) if (isset($blk[$i])) $final[] = $blk[$i];
        for ($i = 0; $i < $ecLen; $i++) foreach ($ecs as $ec) $final[] = $ec[$i];
        $dataBits = [];
        foreach ($final as $c) for ($k = 7; $k >= 0; $k--) $dataBits[] = ($c >> $k) & 1;

        // ---- วาดรูปแบบฟังก์ชัน ----
        $size = 17 + 4 * $version;
        $m = array_fill(0, $size, array_fill(0, $size, 0));
        $fn = array_fill(0, $size, array_fill(0, $size, false));
        $set = function (int $x, int $y, int $val) use (&$m, &$fn, $size) {
            if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) return;
            $m[$y][$x] = $val; $fn[$y][$x] = true;
        };
        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$fx, $fy]) {
            for ($dy = -1; $dy <= 7; $dy++) for ($dx = -1; $dx <= 7; $dx++) {
                $inside = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6;
                $dark = $inside && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
                $set($fx + $dx, $fy + $dy, $dark ? 1 : 0);
            }
        }
        $al = self::ALIGN[$version];
        $last = count($al) - 1;
        foreach ($al as $i => $cy) foreach ($al as $j => $cx) {
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) continue;
            for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) {
                $set($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1 ? 1 : 0);
            }
        }
        // จองพื้นที่ format / version
        for ($i = 0; $i < 9; $i++) { $set(8, $i, 0); $set($i, 8, 0); }
        for ($i = 0; $i < 8; $i++) { $set($size - 1 - $i, 8, 0); $set(8, $size - 1 - $i, 0); }
        // เส้น timing (วาดทีหลังการจองพื้นที่ format เพื่อไม่ให้ถูกทับ)
        for ($i = 8; $i <= $size - 9; $i++) { $set(6, $i, $i % 2 === 0 ? 1 : 0); $set($i, 6, $i % 2 === 0 ? 1 : 0); }
        if ($version >= 7) {
            $rem = $version;
            for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            $vbits = ($version << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = ($vbits >> $i) & 1;
                $a = $size - 11 + $i % 3; $b = intdiv($i, 3);
                $set($a, $b, $bit); $set($b, $a, $bit);
            }
        }

        // ---- วางข้อมูลแบบซิกแซก ----
        $idx = 0; $total = count($dataBits);
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) $right = 5;
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $size - 1 - $vert : $vert;
                    if (!$fn[$y][$x]) {
                        $m[$y][$x] = $idx < $total ? $dataBits[$idx] : 0;
                        $idx++;
                    }
                }
            }
        }

        // ---- เลือก mask ที่คะแนนโทษต่ำสุด ----
        $best = null; $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $t = $m;
            for ($y = 0; $y < $size; $y++) for ($x = 0; $x < $size; $x++) {
                if ($fn[$y][$x]) continue;
                $inv = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($inv) $t[$y][$x] ^= 1;
            }
            // format bits (ระดับ M = 0)
            $fd = (0 << 3) | $mask; $rem = $fd;
            for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
            $fbits = (($fd << 10) | $rem) ^ 0x5412;
            $bit = fn(int $i) => ($fbits >> $i) & 1;
            for ($i = 0; $i <= 5; $i++) $t[$i][8] = $bit($i);
            $t[7][8] = $bit(6); $t[8][8] = $bit(7); $t[8][7] = $bit(8);
            for ($i = 9; $i < 15; $i++) $t[8][14 - $i] = $bit($i);
            for ($i = 0; $i < 8; $i++) $t[8][$size - 1 - $i] = $bit($i);
            for ($i = 8; $i < 15; $i++) $t[$size - 15 + $i][8] = $bit($i);
            $t[$size - 8][8] = 1;   // dark module
            $score = self::penalty($t, $size);
            if ($score < $bestScore) { $bestScore = $score; $best = $t; }
        }
        return $best;
    }

    private static function penalty(array $t, int $size): int
    {
        $p = 0;
        for ($pass = 0; $pass < 2; $pass++) {           // 0 = แถว, 1 = คอลัมน์
            for ($a = 0; $a < $size; $a++) {
                $run = 1; $line = '';
                for ($b = 0; $b < $size; $b++) {
                    $c = $pass === 0 ? $t[$a][$b] : $t[$b][$a];
                    $line .= $c;
                    if ($b > 0) {
                        $prev = $pass === 0 ? $t[$a][$b - 1] : $t[$b - 1][$a];
                        if ($c === $prev) { $run++; if ($run === 5) $p += 3; elseif ($run > 5) $p++; } else $run = 1;
                    }
                }
                $p += 40 * (substr_count($line, '10111010000') + substr_count($line, '00001011101'));
            }
        }
        for ($y = 0; $y < $size - 1; $y++) for ($x = 0; $x < $size - 1; $x++) {
            $c = $t[$y][$x];
            if ($c === $t[$y][$x + 1] && $c === $t[$y + 1][$x] && $c === $t[$y + 1][$x + 1]) $p += 3;
        }
        $dark = 0;
        foreach ($t as $row) $dark += array_sum($row);
        $p += 10 * (int)(abs($dark * 20 - $size * $size * 10) / ($size * $size));
        return $p;
    }

    /** คืนโค้ด SVG ของ QR (มีขอบขาว 4 โมดูล) */
    public static function svg(string $text, string $label = 'QR Code'): string
    {
        $m = self::matrix($text);
        $n = count($m);
        $q = 4;
        $d = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($m[$y][$x]) {
                    $len = 1;
                    while ($x + $len < $n && $m[$y][$x + $len]) $len++;
                    $d .= 'M' . ($x + $q) . ' ' . ($y + $q) . 'h' . $len . 'v1h-' . $len . 'z';
                    $x += $len - 1;
                }
            }
        }
        $s = $n + 2 * $q;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $s . ' ' . $s . '" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES) . '" shape-rendering="crispEdges">'
            . '<rect width="' . $s . '" height="' . $s . '" fill="#fff"/><path d="' . $d . '" fill="#000"/></svg>';
    }
}
