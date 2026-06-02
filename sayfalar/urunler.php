<?php
// --- YARDIMCI FONKSİYONLAR ---
function flashMesaj($tip, $mesaj) {
    $_SESSION['flash'] = ['tip' => $tip, 'mesaj' => $mesaj];
}

// --- İŞLEMLER (TEK FORM İLE EKLE/GÜNCELLE) ---
if (isset($_POST['urun_kaydet'])) {
    $id     = $_POST['urun_id'] ?? null;
    $ad     = trim($_POST['urun_adi']);
    $fiyat  = (float) $_POST['fiyat'];
    $stok   = (int) $_POST['stok_adedi'];
    $barkod = trim($_POST['barkod']);
    $kategori = trim($_POST['kategori']);

    try {
        if ($id) { // Güncelleme
            $stmt = $baglanti->prepare("UPDATE urunler SET urun_adi=?, fiyat=?, stok_adedi=?, barkod=?, kategori=? WHERE id=?");
            $stmt->execute([$ad, $fiyat, $stok, $barkod, $kategori, $id]);
            flashMesaj('success', 'Ürün başarıyla güncellendi.');
        } else {   // Ekleme
            $stmt = $baglanti->prepare("INSERT INTO urunler (urun_adi, fiyat, stok_adedi, barkod, kategori) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$ad, $fiyat, $stok, $barkod, $kategori]);
            flashMesaj('success', 'Yeni ürün eklendi.');
        }
    } catch (PDOException $e) {
        flashMesaj('danger', 'Veritabanı hatası: ' . $e->getMessage());
    }
    header("Location: admin.php?tab=urunler");
    exit;
}

// Ürün Silme (güvenli hale getirildi)
if (isset($_GET['sil_id'])) {
    $id = (int) $_GET['sil_id'];
    try {
        $baglanti->prepare("DELETE FROM urunler WHERE id = ?")->execute([$id]);
        flashMesaj('success', 'Ürün silindi.');
    } catch (PDOException $e) {
        flashMesaj('danger', 'Silme başarısız: ' . $e->getMessage());
    }
    header("Location: admin.php?tab=urunler");
    exit;
}

// --- VERİLERİ ÇEK ---
$urunler = $baglanti->query("SELECT * FROM urunler ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$kategoriler = $baglanti->query("SELECT * FROM urun_kategorileri ORDER BY kategori_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- FLASH MESAJ -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?= $_SESSION['flash']['tip'] ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['flash']['mesaj']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span class="card-title"><i class="fa fa-boxes text-primary"></i> Ürün Stok Yönetimi</span>
        <div>
            <button class="btn btn-warning text-dark btn-sm fw-bold me-1" onclick="kategoriYonetimModalAc()">
                <i class="fa fa-tags"></i> Kategoriler
            </button>
            <button class="btn btn-primary btn-sm" onclick="urunFormuAc()">
                <i class="fa fa-plus"></i> Ürün Ekle
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover m-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>Barkod</th>
                        <th>Ürün Adı</th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        <th>Fiyat</th>
                        <th class="text-end">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($urunler as $u): ?>
                    <tr>
                        <td class="small text-muted"><i class="fa fa-barcode"></i> <?= htmlspecialchars($u['barkod'] ?? '') ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($u['urun_adi']) ?></td>
                        <td>
                            <span class="badge bg-secondary cursor-pointer" style="cursor: pointer;" onclick="kategoriYonetimModalAc()" title="Kategorileri Yönet">
                                <?= htmlspecialchars($u['kategori'] ?? 'Genel') ?> <i class="fa fa-edit small ms-1" style="opacity:0.6"></i>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['stok_adedi'] < 5): ?>
                                <span class="badge bg-danger"><?= $u['stok_adedi'] ?> Kritik</span>
                            <?php else: ?>
                                <span class="badge bg-success"><?= $u['stok_adedi'] ?> Adet</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold text-primary"><?= number_format($u['fiyat'], 2) ?> ₺</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-info me-1" 
                                    onclick='urunFormuAc(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>)' 
                                    title="Düzenle">
                                <i class="fa fa-edit"></i>
                            </button>
                            <a href="?tab=urunler&sil_id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Silmek istediğinize emin misiniz?')">
                                <i class="fa fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TEK MODAL: ÜRÜN EKLE / DÜZENLE -->
<div class="modal fade" id="urunModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="urunModalHeader">
                <h5 class="modal-title"><i class="fa fa-plus"></i> <span id="urunModalTitle">Ürün Ekle</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="urun_id" id="urun_id">
                    <div class="mb-3">
                        <label>Barkod</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-barcode"></i></span>
                            <input type="text" name="barkod" id="barkod" class="form-control" placeholder="Okutun veya yazın">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Ürün Adı</label>
                        <input type="text" name="urun_adi" id="urun_adi" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Kategori</label>
                        <div class="input-group">
                            <select name="kategori" id="kategori" class="form-select kategori-select">
                                <option value="Genel">Genel</option>
                                <?php foreach ($kategoriler as $k): ?>
                                    <option value="<?= htmlspecialchars($k['kategori_adi']) ?>">
                                        <?= htmlspecialchars($k['kategori_adi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-secondary" onclick="kategoriYonetimModalAc()" title="Kategori Yönetimi">
                                <i class="fa fa-cog"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label>Stok Adedi</label>
                            <input type="number" name="stok_adedi" id="stok_adedi" class="form-control" value="1" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label>Satış Fiyatı</label>
                            <input type="number" step="0.01" name="fiyat" id="fiyat" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" name="urun_kaydet" class="btn btn-primary w-100">Kaydet</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: KATEGORİ YÖNETİMİ -->
<div class="modal fade" id="kategoriYonetimModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark"><i class="fa fa-tags"></i> Kategori Yönetimi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" id="yeniKategoriAd" class="form-control" placeholder="Yeni Kategori Adı">
                    <button class="btn btn-success" type="button" onclick="kategoriEkle()"><i class="fa fa-plus"></i> Ekle</button>
                </div>
                <hr>
                <div id="kategoriListesi" style="max-height: 300px; overflow-y: auto;">
                    Yükleniyor...
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// --- ORTAK AJAX KATEGORİ FONKSİYONU ---
function kategoriIslem(islem, id = null, ad = null, callback) {
    let data = { islem: islem };
    if (id) data.id = id;
    if (ad) data.kategori_adi = ad;

    $.post('ajax_kategori_islem.php', data, function(res) {
        if (res.status === 'success') {
            if (callback) callback(res);
            // Başarılı işlem sonrası kategori listesini ve select'leri güncelle
            kategoriListele();
            kategoriSelectleriYenile();
        } else {
            alert(res.message || 'İşlem başarısız!');
        }
    }, 'json').fail(function(xhr) {
        alert("Hata: ajax_kategori_islem.php dosyasına ulaşılamıyor.\n\nDetay: " + xhr.responseText);
    });
}

// Kategori Listesini Modalda Göster
function kategoriListele() {
    $('#kategoriListesi').html('<div class="text-center py-3"><i class="fa fa-spinner fa-spin"></i> Yükleniyor...</div>');
    kategoriIslem('listele', null, null, function(res) {
        let html = '<ul class="list-group">';
        if (res.data.length === 0) {
            html += '<li class="list-group-item text-muted text-center">Henüz kategori yok.</li>';
        } else {
            res.data.forEach(function(k) {
                html += `
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <input type="text" class="form-control form-control-sm border-0 bg-transparent fw-bold" 
                           value="${k.kategori_adi}" id="kat_input_${k.id}">
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-success py-0 me-1" onclick="kategoriGuncelle(${k.id})" title="Güncelle">
                            <i class="fa fa-sync-alt"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0" onclick="kategoriSil(${k.id})" title="Sil">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </li>`;
            });
        }
        html += '</ul>';
        $('#kategoriListesi').html(html);
    });
}

// Kategori Ekle
function kategoriEkle() {
    let ad = $('#yeniKategoriAd').val().trim();
    if (!ad) { alert("Lütfen bir isim yazın."); return; }
    kategoriIslem('ekle', null, ad, function() {
        $('#yeniKategoriAd').val('');
    });
}

// Kategori Güncelle
function kategoriGuncelle(id) {
    let yeniAd = $('#kat_input_' + id).val().trim();
    if (!yeniAd) { alert("Kategori adı boş olamaz."); return; }
    kategoriIslem('guncelle', id, yeniAd);
}

// Kategori Sil
function kategoriSil(id) {
    if (!confirm('Bu kategoriyi silerseniz, bu kategorideki ürünler "Genel" kategorisine aktarılacaktır. Emin misiniz?')) return;
    kategoriIslem('sil', id);
}

// --- KATEGORİ SELECT'LERİNİ GÜNCELLE ---
function kategoriSelectleriYenile() {
    $.post('ajax_kategori_islem.php', { islem: 'listele' }, function(res) {
        if (res.status === 'success') {
            let options = '<option value="Genel">Genel</option>';
            res.data.forEach(function(k) {
                options += `<option value="${k.kategori_adi}">${k.kategori_adi}</option>`;
            });
            $('.kategori-select').each(function() {
                let secili = $(this).val();
                $(this).html(options);
                $(this).val(secili); // Seçili değeri koru (varsa)
            });
        }
    }, 'json');
}

// --- ÜRÜN MODALINI AÇ (Ekle / Düzenle) ---
function urunFormuAc(urun = null) {
    let modal = new bootstrap.Modal(document.getElementById('urunModal'));
    let header = document.getElementById('urunModalHeader');
    let title = document.getElementById('urunModalTitle');

    if (urun) { // Düzenleme
        header.className = 'modal-header bg-info text-white';
        title.innerHTML = '<i class="fa fa-edit"></i> Ürün Düzenle';
        document.getElementById('urun_id').value = urun.id;
        document.getElementById('barkod').value = urun.barkod || '';
        document.getElementById('urun_adi').value = urun.urun_adi;
        document.getElementById('stok_adedi').value = urun.stok_adedi;
        document.getElementById('fiyat').value = urun.fiyat;
        document.getElementById('kategori').value = urun.kategori || 'Genel';
    } else { // Yeni Ekle
        header.className = 'modal-header bg-primary text-white';
        title.innerHTML = '<i class="fa fa-plus"></i> Yeni Ürün Ekle';
        document.getElementById('urun_id').value = '';
        document.getElementById('barkod').value = '';
        document.getElementById('urun_adi').value = '';
        document.getElementById('stok_adedi').value = 1;
        document.getElementById('fiyat').value = '';
        document.getElementById('kategori').value = 'Genel';
    }
    modal.show();
}

// --- KATEGORİ MODALINI AÇ ---
function kategoriYonetimModalAc() {
    let modal = new bootstrap.Modal(document.getElementById('kategoriYonetimModal'));
    modal.show();
    kategoriListele(); // Her açılışta listeyi tazele
}

// Sayfa yüklendiğinde tüm kategori select'lerini hazırla (başlangıçta zaten PHP ile dolu)
// Kategori modalı kapandığında SAYFAYI YENİLEME, sadece select'leri güncelle
$(document).ready(function() {
    $('#kategoriYonetimModal').on('hidden.bs.modal', function () {
        kategoriSelectleriYenile(); // Sayfa yenilenmeden select kutuları güncellenir
    });
});
</script>