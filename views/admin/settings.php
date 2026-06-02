<div class="settings-container">
    <div class="setting-card">
        <h3>🏢 İşletme Bilgileri</h3>
        <label>İşletme Adı: <input type="text" id="isletme-ad" value="Piucapelli"></label>
        <label>Telefon: <input type="text" id="isletme-tel" value="0555 123 4567"></label>
        <button class="btn-save" onclick="ayarlariKaydet('isletme')">Kaydet</button>
    </div>

    <div class="setting-card">
        <h3>⏰ Çalışma Saatleri</h3>
        <label>Açılış Saati: <input type="time" id="saat-acilis" value="08:00"></label>
        <label>Kapanış Saati: <input type="time" id="saat-kapanis" value="22:00"></label>
        <button class="btn-save" onclick="ayarlariKaydet('saatler')">Kaydet</button>
    </div>

    <div class="setting-card">
        <h3>👩‍🎤 Personel Yönetimi</h3>
        <p>Sistemdeki aktif personeller ve yetkileri.</p>
        <button class="btn-view" onclick="alert('Personel listesi modalı açılıyor...')">Personelleri Yönet</button>
    </div>

    <div class="setting-card">
        <h3>🛡️ Engellenen Numaralar ve Talepler</h3>
        <p>Sessiz engelleme listesi ve bekleyen randevu talepleri.</p>
        <button class="btn-view" onclick="engelliTalepleriniGetir()">Listeyi Gör / Yönet</button>
    </div>
</div>

<div id="engelliModal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 15px rgba(0,0,0,0.2); z-index:1000; width:600px; max-height:80vh; overflow-y:auto;">
    <h3 style="margin-top:0; border-bottom:1px solid #ccc; padding-bottom:10px;">Bekleyen Talepler (Sessiz Engelleme)</h3>
    <div id="talep-listesi">
        </div>
    <button onclick="document.getElementById('engelliModal').style.display='none'" style="width:100%; padding:10px; margin-top:15px; cursor:pointer;">Kapat</button>
</div>
</div>

<style>
.settings-container { display: flex; flex-direction: column; gap: 20px; max-width: 800px; }
.setting-card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.setting-card h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; color: #333; }
.setting-card label { display: block; margin-bottom: 10px; font-weight: bold; color: #555; }
.setting-card input { width: 100%; padding: 10px; margin-top: 5px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 5px; }
.btn-save { background: var(--customer-clean); color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; margin-top: 10px; }
.btn-view { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
.btn-save:hover, .btn-view:hover { opacity: 0.9; }
.talep-kart { border: 1px solid #eee; padding: 10px; margin-bottom: 10px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
.talep-bilgi { font-size: 0.9em; }
.btn-onay { background: #27ae60; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; }
.btn-red { background: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; }
</style>

<script src="/public/assets/js/settings.js"></script>