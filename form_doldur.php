<?php
session_start();
include 'db.php';

// Form ID kontrolü
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Geçersiz form ID'si.");
}

$form_id = (int)$_GET['id'];

// Müşteri bilgisi kontrolü (randevu üzerinden veya direkt link)
$musteri_id = $_GET['musteri_id'] ?? null;
$randevu_id = $_GET['randevu_id'] ?? null;

// Form ve soruları çek
$form_stmt = $baglanti->prepare("SELECT * FROM formlar WHERE id = ?");
$form_stmt->execute([$form_id]);
$form = $form_stmt->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    die("Form bulunamadı.");
}

$sorular_stmt = $baglanti->prepare("SELECT * FROM form_sorulari WHERE form_id = ? ORDER BY id ASC");
$sorular_stmt->execute([$form_id]);
$sorular = $sorular_stmt->fetchAll(PDO::FETCH_ASSOC);

// Form gönderimi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['form_gonder'])) {
    $cevaplar = [];
    
    foreach ($sorular as $s) {
        $cevap = $_POST['soru_' . $s['id']] ?? '';
        $cevaplar[$s['id']] = [
            'soru' => $s['soru_metni'],
            'cevap' => $cevap
        ];
    }
    
    $cevaplar_json = json_encode($cevaplar, JSON_UNESCAPED_UNICODE);
    $onay_data = $_POST['onay_data'] ?? '';
    $onay = isset($_POST['onay']) ? 1 : 0;
    $ip_adresi = $_SERVER['REMOTE_ADDR'];
    
    // Onay kontrolü
    if (!$onay || empty($onay_data)) {
        $hata_mesaji = "Lütfen sözleşmeyi okuyup onaylayın!";
    } else {
        try {
            $stmt = $baglanti->prepare("
                INSERT INTO form_cevaplari (form_id, musteri_id, randevu_id, cevaplar, imza_data, imza_tarihi, ip_adresi) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([$form_id, $musteri_id, $randevu_id, $cevaplar_json, $onay_data, $ip_adresi]);
            
            $basari_mesaji = "Form başarıyla kaydedildi ve onaylandı!";
        } catch (PDOException $e) {
            $hata_mesaji = "Kayıt hatası: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($form['baslik']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .form-container { max-width: 800px; margin: 0 auto; background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .imza-alan { margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 10px; border: 2px solid #ffc107; }
        h2 { color: #667eea; font-weight: bold; }
        .btn-primary { background: #667eea; border: none; }
        .btn-primary:hover { background: #5568d3; }
        .btn-primary:disabled { background: #ccc; cursor: not-allowed; }
    </style>
</head>
<body>

<div class="form-container">
    <?php if (isset($basari_mesaji)): ?>
        <div class="alert alert-success border-0 shadow-lg">
            <div class="text-center">
                <i class="fa fa-check-circle fa-4x text-success mb-3"></i>
                <h4 class="fw-bold text-success"><?= $basari_mesaji ?></h4>
                <p class="mb-4">Formunuz başarıyla kaydedildi. Randevunuz için teşekkür ederiz!</p>
                <a href="index.php" class="btn btn-success btn-lg">
                    <i class="fa fa-home"></i> Ana Sayfaya Dön
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center mb-4">
            <i class="fa fa-file-contract fa-3x text-primary mb-3"></i>
            <h2><?= htmlspecialchars($form['baslik']) ?></h2>
            <p class="text-muted">Lütfen aşağıdaki soruları doldurun ve formun sonunda sözleşmeyi onaylayın.</p>
        </div>

        <?php if (isset($hata_mesaji)): ?>
            <div class="alert alert-danger"><?= $hata_mesaji ?></div>
        <?php endif; ?>

        <form method="POST" id="formOnam">
            <?php foreach ($sorular as $index => $s): ?>
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <?= ($index + 1) ?>. <?= htmlspecialchars($s['soru_metni']) ?>
                        <span class="text-danger">*</span>
                    </label>
                    
                    <?php if ($s['soru_tipi'] == 'metin'): ?>
                        <input type="text" name="soru_<?= $s['id'] ?>" class="form-control" required>
                    
                    <?php elseif ($s['soru_tipi'] == 'evet_hayir'): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="soru_<?= $s['id'] ?>" value="Evet" id="s<?= $s['id'] ?>_evet" required>
                            <label class="form-check-label" for="s<?= $s['id'] ?>_evet">Evet</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="soru_<?= $s['id'] ?>" value="Hayır" id="s<?= $s['id'] ?>_hayir">
                            <label class="form-check-label" for="s<?= $s['id'] ?>_hayir">Hayır</label>
                        </div>
                    
                    <?php elseif ($s['soru_tipi'] == 'coktan_secmeli'): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="soru_<?= $s['id'] ?>" value="Onaylıyorum" id="s<?= $s['id'] ?>">
                            <label class="form-check-label" for="s<?= $s['id'] ?>">Onaylıyorum</label>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <!-- ONAYLA VE KABUL ET -->
            <div class="imza-alan">
                <h5 class="mb-3"><i class="fa fa-check-square"></i> Onay ve Kabul</h5>
                
                <div class="p-4 bg-white border rounded mb-3" style="max-height: 300px; overflow-y: auto;">
                    <h6 class="fw-bold text-primary">Sözleşme Metni</h6>
                    <p class="small">
                        Bu formu doldurarak yukarıda belirttiğim bilgilerin doğru olduğunu, 
                        <?= htmlspecialchars($form['baslik']) ?> kapsamında yapılacak işlemler hakkında 
                        bilgilendirildiğimi ve tüm şartları kabul ettiğimi beyan ederim.
                    </p>
                    <p class="small">
                        İşlem sırasında ve sonrasında oluşabilecek komplikasyonlar, yan etkiler ve 
                        bakım kuralları hakkında detaylı bilgi aldım. Sorularım cevaplandı ve 
                        tüm süreç hakkında bilgilendirildim.
                    </p>
                    <p class="small">
                        Kişisel verilerimin Kişisel Verilerin Korunması Kanunu (KVKK) kapsamında 
                        işlenmesine ve saklanmasına izin veriyorum.
                    </p>
                    <p class="small text-muted mb-0">
                        <i class="fa fa-info-circle"></i> 
                        Bu onay tarihi, saati ve IP adresiniz ile birlikte güvenli şekilde kaydedilecektir.
                    </p>
                </div>
                
                <div class="form-check p-3 border-3 border-primary" style="border: 3px solid #667eea; border-radius: 10px; background: #f0f4ff;">
                    <input class="form-check-input" type="checkbox" name="onay" id="onayCheckbox" required style="width: 20px; height: 20px;">
                    <label class="form-check-label fw-bold ms-2" for="onayCheckbox" style="font-size: 1.1rem;">
                        <i class="fa fa-check-circle text-success"></i> 
                        Yukarıdaki sözleşme metnini okudum, anladım ve kabul ediyorum.
                    </label>
                </div>
                
                <input type="hidden" name="onay_data" id="onayData" required>
                <input type="hidden" name="onay_ip" value="<?= $_SERVER['REMOTE_ADDR'] ?>">
                
                <div class="mt-3 small text-muted">
                    <i class="fa fa-shield-alt"></i> 
                    Onayınız tarih: <strong><?= date('d.m.Y H:i:s') ?></strong> ve 
                    IP adresi: <strong><?= $_SERVER['REMOTE_ADDR'] ?></strong> ile kaydedilecektir.
                </div>
            </div>

            <button type="submit" name="form_gonder" class="btn btn-primary btn-lg w-100 mt-4" id="gonderBtn" disabled>
                <i class="fa fa-check-circle"></i> Onayla ve Gönder
            </button>
        </form>
    <?php endif; ?>
</div>

<script>
// Checkbox onay sistemi
const onayCheckbox = document.getElementById('onayCheckbox');
const gonderBtn = document.getElementById('gonderBtn');
const onayData = document.getElementById('onayData');

onayCheckbox.addEventListener('change', function() {
    if (this.checked) {
        gonderBtn.disabled = false;
        gonderBtn.classList.add('pulse');
        
        // Onay bilgisini kaydet
        const onayBilgi = {
            onay: true,
            tarih: new Date().toISOString(),
            ip: '<?= $_SERVER['REMOTE_ADDR'] ?>',
            tarayici: navigator.userAgent
        };
        onayData.value = JSON.stringify(onayBilgi);
    } else {
        gonderBtn.disabled = true;
        gonderBtn.classList.remove('pulse');
        onayData.value = '';
    }
});

// Form gönderme kontrolü
document.getElementById('formOnam').addEventListener('submit', function(e) {
    if (!onayCheckbox.checked) {
        e.preventDefault();
        alert('Lütfen sözleşmeyi okuyup onaylayın!');
        onayCheckbox.focus();
        return false;
    }
});

// Buton animasyonu
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.02); }
    }
    .pulse {
        animation: pulse 2s infinite;
    }
`;
document.head.appendChild(style);
</script>

</body>
</html>