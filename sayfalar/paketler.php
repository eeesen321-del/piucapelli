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
 * Paket verilerini doğrular.
 * @return array|false Hata mesajları dizisi veya true
 */
function paketVerisiDogrula($ad, $kategori, $fiyat, $seans, $aralik) {
    $errors = [];
    if (empty(trim($ad))) {
        $errors[] = "Paket adı boş olamaz.";
    }
    if (empty(trim($kategori))) {
        $errors[] = "Kategori seçilmelidir.";
    }
    if (!is_numeric($fiyat) || $fiyat <= 0) {
        $errors[] = "Geçerli bir fiyat giriniz.";
    }
    if (!is_numeric($seans) || $seans < 1) {
        $errors[] = "Seans sayısı en az 1 olmalıdır.";
    }
    if (!is_numeric($aralik) || $aralik < 1) {
        $errors[] = "Seans aralığı en az 1 gün olmalıdır.";
    }
    return empty($errors) ? true : $errors;
}

// ============================================
//  TEK POST İŞLEYİCİ (EKLE / GÜNCELLE)
// ============================================
if (isset($_POST['paket_kaydet']) || isset($_POST['paket_guncelle'])) {
    $id = isset($_POST['guncelle_id']) ? (int)$_POST['guncelle_id'] : null;
    $ad = trim($_POST['paket_adi'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $fiyat = (float) ($_POST['toplam_tutar'] ?? 0);
    $seans = (int) ($_POST['seans_sayisi'] ?? 0);
    $aralik = (int) ($_POST['seans_araligi'] ?? 0);
    $form_id = (int) ($_POST['form_id'] ?? 0);

    // Doğrulama
    $dogrulama = paketVerisiDogrula($ad, $kategori, $fiyat, $seans, $aralik);
    if ($dogrulama !== true) {
        $hataMesaji = implode(' ', $dogrulama);
        flashMesaj('danger', $hataMesaji);
        redirect("admin.php?tab=paketler" . ($id ? "&duzenle=$id" : ""));
    }

    try {
        $baglanti->beginTransaction();

        if ($id) { // Güncelleme
            $stmt = $baglanti->prepare("
                UPDATE paketler 
                SET paket_adi = ?, kategori = ?, toplam_tutar = ?, seans_sayisi = ?, seans_araligi = ?, form_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$ad, $kategori, $fiyat, $seans, $aralik, $form_id, $id]);
            $mesaj = "Paket başarıyla güncellendi.";
        } else {   // Ekleme
            $stmt = $baglanti->prepare("
                INSERT INTO paketler (paket_adi, kategori, toplam_tutar, seans_sayisi, seans_araligi, form_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$ad, $kategori, $fiyat, $seans, $aralik, $form_id]);
            $mesaj = "Yeni paket eklendi.";
        }

        $baglanti->commit();
        flashMesaj('success', $mesaj);
    } catch (PDOException $e) {
        $baglanti->rollBack();
        // 1062 = duplicate entry (benzersizlik varsa)
        if ($e->errorInfo[1] == 1062) {
            flashMesaj('danger', 'Bu paket adı zaten mevcut.');
        } else {
            flashMesaj('danger', 'Veritabanı hatası: ' . $e->getMessage());
        }
    }
    redirect("admin.php?tab=paketler");
}

// ============================================
//  PAKET SİLME (Güvenli hale getirildi)
// ============================================
if (isset($_GET['paket_sil_id'])) {
    $id = (int) $_GET['paket_sil_id'];
    try {
        $baglanti->prepare("DELETE FROM paketler WHERE id = ?")->execute([$id]);
        flashMesaj('success', 'Paket silindi.');
    } catch (PDOException $e) {
        flashMesaj('danger', 'Silme başarısız: ' . $e->getMessage());
    }
    redirect("admin.php?tab=paketler");
}

// ============================================
//  VERİLERİ ÇEK
// ============================================
$paketler = $baglanti->query("
    SELECT p.*, f.baslik as form_adi 
    FROM paketler p
    LEFT JOIN formlar f ON p.form_id = f.id
    ORDER BY p.kategori ASC, p.paket_adi ASC
")->fetchAll(PDO::FETCH_ASSOC);

$kategoriler = $baglanti->query("
    SELECT * FROM kategoriler 
    ORDER BY kategori_adi ASC
")->fetchAll(PDO::FETCH_ASSOC);

$formlar = $baglanti->query("
    SELECT * FROM formlar 
    ORDER BY baslik ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Düzenleme modu: ID'den paket bilgisini al
$duzenle = null;
if (isset($_GET['duzenle'])) {
    $id = (int) $_GET['duzenle'];
    $stmt = $baglanti->prepare("SELECT * FROM paketler WHERE id = ?");
    $stmt->execute([$id]);
    $duzenle = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$duzenle) {
        flashMesaj('warning', 'Paket bulunamadı.');
        redirect("admin.php?tab=paketler");
    }
}
?>

<!-- FLASH MESAJ GÖSTERİMİ -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?= $_SESSION['flash']['tip'] ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['flash']['mesaj']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<div class="row">
    <!-- SOL KART: EKLE / DÜZENLE -->
    <div class="col-md-4">
        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header <?= $duzenle ? 'bg-warning text-dark' : 'bg-primary text-white' ?>">
                <h5 class="card-title mb-0">
                    <i class="fa fa-<?= $duzenle ? 'edit' : 'box-open' ?>"></i>
                    <?= $duzenle ? 'Paketi Düzenle' : 'Yeni Paket Ekle' ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($duzenle): ?>
                        <input type="hidden" name="guncelle_id" value="<?= $duzenle['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="fw-bold small">Paket Adı</label>
                        <input type="text" name="paket_adi" class="form-control" 
                               value="<?= htmlspecialchars($duzenle['paket_adi'] ?? '') ?>" 
                               placeholder="Örn: 8 Seans Lazer" required>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small text-primary">Bağlı Olduğu Kategori</label>
                        <select name="kategori" class="form-select" required>
                            <option value="">Seçiniz</option>
                            <?php foreach ($kategoriler as $k): ?>
                                <option value="<?= htmlspecialchars($k['kategori_adi']) ?>" 
                                    <?= ($duzenle && $duzenle['kategori'] == $k['kategori_adi']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['kategori_adi']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="fw-bold small">Seans Sayısı</label>
                            <input type="number" name="seans_sayisi" class="form-control" 
                                   value="<?= $duzenle['seans_sayisi'] ?? 1 ?>" min="1" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="fw-bold small text-danger">Seans Aralığı (Gün)</label>
                            <input type="number" name="seans_araligi" class="form-control" 
                                   value="<?= $duzenle['seans_araligi'] ?? 30 ?>" min="1" placeholder="Örn: 30" required>
                            <small class="text-muted" style="font-size:10px;">İki seans arası gün.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold small">Paket Fiyatı (TL)</label>
                        <input type="number" step="0.01" name="toplam_tutar" class="form-control" 
                               value="<?= htmlspecialchars($duzenle['toplam_tutar'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3 p-3 bg-light border rounded">
                        <label class="fw-bold text-primary mb-1"><i class="fa fa-file-contract"></i> Bağlı Onam Formu / Sözleşme</label>
                        <select name="form_id" class="form-select">
                            <option value="0">-- Yok (Sözleşme İstenmiyor) --</option>
                            <?php foreach ($formlar as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= ($duzenle && $duzenle['form_id'] == $f['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($f['baslik']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small text-muted">Bu paketi alan müşteriye otomatik olarak seçilen form gönderilir.</div>
                    </div>

                    <button type="submit" name="paket_kaydet" class="btn <?= $duzenle ? 'btn-warning text-dark' : 'btn-primary' ?> w-100 fw-bold">
                        <i class="fa fa-<?= $duzenle ? 'save' : 'plus' ?>"></i>
                        <?= $duzenle ? 'Güncelle' : 'Kaydet' ?>
                    </button>

                    <?php if ($duzenle): ?>
                        <a href="admin.php?tab=paketler" class="btn btn-secondary w-100 mt-2">
                            <i class="fa fa-times"></i> İptal
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- SAĞ KART: PAKET LİSTESİ -->
    <div class="col-md-8">
        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header-custom">
                <span class="card-title"><i class="fa fa-list"></i> Paket Listesi</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Paket Adı</th>
                                <th>Kategori</th>
                                <th class="text-center">Detaylar</th>
                                <th>Bağlı Form</th>
                                <th class="text-end">Fiyat</th>
                                <th class="text-end">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($paketler)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="fa fa-box-open fa-2x mb-2"></i><br>
                                        Henüz paket eklenmemiş.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($paketler as $p): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($p['paket_adi']) ?></td>
                                        <td>
                                            <?php if ($p['kategori']): ?>
                                                <span class="badge bg-info text-dark">
                                                    <i class="fa fa-tag"></i> <?= htmlspecialchars($p['kategori']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Genel</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-dark rounded-pill" title="Seans Sayısı">
                                                <?= (int)$p['seans_sayisi'] ?> Seans
                                            </span>
                                            <span class="badge bg-warning text-dark rounded-pill" title="Seans Aralığı">
                                                <i class="fa fa-clock"></i> <?= (int)$p['seans_araligi'] ?> Gün Ara
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($p['form_adi']): ?>
                                                <span class="badge bg-success" title="Otomatik form gönderilir">
                                                    <i class="fa fa-file-contract"></i> <?= htmlspecialchars($p['form_adi']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">- Yok -</span>
                                            <?php endif; ?>
                                        </td>
                                                <?= (int)$p['seans_sayisi'] ?> Seans
                                            </span>
                                            <span class="badge bg-warning text-dark rounded-pill" title="Seans Aralığı">
                                                <i class="fa fa-clock"></i> <?= (int)$p['seans_araligi'] ?> Gün Ara
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            <?= number_format($p['toplam_tutar'], 2) ?> ₺
                                        </td>
                                        <td class="text-end">
                                            <a href="?tab=paketler&duzenle=<?= $p['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Düzenle">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <a href="?tab=paketler&paket_sil_id=<?= $p['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Bu paketi silmek istediğinize emin misiniz?')"
                                               title="Sil">
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
    </div>
</div>