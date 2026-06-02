<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// Kullanıcı oturum kontrolü
if (!isset($_SESSION['admin_giris']) || $_SESSION['admin_giris'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Son kontrol zamanını session'dan al
$son_kontrol = $_SESSION['son_bildirim_kontrol'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));

// Yeni randevuları kontrol et
$stmt = $baglanti->prepare("
    SELECT COUNT(*) as yeni_sayi,
           GROUP_CONCAT(CONCAT(musteri_ad, ' - ', hizmet_adi) SEPARATOR '|') as randevular
    FROM randevular 
    WHERE onay_durumu = 'beklemede' 
    AND kayit_tarihi > ?
    ORDER BY kayit_tarihi DESC
    LIMIT 10
");
$stmt->execute([$son_kontrol]);
$sonuc = $stmt->fetch(PDO::FETCH_ASSOC);

// Toplam bekleyen sayısı
$toplam_stmt = $baglanti->query("SELECT COUNT(*) FROM randevular WHERE onay_durumu = 'beklemede'");
$toplam_bekleyen = $toplam_stmt->fetchColumn();

// Son kontrol zamanını güncelle
$_SESSION['son_bildirim_kontrol'] = date('Y-m-d H:i:s');

$response = [
    'success' => true,
    'yeni_randevu_var' => $sonuc['yeni_sayi'] > 0,
    'yeni_sayi' => (int)$sonuc['yeni_sayi'],
    'toplam_bekleyen' => (int)$toplam_bekleyen,
    'randevular' => $sonuc['randevular'] ? explode('|', $sonuc['randevular']) : []
];

echo json_encode($response);