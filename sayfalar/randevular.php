<?php

/**
 * Uygun randevu tarihi ve saati bul
 */
function uygunRandevuBul($baglanti, $baslangic_tarih, $baslangic_saat, $personel_id, $max_deneme = 30) {
    $tarih = $baslangic_tarih;
    $saat = $baslangic_saat;
    $deneme = 0;
    
    while ($deneme < $max_deneme) {
        $gun_numarasi = date('N', strtotime($tarih));
        if ($gun_numarasi == 2) {
            $tarih = date('Y-m-d', strtotime($tarih . ' +1 day'));
            $deneme++;
            continue;
        }
        
        $kontrol = $baglanti->prepare("
            SELECT COUNT(*) FROM randevular 
            WHERE randevu_tarihi = ? AND randevu_saati = ? 
            AND (calisan_id = ? OR personel_id = ?) AND durum != 'iptal'
        ");
        $kontrol->execute([$tarih, $saat, $personel_id, $personel_id]);
        
        if ($kontrol->fetchColumn() == 0) {
            return ['tarih' => $tarih, 'saat' => $saat];
        }
        
        $tarih = date('Y-m-d', strtotime($tarih . ' +1 day'));
        $deneme++;
    }
    
    return ['tarih' => $tarih, 'saat' => $saat];
}

// ============================================
//  YARDIMCI FONKSİYONLAR (TEKRAR KULLANIM)
// ============================================

/**
 * Session tabanlı flash mesaj.
 */
function flashMesaj($tip, $mesaj) {
    $_SESSION['flash'] = ['tip' => $tip, 'mesaj' => $mesaj];

}
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
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
 * ID'ye göre hizmet bilgisi getir (hizmet_adi, sure_dk, fiyat).
 */
function hizmetBilgi($hizmetId) {
    global $baglanti;
    $stmt = $baglanti->prepare("SELECT hizmet_adi, sure_dk, fiyat FROM hizmetler WHERE id = ?");
    $stmt->execute([$hizmetId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * ID'ye göre paket bilgisi getir.
 */
function paketBilgi($paketId) {
    global $baglanti;
    $stmt = $baglanti->prepare("SELECT * FROM paketler WHERE id = ?");
    $stmt->execute([$paketId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
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
 * Filtreli randevu listesi için SQL ve parametreleri oluştur.
 * @param bool $countOnly Sadece sayım mı?
 */
function randevuFiltreSql($countOnly = false, $extraParams = []) {
    $bas_tarih = $_GET['bas_tarih'] ?? date('Y-m-01');
    $bit_tarih = $_GET['bit_tarih'] ?? date('Y-m-t');
    $personel_id = $_GET['personel_id'] ?? '';
    $arama_terimi = $_GET['arama'] ?? '';

    if ($countOnly) {
        $sql = "SELECT COUNT(*) FROM randevular r WHERE 1=1 AND r.onay_durumu = 'onaylandi'";
    } else {
        $sql = "SELECT r.*, c.ad_soyad as calisan_ad 
                FROM randevular r 
                LEFT JOIN calisanlar c ON r.calisan_id = c.id 
                WHERE 1=1 AND r.onay_durumu = 'onaylandi'";
    }
    $params = [];

    $sql .= " AND (r.randevu_tarihi BETWEEN ? AND ?)";
    $params[] = $bas_tarih;
    $params[] = $bit_tarih;

    if (!empty($personel_id)) {
        $sql .= " AND r.calisan_id = ?";
        $params[] = $personel_id;
    }
    if (!empty($arama_terimi)) {
        $sql .= " AND (r.musteri_ad LIKE ? OR r.telefon LIKE ?)";
        $params[] = "%$arama_terimi%";
        $params[] = "%$arama_terimi%";
    }

    if (!$countOnly) {
        $sql .= " ORDER BY r.randevu_tarihi DESC, r.randevu_saati DESC";
    }

    return [$sql, $params];
}

// ============================================
//  EXCEL EXPORT (En üstte kalmalı)
// ============================================
if (isset($_GET['excel'])) {
    list($sql, $params) = randevuFiltreSql(false);
    $sorgu = $baglanti->prepare($sql);
    $sorgu->execute($params);
    $excelData = $sorgu->fetchAll(PDO::FETCH_ASSOC);

    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=randevu_listesi_" . date('d-m-Y') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo "Durum\tMüşteri\tTelefon\tPersonel\tTarih\tSaat\tHizmet\tTutar\n";
    foreach ($excelData as $row) {
        $tutar = number_format($row['fiyat'] ?? 0, 2, ',', '');
        // UTF-8 BOM ekleyelim ki Türkçe karakterler düzgün görünsün
        echo mb_convert_encoding(
            "{$row['durum']}\t{$row['musteri_ad']}\t{$row['telefon']}\t{$row['calisan_ad']}\t{$row['randevu_tarihi']}\t{$row['randevu_saati']}\t{$row['hizmet_adi']}\t{$tutar}",
            "UTF-16LE",
            "UTF-8"
        ) . "\n";
    }
    exit;
}

// ============================================
//  POST İŞLEMLERİ (TEK BİR YERDE TOPLANDI)
// ============================================

// --- A) Hızlı Randevu Ekleme ---
if (isset($_POST['hizli_randevu_ekle'])) {
    try {
        $m_id = (int) $_POST['musteri_id'];
        $h_id = (int) $_POST['hizmet_id'];
        $c_id = (int) $_POST['calisan_id'];
        $tarih = $_POST['tarih'];
        $saat = $_POST['saat'];

        $m_bilgi = musteriBilgi($m_id);
        $h_bilgi = hizmetBilgi($h_id);

        if (!$m_bilgi || !$h_bilgi) {
            throw new Exception("Müşteri veya hizmet bulunamadı.");
        }

        $bitis_saati = bitisSaatiHesapla($tarih, $saat, $h_bilgi['sure_dk']);

        $stmt = $baglanti->prepare("
            INSERT INTO randevular (musteri_id, musteri_ad, telefon, hizmet_adi, randevu_tarihi, randevu_saati, bitis_saati, calisan_id, durum, fiyat)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'bekliyor', ?)
        ");
        $stmt->execute([
            $m_id,
            $m_bilgi['ad_soyad'],
            $m_bilgi['telefon'],
            $h_bilgi['hizmet_adi'],
            $tarih,
            $saat,
            $bitis_saati,
            $c_id,
            $h_bilgi['fiyat']
        ]);

        flashMesaj('success', 'Randevu başarıyla eklendi.');
    } catch (Exception $e) {
        flashMesaj('danger', 'Randevu eklenemedi: ' . $e->getMessage());
    }
    redirect("admin.php?tab=randevular");
}

// --- B) Paket Satışı (Finans sayfasıyla aynı mantık - otomatik randevu oluşturur) ---
if (isset($_POST['paket_satis_yap'])) {
    try {
        $baglanti->beginTransaction();

        $musteri_id    = (int) $_POST['musteri_id'];
        $paket_id      = (int) $_POST['paket_id'];
        $personel_id   = (int) $_POST['personel_id'];
        $bas_tarih     = $_POST['baslangic_tarihi'] ?? date('Y-m-d');
        $bas_saat      = $_POST['baslangic_saati'] ?? '09:00';
        $pesinat       = (float) ($_POST['pesinat_tutari'] ?? 0);
        $odeme_turu    = $_POST['odeme_turu'] ?? 'nakit';
        $otomatik_seans = isset($_POST['otomatik_seans']) && $_POST['otomatik_seans'] == '1';

        if (empty($musteri_id))  throw new Exception("Müşteri seçilmelidir.");
        if (empty($paket_id))    throw new Exception("Paket seçilmelidir.");
        if (empty($personel_id)) throw new Exception("Personel seçilmelidir.");

        $pStmt = $baglanti->prepare("SELECT * FROM paketler WHERE id = ?");
        $pStmt->execute([$paket_id]);
        $paket = $pStmt->fetch(PDO::FETCH_ASSOC);
        if (!$paket) throw new Exception("Paket bulunamadı.");

        $mStmt = $baglanti->prepare("SELECT ad_soyad, telefon FROM musteriler WHERE id = ?");
        $mStmt->execute([$musteri_id]);
        $musteri = $mStmt->fetch(PDO::FETCH_ASSOC);
        if (!$musteri) throw new Exception("Müşteri bulunamadı.");

        $seans_sayisi = (int) $paket['seans_sayisi'];
        $aralik       = (int) $paket['seans_araligi'] > 0 ? (int) $paket['seans_araligi'] : 30;
        $ucret        = $paket['toplam_tutar'];

        // A) Müşteri paketi kaydet
        $stmt = $baglanti->prepare("INSERT INTO musteri_paketleri (musteri_id, paket_id, toplam_seans, kullanilan_seans, ucret, durum) VALUES (?, ?, ?, 0, ?, 'aktif')");
        $stmt->execute([$musteri_id, $paket_id, $seans_sayisi, $ucret]);
        $mp_id = $baglanti->lastInsertId();

        // B) Peşinat varsa kasaya işle
        if ($pesinat > 0) {
            $baglanti->prepare("INSERT INTO kasa_hareketleri (tarih, islem_turu, kategori, tutar, aciklama, odeme_turu, musteri_id) VALUES (NOW(), 'tahsilat', 'Paket Satışı', ?, ?, ?, ?)")
                     ->execute([$pesinat, $paket['paket_adi'] . " Satışı Peşinatı", $odeme_turu, $musteri_id]);
        }

        // C) Otomatik seans + randevu oluştur (sadece checkbox işaretliyse)
        if ($otomatik_seans) {
            $current_date = $bas_tarih;
            for ($i = 1; $i <= $seans_sayisi; $i++) {
                $uygun_slot = uygunRandevuBul($baglanti, $current_date, $bas_saat, $personel_id);
                $current_date = $uygun_slot['tarih'];
                $current_saat = $uygun_slot['saat'];
                $bitis_saat = date('H:i', strtotime($current_saat . ' +60 minutes'));
                $hizmet_adi = $paket['paket_adi'] . " ($i. Seans)";

                $s = $baglanti->prepare("INSERT INTO seanslar (musteri_paket_id, calisan_id, kacinci_seans, durum, randevu_tarihi, randevu_saati) VALUES (?, ?, ?, 'bekliyor', ?, ?)");
                $s->execute([$mp_id, $personel_id, $i, $current_date, $bas_saat]);
                $seans_id = $baglanti->lastInsertId();

                $r = $baglanti->prepare("
                    INSERT INTO randevular 
                    (musteri_id, musteri_ad, telefon, hizmet_adi, randevu_tarihi, randevu_saati, bitis_saati, calisan_id, personel_id, durum, fiyat, seans_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'bekliyor', 0, ?)
                ");
                $r->execute([$musteri_id, $musteri['ad_soyad'], $musteri['telefon'], $hizmet_adi, $current_date, $current_saat, $bitis_saat, $personel_id, $personel_id, $seans_id]);

                $current_date = date('Y-m-d', strtotime($current_date . " +$aralik days"));
            }
            $flash_mesaj = "Paket satıldı ve $seans_sayisi adet randevu otomatik oluşturuldu.";
        } else {
            $flash_mesaj = "Paket başarıyla kaydedildi. Seansları 'Seans Düş' butonuyla kendiniz oluşturabilirsiniz.";
        }

        $baglanti->commit();
        flashMesaj('success', $flash_mesaj);
    } catch (Exception $e) {
        if ($baglanti->inTransaction()) {
            $baglanti->rollBack();
        }
        error_log("PAKET SATIS HATASI: " . $e->getMessage() . " - Line: " . $e->getLine());
        flashMesaj('danger', 'Paket satışı başarısız: ' . $e->getMessage() . ' (Satır: ' . $e->getLine() . ')');
    }
    redirect("admin.php?tab=randevular");
}

// --- C) Seans Ekleme (Aktif paketten seans düş + randevu oluştur) ---
if (isset($_POST['seans_ekle'])) {
    try {
        $baglanti->beginTransaction();

        $mp_id      = (int) $_POST['musteri_paket_id'];
        $calisan_id = (int) $_POST['calisan_id'];
        $oda_id     = !empty($_POST['oda_id']) ? (int) $_POST['oda_id'] : null;
        $tarih      = $_POST['tarih'];
        $saat       = $_POST['saat'];

        // Paket + musteri + gercek seans sayisi
        $kontrol = $baglanti->prepare("
            SELECT mp.toplam_seans, mp.musteri_id, mp.paket_id,
                   m.ad_soyad, m.telefon, p.paket_adi,
                   COUNT(s.id) AS gercek_seans_sayisi
            FROM musteri_paketleri mp
            JOIN musteriler m ON mp.musteri_id = m.id
            JOIN paketler p ON mp.paket_id = p.id
            LEFT JOIN seanslar s ON s.musteri_paket_id = mp.id
            WHERE mp.id = ? AND mp.durum = 'aktif'
            GROUP BY mp.id
        ");
        $kontrol->execute([$mp_id]);
        $veri = $kontrol->fetch(PDO::FETCH_ASSOC);

        if (!$veri) throw new Exception("Aktif paket bulunamadi.");

        $yeni_seans_no = $veri['gercek_seans_sayisi'] + 1;
        $bitis_saat    = date('H:i', strtotime($saat . ' +60 minutes'));
        $hizmet_adi    = $veri['paket_adi'] . " ($yeni_seans_no. Seans)";

        // 1. Seans kaydi
        $stmt = $baglanti->prepare("
            INSERT INTO seanslar (musteri_paket_id, calisan_id, oda_id, randevu_tarihi, randevu_saati, kacinci_seans, durum)
            VALUES (?, ?, ?, ?, ?, ?, 'bekliyor')
        ");
        $stmt->execute([$mp_id, $calisan_id, $oda_id, $tarih, $saat, $yeni_seans_no]);
        $seans_id = $baglanti->lastInsertId();

        // 2. Randevu kaydi (ajandada ve randevular listesinde gorunsun)
        $r = $baglanti->prepare("
            INSERT INTO randevular 
            (musteri_id, musteri_ad, telefon, hizmet_adi, randevu_tarihi, randevu_saati, bitis_saati, calisan_id, personel_id, durum, fiyat, seans_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'bekliyor', 0, ?)
        ");
        $r->execute([
            $veri['musteri_id'],
            $veri['ad_soyad'],
            $veri['telefon'],
            $hizmet_adi,
            $tarih,
            $saat,
            $bitis_saat,
            $calisan_id,
            $calisan_id,
            $seans_id
        ]);

        // 3. Paket sayacini guncelle
        $baglanti->prepare("UPDATE musteri_paketleri SET kullanilan_seans = kullanilan_seans + 1 WHERE id = ?")
                 ->execute([$mp_id]);

        // 4. Tum seanslar bittiyse paketi tamamlandi yap
        if ($yeni_seans_no >= (int)$veri['toplam_seans']) {
            $baglanti->prepare("UPDATE musteri_paketleri SET durum = 'tamamlandi' WHERE id = ?")
                     ->execute([$mp_id]);
        }

        $baglanti->commit();
        flashMesaj('success', "$yeni_seans_no. seans basariyla eklendi ve randevu olusturuldu.");
    } catch (Exception $e) {
        $baglanti->rollBack();
        flashMesaj('danger', 'Seans eklenemedi: ' . $e->getMessage());
    }
    redirect("admin.php?tab=randevular");
}

// --- D) Randevu Silme ---
if (isset($_GET['randevu_sil_id'])) {
    $id = (int) $_GET['randevu_sil_id'];
    try {
        $baglanti->prepare("DELETE FROM randevular WHERE id = ?")->execute([$id]);
        flashMesaj('success', 'Randevu silindi.');
    } catch (PDOException $e) {
        flashMesaj('danger', 'Silme başarısız: ' . $e->getMessage());
    }
    redirect("admin.php?tab=randevular");
}

// ============================================
//  VERİLERİ ÇEK (SAYFA YÜKLENİRKEN)
// ============================================

// Randevular (filtreli)
list($sql, $params) = randevuFiltreSql(false);
$randevular = $baglanti->prepare($sql);
$randevular->execute($params);
$randevular = $randevular->fetchAll(PDO::FETCH_ASSOC);

// Modal ve filtreler için gerekli listeler
$calisanlar = $baglanti->query("SELECT * FROM calisanlar ORDER BY ad_soyad ASC")->fetchAll(PDO::FETCH_ASSOC);
$musteriler = $baglanti->query("SELECT id, ad_soyad, telefon FROM musteriler ORDER BY ad_soyad ASC")->fetchAll(PDO::FETCH_ASSOC);
$hizmetler = $baglanti->query("SELECT * FROM hizmetler ORDER BY hizmet_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
$paketler = $baglanti->query("SELECT * FROM paketler ORDER BY paket_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
$odalar = $baglanti->query("SELECT * FROM odalar ORDER BY oda_adi ASC")->fetchAll(PDO::FETCH_ASSOC);

// Aktif paketler (seans ekleme modalı için)
$aktif_paketler = $baglanti->query("
    SELECT mp.id, mp.musteri_id, mp.paket_id, mp.toplam_seans, mp.kullanilan_seans, mp.ucret, mp.durum,
           m.ad_soyad, p.paket_adi,
           COUNT(s.id) AS gercek_seans_sayisi,
           mp.toplam_seans AS paket_limit,
           COUNT(s.id) AS eklenen_seans
    FROM musteri_paketleri mp
    JOIN musteriler m ON mp.musteri_id = m.id
    JOIN paketler p ON mp.paket_id = p.id
    LEFT JOIN seanslar s ON s.musteri_paket_id = mp.id
    WHERE mp.durum = 'aktif'
    GROUP BY mp.id, mp.musteri_id, mp.paket_id, mp.toplam_seans, mp.kullanilan_seans, mp.ucret, mp.durum, m.ad_soyad, p.paket_adi
    ORDER BY m.ad_soyad ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Filtre değerlerini GET'ten al (formda gösterim için)
$bas_tarih = $_GET['bas_tarih'] ?? date('Y-m-01');
$bit_tarih = $_GET['bit_tarih'] ?? date('Y-m-t');
$personel_id = $_GET['personel_id'] ?? '';
$arama_terimi = $_GET['arama'] ?? '';

// Excel linki (filtre parametrelerini koru)
$excel_url = "admin.php?tab=randevular&excel=1&bas_tarih=" . urlencode($bas_tarih) .
             "&bit_tarih=" . urlencode($bit_tarih) .
             "&personel_id=" . urlencode($personel_id) .
             "&arama=" . urlencode($arama_terimi);
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
     ANA KART: RANDEVULAR LİSTESİ
     ============================================ -->
<div class="card card-custom">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="card-title"><i class="fa fa-list text-primary"></i> Randevular</span>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRandevuEkle">
                <i class="fa fa-plus"></i> Randevu
            </button>
            <button class="btn btn-info text-white btn-sm" data-bs-toggle="modal" data-bs-target="#seansEkleModal">
                <i class="fa fa-clock"></i> Seans
            </button>
            <button class="btn btn-warning text-dark btn-sm" data-bs-toggle="modal" data-bs-target="#paketSatisModal">
                <i class="fa fa-box"></i> Paket Sat
            </button>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($excel_url) ?>" target="_blank" class="btn btn-success btn-sm">
                <i class="fa fa-file-excel"></i> Excel
            </a>
            <button class="btn btn-dark btn-sm" onclick="window.print()">
                <i class="fa fa-print"></i> Yazdır
            </button>
        </div>
    </div>

    <!-- FİLTRE FORM -->
    <div class="card-body p-0">
        <form method="GET" action="admin.php" class="p-3 bg-light border-bottom d-flex gap-2 flex-wrap align-items-center">
            <input type="hidden" name="tab" value="randevular">
            <div class="d-flex align-items-center gap-1">
                <label class="text-muted small">Başlangıç:</label>
                <input type="date" name="bas_tarih" class="form-control form-control-sm" value="<?= $bas_tarih ?>">
            </div>
            <div class="d-flex align-items-center gap-1">
                <label class="text-muted small">Bitiş:</label>
                <input type="date" name="bit_tarih" class="form-control form-control-sm" value="<?= $bit_tarih ?>">
            </div>
            <select name="personel_id" class="form-select form-select-sm" style="width: 150px;">
                <option value="">Tüm Personeller</option>
                <?php foreach ($calisanlar as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $personel_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['ad_soyad']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="arama" class="form-control form-control-sm" placeholder="Müşteri Ara" style="width: 150px;" value="<?= htmlspecialchars($arama_terimi) ?>">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filtrele</button>
        </form>

        <!-- TABLO -->
        <div class="table-responsive">
            <table class="table table-hover m-0">
                <thead>
                    <tr>
                        <th class="text-center">Durum</th>
                        <th>Müşteri</th>
                        <th>Telefon</th>
                        <th>Personel</th>
                        <th>Tarih</th>
                        <th>Saat</th>
                        <th>Hizmet</th>
                        <th>Tutar</th>
                        <th class="text-end">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($randevular)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa fa-calendar-times fa-2x mb-2"></i><br>
                                Bu kriterlere uygun randevu bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($randevular as $r): 
                            $durumRenk = 'bg-warning text-dark';
                            $durumMetin = 'Bekliyor';
                            if ($r['durum'] == 'geldi') { $durumRenk = 'bg-success'; $durumMetin = 'GELDİ'; }
                            elseif ($r['durum'] == 'gelmedi') { $durumRenk = 'bg-secondary'; $durumMetin = 'GELMEDİ'; }
                            elseif ($r['durum'] == 'iptal') { $durumRenk = 'bg-danger'; $durumMetin = 'İPTAL'; }
                        ?>
                        <tr style="cursor: pointer;" onclick="randevuDetayAc(<?= $r['id'] ?>)">
                            <td class="text-center"><span class="badge <?= $durumRenk ?>"><?= $durumMetin ?></span></td>
                            <td class="fw-bold"><?= e($r['musteri_ad']) ?></td>
                            <td><?= formatTel($r['telefon']) ?></td>
                            <td><?= e($r['calisan_ad']) ?></td>
                            <td><?= !empty($r['randevu_tarihi']) ? date('d.m.Y', strtotime($r['randevu_tarihi'])) : '' ?></td>
                            <td><?= e($r['randevu_saati']) ?></td>
                            <td><?= e($r['hizmet_adi']) ?></td>
                            <td><?= number_format($r['fiyat'] ?? 0, 2) ?> ₺</td>
                            <td class="text-end">
                                <a href="?tab=randevular&randevu_sil_id=<?= $r['id'] ?>" 
                                   class="btn btn-sm btn-outline-danger" 
                                   onclick="event.stopPropagation(); return confirm('Bu randevuyu silmek istediğinize emin misiniz?')">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================
     MODAL: RANDEVU EKLE (Müşteri Arama ile)
     ============================================ -->
<div class="modal fade" id="modalRandevuEkle" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa fa-plus"></i> Hızlı Randevu Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="musteri_id" id="secilen_musteri_id" required>

                    <!-- Müşteri Seçimi -->
                    <div class="mb-3 p-2 border rounded bg-light">
                        <label class="small fw-bold text-muted mb-2">Müşteri Seçimi / Yeni Ekleme</label>
                        <div class="row g-2">
                            <div class="col-md-7 position-relative">
                                <input type="text" id="musteri_arama_input" class="form-control" placeholder="Ad Soyad ile arayın..." autocomplete="off">
                                <div id="arama_sonuclari" class="list-group position-absolute w-100 shadow" style="display:none; z-index: 1000; max-height: 200px; overflow-y: auto;"></div>
                            </div>
                            <div class="col-md-5">
                                <div class="input-group">
                                    <input type="text" name="yeni_telefon" id="yeni_telefon_input" class="form-control" placeholder="05XX...">
                                    <button class="btn btn-success" type="button" onclick="hizliMusteriEkle()" title="Yeni Müşteri Kaydet">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="secilen_bilgi" class="small text-success mt-1 fw-bold" style="display:none;">
                            <i class="fa fa-check-circle"></i> Seçilen: <span id="secilen_ad"></span>
                        </div>
                    </div>

                    <!-- Hizmet -->
                    <div class="mb-3">
                        <label>Hizmet</label>
                        <select name="hizmet_id" class="form-select" required>
                            <option value="">Seçiniz</option>
                            <?php foreach ($hizmetler as $h): ?>
                                <option value="<?= $h['id'] ?>">
                                    <?= htmlspecialchars($h['hizmet_adi']) ?> (<?= $h['sure_dk'] ?> dk)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Personel -->
                    <div class="mb-3">
                        <label>Personel</label>
                        <select name="calisan_id" class="form-select" required>
                            <option value="">Seçiniz</option>
                            <?php foreach ($calisanlar as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['ad_soyad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tarih / Saat -->
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label>Tarih</label>
                            <input type="date" name="tarih" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label>Saat</label>
                            <input type="time" name="saat" class="form-control" value="<?= date('H:00') ?>" required>
                        </div>
                    </div>

                    <button type="submit" name="hizli_randevu_ekle" class="btn btn-primary w-100">
                        <i class="fa fa-calendar-check"></i> Randevuyu Oluştur
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     MODAL: PAKET SATIŞI
     ============================================ -->
<div class="modal fade" id="paketSatisModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark"><i class="fa fa-box-open"></i> Paket Satışı Yap</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">

                    <!-- Müşteri Seçimi -->
                    <div class="mb-3 position-relative">
                        <label class="fw-bold">Müşteri Seçimi</label>
                        <input type="hidden" name="musteri_id" id="rv_musteri_id" required>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa fa-search"></i></span>
                            <input type="text" id="rv_musteri_arama" class="form-control" placeholder="Müşteri Ara..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#rvYeniMusteriAlan"><i class="fa fa-plus"></i></button>
                            <button class="btn btn-outline-danger" type="button" id="rv_btn_temizle" style="display:none;" onclick="rvMusteriTemizle()"><i class="fa fa-times"></i></button>
                        </div>
                        <div id="rv_arama_sonuclari" class="list-group position-absolute w-100 shadow" style="display:none; z-index:1050; max-height:200px; overflow-y:auto;"></div>
                        <div id="rv_secilen_bilgi" class="small text-success mt-1 fw-bold" style="display:none;"><i class="fa fa-check-circle"></i> Seçilen: <span id="rv_secilen_ad"></span></div>
                        <div class="collapse mt-2 p-3 bg-light border rounded shadow-sm" id="rvYeniMusteriAlan" style="position:absolute; z-index:1060; width:100%;">
                            <label class="small fw-bold text-muted mb-1">Yeni Müşteri Ekle</label>
                            <div class="mb-2">
                                <input type="text" id="rv_yeni_ad" class="form-control form-control-sm mb-1" placeholder="Ad Soyad">
                                <input type="text" id="rv_yeni_tel" class="form-control form-control-sm" placeholder="Telefon">
                            </div>
                            <button class="btn btn-success btn-sm w-100" type="button" onclick="rvHizliMusteriEkle()"><i class="fa fa-save"></i> Kaydet ve Seç</button>
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
                        <select name="personel_id" class="form-select" required>
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
                            <input class="form-check-input" type="checkbox" name="otomatik_seans" id="rv_otomatik_seans" value="1" checked>
                            <label class="form-check-label fw-bold" for="rv_otomatik_seans">Seansları Otomatik Oluştur</label>
                        </div>
                        <div class="form-text text-muted mt-1" id="rv_seans_aciklama">
                            <i class="fa fa-calendar-check text-success me-1"></i>Tüm seanslar ve randevular otomatik planlanacak.
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" onclick="rvPaketSatisKaydet()" class="btn btn-warning fw-bold text-dark"><i class="fa fa-check"></i> Satışı Onayla</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     MODAL: SEANS EKLE (Aktif Paketler)
     ============================================ -->
<div class="modal fade" id="seansEkleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fa fa-clock"></i> Seans Düş (Aktif Paketler)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="mb-3">
                        <label>Paket Seçimi</label>
                        <select name="musteri_paket_id" class="form-select" required data-ts data-placeholder="Müşteri adı veya paket ara...">
                            <option value="">Seçiniz</option>
                            <?php foreach ($aktif_paketler as $ap): ?>
                                <option value="<?= $ap['id'] ?>">
                                    <?= htmlspecialchars($ap['ad_soyad']) ?> - <?= htmlspecialchars($ap['paket_adi']) ?> 
                                    (<?= $ap['eklenen_seans'] ?>/<?= $ap['paket_limit'] ?> seans)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>İlgilenen Personel</label>
                        <select name="calisan_id" class="form-select" required>
                            <option value="">Seçiniz</option>
                            <?php foreach ($calisanlar as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['ad_soyad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Oda (Opsiyonel)</label>
                        <select name="oda_id" class="form-select">
                            <option value="">Seçiniz</option>
                            <?php foreach ($odalar as $oda): ?>
                                <option value="<?= $oda['id'] ?>"><?= htmlspecialchars($oda['oda_adi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label>Tarih</label>
                            <input type="date" name="tarih" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label>Saat</label>
                            <input type="time" name="saat" class="form-control" value="<?= date('H:i') ?>" required>
                        </div>
                    </div>
                    <button type="submit" name="seans_ekle" class="btn btn-info text-white w-100">
                        <i class="fa fa-save"></i> Seansı İşle
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     JAVASCRIPT: Müşteri Arama & Hızlı Ekleme
     ============================================ -->
<script>
// Müşteri arama (jQuery ile)
$(document).ready(function() {
    $('#musteri_arama_input').on('keyup', function() {
        var term = $(this).val().trim();
        var resultBox = $('#arama_sonuclari');

        if (term.length < 2) {
            resultBox.hide();
            if (term.length === 0) {
                $('#secilen_musteri_id').val('');
                $('#secilen_bilgi').hide();
            }
            return;
        }

        $.post('ajax_musteri_islem.php', { islem: 'ara', term: term }, function(data) {
            var html = '';
            if (data.length > 0) {
                data.forEach(function(m) {
                    html += `<button type="button" class="list-group-item list-group-item-action" 
                             onclick="musteriSec(${m.id}, '${m.ad_soyad.replace(/'/g, "\\'")}', '${m.telefon}')">
                             <strong>${m.ad_soyad}</strong> <small class="text-muted">(${m.telefon})</small>
                             </button>`;
                });
            } else {
                html = '<div class="list-group-item text-muted">Kayıt bulunamadı. Yeni ekleyebilirsiniz.</div>';
            }
            resultBox.html(html).show();
        }, 'json');
    });
});

// Müşteri seç
function musteriSec(id, ad, telefon) {
    $('#secilen_musteri_id').val(id);
    $('#musteri_arama_input').val(ad);
    $('#yeni_telefon_input').val(telefon);
    $('#arama_sonuclari').hide();
    $('#secilen_ad').text(ad);
    $('#secilen_bilgi').show();
}

// Hızlı müşteri ekle (+ butonu)
function hizliMusteriEkle() {
    var ad = $('#musteri_arama_input').val().trim();
    var tel = $('#yeni_telefon_input').val().trim();

    if (ad === '' || tel === '') {
        alert("Lütfen Ad Soyad ve Telefon giriniz!");
        return;
    }

    if ($('#secilen_musteri_id').val() !== '' && !confirm("Şu an zaten bir müşteri seçili. Yeni müşteri olarak kaydetmek istiyor musunuz?")) {
        return;
    }

    $.post('ajax_musteri_islem.php', { islem: 'ekle', ad_soyad: ad, telefon: tel }, function(res) {
        if (res.status === 'success') {
            musteriSec(res.musteri.id, res.musteri.ad_soyad, res.musteri.telefon);
            alert("Müşteri başarıyla kaydedildi ve seçildi!");
        } else {
            alert("Hata: " + res.message);
        }
    }, 'json');
}

// Modal kapanınca formu sıfırla
var randevuModal = document.getElementById('modalRandevuEkle');
if (randevuModal) {
    randevuModal.addEventListener('hidden.bs.modal', function () {
        $('#musteri_arama_input').val('');
        $('#yeni_telefon_input').val('');
        $('#secilen_musteri_id').val('');
        $('#secilen_bilgi').hide();
        $('#arama_sonuclari').hide();
    });
}

// randevuDetayAc fonksiyonu tanımlı değilse uyarı
// Paket Satış Modalı - Müşteri Arama (finans stili)
$('#rv_musteri_arama').on('keyup', function() {
    var term = $(this).val().trim();
    var box = $('#rv_arama_sonuclari');
    if (term.length < 2) { box.hide(); return; }
    $.post('ajax_musteri_islem.php', { islem: 'ara', term: term }, function(data) {
        var html = '';
        if (data.length > 0) {
            data.forEach(function(m) {
                html += `<button type="button" class="list-group-item list-group-item-action"
                         onclick="rvMusteriSec(${m.id}, '${m.ad_soyad.replace(/'/g,"\\'")}', '${m.telefon}')">
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
    if (!$(e.target).closest('#rv_musteri_arama, #rv_arama_sonuclari, #rvYeniMusteriAlan').length) {
        $('#rv_arama_sonuclari').hide();
    }
});

function rvMusteriSec(id, ad, telefon) {
    $('#rv_musteri_id').val(id);
    $('#rv_musteri_arama').val(ad).prop('disabled', true);
    $('#rv_arama_sonuclari').hide();
    $('#rvYeniMusteriAlan').collapse('hide');
    $('#rv_secilen_ad').text(ad + ' (' + telefon + ')');
    $('#rv_secilen_bilgi').show();
    $('#rv_btn_temizle').show();
}

function rvMusteriTemizle() {
    $('#rv_musteri_id').val('');
    $('#rv_musteri_arama').val('').prop('disabled', false).focus();
    $('#rv_secilen_bilgi').hide();
    $('#rv_btn_temizle').hide();
}

function rvHizliMusteriEkle() {
    var ad = $('#rv_yeni_ad').val().trim();
    var tel = $('#rv_yeni_tel').val().trim();
    if (ad === '' || tel === '') { Swal.fire('Hata', 'Ad Soyad ve Telefon giriniz.', 'warning'); return; }
    $.post('ajax_musteri_islem.php', { islem: 'ekle', ad_soyad: ad, telefon: tel }, function(res) {
        if (res.status === 'success') {
            rvMusteriSec(res.musteri.id, res.musteri.ad_soyad, res.musteri.telefon);
            $('#rv_yeni_ad').val(''); $('#rv_yeni_tel').val('');
            Swal.fire({ icon: 'success', title: 'Müşteri Eklendi', timer: 1500, showConfirmButton: false });
        } else { Swal.fire('Hata', res.message, 'error'); }
    }, 'json');
}

function rvPaketSatisKaydet() {
    if ($('#rv_musteri_id').val() == '') {
        Swal.fire('Uyarı', 'Müşteri seçiniz.', 'warning'); return;
    }
    var form = $('#paketSatisModal form');
    var formData = form.serialize();
    if (!$('#rv_otomatik_seans').is(':checked')) formData += '&otomatik_seans=0';
    formData += '&paket_satis_yap=1';
    var btn = $('button[onclick="rvPaketSatisKaydet()"]');
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> İşleniyor...');
    $.ajax({
        url: 'admin.php?tab=randevular',
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
    rvMusteriTemizle();
    $('#rvYeniMusteriAlan').collapse('hide');
    $('#rv_yeni_ad').val(''); $('#rv_yeni_tel').val('');
});

// Otomatik seans checkbox açıklama değiştir
document.getElementById('rv_otomatik_seans').addEventListener('change', function() {
    var el = document.getElementById('rv_seans_aciklama');
    if (this.checked) {
        el.innerHTML = '<i class="fa fa-calendar-check text-success me-1"></i>Tüm seanslar ve randevular otomatik planlanacak.';
    } else {
        el.innerHTML = '<i class="fa fa-hand-pointer text-warning me-1"></i>Sadece paket kaydedilecek. Seansları kendiniz oluşturabilirsiniz.';
    }
});

window.randevuDetayAc = window.randevuDetayAc || function(id) {
    console.log('Randevu detay fonksiyonu tanımlanmamış, ID:', id);
};
</script>