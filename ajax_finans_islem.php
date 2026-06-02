<?php
// Dosya: piucapelli/ajax_finans_islem.php
include 'db.php';
header('Content-Type: application/json; charset=utf-8');

// Hataları ekrana basma, JSON yapısını bozar
error_reporting(0);
ini_set('display_errors', 0);

$islem = $_POST['islem'] ?? '';

try {
    // 1. BİLGİ GETİR (MODAL İÇİN)
    if ($islem == 'get') {
        $id = $_POST['id'];
        $sorgu = $baglanti->prepare("SELECT * FROM kasa_hareketleri WHERE id = ?");
        $sorgu->execute([$id]);
        $veri = $sorgu->fetch(PDO::FETCH_ASSOC);
        
        if($veri) {
            // Tarihi inputa uygun formata çevir
            $dt = new DateTime($veri['tarih']);
            $veri['tarih_sadece'] = $dt->format('Y-m-d');
        }
        echo json_encode($veri);
        exit;
    }

    // 2. GÜNCELLE
    if ($islem == 'guncelle') {
        $id = $_POST['id'];
        $aciklama = $_POST['aciklama'];
        
        // Tutarı düzelt (Virgülü noktaya çevir)
        $tutar = str_replace(',', '.', $_POST['tutar']); 
        
        $odeme_turu = $_POST['odeme_turu'];
        $tarih = $_POST['tarih']; 
        
        // Saati korumak için şu anki saati ekle
        $tam_tarih = $tarih . ' ' . date('H:i:s');

        $guncelle = $baglanti->prepare("UPDATE kasa_hareketleri SET aciklama=?, tutar=?, odeme_turu=?, tarih=? WHERE id=?");
        $sonuc = $guncelle->execute([$aciklama, $tutar, $odeme_turu, $tam_tarih, $id]);

        echo json_encode(['status' => $sonuc ? 'success' : 'error']);
        exit;
    }

    // 3. SİL
    if ($islem == 'sil') {
        $id = $_POST['id'];
        $sil = $baglanti->prepare("DELETE FROM kasa_hareketleri WHERE id = ?");
        $sonuc = $sil->execute([$id]);
        echo json_encode(['status' => $sonuc ? 'success' : 'error']);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>