<?php
// --- HİZMET EKLEME / GÜNCELLEME / SİLME İŞLEMLERİ ---
if ($_POST) {
    $islem = $_POST['islem'] ?? '';

    if ($islem == 'ekle' || $islem == 'guncelle') {
        $ad      = trim($_POST['hizmet_adi']);
        $fiyat   = $_POST['fiyat'];
        $kat     = trim($_POST['kategori']);
        $sure    = $_POST['sure'];
        $form_id = $_POST['form_id'] ?? 0;

        if ($islem == 'ekle') {
            $baglanti->prepare("INSERT INTO hizmetler (hizmet_adi, fiyat, kategori, sure_dk, form_id) VALUES (?,?,?,?,?)")
                     ->execute([$ad, $fiyat, $kat, $sure, $form_id]);
        } 
        elseif ($islem == 'guncelle') {
            $id = $_POST['id'];
            $baglanti->prepare("UPDATE hizmetler SET hizmet_adi=?, fiyat=?, kategori=?, sure_dk=?, form_id=? WHERE id=?")
                     ->execute([$ad, $fiyat, $kat, $sure, $form_id, $id]);
        }
    } 
    elseif ($islem == 'sil') {
        $baglanti->prepare("DELETE FROM hizmetler WHERE id=?")->execute([$_POST['id']]);
        exit; // Ajax isteği olduğu için burada işlemi bitiriyoruz
    }

    header("Location: admin.php?tab=hizmetler"); 
    exit;
}

// --- VERİ ÇEKME ---
// sure_dk sütununu 'sure' olarak alias'lıyoruz
$hizmetler = $baglanti->query("SELECT h.id, h.hizmet_adi, h.fiyat, h.kategori, h.sure_dk as sure, h.form_id, f.baslik as form_adi FROM hizmetler h LEFT JOIN formlar f ON h.form_id = f.id ORDER BY h.kategori ASC")->fetchAll(PDO::FETCH_ASSOC);

// Kategorileri ve Formları Çek
$kategoriler = $baglanti->query("SELECT DISTINCT kategori FROM hizmetler ORDER BY kategori ASC")->fetchAll(PDO::FETCH_COLUMN);
$formlar = $baglanti->query("SELECT * FROM formlar ORDER BY baslik ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card card-custom">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <h5 class="m-0"><i class="fa fa-magic text-primary"></i> Hizmet Listesi</h5>
        <button class="btn btn-primary btn-sm" onclick="hizmetModalAc('yeni')">
            <i class="fa fa-plus"></i> Hizmet Ekle
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped m-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>Kategori</th>
                        <th>Hizmet Adı</th>
                        <th>Süre</th>
                        <th>Fiyat</th>
                        <th>Bağlı Sözleşme</th>
                        <th class="text-end">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($hizmetler)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">Kayıtlı hizmet bulunamadı.</td></tr>
                    <?php endif; ?>

                    <?php foreach($hizmetler as $h): ?>
                    <tr>
                        <td class="fw-bold text-secondary"><?= htmlspecialchars($h['kategori']) ?></td>
                        <td><?= htmlspecialchars($h['hizmet_adi']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= $h['sure'] ?> dk</span></td>
                        <td class="fw-bold text-success"><?= number_format($h['fiyat'], 2) ?> ₺</td>
                        <td>
                            <?php if($h['form_adi']): ?>
                                <span class="badge bg-info text-dark" title="Randevu alındığında bu form otomatik atanır.">
                                    <i class="fa fa-file-contract"></i> <?= htmlspecialchars($h['form_adi']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">- Yok -</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" onclick='duzenle(<?= json_encode($h) ?>)' title="Düzenle">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="sil(<?= $h['id'] ?>)" title="Sil">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="hizmetModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="modalBaslik">Hizmet İşlemleri</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="islem" id="islem">
                <input type="hidden" name="id" id="hizmet_id">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Kategori</label>
                    <input type="text" name="kategori" id="kategori" class="form-control" list="kategoriList" placeholder="Kategori seçin veya yeni yazın" required autocomplete="off">
                    <datalist id="kategoriList">
                        <?php foreach($kategoriler as $k) echo "<option value='".htmlspecialchars($k)."'>"; ?>
                    </datalist>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Hizmet Adı</label>
                    <input type="text" name="hizmet_adi" id="hizmet_adi" class="form-control" required placeholder="Örn: Saç Kesimi">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Fiyat (TL)</label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="fiyat" id="fiyat" class="form-control" required>
                            <span class="input-group-text">₺</span>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Süre (Dk)</label>
                        <div class="input-group">
                            <input type="number" name="sure" id="sure" class="form-control" value="30" required>
                            <span class="input-group-text">dk</span>
                        </div>
                    </div>
                </div>
                
                <div class="mb-3 p-3 bg-light border rounded">
                    <label class="fw-bold text-primary mb-1"><i class="fa fa-file-contract"></i> Bağlı Onam Formu / Sözleşme</label>
                    <select name="form_id" id="form_id" class="form-select">
                        <option value="0">-- Yok (Sözleşme İstenmiyor) --</option>
                        <?php foreach($formlar as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['baslik']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text small text-muted">Bu hizmeti alan müşteriye otomatik olarak seçilen form gönderilir/imzalatılır.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="submit" class="btn btn-success fw-bold"><i class="fa fa-save"></i> Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
function hizmetModalAc(tur) {
    var modal = new bootstrap.Modal(document.getElementById('hizmetModal'));
    
    if(tur === 'yeni') {
        $('#modalBaslik').text('Yeni Hizmet Ekle');
        $('#islem').val('ekle');
        $('#hizmet_id').val('');
        $('#hizmetModal form')[0].reset();
        // Varsayılan değerleri ayarla
        $('#sure').val(30);
        $('#form_id').val(0);
    }
    modal.show();
}

function duzenle(data) {
    var modal = new bootstrap.Modal(document.getElementById('hizmetModal'));
    $('#modalBaslik').text('Hizmet Düzenle');
    $('#islem').val('guncelle');
    $('#hizmet_id').val(data.id);
    
    $('#kategori').val(data.kategori);
    $('#hizmet_adi').val(data.hizmet_adi);
    $('#fiyat').val(data.fiyat);
    $('#sure').val(data.sure);
    $('#form_id').val(data.form_id || 0);
    
    modal.show();
}

function sil(id) {
    if(confirm('Bu hizmeti silmek istediğinize emin misiniz?')) {
        $.post('sayfalar/hizmetler.php', {islem: 'sil', id: id}, function() {
            // Başarılı silme sonrası sayfayı yenile
            location.reload(); 
        });
    }
}
</script>