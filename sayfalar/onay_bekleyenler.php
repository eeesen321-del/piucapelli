<?php
// --- İŞLEMLER ---

// 1. Randevu Onayla
if (isset($_POST['randevu_onayla'])) {
    $id = (int)$_POST['randevu_id'];
    $tumPaketi = isset($_POST['tum_paketi_onayla']) && $_POST['tum_paketi_onayla'] == '1';

    if ($tumPaketi && !empty($_POST['musteri_paket_id'])) {
        // Paketteki tüm bekleyen randevuları onayla
        $mpId = (int)$_POST['musteri_paket_id'];
        $baglanti->prepare("
            UPDATE randevular r
            JOIN seanslar s ON r.seans_id = s.id
            SET r.onay_durumu = 'onaylandi'
            WHERE s.musteri_paket_id = ? AND r.onay_durumu = 'beklemede'
        ")->execute([$mpId]);
        $_SESSION['flash'] = ['tip' => 'success', 'mesaj' => 'Paketin tüm randevuları onaylandı.'];
    } else {
        // Sadece bu randevuyu onayla
        $baglanti->prepare("UPDATE randevular SET onay_durumu = 'onaylandi' WHERE id = ?")->execute([$id]);
        $_SESSION['flash'] = ['tip' => 'success', 'mesaj' => 'Randevu onaylandı ve randevu listesine eklendi.'];
    }
    header("Location: admin.php?tab=onay_bekleyenler");
    exit;
}

// 2. Randevu Reddet
if (isset($_POST['randevu_reddet'])) {
    $id = (int)$_POST['randevu_id'];
    $baglanti->prepare("UPDATE randevular SET onay_durumu = 'reddedildi', durum = 'iptal' WHERE id = ?")->execute([$id]);
    $_SESSION['flash'] = ['tip' => 'warning', 'mesaj' => 'Randevu reddedildi.'];
    header("Location: admin.php?tab=onay_bekleyenler");
    exit;
}

// 3. Numara Engelle
if (isset($_POST['numara_engelle'])) {
    $telefon = trim($_POST['telefon']);
    $sebep = trim($_POST['sebep'] ?? 'Belirsiz');
    $admin_id = $_SESSION['admin_id'];
    
    try {
        $baglanti->prepare("INSERT INTO engellenen_numaralar (telefon, sebep, engelleyen_admin) VALUES (?, ?, ?)")
                 ->execute([$telefon, $sebep, $admin_id]);
        
        // Bu numaranın bekleyen randevularını reddet
        $baglanti->prepare("UPDATE randevular SET onay_durumu = 'reddedildi', durum = 'iptal' WHERE telefon = ? AND onay_durumu = 'beklemede'")->execute([$telefon]);
        
        $_SESSION['flash'] = ['tip' => 'success', 'mesaj' => 'Numara engellendi. Bekleyen randevuları otomatik reddedildi.'];
    } catch (PDOException $e) {
        $_SESSION['flash'] = ['tip' => 'danger', 'mesaj' => 'Hata: ' . $e->getMessage()];
    }
    header("Location: admin.php?tab=onay_bekleyenler");
    exit;
}

// 4. Engeli Kaldır
if (isset($_GET['engel_kaldir'])) {
    $id = (int)$_GET['engel_kaldir'];
    $baglanti->prepare("DELETE FROM engellenen_numaralar WHERE id = ?")->execute([$id]);
    $_SESSION['flash'] = ['tip' => 'success', 'mesaj' => 'Engel kaldırıldı.'];
    header("Location: admin.php?tab=onay_bekleyenler");
    exit;
}

// --- VERİ ÇEKME ---

// Onay bekleyen randevular
$bekleyenler = $baglanti->query("
    SELECT r.*, c.ad_soyad as personel_adi,
           s.id as seans_db_id, s.musteri_paket_id,
           mp.paket_id, mp.toplam_seans,
           p.paket_adi, p.seans_araligi
    FROM randevular r
    LEFT JOIN calisanlar c ON r.calisan_id = c.id
    LEFT JOIN seanslar s ON r.seans_id = s.id
    LEFT JOIN musteri_paketleri mp ON s.musteri_paket_id = mp.id
    LEFT JOIN paketler p ON mp.paket_id = p.id
    WHERE r.onay_durumu = 'beklemede'
    ORDER BY r.kayit_tarihi DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Reddedilen randevular (son 30 gün)
$reddedilenler = $baglanti->query("
    SELECT r.*, c.ad_soyad as personel_adi 
    FROM randevular r
    LEFT JOIN calisanlar c ON r.calisan_id = c.id
    WHERE r.onay_durumu = 'reddedildi' 
    AND r.kayit_tarihi >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY r.kayit_tarihi DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Engellenen numaralar
$engellenenler = $baglanti->query("
    SELECT en.*, y.kullanici_adi 
    FROM engellenen_numaralar en
    LEFT JOIN yoneticiler y ON en.engelleyen_admin = y.id
    ORDER BY en.engelleme_tarihi DESC
")->fetchAll(PDO::FETCH_ASSOC);

// İstatistikler
$stats = [
    'bekleyen' => count($bekleyenler),
    'reddedilen' => count($reddedilenler),
    'engellenen' => count($engellenenler)
];
?>

<!-- Flash Mesaj -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?= $_SESSION['flash']['tip'] ?> alert-dismissible fade show">
        <?= htmlspecialchars($_SESSION['flash']['mesaj']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<!-- İstatistik Kartları -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 mb-1">Onay Bekleyen</h6>
                        <h2 class="mb-0 fw-bold"><?= $stats['bekleyen'] ?></h2>
                    </div>
                    <i class="fa fa-clock fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 mb-1">Reddedilen</h6>
                        <h2 class="mb-0 fw-bold"><?= $stats['reddedilen'] ?></h2>
                    </div>
                    <i class="fa fa-times-circle fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-dark text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-white-50 mb-1">Engellenen Numara</h6>
                        <h2 class="mb-0 fw-bold"><?= $stats['engellenen'] ?></h2>
                    </div>
                    <i class="fa fa-ban fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Onay Bekleyen Randevular -->
<div class="card card-custom mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="fa fa-clock"></i> Onay Bekleyen Randevular</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover m-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3">Tarih</th>
                        <th>Müşteri</th>
                        <th>Telefon</th>
                        <th>Hizmet</th>
                        <th>Personel</th>
                        <th>Kayıt Zamanı</th>
                        <th class="text-end pe-3">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bekleyenler)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa fa-check-circle fa-3x mb-3 d-block"></i>
                                Onay bekleyen randevu yok!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bekleyenler as $r): ?>
                            <tr>
                                <td class="ps-3 fw-bold">
                                    <?= date('d.m.Y', strtotime($r['randevu_tarihi'])) ?><br>
                                    <small class="text-primary"><?= $r['randevu_saati'] ?></small>
                                </td>
                                <td><?= htmlspecialchars($r['musteri_ad']) ?></td>
                                <td>
                                    <a href="tel:<?= $r['telefon'] ?>" class="text-decoration-none">
                                        <i class="fa fa-phone"></i> <?= formatTel($r['telefon']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($r['hizmet_adi']) ?></td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        <?= htmlspecialchars($r['personel_adi']) ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('d.m.Y H:i', strtotime($r['kayit_tarihi'])) ?>
                                    </small>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <?php if (!empty($r['musteri_paket_id'])): ?>
                                            <!-- Paket randevusu: özel onay seçenekleri -->
                                            <button type="button" class="btn btn-success"
                                                onclick="paketOnayAc(<?= $r['id'] ?>, <?= (int)$r['musteri_paket_id'] ?>, '<?= htmlspecialchars($r['paket_adi'] ?? '') ?>', <?= (int)$r['toplam_seans'] ?>)"
                                                title="Onayla">
                                                <i class="fa fa-check"></i>
                                            </button>
                                        <?php else: ?>
                                            <!-- Normal randevu: tek onay -->
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="randevu_id" value="<?= $r['id'] ?>">
                                                <button type="submit" name="randevu_onayla" class="btn btn-success" title="Onayla">
                                                    <i class="fa fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="randevu_id" value="<?= $r['id'] ?>">
                                            <button type="submit" name="randevu_reddet" class="btn btn-danger" 
                                                    onclick="return confirm('Randevuyu reddetmek istediğinize emin misiniz?')" title="Reddet">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        </form>
                                        <button class="btn btn-dark" onclick="numaraEngelle('<?= htmlspecialchars($r['telefon']) ?>')" title="Numarayı Engelle">
                                            <i class="fa fa-ban"></i>
                                        </button>
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

<!-- İki Sütunlu Düzen: Reddedilen & Engellenenler -->
<div class="row">
    <!-- Reddedilen Randevular -->
    <div class="col-md-6">
        <div class="card card-custom">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0"><i class="fa fa-times-circle"></i> Reddedilen Randevular (Son 30 Gün)</h6>
            </div>
            <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-hover m-0">
                    <tbody>
                        <?php if (empty($reddedilenler)): ?>
                            <tr><td class="text-center py-3 text-muted">Reddedilen randevu yok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reddedilenler as $r): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($r['musteri_ad']) ?></strong><br>
                                        <small class="text-muted">
                                            <i class="fa fa-phone"></i> <?= formatTel($r['telefon']) ?> | 
                                            <?= date('d.m.Y', strtotime($r['randevu_tarihi'])) ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Engellenen Numaralar -->
    <div class="col-md-6">
        <div class="card card-custom">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fa fa-ban"></i> Engellenen Numaralar</h6>
                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#engelModal">
                    <i class="fa fa-plus"></i> Numara Engelle
                </button>
            </div>
            <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-hover m-0">
                    <tbody>
                        <?php if (empty($engellenenler)): ?>
                            <tr><td class="text-center py-3 text-muted">Engellenen numara yok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($engellenenler as $e): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong class="text-danger">
                                                    <i class="fa fa-ban"></i> <?= htmlspecialchars($e['telefon']) ?>
                                                </strong><br>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($e['sebep']) ?> |
                                                    <?= date('d.m.Y', strtotime($e['engelleme_tarihi'])) ?>
                                                </small>
                                            </div>
                                            <a href="?tab=onay_bekleyenler&engel_kaldir=<?= $e['id'] ?>" 
                                               class="btn btn-sm btn-outline-success"
                                               onclick="return confirm('Engeli kaldırmak istediğinize emin misiniz?')">
                                                <i class="fa fa-unlock"></i> Kaldır
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

<!-- Numara Engelleme Modal -->
<div class="modal fade" id="engelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fa fa-ban"></i> Numara Engelle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="fw-bold">Telefon Numarası</label>
                        <input type="text" name="telefon" id="engelTelefon" class="form-control" 
                               placeholder="05XX XXX XX XX" required>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Sebep</label>
                        <textarea name="sebep" class="form-control" rows="3" 
                                  placeholder="Neden engellendiğini açıklayın..."></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i> 
                        Bu numaradan yeni randevu alınamayacak ve bekleyen randevuları otomatik reddedilecektir.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="numara_engelle" class="btn btn-danger">
                        <i class="fa fa-ban"></i> Engelle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function numaraEngelle(telefon) {
    $('#engelTelefon').val(telefon);
    var modal = new bootstrap.Modal(document.getElementById('engelModal'));
    modal.show();
}

// Paket randevusu onaylama
var _paketOnayData = {};
function paketOnayAc(randevuId, mpId, paketAdi, seansSayisi) {
    _paketOnayData = { randevuId: randevuId, mpId: mpId };
    document.getElementById('paketOnayBaslik').textContent = paketAdi + ' (' + seansSayisi + ' Seans)';
    var modal = new bootstrap.Modal(document.getElementById('paketOnayModal'));
    modal.show();
}
function paketOnayKaydet(tumunu) {
    var form = document.getElementById('formPaketOnay');
    document.getElementById('paketOnay_randevu_id').value = _paketOnayData.randevuId;
    document.getElementById('paketOnay_mp_id').value = _paketOnayData.mpId;
    document.getElementById('paketOnay_tum').value = tumunu ? '1' : '0';
    form.submit();
}
</script>

<!-- Paket Onay Seçim Modalı -->
<div class="modal fade" id="paketOnayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h6 class="modal-title"><i class="fa fa-box-open me-2"></i>Paket Randevusu Onayı</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="fw-bold mb-1" id="paketOnayBaslik"></p>
                <p class="text-muted small mb-3">Bu randevuyu nasıl onaylamak istersiniz?</p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-success" onclick="paketOnayKaydet(true)">
                        <i class="fa fa-check-double me-2"></i>Paketin Tüm Randevularını Onayla
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="paketOnayKaydet(false)">
                        <i class="fa fa-check me-2"></i>Sadece Bu Randevuyu Onayla
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="POST" id="formPaketOnay" style="display:none;">
    <input type="hidden" name="randevu_onayla" value="1">
    <input type="hidden" name="randevu_id" id="paketOnay_randevu_id">
    <input type="hidden" name="musteri_paket_id" id="paketOnay_mp_id">
    <input type="hidden" name="tum_paketi_onayla" id="paketOnay_tum">
</form>