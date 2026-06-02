<?php
// --- İŞLEMLER ---

// 1. Yeni Form Ekle
if (isset($_POST['form_ekle'])) {
    $baslik = trim($_POST['baslik']);
    if ($baslik) {
        $baglanti->prepare("INSERT INTO formlar (baslik) VALUES (?)")->execute([$baslik]);
        $yeni_id = $baglanti->lastInsertId();
        header("Location: admin.php?tab=formlar&duzenle=$yeni_id"); 
        exit;
    }
}

// 2. Soru Ekle
if (isset($_POST['soru_ekle'])) {
    $form_id = $_POST['form_id'];
    $soru    = trim($_POST['soru_metni']);
    $tip     = $_POST['soru_tipi'];

    if ($soru && $form_id) {
        $baglanti->prepare("INSERT INTO form_sorulari (form_id, soru_metni, soru_tipi) VALUES (?, ?, ?)")->execute([$form_id, $soru, $tip]);
        header("Location: admin.php?tab=formlar&duzenle=$form_id"); 
        exit;
    }
}

// 3. Form Sil
if (isset($_GET['sil_id'])) {
    // Önce soruları sil (Opsiyonel ama temizlik için iyi olur)
    $baglanti->prepare("DELETE FROM form_sorulari WHERE form_id = ?")->execute([$_GET['sil_id']]);
    // Sonra formu sil
    $baglanti->prepare("DELETE FROM formlar WHERE id = ?")->execute([$_GET['sil_id']]);
    header("Location: admin.php?tab=formlar"); 
    exit;
}

// 4. Soru Sil
if (isset($_GET['soru_sil'])) {
    $s_id = $_GET['soru_sil'];
    $f_id = $_GET['f_id'];
    $baglanti->prepare("DELETE FROM form_sorulari WHERE id = ?")->execute([$s_id]);
    header("Location: admin.php?tab=formlar&duzenle=$f_id"); 
    exit;
}

// --- VERİ ÇEKME ---
$formlar = $baglanti->query("
    SELECT f.*, 
    (SELECT COUNT(*) FROM form_sorulari WHERE form_id = f.id) as soru_sayisi,
    (SELECT COUNT(*) FROM form_cevaplari WHERE form_id = f.id) as cevaplama_sayisi
    FROM formlar f ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Düzenleme Modu Verileri
$aktif_form = null;
$sorular = [];
if (isset($_GET['duzenle'])) {
    $f_id = $_GET['duzenle'];
    $stmt = $baglanti->prepare("SELECT * FROM formlar WHERE id = ?");
    $stmt->execute([$f_id]);
    $aktif_form = $stmt->fetch(PDO::FETCH_ASSOC);

    if($aktif_form) {
        $stmt_s = $baglanti->prepare("SELECT * FROM form_sorulari WHERE form_id = ? ORDER BY id ASC");
        $stmt_s->execute([$f_id]);
        $sorular = $stmt_s->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<div class="row">
    <div class="col-md-5">
        <div class="card card-custom h-100">
            <div class="card-header bg-white border-bottom pt-3 pb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="m-0 text-primary"><i class="fa fa-clipboard-list"></i> Form Listesi</h5>
                    <button class="btn btn-success btn-sm shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#yeniFormModal">
                        <i class="fa fa-plus"></i> Yeni Form
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover m-0 align-middle">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-3">Başlık</th>
                                <th class="text-center">Soru</th>
                                <th class="text-center">Yanıt</th>
                                <th class="text-end pe-3">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($formlar)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Henüz form oluşturulmadı.</td></tr>
                            <?php endif; ?>
                            
                            <?php foreach($formlar as $f): ?>
                            <tr class="<?= ($aktif_form && $aktif_form['id'] == $f['id']) ? 'table-primary' : '' ?>">
                                <td class="fw-bold ps-3 text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($f['baslik']) ?>">
                                    <?= htmlspecialchars($f['baslik']) ?>
                                </td>
                                <td class="text-center"><span class="badge bg-secondary rounded-pill"><?= $f['soru_sayisi'] ?></span></td>
                                <td class="text-center"><span class="badge bg-info text-dark rounded-pill"><?= $f['cevaplama_sayisi'] ?></span></td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="?tab=formlar&duzenle=<?= $f['id'] ?>" class="btn btn-outline-primary" title="Düzenle"><i class="fa fa-edit"></i></a>
                                        <a href="?tab=formlar&sil_id=<?= $f['id'] ?>" class="btn btn-outline-danger" onclick="return confirm('Bu formu ve tüm cevaplarını silmek istediğinize emin misiniz?')" title="Sil"><i class="fa fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <?php if($aktif_form): ?>
        <div class="card card-custom border-0 shadow-lg h-100">
            <div class="card-header bg-primary text-white pt-3 pb-3 d-flex justify-content-between align-items-center">
                <h5 class="m-0"><i class="fa fa-edit"></i> Düzenle: <?= htmlspecialchars($aktif_form['baslik']) ?></h5>
                <a href="form_doldur.php?id=<?= $aktif_form['id'] ?>" target="_blank" class="btn btn-sm btn-light text-primary fw-bold">
                    <i class="fa fa-eye"></i> Önizle
                </a>
            </div>
            
            <div class="card-body bg-light">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h6 class="card-title fw-bold text-primary mb-3"><i class="fa fa-plus-circle"></i> Yeni Soru Ekle</h6>
                        <form method="POST" class="row g-2 align-items-end">
                            <input type="hidden" name="form_id" value="<?= $aktif_form['id'] ?>">
                            <input type="hidden" name="soru_ekle" value="1">
                            
                            <div class="col-md-7">
                                <label class="small fw-bold text-muted mb-1">Soru Metni</label>
                                <input type="text" name="soru_metni" class="form-control" placeholder="Örn: Kronik rahatsızlığınız var mı?" required>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted mb-1">Cevap Tipi</label>
                                <select name="soru_tipi" class="form-select">
                                    <option value="metin">Yazılı (Metin)</option>
                                    <option value="evet_hayir">Evet / Hayır</option>
                                    <option value="coktan_secmeli">Onay Kutusu</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100 fw-bold">Ekle</button>
                            </div>
                        </form>
                    </div>
                </div>

                <h6 class="text-muted text-uppercase small fw-bold mb-3 ps-1">Mevcut Sorular (<?= count($sorular) ?>)</h6>
                
                <?php if(empty($sorular)): ?>
                    <div class="alert alert-warning border-0 shadow-sm text-center py-4">
                        <i class="fa fa-exclamation-circle fa-2x mb-2 text-warning"></i><br>
                        Bu forma henüz soru eklenmemiş. Yukarıdan ekleyebilirsiniz.
                    </div>
                <?php else: ?>
                    <ul class="list-group shadow-sm border-0">
                        <?php foreach($sorular as $s): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center p-3 border-bottom">
                            <div>
                                <div class="fw-bold text-dark mb-1">
                                    <i class="fa fa-question-circle text-primary me-2"></i><?= htmlspecialchars($s['soru_metni']) ?>
                                </div>
                                <span class="badge bg-light text-secondary border">Tip: <?= $s['soru_tipi'] ?></span>
                            </div>
                            <a href="?tab=formlar&duzenle=<?= $aktif_form['id'] ?>&soru_sil=<?= $s['id'] ?>&f_id=<?= $aktif_form['id'] ?>" 
                               class="btn btn-sm btn-outline-danger border-0" 
                               onclick="return confirm('Soruyu silmek istediğinize emin misiniz?')" 
                               title="Soruyu Sil">
                                <i class="fa fa-times"></i>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
            <div class="alert alert-info shadow-sm p-5 text-center mt-5">
                <i class="fa fa-arrow-left fa-3x mb-3 text-info"></i><br>
                <h5 class="fw-bold">Form Seçimi Yapın</h5>
                <p class="mb-0">Düzenlemek veya soru eklemek için soldaki listeden bir form seçiniz.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- İMZALANMIŞ FORMLAR BÖLÜMÜ -->
<?php
// Cevaplanan formları çek
$cevaplanan = $baglanti->query("
    SELECT fc.*, f.baslik, m.ad_soyad, m.telefon 
    FROM form_cevaplari fc
    JOIN formlar f ON fc.form_id = f.id
    LEFT JOIN musteriler m ON fc.musteri_id = m.id
    ORDER BY fc.id DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mt-4">
    <div class="col-12">
        <div class="card card-custom">
            <div class="card-header bg-info text-white pt-3 pb-3">
                <h5 class="mb-0"><i class="fa fa-check-circle"></i> İmzalanmış / Onaylanmış Formlar</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover m-0 align-middle">
                        <thead class="bg-light text-uppercase small">
                            <tr>
                                <th class="ps-3">Tarih</th>
                                <th>Müşteri</th>
                                <th>Form</th>
                                <th>IP Adresi</th>
                                <th class="text-end pe-3">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cevaplanan)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Henüz onaylanmış form yok.</td></tr>
                            <?php else: ?>
                                <?php foreach ($cevaplanan as $c): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="badge bg-secondary"><?= date('d.m.Y', strtotime($c['imza_tarihi'])) ?></span>
                                            <br>
                                            <small class="text-muted"><?= date('H:i', strtotime($c['imza_tarihi'])) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($c['ad_soyad']) ?></strong><br>
                                            <small class="text-muted"><i class="fa fa-phone"></i> <?= htmlspecialchars($c['telefon']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= htmlspecialchars($c['baslik']) ?></span>
                                        </td>
                                        <td><code class="small"><?= htmlspecialchars($c['ip_adresi']) ?></code></td>
                                        <td class="text-end pe-3">
                                            <button class="btn btn-sm btn-primary" onclick="formDetayGoruntule(<?= $c['id'] ?>)">
                                                <i class="fa fa-eye"></i> Görüntüle
                                            </button>
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

<!-- Form Detay Modal -->
<div class="modal fade" id="formDetayModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa fa-file-contract"></i> Form Detayı</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="formDetayIcerik">
                <div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-3x text-primary"></i></div>
            </div>
        </div>
    </div>
</div>

<script>
function formDetayGoruntule(id) {
    var modal = new bootstrap.Modal(document.getElementById('formDetayModal'));
    
    $.get('form_detay.php?id=' + id, function(data) {
        $('#formDetayIcerik').html(data);
    }).fail(function() {
        $('#formDetayIcerik').html('<div class="alert alert-danger">Yükleme hatası!</div>');
    });
    
    modal.show();
}
</script>

<div class="modal fade" id="yeniFormModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fa fa-plus-square"></i> Yeni Bilgi Formu Oluştur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="fw-bold mb-2">Form Başlığı</label>
                        <input type="text" name="baslik" class="form-control form-control-lg" placeholder="Örn: Lazer Epilasyon Onam Formu" required>
                    </div>
                    <button type="submit" name="form_ekle" class="btn btn-success w-100 fw-bold py-2">
                        <i class="fa fa-check-circle"></i> Oluştur
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>