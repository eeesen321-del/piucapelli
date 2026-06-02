<?php
// Dosya: ajax_adisyon_islem.php
include 'db.php';

if (!isset($_POST['islem']) || !isset($_POST['randevu_id'])) {
    echo json_encode(['error' => 'Eksik parametre']);
    exit;
}

$islem = $_POST['islem'];
$randevu_id = $_POST['randevu_id'];

try {
    // --- EKLEME İŞLEMİ ---
    if ($islem == 'ekle') {
        $tur = $_POST['tur']; // 'hizmet' veya 'urun'
        $oge_id = $_POST['oge_id'];

        // 1. Seçilenin Fiyatını ve Adını Bul
        if ($tur == 'hizmet') {
            $sorgu = $baglanti->prepare("SELECT hizmet_adi as ad, fiyat FROM hizmetler WHERE id = ?");
        } else {
            $sorgu = $baglanti->prepare("SELECT urun_adi as ad, fiyat FROM urunler WHERE id = ?");
        }
        $sorgu->execute([$oge_id]);
        $oge = $sorgu->fetch(PDO::FETCH_ASSOC);

        if ($oge) {
            // Fiyat kontrolü (Null gelirse 0 yap)
            $fiyat = $oge['fiyat'] ?? 0;
            
            // 2. Adisyona Ekle
            $ekle = $baglanti->prepare("INSERT INTO randevu_adisyon (randevu_id, tur, oge_id, oge_adi, adet, fiyat, toplam) VALUES (?, ?, ?, ?, 1, ?, ?)");
            $ekle->execute([$randevu_id, $tur, $oge_id, $oge['ad'], $fiyat, $fiyat]);
        }
    }

    // --- SİLME İŞLEMİ ---
    if ($islem == 'sil') {
        $adisyon_id = $_POST['adisyon_id'];
        $baglanti->prepare("DELETE FROM randevu_adisyon WHERE id = ?")->execute([$adisyon_id]);
    }

    // --- GÜNCEL LİSTEYİ VE YENİ TOPLAMI DÖNDÜR ---
    
    // 1. Güncel Listeyi Çek
    $liste_sorgu = $baglanti->prepare("SELECT * FROM randevu_adisyon WHERE randevu_id = ?");
    $liste_sorgu->execute([$randevu_id]);
    $liste = $liste_sorgu->fetchAll(PDO::FETCH_ASSOC);

    // 2. Yeni Toplamı Hesapla
    $yeni_toplam = 0;
    foreach ($liste as $item) {
        $yeni_toplam += $item['toplam'];
    }

    // 3. Randevu Ana Tablosundaki Fiyatı Güncelle (Ciro takibi için şart)
    $guncelle = $baglanti->prepare("UPDATE randevular SET fiyat = ? WHERE id = ?");
    $guncelle->execute([$yeni_toplam, $randevu_id]);

    // Sonucu JS'ye gönder
    echo json_encode([
        'status' => 'success', 
        'liste' => $liste, 
        'yeni_toplam' => number_format($yeni_toplam, 2, '.', '')
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>