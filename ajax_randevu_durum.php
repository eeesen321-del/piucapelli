<?php
include 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $randevu_id = $_POST['randevu_id'];
    $yeni_durum = $_POST['durum'];
    
    // 1. Randevuyu Güncelle
    $sorgu = $baglanti->prepare("UPDATE randevular SET durum = ? WHERE id = ?");
    $sonuc = $sorgu->execute([$yeni_durum, $randevu_id]);

    if ($sonuc) {
        // 2. Eğer bu randevu bir SEANSA bağlıysa, o seansı da güncelle
        $kontrol = $baglanti->prepare("SELECT seans_id FROM randevular WHERE id = ?");
        $kontrol->execute([$randevu_id]);
        $veri = $kontrol->fetch(PDO::FETCH_ASSOC);

        if ($veri && !empty($veri['seans_id'])) {
            $seans_guncelle = $baglanti->prepare("UPDATE seanslar SET durum = ? WHERE id = ?");
            $seans_guncelle->execute([$yeni_durum, $veri['seans_id']]);
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Durum güncellendi.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Hata oluştu.']);
    }
}
?>