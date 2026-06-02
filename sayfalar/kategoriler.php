<?php
// --- İŞLEMLER ---

// 1. Kategori Ekle
if (isset($_POST['kategori_ekle'])) {
    $ad = trim($_POST['kategori_adi']);
    if ($ad) {
        try {
            $baglanti->prepare("INSERT INTO kategoriler (kategori_adi) VALUES (?)")->execute([$ad]);
            $mesaj = ['tip' => 'success', 'mesaj' => 'Kategori eklendi.'];
        } catch (PDOException $e) {
            $mesaj = ['tip' => 'danger', 'mesaj' => 'Hata: ' . $e->getMessage()];
        }
    }
}

// 2. Kategori Güncelle
if (isset($_POST['kategori_guncelle'])) {
    $id = $_POST['id'];
    $yeni_ad = trim($_POST['kategori_adi']);
    if ($yeni_ad) {
        try {
            $baglanti->prepare("UPDATE kategoriler SET kategori_adi = ? WHERE id = ?")->execute([$yeni_ad, $id]);
            $mesaj = ['tip' => 'success', 'mesaj' => 'Kategori güncellendi.'];
        } catch (PDOException $e) {
            $mesaj = ['tip' => 'danger', 'mesaj' => 'Hata: ' . $e->getMessage()];
        }
    }
}

// 3. Kategori Sil
if (isset($_GET['sil_id'])) {
    $id = $_GET['sil_id'];
    try {
        $baglanti->prepare("DELETE FROM kategoriler WHERE id = ?")->execute([$id]);
        $mesaj = ['tip' => 'success', 'mesaj' => 'Kategori silindi.'];
    } catch (PDOException $e) {
        $mesaj = ['tip' => 'danger', 'mesaj' => 'Silme başarısız: ' . $e->getMessage()];
    }
    header("Location: admin.php?tab=kategoriler");
    exit;
}

// --- VERİ ÇEKME ---
$kategoriler = $baglanti->query("SELECT * FROM kategoriler ORDER BY kategori_adi ASC")->fetchAll(PDO::FETCH_ASSOC);

// Düzenleme modu
$duzenle = null;
if (isset($_GET['duzenle'])) {
    $id = $_GET['duzenle'];
    $stmt = $baglanti->prepare("SELECT * FROM kategoriler WHERE id = ?");
    $stmt->execute([$id]);
    $duzenle = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<?php if (isset($mesaj)): ?>
    <div class="alert alert-<?= $mesaj['tip'] ?> alert-dismissible fade show">
        <?= htmlspecialchars($mesaj['mesaj']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card card-custom h-100">
            <div class="card-header <?= $duzenle ? 'bg-warning text-dark' : 'bg-success text-white' ?>">
                <h5 class="mb-0">
                    <i class="fa fa-<?= $duzenle ? 'edit' : 'plus' ?>"></i>
                    <?= $duzenle ? 'Kategori Düzenle' : 'Yeni Kategori Ekle' ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($duzenle): ?>
                        <input type="hidden" name="id" value="<?= $duzenle['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="fw-bold">Kategori Adı</label>
                        <input type="text" name="kategori_adi" class="form-control" 
                               value="<?= htmlspecialchars($duzenle['kategori_adi'] ?? '') ?>" 
                               placeholder="Örn: Saç Hizmetleri" required>
                    </div>

                    <button type="submit" name="<?= $duzenle ? 'kategori_guncelle' : 'kategori_ekle' ?>" 
                            class="btn <?= $duzenle ? 'btn-warning' : 'btn-success' ?> w-100 fw-bold">
                        <i class="fa fa-<?= $duzenle ? 'save' : 'plus' ?>"></i>
                        <?= $duzenle ? 'Güncelle' : 'Kaydet' ?>
                    </button>

                    <?php if ($duzenle): ?>
                        <a href="?tab=kategoriler" class="btn btn-secondary w-100 mt-2">
                            <i class="fa fa-times"></i> İptal
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card card-custom">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="fa fa-tags text-primary"></i> Kategori Listesi</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover m-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Kategori Adı</th>
                                <th class="text-center">Hizmet Sayısı</th>
                                <th class="text-center">Paket Sayısı</th>
                                <th class="text-end pe-3">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($kategoriler)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        Henüz kategori eklenmemiş.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($kategoriler as $k): 
                                    // Hizmet ve paket sayılarını say
                                    $h_sayisi = $baglanti->prepare("SELECT COUNT(*) FROM hizmetler WHERE kategori = ?");
                                    $h_sayisi->execute([$k['kategori_adi']]);
                                    $hizmet_adet = $h_sayisi->fetchColumn();

                                    $p_sayisi = $baglanti->prepare("SELECT COUNT(*) FROM paketler WHERE kategori = ?");
                                    $p_sayisi->execute([$k['kategori_adi']]);
                                    $paket_adet = $p_sayisi->fetchColumn();
                                ?>
                                    <tr class="<?= ($duzenle && $duzenle['id'] == $k['id']) ? 'table-warning' : '' ?>">
                                        <td class="fw-bold ps-3">
                                            <i class="fa fa-tag text-primary me-2"></i>
                                            <?= htmlspecialchars($k['kategori_adi']) ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info text-dark"><?= $hizmet_adet ?> Hizmet</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-warning text-dark"><?= $paket_adet ?> Paket</span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <div class="btn-group btn-group-sm">
                                                <a href="?tab=kategoriler&duzenle=<?= $k['id'] ?>" 
                                                   class="btn btn-outline-warning" title="Düzenle">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                                <a href="?tab=kategoriler&sil_id=<?= $k['id'] ?>" 
                                                   class="btn btn-outline-danger" 
                                                   onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?')"
                                                   title="Sil">
                                                    <i class="fa fa-trash"></i>
                                                </a>
                                            </div>
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