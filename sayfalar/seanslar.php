<?php
// ============================================
//  YARDIMCI FONKSİYONLAR (TEKRAR KULLANIM)
// ============================================

/**
 * Session tabanlı flash mesaj.
 */
function flashMesaj($tip, $mesaj) {
    $_SESSION['flash'] = ['tip' => $tip, 'mesaj' => $mesaj];
}

/**
 * Güvenli yönlendirme.
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * ID'ye göre müşteri bilgisi getir (ad_soyad, telefon).
 */
function musteriBilgi($musteriId) {
    global $baglanti;
    $stmt = $baglanti->prepare("SELECT ad_soyad, telefon FROM musteriler WHERE id = ?");
    $stmt->execute([$musteriId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Paket bilgilerini getir.
 */
function paketBilgi($paketId) {
    global $baglanti;
    $stmt = $baglanti->prepare("SELECT * FROM paketler WHERE id = ?");
    $stmt->execute([$paketId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Pakete bağlı hizmetin süresini getir (yoksa varsayılan 30 dk).
 */
function paketSeansSuresi($paketId) {
    global $baglanti;
    $stmt = $baglanti->prepare("
        SELECT h.sure_dk 
        FROM paketler p 
        LEFT JOIN hizmetler h ON p.hizmet_id = h.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$paketId]);
    $sure = $stmt->fetchColumn();
    return $sure ?: 30; // varsayılan 30 dk
}

/**
 * Bitiş saatini hesapla.
 */
function bitisSaatiHesapla($tarih, $saat, $dakika) {
    $dt = new DateTime("$tarih $saat");
    $dt->modify("+$dakika minutes");
    return $dt->format("H:i");
}

/**
 * Seans + Randevu birlikte ekleme (senkronize).
 */
function seansVeRandevuEkle($musteriPaketId, $musteriId, $musteriAd, $musteriTel, $paketAdi, $kacinciSeans, $tarih, $saat, $calisanId, $odaId = null) {
    global $baglanti;

    // 1. Seans ekle
    $seansStmt = $baglanti->prepare("
        INSERT INTO seanslar (musteri_paket_id, calisan_id, oda_id, randevu_tarihi, randevu_saati, kacinci_seans, durum)
        VALUES (?, ?, ?, ?, ?, ?, 'bekliyor')
    ");
    $seansStmt->execute([$musteriPaketId, $calisanId, $odaId, $tarih, $saat, $kacinciSeans]);
    $seansId = $baglanti->lastInsertId();

    // 2. Hizmet süresi (paketten al)
    static $paketSureCache = [];
    if (!isset($paketSureCache[$musteriPaketId])) {
        $paketId = $baglanti->prepare("SELECT paket_id FROM musteri_paketleri WHERE id = ?");
        $paketId->execute([$musteriPaketId]);
        $pid = $paketId->fetchColumn();
        $paketSureCache[$musteriPaketId] = paketSeansSuresi($pid);
    }
    $sure = $paketSureCache[$musteriPaketId];
    $bitisSaati = bitisSaatiHesapla($tarih, $saat, $sure);

    // 3. Randevu ekle (seans_id ile bağla)
    $hizmetText = $paketAdi . " ($kacinciSeans. Seans)";
    $randevuStmt = $baglanti->prepare("
        INSERT INTO randevular (musteri_id, musteri_ad, telefon, hizmet_adi, randevu_tarihi, randevu_saati, bitis_saati, calisan_id, durum, fiyat, seans_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'bekliyor', 0, ?)
    ");
    $randevuStmt->execute([$musteriId, $musteriAd, $musteriTel, $hizmetText, $tarih, $saat, $bitisSaati, $calisanId, $seansId]);

    return $seansId;
}

// ============================================
//  POST İŞLEMLERİ (TEK BİR YERDE TOPLANDI)
// ============================================

// --- 1. Seans Durum Güncelleme (Senkronize) ---
if (isset($_POST['seans_durum_guncelle'])) {
    $seansId = (int) $_POST['seans_id'];
    $yeniDurum = $_POST['yeni_durum'];

    try {
        // Seans güncelle
        $baglanti->prepare("UPDATE seanslar SET durum = ? WHERE id = ?")->execute([$yeniDurum, $seansId]);
        // Bağlı randevuyu güncelle
        $baglanti->prepare("UPDATE randevular SET durum = ? WHERE seans_id = ?")->execute([$yeniDurum, $seansId]);
        flashMesaj('success', 'Seans durumu güncellendi.');
    } catch (PDOException $e) {
        flashMesaj('danger', 'Güncelleme hatası: ' . $e->getMessage());
    }
    redirect("admin.php?tab=seanslar");
}

// --- 1b. Seans Tarih/Saat/Personel Düzenleme ---
if (isset($_POST['seans_duzenle'])) {
    $seansId   = (int) $_POST['seans_id'];
    $yeniTarih = $_POST['yeni_tarih'];
    $yeniSaat  = $_POST['yeni_saat'];
    $yeniCalisan = (int) $_POST['yeni_calisan_id'];

    try {
        $baglanti->prepare("UPDATE seanslar SET randevu_tarihi = ?, randevu_saati = ?, calisan_id = ? WHERE id = ?")
                 ->execute([$yeniTarih, $yeniSaat, $yeniCalisan, $seansId]);
        // Bağlı randevuyu da güncelle
        $baglanti->prepare("UPDATE randevular SET randevu_tarihi = ?, randevu_saati = ?, calisan_id = ?, personel_id = ? WHERE seans_id = ?")
                 ->execute([$yeniTarih, $yeniSaat, $yeniCalisan, $yeniCalisan, $seansId]);
        flashMesaj('success', 'Seans güncellendi.');
    } catch (PDOException $e) {
        flashMesaj('danger', 'Güncelleme hatası: ' . $e->getMessage());
    }
    redirect("admin.php?tab=seanslar");
}


// --- 1c. Detay Modalından Seans Ekleme ---
if (isset($_POST['seans_ekle_detaydan'])) {
    $mpId      = (int) $_POST['musteri_paket_id'];
    $calisanId = (int) $_POST['calisan_id'];
    $tarih     = $_POST['tarih'];
    $saat      = $_POST['saat'];

    try {
        $stmt = $baglanti->prepare("
            SELECT mp.id, mp.musteri_id, mp.kullanilan_seans, m.ad_soyad, m.telefon, p.paket_adi
            FROM musteri_paketleri mp
            JOIN musteriler m ON mp.musteri_id = m.id
            JOIN paketler p ON mp.paket_id = p.id
            WHERE mp.id = ?
        ");
        $stmt->execute([$mpId]);
        $pb = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pb) throw new Exception('Paket bulunamadı.');

        // Kaçıncı seans olduğunu hesapla
        $seansNoStmt = $baglanti->prepare("SELECT COUNT(*) FROM seanslar WHERE musteri_paket_id = ?");
        $seansNoStmt->execute([$mpId]);
        $yeniSeansNo = (int)$seansNoStmt->fetchColumn() + 1;

        seansVeRandevuEkle($mpId, $pb['musteri_id'], $pb['ad_soyad'], $pb['telefon'],
            $pb['paket_adi'], $yeniSeansNo, $tarih, $saat, $calisanId, null);

        flashMesaj('success', $yeniSeansNo . '. seans eklendi.');
    } catch (Exception $e) {
        flashMesaj('danger', 'Hata: ' . $e->getMessage());
    }
    redirect("admin.php?tab=seanslar");
}

// --- 2. Manuel Seans Ekleme (Senkronize) ---
if (isset($_POST['seans_ekle_manuel'])) {
    $mpId = (int) $_POST['musteri_paket_id'];
    $calisanId = (int) $_POST['calisan_id'];
    $odaId = !empty($_POST['oda_id']) ? (int) $_POST['oda_id'] : null;
    $tarih = $_POST['tarih'];
    $saat = $_POST['saat'];

    try {
        // Paket bilgilerini al
        $stmt = $baglanti->prepare("
            SELECT mp.id, mp.musteri_id, mp.kullanilan_seans, m.ad_soyad, m.telefon, p.paket_adi
            FROM musteri_paketleri mp
            JOIN musteriler m ON mp.musteri_id = m.id
            JOIN paketler p ON mp.paket_id = p.id
            WHERE mp.id = ?
        ");
        $stmt->execute([$mpId]);
        $pb = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pb) {
            flashMesaj('danger', 'Paket bulunamadı.');
            redirect("admin.php?tab=seanslar");
        }

        $yeniSeansNo = $pb['kullanilan_seans'] + 1;

        // Seans ve randevu ekle
        seansVeRandevuEkle(
            $mpId,
            $pb['musteri_id'],
            $pb['ad_soyad'],
            $pb['telefon'],
            $pb['paket_adi'],
            $yeniSeansNo,
            $tarih,
            $saat,
            $calisanId,
            $odaId
        );

        // Paket sayaç güncelle
        $baglanti->prepare("UPDATE musteri_paketleri SET kullanilan_seans = kullanilan_seans + 1 WHERE id = ?")->execute([$mpId]);

        flashMesaj('success', 'Yeni seans eklendi.');
    } catch (PDOException $e) {
        flashMesaj('danger', 'Seans eklenemedi: ' . $e->getMessage());
    }
    redirect("admin.php?tab=seanslar");
}

// --- 3. Paket Satışı ---
if (isset($_POST['paket_satis_yap'])) {
    try {
        $musteriId   = (int) ($_POST['musteri_id'] ?? 0);
        $paketId     = (int) $_POST['paket_id'];
        $calisanId   = (int) $_POST['calisan_id'];
        $basTarih    = $_POST['baslangic_tarihi'];
        $basSaat     = $_POST['baslangic_saati'];
        $pesinat     = (float) ($_POST['pesinat_tutari'] ?? 0);
        $odemeTuru   = $_POST['odeme_turu'] ?? 'nakit';
        $otomatik    = isset($_POST['otomatik_seans']) && $_POST['otomatik_seans'] != '0';

        if (!$musteriId) {
            flashMesaj('danger', 'Müşteri seçilmedi.');
            redirect("admin.php?tab=seanslar");
        }

        $paket = paketBilgi($paketId);
        if (!$paket) { flashMesaj('danger', 'Paket bulunamadı.'); redirect("admin.php?tab=seanslar"); }

        $musteri = musteriBilgi($musteriId);
        if (!$musteri) { flashMesaj('danger', 'Müşteri bilgisi alınamadı.'); redirect("admin.php?tab=seanslar"); }

        $ucret = $paket['toplam_tutar'];

        // Müşteri Paketi Kaydı
        $baglanti->prepare("
            INSERT INTO musteri_paketleri (musteri_id, paket_id, toplam_seans, kullanilan_seans, ucret, durum)
            VALUES (?, ?, ?, 0, ?, 'aktif')
        ")->execute([$musteriId, $paketId, $paket['seans_sayisi'], $ucret]);
        $mpId = $baglanti->lastInsertId();

        // Peşinat varsa kasaya işle
        if ($pesinat > 0) {
            $baglanti->prepare("INSERT INTO kasa_hareketleri (musteri_id, islem_turu, tutar, aciklama, odeme_turu, tarih) VALUES (?, 'tahsilat', ?, ?, ?, NOW())")
                     ->execute([$musteriId, $pesinat, $paket['paket_adi'] . ' Satışı Peşinatı', $odemeTuru]);
        }

        // Otomatik seans oluştur
        $seansSayisi = $paket['seans_sayisi'];
        if ($otomatik) {
            $aralikGun = $paket['seans_araligi'] > 0 ? $paket['seans_araligi'] : 30;
            for ($i = 0; $i < $seansSayisi; $i++) {
                $tarih = date('Y-m-d', strtotime("$basTarih +" . ($i * $aralikGun) . " days"));
                seansVeRandevuEkle($mpId, $musteriId, $musteri['ad_soyad'], $musteri['telefon'],
                    $paket['paket_adi'], $i + 1, $tarih, $basSaat, $calisanId, null);
            }
            flashMesaj('success', 'Paket satışı tamamlandı. ' . $seansSayisi . ' seans oluşturuldu.');
        } else {
            flashMesaj('success', 'Paket başarıyla kaydedildi. Seansları kendiniz oluşturabilirsiniz.');
        }
    } catch (PDOException $e) {
        flashMesaj('danger', 'Paket satışı başarısız: ' . $e->getMessage());
    }
    redirect("admin.php?tab=seanslar");
}

// ============================================
//  VERİLERİ ÇEK (SAYFA YÜKLENİRKEN)
// ============================================

$arama = $_GET['arama'] ?? '';

// Aktif paketleri listele (ana tablo)
$sql = "
    SELECT 
        mp.id as musteri_paket_id, 
        mp.toplam_seans,
        m.id as musteri_id,
        m.ad_soyad, 
        m.telefon, 
        p.paket_adi,
        (SELECT COUNT(*) FROM seanslar WHERE musteri_paket_id = mp.id AND durum = 'geldi') as tamamlanan_seans
    FROM musteri_paketleri mp
    JOIN musteriler m ON mp.musteri_id = m.id
    JOIN paketler p ON mp.paket_id = p.id
    WHERE mp.durum = 'aktif'
";
$params = [];
if (!empty($arama)) {
    $sql .= " AND (m.ad_soyad LIKE ? OR m.telefon LIKE ?)";
    $params[] = "%$arama%";
    $params[] = "%$arama%";
}
$sql .= " ORDER BY mp.id DESC";
$stmt = $baglanti->prepare($sql);
$stmt->execute($params);
$musteriPaketleri = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dropdown için: kalan seansı olan aktif paketler
$aktifPaketlerDropdown = $baglanti->query("
    SELECT mp.id, m.ad_soyad, p.paket_adi, (mp.toplam_seans - mp.kullanilan_seans) as kalan
    FROM musteri_paketleri mp
    JOIN musteriler m ON mp.musteri_id = m.id
    JOIN paketler p ON mp.paket_id = p.id
    WHERE mp.durum = 'aktif' AND (mp.toplam_seans - mp.kullanilan_seans) > 0
    ORDER BY m.ad_soyad ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Diğer sabit veriler
$calisanlar = $baglanti->query("SELECT * FROM calisanlar ORDER BY ad_soyad ASC")->fetchAll(PDO::FETCH_ASSOC);
$odalar = $baglanti->query("SELECT * FROM odalar ORDER BY oda_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
$musteriler = $baglanti->query("SELECT id, ad_soyad, telefon FROM musteriler ORDER BY ad_soyad ASC")->fetchAll(PDO::FETCH_ASSOC);
$paketler = $baglanti->query("SELECT * FROM paketler ORDER BY paket_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- FLASH MESAJ GÖSTERİMİ -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?= $_SESSION['flash']['tip'] ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['flash']['mesaj']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<!-- ============================================
     ANA KART: MÜŞTERİ & SEANS TAKİBİ
     ============================================ -->
<div class="card card-custom border-0 shadow-sm">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2 bg-white pb-3">
        <h5 class="card-title text-primary mb-0"><i class="fa fa-users-cog"></i> Müşteri & Seans Takibi</h5>
        <div class="d-flex align-items-center gap-2">
            <!-- Paket Sat butonu - doğru modal hedefi -->
            <button class="btn btn-warning text-dark btn-sm" data-bs-toggle="modal" data-bs-target="#paketSatisModal">
                <i class="fa fa-box-open"></i> Paket Sat
            </button>
            <!-- Manuel Seans Ekle butonu -->
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#seansEkleModal">
                <i class="fa fa-plus-circle"></i> Seans Ekle
            </button>
        </div>
    </div>

    <!-- Arama -->
    <div class="p-3 bg-light border-bottom">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="seanslar">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <input type="text" name="arama" class="form-control" placeholder="Müşteri Adı veya Telefon Ara..." value="<?= htmlspecialchars($arama) ?>">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Ara</button>
                </div>
            </div>
            <div class="col-md-2">
                <a href="admin.php?tab=seanslar" class="btn btn-outline-secondary btn-sm w-100">Temizle</a>
            </div>
        </form>
    </div>

    <!-- Tablo -->
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead class="bg-light text-muted">
                    <tr>
                        <th class="ps-4">Müşteri Bilgisi</th>
                        <th>Paket Adı</th>
                        <th>Durum / İlerleme</th>
                        <th class="text-end pe-4">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($musteriPaketleri)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">Aktif paket veya müşteri bulunamadı.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($musteriPaketleri as $mp):
                        $toplam = $mp['toplam_seans'];
                        $yapilan = $mp['tamamlanan_seans'];
                        $yuzde = $toplam > 0 ? ($yapilan / $toplam) * 100 : 0;
                        $renk = $yuzde == 100 ? 'bg-success' : 'bg-primary';
                    ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($mp['ad_soyad']) ?></div>
                            <div class="small text-muted"><i class="fa fa-phone me-1"></i><?= formatTel($mp['telefon']) ?></div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border px-3 py-2"><?= htmlspecialchars($mp['paket_adi']) ?></span>
                        </td>
                        <td style="width: 35%;">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small fw-bold text-dark"><?= $yapilan ?> / <?= $toplam ?> Seans Tamamlandı</span>
                                <span class="small text-muted">%<?= number_format($yuzde, 0) ?></span>
                            </div>
                            <div class="progress" style="height: 10px; border-radius: 5px;">
                                <div class="progress-bar <?= $renk ?>" role="progressbar" style="width: <?= $yuzde ?>%"></div>
                            </div>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#detayModal<?= $mp['musteri_paket_id'] ?>">
                                <i class="fa fa-list-ul me-1"></i> Seansları Yönet
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================
     SEANS DETAY MODALLARI (Her paket için)
     ============================================ -->
<?php foreach ($musteriPaketleri as $mp): 
    // Bu pakete ait seansları çek
    $seanslarStmt = $baglanti->prepare("
        SELECT s.*, c.ad_soyad as personel_ad 
        FROM seanslar s 
        LEFT JOIN calisanlar c ON s.calisan_id = c.id 
        WHERE s.musteri_paket_id = ? 
        ORDER BY s.randevu_tarihi ASC
    ");
    $seanslarStmt->execute([$mp['musteri_paket_id']]);
    $seanslar = $seanslarStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="modal fade" id="detayModal<?= $mp['musteri_paket_id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa fa-calendar-alt me-2"></i><?= htmlspecialchars($mp['ad_soyad']) ?> - Seans Detayları</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-striped mb-0" style="font-size: 0.9rem;">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Tarih</th>
                            <th>Saat</th>
                            <th>Personel</th>
                            <th>Durum</th>
                            <th class="text-center">Düzenle</th>
                            <th class="text-end pe-3">Durum Değiştir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($seanslar as $bps): 
                            $tarihF = date('d.m.Y', strtotime($bps['randevu_tarihi']));
                            $saatF = substr($bps['randevu_saati'], 0, 5);
                            $badgeClass = 'bg-warning text-dark';
                            $durumText = 'Bekliyor';
                            if ($bps['durum'] == 'geldi') { $badgeClass = 'bg-success'; $durumText = 'GELDİ'; }
                            elseif ($bps['durum'] == 'gelmedi') { $badgeClass = 'bg-danger'; $durumText = 'GELMEDİ'; }
                            elseif ($bps['durum'] == 'iptal') { $badgeClass = 'bg-secondary'; $durumText = 'İPTAL'; }
                        ?>
                        <tr id="seans-row-<?= $bps['id'] ?>">
                            <td class="ps-3 fw-bold"><?= $bps['kacinci_seans'] ?>.</td>
                            <td class="seans-tarih-goster"><?= $tarihF ?></td>
                            <td class="seans-saat-goster"><?= $saatF ?></td>
                            <td class="seans-personel-goster"><?= htmlspecialchars($bps['personel_ad'] ?? 'Atanmamış') ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $durumText ?></span></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary" title="Tarih/Saat Düzenle"
                                    onclick="seansDuzenleAc(<?= $bps['id'] ?>, '<?= $bps['randevu_tarihi'] ?>', '<?= $saatF ?>', <?= (int)($bps['calisan_id'] ?? 0) ?>)">
                                    <i class="fa fa-pencil-alt"></i>
                                </button>
                            </td>
                            <td class="text-end pe-3">
                                <form method="POST" class="d-inline-flex gap-1">
                                    <input type="hidden" name="seans_id" value="<?= $bps['id'] ?>">
                                    <input type="hidden" name="yeni_durum" id="durum_input_<?= $bps['id'] ?>">
                                    <input type="hidden" name="seans_durum_guncelle" value="1">
                                    <button type="button" onclick="document.getElementById('durum_input_<?= $bps['id'] ?>').value='geldi'; this.form.submit();" class="btn btn-sm btn-outline-success" title="Geldi"><i class="fa fa-check"></i></button>
                                    <button type="button" onclick="document.getElementById('durum_input_<?= $bps['id'] ?>').value='gelmedi'; this.form.submit();" class="btn btn-sm btn-outline-danger" title="Gelmedi"><i class="fa fa-times"></i></button>
                                    <button type="button" onclick="document.getElementById('durum_input_<?= $bps['id'] ?>').value='iptal'; this.form.submit();" class="btn btn-sm btn-outline-secondary" title="İptal"><i class="fa fa-ban"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer bg-light d-block p-0">
                <!-- Seans Ekle paneli (gizli, toggle ile açılır) -->
                <div id="seansEklePanel<?= $mp['musteri_paket_id'] ?>" style="display:none;" class="p-3 border-bottom bg-white">
                    <form method="POST" class="row g-2 align-items-end">
                        <input type="hidden" name="seans_ekle_detaydan" value="1">
                        <input type="hidden" name="musteri_paket_id" value="<?= $mp['musteri_paket_id'] ?>">
                        <div class="col-sm-3">
                            <label class="form-label small fw-bold mb-1">Tarih</label>
                            <input type="date" name="tarih" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label small fw-bold mb-1">Saat</label>
                            <input type="time" name="saat" class="form-control form-control-sm" value="09:00" required>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label small fw-bold mb-1">Personel</label>
                            <select name="calisan_id" class="form-select form-select-sm" required>
                                <?php foreach ($calisanlar as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['ad_soyad']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-3 d-flex gap-1">
                            <button type="submit" class="btn btn-success btn-sm flex-fill">
                                <i class="fa fa-plus me-1"></i>Ekle
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                onclick="document.getElementById('seansEklePanel<?= $mp['musteri_paket_id'] ?>').style.display='none'">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <!-- Footer butonları -->
                <div class="d-flex justify-content-between align-items-center px-3 py-2">
                    <button type="button" class="btn btn-success btn-sm"
                        onclick="var p=document.getElementById('seansEklePanel<?= $mp['musteri_paket_id'] ?>'); p.style.display = p.style.display==='none' ? 'block' : 'none';">
                        <i class="fa fa-plus me-1"></i>Seans Ekle
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- ============================================
     MODAL: EK SEANS EKLE (Manuel)
     ============================================ -->
<div class="modal fade" id="seansEkleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fa fa-plus-circle"></i> Ek Seans Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="fw-bold small">Müşteri ve Paketi Seçin</label>
                        <select name="musteri_paket_id" class="form-select" required data-ts data-placeholder="Müşteri adı veya paket adı ara...">
                            <option value="">Seçiniz...</option>
                            <?php foreach ($aktifPaketlerDropdown as $ap): ?>
                                <option value="<?= $ap['id'] ?>">
                                    <?= htmlspecialchars($ap['ad_soyad']) ?> - <?= htmlspecialchars($ap['paket_adi']) ?> (Kalan: <?= $ap['kalan'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="fw-bold small">Tarih</label>
                            <input type="date" name="tarih" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="fw-bold small">Saat</label>
                            <input type="time" name="saat" class="form-control" value="09:00" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold small">Personel</label>
                        <select name="calisan_id" class="form-select" required>
                            <?php foreach ($calisanlar as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['ad_soyad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold small">Oda (Opsiyonel)</label>
                        <select name="oda_id" class="form-select">
                            <option value="">Seçiniz</option>
                            <?php foreach ($odalar as $o): ?>
                                <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['oda_adi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="seans_ekle_manuel" class="btn btn-success w-100 fw-bold">Seansı Kaydet</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     MODAL: PAKET SATIŞI (Düzeltilmiş ID)
     ============================================ -->

<!-- ============================================
     MODAL: SEANS DÜZENLE
     ============================================ -->
<div class="modal fade" id="seansDuzenleModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title"><i class="fa fa-pencil-alt me-2"></i>Seans Düzenle</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="seans_duzenle" value="1">
                    <input type="hidden" name="seans_id" id="duzenle_seans_id">

                    <div class="mb-3">
                        <label class="fw-bold small">Tarih</label>
                        <input type="date" name="yeni_tarih" id="duzenle_tarih" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold small">Saat</label>
                        <input type="time" name="yeni_saat" id="duzenle_saat" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold small">Personel</label>
                        <select name="yeni_calisan_id" id="duzenle_calisan" class="form-select" required>
                            <?php foreach ($calisanlar as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['ad_soyad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save me-1"></i>Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="paketSatisModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark"><i class="fa fa-box-open"></i> Paket Satışı Yap</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formSnPaketSatis">

                    <!-- Müşteri Seçimi -->
                    <div class="mb-3 position-relative">
                        <label class="fw-bold">Müşteri Seçimi</label>
                        <input type="hidden" name="musteri_id" id="sn_musteri_id" required>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa fa-search"></i></span>
                            <input type="text" id="sn_musteri_arama" class="form-control" placeholder="Müşteri Ara..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#snYeniMusteriAlan"><i class="fa fa-plus"></i></button>
                            <button class="btn btn-outline-danger" type="button" id="sn_btn_temizle" style="display:none;" onclick="snMusteriTemizle()"><i class="fa fa-times"></i></button>
                        </div>
                        <div id="sn_arama_sonuclari" class="list-group position-absolute w-100 shadow" style="display:none; z-index:1050; max-height:200px; overflow-y:auto;"></div>
                        <div id="sn_secilen_bilgi" class="small text-success mt-1 fw-bold" style="display:none;"><i class="fa fa-check-circle"></i> Seçilen: <span id="sn_secilen_ad"></span></div>
                        <div class="collapse mt-2 p-3 bg-light border rounded shadow-sm" id="snYeniMusteriAlan" style="position:absolute; z-index:1060; width:100%;">
                            <label class="small fw-bold text-muted mb-1">Yeni Müşteri Ekle</label>
                            <div class="mb-2">
                                <input type="text" id="sn_yeni_ad" class="form-control form-control-sm mb-1" placeholder="Ad Soyad">
                                <input type="text" id="sn_yeni_tel" class="form-control form-control-sm" placeholder="Telefon">
                            </div>
                            <button class="btn btn-success btn-sm w-100" type="button" onclick="snHizliMusteriEkle()"><i class="fa fa-save"></i> Kaydet ve Seç</button>
                        </div>
                    </div>

                    <!-- Paket Seçimi -->
                    <div class="mb-3">
                        <label class="fw-bold">Paket Seçiniz</label>
                        <select name="paket_id" class="form-select" required>
                            <option value="">-- Paket Listesinden Seçiniz --</option>
                            <?php foreach ($paketler as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['paket_adi']) ?>
                                    (<?= $p['seans_sayisi'] ?> Seans - <?= number_format($p['toplam_tutar'],2) ?> ₺)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Personel Seçimi -->
                    <div class="mb-3">
                        <label class="fw-bold">Personel Seçiniz</label>
                        <select name="calisan_id" class="form-select" required>
                            <option value="">-- Personel Seç --</option>
                            <?php foreach ($calisanlar as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['ad_soyad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tarih / Saat -->
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="small fw-bold">İlk Seans Tarihi</label>
                            <input type="date" name="baslangic_tarihi" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="small fw-bold">Seans Saati</label>
                            <input type="time" name="baslangic_saati" class="form-control" value="09:00" required>
                        </div>
                    </div>

                    <hr>

                    <!-- Peşinat / Ödeme -->
                    <div class="mb-3 bg-light p-2 border rounded">
                        <label class="small fw-bold text-success">Peşinat / Ödeme</label>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text">Tutar:</span>
                            <input type="number" step="0.01" name="pesinat_tutari" class="form-control" placeholder="0.00">
                        </div>
                        <select name="odeme_turu" class="form-select form-select-sm">
                            <option value="nakit">Nakit</option>
                            <option value="kredi_karti">Kredi Kartı</option>
                            <option value="havale">Havale</option>
                        </select>
                    </div>

                    <!-- Not -->
                    <div class="mb-3">
                        <label>Not</label>
                        <textarea name="aciklama" class="form-control" rows="2"></textarea>
                    </div>

                    <!-- Otomatik Seans -->
                    <div class="mb-3 p-2 rounded border" style="background:#f8f9fa;">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="otomatik_seans" id="sn_otomatik_seans" value="1" checked>
                            <label class="form-check-label fw-bold" for="sn_otomatik_seans">Seansları Otomatik Oluştur</label>
                        </div>
                        <div class="form-text text-muted mt-1" id="sn_seans_aciklama">
                            <i class="fa fa-calendar-check text-success me-1"></i>Tüm seanslar ve randevular otomatik planlanacak.
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" onclick="snPaketSatisKaydet()" class="btn btn-warning fw-bold text-dark"><i class="fa fa-check"></i> Satışı Onayla</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     JAVASCRIPT: Paket Satış Modalı
     ============================================ -->
<script>
// Seans Düzenle Modal aç
function seansDuzenleAc(seansId, tarih, saat, calisanId) {
    document.getElementById('duzenle_seans_id').value = seansId;
    document.getElementById('duzenle_tarih').value = tarih;
    document.getElementById('duzenle_saat').value = saat;
    var sel = document.getElementById('duzenle_calisan');
    for (var i = 0; i < sel.options.length; i++) {
        sel.options[i].selected = (parseInt(sel.options[i].value) === calisanId);
    }
    var modal = new bootstrap.Modal(document.getElementById('seansDuzenleModal'));
    modal.show();
}

// Paket Satış - Müşteri Arama
$('#sn_musteri_arama').on('keyup', function() {
    var term = $(this).val().trim();
    var box = $('#sn_arama_sonuclari');
    if (term.length < 2) { box.hide(); return; }
    $.post('ajax_musteri_islem.php', { islem: 'ara', term: term }, function(data) {
        var html = '';
        if (data.length > 0) {
            data.forEach(function(m) {
                html += `<button type="button" class="list-group-item list-group-item-action"
                         onclick="snMusteriSec(${m.id}, '${m.ad_soyad.replace(/'/g,"\\'")}', '${m.telefon}')">
                         <strong>${m.ad_soyad}</strong> <small class="text-muted">(${m.telefon})</small>
                         </button>`;
            });
        } else {
            html = '<div class="list-group-item text-muted small">Kayıt bulunamadı. (+) ile ekleyebilirsiniz.</div>';
        }
        box.html(html).show();
    }, 'json');
});

$(document).on('click', function(e) {
    if (!$(e.target).closest('#sn_musteri_arama, #sn_arama_sonuclari, #snYeniMusteriAlan').length) {
        $('#sn_arama_sonuclari').hide();
    }
});

function snMusteriSec(id, ad, telefon) {
    $('#sn_musteri_id').val(id);
    $('#sn_musteri_arama').val(ad).prop('disabled', true);
    $('#sn_arama_sonuclari').hide();
    $('#snYeniMusteriAlan').collapse('hide');
    $('#sn_secilen_ad').text(ad + ' (' + telefon + ')');
    $('#sn_secilen_bilgi').show();
    $('#sn_btn_temizle').show();
}

function snMusteriTemizle() {
    $('#sn_musteri_id').val('');
    $('#sn_musteri_arama').val('').prop('disabled', false).focus();
    $('#sn_secilen_bilgi').hide();
    $('#sn_btn_temizle').hide();
}

function snHizliMusteriEkle() {
    var ad = $('#sn_yeni_ad').val().trim();
    var tel = $('#sn_yeni_tel').val().trim();
    if (ad === '' || tel === '') { Swal.fire('Hata', 'Ad Soyad ve Telefon giriniz.', 'warning'); return; }
    $.post('ajax_musteri_islem.php', { islem: 'ekle', ad_soyad: ad, telefon: tel }, function(res) {
        if (res.status === 'success') {
            snMusteriSec(res.musteri.id, res.musteri.ad_soyad, res.musteri.telefon);
            $('#sn_yeni_ad').val(''); $('#sn_yeni_tel').val('');
            Swal.fire({ icon: 'success', title: 'Müşteri Eklendi', timer: 1500, showConfirmButton: false });
        } else { Swal.fire('Hata', res.message, 'error'); }
    }, 'json');
}

function snPaketSatisKaydet() {
    if ($('#sn_musteri_id').val() == '') {
        Swal.fire('Uyarı', 'Müşteri seçiniz.', 'warning'); return;
    }
    var formData = $('#formSnPaketSatis').serialize();
    if (!$('#sn_otomatik_seans').is(':checked')) formData += '&otomatik_seans=0';
    formData += '&paket_satis_yap=1';
    var btn = $('button[onclick="snPaketSatisKaydet()"]');
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> İşleniyor...');
    $.ajax({
        url: 'admin.php?tab=seanslar',
        type: 'POST',
        data: formData,
        success: function() {
            btn.prop('disabled', false).html('<i class="fa fa-check"></i> Satışı Onayla');
            Swal.fire({ title: 'Başarılı!', icon: 'success', timer: 1500, showConfirmButton: false })
                .then(function() { location.reload(); });
        },
        error: function() {
            btn.prop('disabled', false).html('<i class="fa fa-check"></i> Satışı Onayla');
            Swal.fire('Hata', 'Bir sorun oluştu.', 'error');
        }
    });
}

// Modal kapanınca sıfırla
document.getElementById('paketSatisModal').addEventListener('hidden.bs.modal', function() {
    snMusteriTemizle();
    $('#snYeniMusteriAlan').collapse('hide');
    $('#sn_yeni_ad').val(''); $('#sn_yeni_tel').val('');
});

// Otomatik seans checkbox açıklama
$(document).on('change', '#sn_otomatik_seans', function() {
    var el = document.getElementById('sn_seans_aciklama');
    if (this.checked) {
        el.innerHTML = '<i class="fa fa-calendar-check text-success me-1"></i>Tüm seanslar ve randevular otomatik planlanacak.';
    } else {
        el.innerHTML = '<i class="fa fa-hand-pointer text-warning me-1"></i>Sadece paket kaydedilecek. Seansları kendiniz oluşturabilirsiniz.';
    }
});
</script>