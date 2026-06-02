<?php
// --- YARDIMCI FONKSİYONLAR ---
function flashMesaj($tip, $mesaj) {
    $_SESSION['flash'] = ['tip' => $tip, 'mesaj' => $mesaj];
}

// --- TEK POST İŞLEYİCİ (EKLE / GÜNCELLE) ---
if (isset($_POST['kategori_ekle']) || isset($_POST['kategori_guncelle'])) {
    $id = $_POST['cat_id'] ?? null;
    $ad = trim($_POST['kategori_adi'] ?? '');

    if ($ad === '') {
        flashMesaj('danger', 'Kategori adı boş olamaz.');
        header("Location: admin.php?tab=urun_kategorileri");
        exit;
    }

    try {
        if ($id) { // Güncelleme
            $stmt = $baglanti->prepare("UPDATE urun_kategorileri SET kategori_adi = ? WHERE id = ?");
            $stmt->execute([$ad, $id]);
            flashMesaj('success', 'Kategori başarıyla güncellendi.');
        } else {   // Ekleme
            $stmt = $baglanti->prepare("INSERT INTO urun_kategorileri (kategori_adi) VALUES (?)");
            $stmt->execute([$ad]);
            flashMesaj('success', 'Yeni kategori eklendi.');
        }
    } catch (PDOException $e) {
        // 1062 = Duplicate entry (benzersiz kategori adı)
        if ($e->errorInfo[1] == 1062) {
            flashMesaj('danger', 'Bu kategori adı zaten mevcut.');
        } else {
            flashMesaj('danger', 'Veritabanı hatası: ' . $e->getMessage());
        }
    }

    header("Location: admin.php?tab=urun_kategorileri");
    exit;
}

// --- SİLME İŞLEMİ (GÜVENLİ HALE GETİRİLDİ) ---
if (isset($_GET['sil_id'])) {
    $id = (int) $_GET['sil_id']; // Tamsayıya dönüştür, SQL injection önlemi
    try {
        $baglanti->prepare("DELETE FROM urun_kategorileri WHERE id = ?")->execute([$id]);
        flashMesaj('success', 'Kategori silindi.');
    } catch (PDOException $e) {
        flashMesaj('danger', 'Silme başarısız: ' . $e->getMessage());
    }
    header("Location: admin.php?tab=urun_kategorileri");
    exit;
}

// --- VERİLERİ ÇEK ---
$kategoriler = $baglanti->query("SELECT * FROM urun_kategorileri ORDER BY kategori_adi ASC")
                       ->fetchAll(PDO::FETCH_ASSOC);
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
    <div class="col-md-4">
        <div class="card card-custom border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="fa fa-tags"></i> Kategori Yönetimi</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="kategoriForm">
                    <input type="hidden" name="cat_id" id="cat_id">
                    <div class="mb-3">
                        <label class="fw-bold small">Kategori Adı</label>
                        <input type="text" name="kategori_adi" id="cat_ad" class="form-control" placeholder="Örn: Şampuanlar" required>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="kategori_ekle" id="btnEkle" class="btn btn-success fw-bold">
                            <i class="fa fa-plus"></i> Ekle
                        </button>
                        <button type="submit" name="kategori_guncelle" id="btnGuncelle" class="btn btn-warning fw-bold" style="display:none;">
                            <i class="fa fa-save"></i> Güncelle
                        </button>
                        <button type="button" id="btnIptal" class="btn btn-secondary btn-sm" style="display:none;" onclick="formSifirla()">
                            İptal
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="mt-2 text-center">
            <a href="admin.php?tab=urunler" class="btn btn-light border"><i class="fa fa-arrow-left"></i> Ürünlere Dön</a>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card card-custom border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover align-middle m-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Kategori Adı</th>
                            <th class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($kategoriler)): ?>
                            <tr>
                                <td colspan="2" class="text-center text-muted py-3">
                                    Henüz kategori eklenmemiş.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($kategoriler as $k): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($k['kategori_adi']) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="duzenleModu(<?= $k['id'] ?>, '<?= htmlspecialchars($k['kategori_adi'], ENT_QUOTES) ?>')">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <a href="?tab=urun_kategorileri&sil_id=<?= $k['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Bu kategoriyi silmek istediğinize emin misiniz?')">
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

<script>
// Düzenleme moduna geç
function duzenleModu(id, ad) {
    document.getElementById('cat_id').value = id;
    document.getElementById('cat_ad').value = ad;
    
    document.getElementById('btnEkle').style.display = 'none';
    document.getElementById('btnGuncelle').style.display = 'block';
    document.getElementById('btnIptal').style.display = 'block';
}

// Formu sıfırla (ekleme moduna dön)
function formSifirla() {
    document.getElementById('cat_id').value = '';
    document.getElementById('cat_ad').value = '';
    
    document.getElementById('btnEkle').style.display = 'block';
    document.getElementById('btnGuncelle').style.display = 'none';
    document.getElementById('btnIptal').style.display = 'none';
}

// İptal butonuna ESC tuşu veya modal dışı tıklama gibi durumlar için form sıfırlanabilir.
// Sayfa yeniden yüklendiğinde form başlangıç durumunda olur, ekstra işlem gerekmez.
</script>