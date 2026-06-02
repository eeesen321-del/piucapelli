<?php
// Ayarları Kaydet (Tek sorgu, UNIQUE indeks gerektirir)
if(isset($_POST['ayarlari_kaydet'])) {
    // ON DUPLICATE KEY UPDATE için ayar_adi sütununun UNIQUE olduğundan emin olun
    $upsert = $baglanti->prepare("INSERT INTO ayarlar (ayar_adi, ayar_degeri) VALUES (?, ?)
                                  ON DUPLICATE KEY UPDATE ayar_degeri = VALUES(ayar_degeri)");
    foreach($_POST['ayar'] as $ad => $deger) {
        $upsert->execute([$ad, $deger]);
    }
    echo "<div class='alert alert-success'>Ayarlar başarıyla güncellendi!</div>";
}

// Ayarları çek (değişiklik sonrası yeni veriler gelir)
$ayarlar_raw = $baglanti->query("SELECT ayar_adi, ayar_degeri FROM ayarlar")
                        ->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!-- HTML FORM (değişiklik yok) -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-dark text-white fw-bold"><i class="fa fa-cog me-2"></i> Genel Site Ayarları</div>
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="fw-bold text-muted">Site Başlığı</label>
                    <input type="text" name="ayar[site_baslik]" class="form-control" value="<?= $ayarlar_raw['site_baslik'] ?? '' ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold text-muted">Telefon Numarası</label>
                    <input type="text" name="ayar[telefon]" class="form-control" value="<?= $ayarlar_raw['telefon'] ?? '' ?>">
                </div>
                <div class="col-md-12 mb-3">
                    <label class="fw-bold text-muted">Adres</label>
                    <textarea name="ayar[adres]" class="form-control" rows="2"><?= $ayarlar_raw['adres'] ?? '' ?></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold text-muted">Instagram Kullanıcı Adı</label>
                    <div class="input-group">
                        <span class="input-group-text">@</span>
                        <input type="text" name="ayar[instagram]" class="form-control" value="<?= $ayarlar_raw['instagram'] ?? '' ?>">
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="fw-bold text-muted">Çalışma Saatleri Metni</label>
                    <input type="text" name="ayar[calisma_saatleri]" class="form-control" value="<?= $ayarlar_raw['calisma_saatleri'] ?? '' ?>">
                </div>
            </div>
            <button type="submit" name="ayarlari_kaydet" class="btn btn-primary w-100"><i class="fa fa-save"></i> Değişiklikleri Kaydet</button>
        </form>
    </div>
</div>