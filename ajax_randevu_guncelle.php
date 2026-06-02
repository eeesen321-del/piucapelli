<?php
// Dosya: ajax_randevu_guncelle.php
include 'db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_POST['randevu_id']) || !isset($_POST['tarih']) || !isset($_POST['saat'])) {
    echo json_encode(['status' => 'error', 'message' => 'Eksik bilgi gönderildi.']);
    exit;
}

$id = $_POST['randevu_id'];
$yeni_tarih = $_POST['tarih'];
$yeni_saat = $_POST['saat'];

try {
    // 1. Önce randevunun hangi hizmet olduğunu ve süresini bulalım
    $bul = $baglanti->prepare("
        SELECT r.hizmet_adi, h.sure_dk 
        FROM randevular r 
        LEFT JOIN hizmetler h ON r.hizmet_adi = h.hizmet_adi 
        WHERE r.id = ?
    ");
    $bul->execute([$id]);
    $randevu = $bul->fetch(PDO::FETCH_ASSOC);

    if (!$randevu) {
        echo json_encode(['status' => 'error', 'message' => 'Randevu bulunamadı.']);
        exit;
    }

    // 2. Yeni bitiş saatini hesapla
    $sure = $randevu['sure_dk'] ? $randevu['sure_dk'] : 30; // Eğer süre yoksa varsayılan 30 dk
    
    $baslangic = new DateTime("$yeni_tarih $yeni_saat");
    $bitis = clone $baslangic;
    $bitis->modify("+$sure minutes");
    $yeni_bitis_saati = $bitis->format("H:i");

    // 3. Güncelleme İşlemi
    $guncelle = $baglanti->prepare("UPDATE randevular SET randevu_tarihi = ?, randevu_saati = ?, bitis_saati = ? WHERE id = ?");
    $sonuc = $guncelle->execute([$yeni_tarih, $yeni_saat, $yeni_bitis_saati, $id]);

    if ($sonuc) {
        echo json_encode(['status' => 'success', 'message' => 'Tarih ve saat güncellendi.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Güncelleme yapılamadı.']);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Hata: ' . $e->getMessage()]);
}
?>