<?php
// ตัวปักหมุดแผนที่ (Leaflet + OpenStreetMap ใช้ฟรี ไม่ต้องมี API key แต่ต้องต่ออินเทอร์เน็ต)
// ใช้: render_map_picker($lat, $lng)  → ได้ input hidden ชื่อ lat และ lng ในฟอร์ม
function render_map_picker($lat = null, $lng = null): void {
    $hasPin = is_numeric($lat) && is_numeric($lng);
    ?>
    <div class="map-picker">
        <label><?= e(t('map_title')) ?></label>
        <p class="muted map-hint"><?= e(t('map_hint')) ?></p>
        <div id="map-canvas" role="application" aria-label="<?= e(t('map_title')) ?>"></div>
        <input type="hidden" name="lat" id="pin-lat" value="<?= $hasPin ? e($lat) : '' ?>">
        <input type="hidden" name="lng" id="pin-lng" value="<?= $hasPin ? e($lng) : '' ?>">
        <p class="map-actions">
            <button type="button" class="btn btn-sm btn-outline" id="pin-locate"><?= e(t('map_locate')) ?></button>
            <button type="button" class="btn btn-sm btn-outline" id="pin-clear"><?= e(t('map_clear')) ?></button>
            <span class="muted" id="pin-status"><?= e($hasPin ? t('map_pinned') : t('map_none')) ?></span>
        </p>
    </div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    (function () {
        var el = document.getElementById('map-canvas');
        if (typeof L === 'undefined') { el.innerHTML = '<p class="muted" style="padding:16px">Map unavailable offline.</p>'; return; }
        var latIn = document.getElementById('pin-lat'), lngIn = document.getElementById('pin-lng'), status = document.getElementById('pin-status');
        var has = latIn.value !== '' && lngIn.value !== '';
        var map = L.map(el).setView(has ? [parseFloat(latIn.value), parseFloat(lngIn.value)] : [13.7563, 100.5018], has ? 16 : 11);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
        var marker = null;
        function setPin(la, ln, pan) {
            latIn.value = la.toFixed(7); lngIn.value = ln.toFixed(7);
            if (!marker) {
                marker = L.marker([la, ln], { draggable: true }).addTo(map);
                marker.on('dragend', function () { var p = marker.getLatLng(); setPin(p.lat, p.lng, false); });
            } else { marker.setLatLng([la, ln]); }
            if (pan) map.setView([la, ln], Math.max(map.getZoom(), 16));
            status.textContent = <?= json_encode(t('map_pinned'), JSON_UNESCAPED_UNICODE) ?>;
        }
        if (has) setPin(parseFloat(latIn.value), parseFloat(lngIn.value), false);
        map.on('click', function (e) { setPin(e.latlng.lat, e.latlng.lng, false); });
        document.getElementById('pin-clear').addEventListener('click', function () {
            if (marker) { map.removeLayer(marker); marker = null; }
            latIn.value = ''; lngIn.value = '';
            status.textContent = <?= json_encode(t('map_none'), JSON_UNESCAPED_UNICODE) ?>;
        });
        document.getElementById('pin-locate').addEventListener('click', function () {
            if (!navigator.geolocation) { alert(<?= json_encode(t('map_denied'), JSON_UNESCAPED_UNICODE) ?>); return; }
            navigator.geolocation.getCurrentPosition(
                function (pos) { setPin(pos.coords.latitude, pos.coords.longitude, true); },
                function () { alert(<?= json_encode(t('map_denied'), JSON_UNESCAPED_UNICODE) ?>); }
            );
        });
        setTimeout(function () { map.invalidateSize(); }, 200);
    })();
    </script>
    <?php
}
