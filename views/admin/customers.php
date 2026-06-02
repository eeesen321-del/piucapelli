<div class="customer-grid">
    <?php foreach ($customers as $customer): ?>
        <?php $borcluMu = $customer['borc'] > 0; ?>
        <div class="customer-card">
            <h3 class="musteri-adi <?= $borcluMu ? 'borclu' : 'temiz' ?>">
                <?= htmlspecialchars($customer['ad']) ?>
            </h3>
            <p>📞 <?= htmlspecialchars($customer['telefon']) ?></p>
            <p>📦 Aktif Paket: <?= $customer['aktif_paket'] ?> (Kalan: <?= $customer['kalan_seans'] ?> Seans)</p>
            
            <?php if ($borcluMu): ?>
                <p class="borc-uyari">⚠️ <?= number_format($customer['borc'], 2) ?> TL Borç</p>
            <?php else: ?>
                <p class="borc-uyari temiz-uyari">✔️ Borç Yok</p>
            <?php endif; ?>
            
            <button class="btn-tahsilat" onclick="tahsilatModalAc(<?= $customer['id'] ?>, '<?= htmlspecialchars($customer['ad']) ?>')">
                💰 Tahsilat Yap
            </button>
        </div>
    <?php endforeach; ?>
</div>

<div id="tahsilatModal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 15px rgba(0,0,0,0.2); z-index:1000; width:300px;">
    <h3 id="tahsilat-musteri-ad"></h3>
    <input type="hidden" id="tahsilat-musteri-id">
    
    <div style="margin-bottom: 15px;">
        <label>Tutar (TL):</label>
        <input type="number" id="tahsilat-tutar" style="width: 100%; padding: 8px; margin-top: 5px;" placeholder="Örn: 350">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label><input type="radio" name="odeme_tipi" value="nakit" checked> Nakit</label>
        <label style="margin-left: 10px;"><input type="radio" name="odeme_tipi" value="kart"> Kredi Kartı</label>
    </div>
    
    <button onclick="tahsilatKaydet()" style="background:var(--customer-clean); color:#fff; padding:10px; border:none; width:100%; cursor:pointer; margin-bottom:10px; font-weight:bold;">Kaydet</button>
    <button onclick="document.getElementById('tahsilatModal').style.display='none'" style="width:100%; padding:10px; cursor:pointer;">İptal</button>
</div>

<style>
.customer-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
.customer-card { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.customer-card h3 { margin: 0 0 10px 0; font-size: 1.3em; }
.customer-card p { margin: 5px 0; color: #555; }
.borc-uyari { font-weight: bold; color: var(--customer-debt); }
.temiz-uyari { color: var(--customer-clean); }
.btn-tahsilat { margin-top: 15px; width: 100%; padding: 12px; font-size: 1.1em; border: none; border-radius: 5px; cursor: pointer; background-color: var(--customer-clean); color: white; font-weight: bold; transition: opacity 0.2s; }
.btn-tahsilat:hover { opacity: 0.9; }
</style>

<script src="/public/assets/js/customers.js"></script>