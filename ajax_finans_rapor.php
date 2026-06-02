<?php
// ajax_finans_rapor.php - DÜZELTİLMİŞ VE ÇALIŞAN VERSİYON
include 'db.php';
header('Content-Type: application/json');

// Hataları gizle ki JSON yapısı bozulmasın
error_reporting(0);
ini_set('display_errors', 0);

$baslangic = $_GET['baslangic'] ?? date('Y-m-01');
$bitis = $_GET['bitis'] ?? date('Y-m-t');
$islem_turu = $_GET['islem_turu'] ?? '';

try {
    // ----------------------------------------------------------------
    // 1. LİSTELEME SORGUSU (TABLO VERİSİ)
    // ----------------------------------------------------------------
    $filtre = "WHERE DATE(kh.tarih) BETWEEN :baslangic AND :bitis";
    $params = [':baslangic' => $baslangic, ':bitis' => $bitis];
    
    if(!empty($islem_turu)) {
        if($islem_turu == 'paket_satisi') {
             $filtre .= " AND (kh.islem_turu = 'paket_satisi' OR kh.kategori = 'Paket Satışı')";
        } else {
             $filtre .= " AND kh.islem_turu = :islem_turu";
             $params[':islem_turu'] = $islem_turu;
        }
    }
    
    $stmt = $baglanti->prepare("
        SELECT 
            kh.id, kh.tarih, kh.islem_turu, kh.kategori, kh.tutar, kh.aciklama, kh.odeme_turu, kh.musteri_id, kh.randevu_id,
            COALESCE(m.ad_soyad, '') as musteri_adi,
            COALESCE(r.id, '') as randevu_no
        FROM kasa_hareketleri kh
        LEFT JOIN musteriler m ON kh.musteri_id = m.id
        LEFT JOIN randevular r ON kh.randevu_id = r.id
        $filtre
        ORDER BY kh.tarih DESC
    ");
    
    $stmt->execute($params);
    $kayitlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ----------------------------------------------------------------
    // 2. TOPLAM HESAPLAMALARI (HATALI KISIM DÜZELTİLDİ)
    // ----------------------------------------------------------------
    $params_toplam = [':baslangic' => $baslangic, ':bitis' => $bitis];
    
    $stmt_toplam = $baglanti->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN islem_turu IN ('gelir', 'tahsilat', 'paket_satisi') THEN tutar ELSE 0 END), 0) as toplam_gelir,
            COALESCE(SUM(CASE WHEN islem_turu IN ('gider', 'odemeler') THEN tutar ELSE 0 END), 0) as toplam_gider,
            COALESCE(SUM(CASE WHEN islem_turu = 'paket_satisi' OR kategori = 'Paket Satışı' THEN tutar ELSE 0 END), 0) as paket_satis
        FROM kasa_hareketleri 
        WHERE DATE(tarih) BETWEEN :baslangic AND :bitis
    ");
    
    // Önceki hatanız: execute demeden fetch yapıyordunuz.
    $stmt_toplam->execute($params_toplam);
    $toplamlar = $stmt_toplam->fetch(PDO::FETCH_ASSOC);

    // Net kâr hesapla
    $net_kar = $toplamlar['toplam_gelir'] - $toplamlar['toplam_gider'];
    
    // ----------------------------------------------------------------
    // 3. GRAFİK VERİLERİ (HATALI KISIM DÜZELTİLDİ)
    // ----------------------------------------------------------------
    $stmt_grafik = $baglanti->prepare("
        SELECT kategori, SUM(tutar) as toplam
        FROM kasa_hareketleri 
        WHERE islem_turu IN ('gider', 'odemeler')
          AND DATE(tarih) BETWEEN :baslangic AND :bitis
        GROUP BY kategori
        ORDER BY toplam DESC
        LIMIT 10
    ");
    
    // Önceki hatanız: execute demeden fetchAll yapıyordunuz.
    $stmt_grafik->execute($params_toplam);
    $gider_kategorileri = $stmt_grafik->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'kayitlar' => $kayitlar,
        'toplam_gelir' => (float)$toplamlar['toplam_gelir'],
        'toplam_gider' => (float)$toplamlar['toplam_gider'],
        'net_kar' => (float)$net_kar,
        'paket_satis' => (float)$toplamlar['paket_satis'],
        'grafik_verileri' => [
            'toplam_gelir' => (float)$toplamlar['toplam_gelir'],
            'toplam_gider' => (float)$toplamlar['toplam_gider'],
            'gider_kategorileri' => array_column($gider_kategorileri, 'kategori'),
            'gider_degerleri' => array_column($gider_kategorileri, 'toplam')
        ]
    ]);
    
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>