<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">

<div class="calendar-container" style="background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
    <div id="calendar"></div>
</div>

<div id="randevuModal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 15px rgba(0,0,0,0.2); z-index:1000;">
    <h3>Hızlı İşlem</h3>
    <p id="modal-event-title"></p>
    <button class="btn-geldi" style="background:var(--status-geldi); color:#fff; padding:10px; border:none; cursor:pointer;">Geldi (Tahsilat)</button>
    <button class="btn-gelmedi" style="background:var(--status-gelmedi); color:#fff; padding:10px; border:none; cursor:pointer;">Gelmedi</button>
    <button class="btn-kapat" onclick="document.getElementById('randevuModal').style.display='none'" style="margin-top:10px;">Kapat</button>
</div>
<div id="yeniRandevuModal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 15px rgba(0,0,0,0.2); z-index:1000; width:350px;">
    <h3>Yeni Randevu <span id="secilen-saat-metin" style="font-size:14px; color:#555;"></span></h3>
    
    <input type="hidden" id="yeni-randevu-tarih">
    <input type="hidden" id="yeni-randevu-saat">

    <div style="margin-bottom: 15px;">
        <label>Müşteri Adı / Telefonu:</label>
        <input type="text" id="yeni-randevu-musteri" placeholder="Örn: Eda Yıldız - 0555..." style="width: 100%; padding: 8px; margin-top: 5px;">
    </div>

    <div style="margin-bottom: 15px;">
        <label>Hizmet:</label>
        <select id="yeni-randevu-hizmet" style="width: 100%; padding: 8px; margin-top: 5px;">
            <option value="Ağda">Ağda (30 dk)</option>
            <option value="Lazer">Lazer (45 dk)</option>
        </select>
    </div>

    <button onclick="randevuKaydet()" style="background:var(--customer-clean); color:#fff; padding:10px; border:none; width:100%; cursor:pointer; font-weight:bold; margin-bottom:10px;">Kaydet (3. Tık)</button>
    <button onclick="document.getElementById('yeniRandevuModal').style.display='none'" style="width:100%; padding:10px; cursor:pointer;">İptal</button>
</div>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js"></script>
<script src="/public/assets/js/calendar.js"></script>
