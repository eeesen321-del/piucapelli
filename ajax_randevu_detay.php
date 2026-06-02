<?php
include 'db.php';
header('Content-Type: application/json');

if (!isset($_POST['randevu_id'])) { echo json_encode(['error' => 'ID yok']); exit; }

$r_id = $_POST['randevu_id'];

// 1. Randevu Temel Bilgileri (Form bilgisiyle beraber)
$sorgu = $baglanti->prepare("
    SELECT r.*, 
    m.ad_soyad as musteri_ad, m.telefon as musteri_tel, 
    c.ad_soyad as personel_ad, 
    h.hizmet_adi, h.fiyat, h.form_id,
    f.baslik as form_baslik
    FROM randevular r 
    LEFT JOIN musteriler m ON r.musteri_id = m.id 
    LEFT JOIN calisanlar c ON r.personel_id = c.id 
    LEFT JOIN hizmetler h ON r.hizmet_id = h.id
    LEFT JOIN formlar f ON h.form_id = f.id
    WHERE r.id = ?
");
$sorgu->execute([$r_id]);
$randevu = $sorgu->fetch(PDO::FETCH_ASSOC);

// Verileri Düzenle
$randevu['musteri_final_ad'] = $randevu['musteri_ad'] ?: $randevu['musteri_ismi_manuel'];
$randevu['musteri_final_telefon'] = $randevu['musteri_tel'] ?: $randevu['musteri_telefon_manuel'];
$randevu['hizmet_final_adi'] = $randevu['hizmet_adi'] ?: $randevu['hizmet_manuel'];

// --- YENİ: SÖZLEŞME DURUMU KONTROLÜ ---
$sozlesme = null;
if ($randevu['form_id'] > 0) {
    // Bu randevu için imzalanmış mı?
    $form_kontrol = $baglanti->prepare("SELECT * FROM form_cevaplari WHERE randevu_id = ? AND form_id = ?");
    $form_kontrol->execute([$r_id, $randevu['form_id']]);
    $imzali_form = $form_kontrol->fetch(PDO::FETCH_ASSOC);

    $sozlesme = [
        'gerekli' => true,
        'form_adi' => $randevu['form_baslik'],
        'durum' => $imzali_form ? 'imzalandi' : 'bekliyor',
        'imza_tarihi' => $imzali_form ? date('d.m.Y H:i', strtotime($imzali_form['tarih'])) : null,
        'link' => "form_doldur.php?r_id=" . $r_id // SMS gönderilecek link
    ];
} else {
    $sozlesme = ['gerekli' => false];
}

// 2. Adisyon
$adisyon = $baglanti->prepare("SELECT * FROM adisyon WHERE randevu_id = ?");
$adisyon->execute([$r_id]);

// 3. Tahsilatlar
$tahsilat = $baglanti->prepare("SELECT * FROM kasa WHERE randevu_id = ?");
$tahsilat->execute([$r_id]);

// 4. Loglar
$logs = $baglanti->prepare("SELECT * FROM islem_loglari WHERE randevu_id = ? ORDER BY tarih DESC");
$logs->execute([$r_id]);

// 5. SMS Geçmişi
$sms = $baglanti->prepare("SELECT * FROM sms_loglari WHERE musteri_id = ? ORDER BY tarih DESC LIMIT 5");
$sms->execute([$randevu['musteri_id']]);

echo json_encode([
    'detay' => $randevu,
    'sozlesme' => $sozlesme, // Yeni ekledik
    'adisyon' => $adisyon->fetchAll(PDO::FETCH_ASSOC),
    'tahsilatlar' => $tahsilat->fetchAll(PDO::FETCH_ASSOC),
    'logs' => $logs->fetchAll(PDO::FETCH_ASSOC),
    'sms_logs' => $sms->fetchAll(PDO::FETCH_ASSOC)
]);
?>