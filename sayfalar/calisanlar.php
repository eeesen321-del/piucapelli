<?php
// --- İŞLEMLER (TEK MERKEZLİ) ---

// 1. KAYDETME (Ekleme ve Güncelleme)
if (isset($_POST['calisan_kaydet'])) {
    $ad      = trim($_POST['ad_soyad']);
    $unvan   = trim($_POST['unvan']);
    $tel     = trim($_POST['telefon']);
    $prim    = $_POST['prim_orani'] ?? 0;
    $id      = $_POST['calisan_id'] ?? null; // ID varsa güncelleme, yoksa ekleme

    if ($ad) {
        if ($id > 0) {
            // GÜNCELLEME
            $baglanti->prepare("UPDATE calisanlar SET ad_soyad = ?, unvan = ?, telefon = ?, prim_orani = ? WHERE id = ?")->execute([$ad, $unvan, $tel, $prim, $id]);
            
            // Eski Yetkileri Sil
            $baglanti->prepare("DELETE FROM calisan_hizmetleri WHERE calisan_id = ?")->execute([$id]);
            $baglanti->prepare("DELETE FROM calisan_paketleri WHERE calisan_id = ?")->execute([$id]);
            $calisan_id = $id;
        } else {
            // EKLEME
            $baglanti->prepare("INSERT INTO calisanlar (ad_soyad, unvan, telefon, prim_orani) VALUES (?, ?, ?, ?)")->execute([$ad, $unvan, $tel, $prim]);
            $calisan_id = $baglanti->lastInsertId();
        }

        // Yeni Yetkileri Ekle (Hizmetler)
        $ekle_h = $baglanti->prepare("INSERT INTO calisan_hizmetleri (calisan_id, hizmet_id) VALUES (?, ?)");
        foreach ($_POST['hizmetler'] ?? [] as $h_id) $ekle_h->execute([$calisan_id, $h_id]);

        // Yeni Yetkileri Ekle (Paketler)
        $ekle_p = $baglanti->prepare("INSERT INTO calisan_paketleri (calisan_id, paket_id) VALUES (?, ?)");
        foreach ($_POST['paketler'] ?? [] as $p_id) $ekle_p->execute([$calisan_id, $p_id]);
    }
    header("Location: admin.php?tab=calisanlar"); 
    exit;
}

// 2. SİLME
if (isset($_GET['calisan_sil_id'])) {
    $id = $_GET['calisan_sil_id'];
    // Yetkileri ve çalışanı sil
    $baglanti->prepare("DELETE FROM calisan_hizmetleri WHERE calisan_id = ?")->execute([$id]);
    $baglanti->prepare("DELETE FROM calisan_paketleri WHERE calisan_id = ?")->execute([$id]);
    $baglanti->prepare("DELETE FROM calisanlar WHERE id = ?")->execute([$id]);
    header("Location: admin.php?tab=calisanlar"); 
    exit;
}

// --- VERİ ÇEKME ---
$calisanlar = $baglanti->query("
    SELECT c.*, 
    ((SELECT COUNT(*) FROM calisan_hizmetleri WHERE calisan_id = c.id) + 
     (SELECT COUNT(*) FROM calisan_paketleri WHERE calisan_id = c.id)) as yetki_sayisi 
    FROM calisanlar c 
    ORDER BY c.ad_soyad ASC
")->fetchAll(PDO::FETCH_ASSOC);

$tum_hizmetler = $baglanti->query("SELECT * FROM hizmetler ORDER BY kategori ASC, hizmet_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
$tum_paketler  = $baglanti->query("SELECT * FROM paketler ORDER BY kategori ASC, paket_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
$roller        = $baglanti->query("SELECT * FROM roller ORDER BY rol_adi ASC")->fetchAll(PDO::FETCH_ASSOC);

// Düzenleme Verisi Hazırlığı
$duzenle_id = $_GET['calisan_duzenle'] ?? null;
$ad_val = ""; $unvan_val = ""; $tel_val = ""; $prim_val = 0;
$aktif_hizmetler = []; $aktif_paketler = [];

if ($duzenle_id) {
    $c_sorgu = $baglanti->prepare("SELECT * FROM calisanlar WHERE id = ?"); 
    $c_sorgu->execute([$duzenle_id]); 
    $c_veri = $c_sorgu->fetch(PDO::FETCH_ASSOC);
    
    if($c_veri) {
        $ad_val = $c_veri['ad_soyad'];
        $unvan_val = $c_veri['unvan']; 
        $tel_val = $c_veri['telefon']; 
        $prim_val = $c_veri['prim_orani'];
        
        // Yetkileri Çek
        $y = $baglanti->prepare("SELECT hizmet_id FROM calisan_hizmetleri WHERE calisan_id = ?"); 
        $y->execute([$duzenle_id]); 
        $aktif_hizmetler = $y->fetchAll(PDO::FETCH_COLUMN);

        $p = $baglanti->prepare("SELECT paket_id FROM calisan_paketleri WHERE calisan_id = ?"); 
        $p->execute([$duzenle_id]); 
        $aktif_paketler = $p->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>

<div class="row">
    <div class="col-md-4">
        <div class="card card-custom p-3 border-0 shadow-sm">
            <h5 class="mb-3 text-primary"><i class="fa fa-user-plus"></i> <?= $duzenle_id ? 'Personeli Düzenle' : 'Yeni Personel Ekle' ?></h5>
            
            <form method="POST">
                <?php if($duzenle_id): ?>
                    <input type="hidden" name="calisan_id" value="<?= $duzenle_id ?>">
                <?php endif; ?>
                
                <div class="mb-2">
                    <label class="small fw-bold">Ad Soyad</label>
                    <input type="text" name="ad_soyad" class="form-control" value="<?= htmlspecialchars($ad_val) ?>" placeholder="Örn: Ahmet Yılmaz" required>
                </div>

                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="small fw-bold">Ünvan (Rol)</label>
                        <select name="unvan" class="form-select">
                            <option value="">Seçiniz</option>
                            <?php foreach($roller as $rol): ?>
                                <option value="<?= htmlspecialchars($rol['rol_adi']) ?>" <?= $unvan_val == $rol['rol_adi'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rol['rol_adi']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="small fw-bold">Telefon</label>
                        <input type="text" name="telefon" class="form-control" value="<?= htmlspecialchars($tel_val) ?>" placeholder="05XX...">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="small fw-bold">Prim Oranı (%)</label>
                    <div class="input-group">
                        <input type="number" name="prim_orani" class="form-control" value="<?= $prim_val ?>" placeholder="10" min="0" max="100">
                        <span class="input-group-text">%</span>
                    </div>
                </div>

                <div class="mb-3 border p-2 bg-light rounded" style="max-height: 300px; overflow-y: auto;">
                    <label class="d-block mb-2 text-dark fw-bold small border-bottom pb-1">Yapabildiği İşlemler</label>
                    
                    <div class="text-primary small fw-bold mt-2 mb-1"><i class="fa fa-cut"></i> HİZMETLER</div>
                    <?php foreach($tum_hizmetler as $h): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="hizmetler[]" value="<?= $h['id'] ?>" <?= in_array($h['id'], $aktif_hizmetler) ? 'checked' : '' ?>>
                        <label class="form-check-label small"><?= htmlspecialchars($h['hizmet_adi']) ?></label>
                    </div>
                    <?php endforeach; ?>

                    <div class="text-success small fw-bold mt-3 mb-1 border-top pt-2"><i class="fa fa-box-open"></i> PAKETLER</div>
                    <?php foreach($tum_paketler as $pk): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="paketler[]" value="<?= $pk['id'] ?>" <?= in_array($pk['id'], $aktif_paketler) ? 'checked' : '' ?>>
                        <label class="form-check-label small">
                            <?= htmlspecialchars($pk['paket_adi']) ?> 
                            <span class="text-muted" style="font-size:0.8em;">(<?= $pk['kategori'] ?>)</span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" name="calisan_kaydet" class="btn btn-primary w-100">
                    <?= $duzenle_id ? 'Güncelle' : 'Kaydet' ?>
                </button>
                
                <?php if($duzenle_id): ?>
                    <a href="admin.php?tab=calisanlar" class="btn btn-secondary w-100 mt-2">İptal / Yeni Ekle</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header-custom">
                <span class="card-title">Personel Listesi</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover m-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Ad Soyad</th>
                                <th>Ünvan</th>
                                <th>Telefon</th>
                                <th>Prim</th>
                                <th>Yetki (Adet)</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($calisanlar as $c): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($c['ad_soyad']) ?></td>
                            <td><span class="badge bg-secondary text-light"><?= htmlspecialchars($c['unvan'] ?? '-') ?></span></td>
                            <td class="small text-muted"><?= formatTel($c['telefon'] ?? '') ?></td>
                            <td><span class="badge bg-success">%<?= $c['prim_orani'] ?></span></td>
                            <td><span class="badge bg-light text-dark border"><?= $c['yetki_sayisi'] ?> İşlem</span></td>
                            <td class="text-end">
                                <a href="?tab=calisanlar&calisan_duzenle=<?= $c['id'] ?>" class="btn btn-sm btn-info text-white"><i class="fa fa-edit"></i></a>
                                <a href="?tab=calisanlar&calisan_sil_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Personeli silmek istediğinize emin misiniz?')"><i class="fa fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>