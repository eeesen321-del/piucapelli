<?php
include 'db.php';

$id = $_GET['id'] ?? 0;

$stmt = $baglanti->prepare("
    SELECT fc.*, f.baslik, m.ad_soyad, m.telefon 
    FROM form_cevaplari fc
    JOIN formlar f ON fc.form_id = f.id
    LEFT JOIN musteriler m ON fc.musteri_id = m.id
    WHERE fc.id = ?
");
$stmt->execute([$id]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    echo "<div class='alert alert-danger'>Form bulunamadı.</div>";
    exit;
}

$cevaplar = json_decode($form['cevaplar'], true);
?>

<div class="mb-3">
    <h6 class="fw-bold"><?= htmlspecialchars($form['baslik']) ?></h6>
    <div class="small text-muted">
        <i class="fa fa-user"></i> <?= htmlspecialchars($form['ad_soyad']) ?> 
        (<?= htmlspecialchars($form['telefon']) ?>)
        <br>
        <i class="fa fa-calendar"></i> <?= date('d.m.Y H:i:s', strtotime($form['imza_tarihi'])) ?>
        <br>
        <i class="fa fa-map-marker-alt"></i> IP: <?= htmlspecialchars($form['ip_adresi']) ?>
    </div>
</div>

<hr>

<h6 class="fw-bold mb-3">Cevaplar:</h6>
<?php foreach ($cevaplar as $soru_id => $c): ?>
    <div class="mb-3 p-3 bg-light border-start border-primary border-4">
        <div class="fw-bold text-primary"><?= htmlspecialchars($c['soru']) ?></div>
        <div class="mt-2"><?= htmlspecialchars($c['cevap']) ?></div>
    </div>
<?php endforeach; ?>

<hr>

<h6 class="fw-bold mb-3">Onay Bilgileri:</h6>
<div class="p-4 bg-light border rounded">
    <?php 
    $onay_bilgi = json_decode($form['imza_data'], true);
    if ($onay_bilgi && isset($onay_bilgi['onay']) && $onay_bilgi['onay']): 
    ?>
        <div class="alert alert-success border-0 shadow-sm">
            <h5 class="mb-3">
                <i class="fa fa-check-circle"></i> Sözleşme Onaylandı
            </h5>
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="fw-bold" width="150"><i class="fa fa-calendar"></i> Onay Tarihi:</td>
                    <td><?= date('d.m.Y H:i:s', strtotime($form['imza_tarihi'])) ?></td>
                </tr>
                <tr>
                    <td class="fw-bold"><i class="fa fa-map-marker-alt"></i> IP Adresi:</td>
                    <td><code><?= htmlspecialchars($form['ip_adresi']) ?></code></td>
                </tr>
                <?php if (isset($onay_bilgi['tarayici'])): ?>
                <tr>
                    <td class="fw-bold"><i class="fa fa-browser"></i> Tarayıcı:</td>
                    <td class="small"><?= htmlspecialchars($onay_bilgi['tarayici']) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="mt-3 p-3 border-start border-success border-4 bg-white">
            <p class="mb-0 small text-muted">
                <i class="fa fa-info-circle"></i> 
                Müşteri "Yukarıdaki sözleşme metnini okudum, anladım ve kabul ediyorum" 
                ifadesini onaylamıştır.
            </p>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">
            <i class="fa fa-exclamation-triangle"></i> Onay bilgisi bulunamadı.
        </div>
    <?php endif; ?>
</div>

<div class="mt-4 text-end">
    <button class="btn btn-secondary" onclick="window.print()">
        <i class="fa fa-print"></i> Yazdır
    </button>
</div>