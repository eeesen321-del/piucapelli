<?php
// Dosya: piucapelli/ajax_musteri_islem.php
include 'db.php';
header('Content-Type: application/json; charset=utf-8');

$islem = $_POST['islem'] ?? '';

try {
    // --- 1. CANLI MÜŞTERİ ARAMA ---
    if ($islem == 'ara') {
        $term = $_POST['term'] ?? '';
        // Hem isme hem telefona göre arar
        $sorgu = $baglanti->prepare("SELECT id, ad_soyad, telefon FROM musteriler WHERE ad_soyad LIKE ? OR telefon LIKE ? LIMIT 10");
        $sorgu->execute(["%$term%", "%$term%"]);
        $sonuclar = $sorgu->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($sonuclar);
        exit;
    }

    // --- 2. HIZLI MÜŞTERİ EKLEME ---
    if ($islem == 'ekle') {
        $ad = trim($_POST['ad_soyad']);
        $tel = trim($_POST['telefon']);

        // Önce var mı kontrol et (Telefona göre)
        $kontrol = $baglanti->prepare("SELECT id FROM musteriler WHERE telefon = ?");
        $kontrol->execute([$tel]);
        if($kontrol->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Bu telefon numarası zaten kayıtlı!']);
            exit;
        }

        // Yoksa ekle
        $ekle = $baglanti->prepare("INSERT INTO musteriler (ad_soyad, telefon, eposta) VALUES (?, ?, '')");
        $ekle->execute([$ad, $tel]);
        $yeni_id = $baglanti->lastInsertId();

        echo json_encode([
            'status' => 'success', 
            'message' => 'Müşteri başarıyla eklendi.',
            'musteri' => ['id' => $yeni_id, 'ad_soyad' => $ad, 'telefon' => $tel]
        ]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>