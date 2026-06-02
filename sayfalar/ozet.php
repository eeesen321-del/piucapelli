<?php
// --- YARDIMCI FONKSİYONLAR (TEKRAR KULLANIM) ---

/**
 * Belirtilen tarihteki tahsilat toplamını getirir.
 */
function gunlukTahsilat($tarih) {
    global $baglanti;
    $stmt = $baglanti->prepare("SELECT COALESCE(SUM(tutar), 0) FROM kasa_hareketleri WHERE DATE(tarih) = ? AND islem_turu = 'tahsilat'");
    $stmt->execute([$tarih]);
    return $stmt->fetchColumn();
}

/**
 * Belirtilen aydaki tahsilat toplamını getirir (Yıl-Ay formatında 'Y-m').
 */
function aylikTahsilat($yilAy) {
    global $baglanti;
    $stmt = $baglanti->prepare("SELECT COALESCE(SUM(tutar), 0) FROM kasa_hareketleri WHERE DATE_FORMAT(tarih, '%Y-%m') = ? AND islem_turu = 'tahsilat'");
    $stmt->execute([$yilAy]);
    return $stmt->fetchColumn();
}

/**
 * Bugünün tarihindeki randevu sayısı.
 */
function bugunRandevuSayisi() {
    global $baglanti;
    $stmt = $baglanti->prepare("SELECT COUNT(*) FROM randevular WHERE randevu_tarihi = ?");
    $stmt->execute([date('Y-m-d')]);
    return $stmt->fetchColumn();
}

/**
 * Bugünden itibaren bekleyen randevuların sayısı.
 */
function bekleyenRandevuSayisi() {
    global $baglanti;
    $stmt = $baglanti->prepare("SELECT COUNT(*) FROM randevular WHERE randevu_tarihi >= ? AND durum = 'bekliyor'");
    $stmt->execute([date('Y-m-d')]);
    return $stmt->fetchColumn();
}

/**
 * Son n günün gelirlerini (tarih etiketi + tutar) döndürür.
 */
function sonGunlukGelir($gunAdet = 7) {
    $gunler = [];
    $gelirler = [];
    for ($i = $gunAdet - 1; $i >= 0; $i--) {
        $tarih = date('Y-m-d', strtotime("-$i days"));
        $gunler[] = date('d.m', strtotime($tarih));
        $gelirler[] = gunlukTahsilat($tarih);
    }
    return [$gunler, $gelirler];
}

/**
 * Randevu durumlarının dağılımını (etiket ve değer) döndürür.
 */
function randevuDurumDagilimi() {
    global $baglanti;
    $stmt = $baglanti->query("SELECT durum, COUNT(*) as sayi FROM randevular GROUP BY durum");
    $sonuc = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($sonuc)) {
        return [['Veri Yok'], [1]];
    }
    return [array_keys($sonuc), array_values($sonuc)];
}

// --- VERİLERİ ÇEK (FONKSİYONLARLA) ---
$bugun      = date('Y-m-d');
$buAy       = date('Y-m');

$randevu_bugun   = bugunRandevuSayisi();
$ciro_bugun      = gunlukTahsilat($bugun);
$ciro_bu_ay      = aylikTahsilat($buAy);
$bekleyen_randevu = bekleyenRandevuSayisi();

list($gunler, $gelirler) = sonGunlukGelir(7);
list($durum_etiketleri, $durum_sayilari) = randevuDurumDagilimi();

// Kritik stok (tek seferlik sorgu)
$kritik_urunler = $baglanti->query(
    "SELECT * FROM urunler WHERE stok_adedi <= kritik_stok ORDER BY stok_adedi ASC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- HTML – AYNEN KORUNDU (sadece değişken isimleri aynı) -->
<div class="row g-3 mb-4">
    <!-- Kartlar -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left: 5px solid #d4a373 !important;">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Bugünkü Randevu</div>
                <div class="fs-2 fw-bold text-dark"><?= $randevu_bugun ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left: 5px solid #28a745 !important;">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Bugünkü Ciro</div>
                <div class="fs-2 fw-bold text-success"><?= number_format($ciro_bugun, 0) ?> ₺</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left: 5px solid #17a2b8 !important;">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Bu Ay Toplam</div>
                <div class="fs-2 fw-bold text-info"><?= number_format($ciro_bu_ay, 0) ?> ₺</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left: 5px solid #ffc107 !important;">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Bekleyen Randevular</div>
                <div class="fs-2 fw-bold text-warning"><?= $bekleyen_randevu ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold py-3"><i class="fa fa-chart-line text-primary me-2"></i> Son 7 Günlük Gelir Analizi</div>
            <div class="card-body">
                <canvas id="gelirChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-bold py-3"><i class="fa fa-chart-pie text-warning me-2"></i> Randevu Durumları</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="durumChart" style="max-height: 250px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white fw-bold"><i class="fa fa-exclamation-triangle me-2"></i> Azalan Ürünler</div>
            <div class="card-body p-0">
                <table class="table table-hover m-0">
                    <thead class="table-light"><tr><th>Ürün</th><th class="text-end">Stok</th></tr></thead>
                    <tbody>
                        <?php foreach($kritik_urunler as $ku): ?>
                        <tr>
                            <td><?= htmlspecialchars($ku['urun_adi']) ?></td>
                            <td class="text-end fw-bold text-danger"><?= $ku['stok_adedi'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($kritik_urunler)): ?>
                            <tr><td colspan="2" class="text-center text-muted py-3">Kritik stok uyarısı yok.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-dark text-white fw-bold"><i class="fa fa-bolt me-2"></i> Hızlı Menü</div>
            <div class="card-body d-flex flex-wrap gap-2 align-content-start">
                <a href="?tab=randevular" class="btn btn-outline-primary flex-grow-1"><i class="fa fa-calendar-plus"></i> Randevu Ekle</a>
                <a href="?tab=satis" class="btn btn-outline-success flex-grow-1"><i class="fa fa-cash-register"></i> Satış Yap</a>
                <a href="?tab=musteriler" class="btn btn-outline-secondary flex-grow-1"><i class="fa fa-user-plus"></i> Müşteri Ekle</a>
                <a href="?tab=finans" class="btn btn-outline-danger flex-grow-1"><i class="fa fa-wallet"></i> Gider Ekle</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Gelir Grafiği (değişiklik yok, sadece JSON_UNESCAPED_UNICODE eklendi)
const ctxGelir = document.getElementById('gelirChart').getContext('2d');
new Chart(ctxGelir, {
    type: 'line',
    data: {
        labels: <?= json_encode($gunler, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'Günlük Ciro (TL)',
            data: <?= json_encode($gelirler) ?>,
            borderColor: '#d4a373',
            backgroundColor: 'rgba(212, 163, 115, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#d4a373',
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, grid: { borderDash: [5,5] } } }
    }
});

// Durum Grafiği (JSON_UNESCAPED_UNICODE eklendi)
const ctxDurum = document.getElementById('durumChart').getContext('2d');
new Chart(ctxDurum, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($durum_etiketleri, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            data: <?= json_encode($durum_sayilari) ?>,
            backgroundColor: ['#ffc107', '#28a745', '#dc3545', '#6c757d', '#17a2b8'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>