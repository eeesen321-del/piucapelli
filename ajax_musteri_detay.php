<?php
// ajax_musteri_detay.php
// Müşteri detay bilgilerini JSON olarak döner

include 'db.php';
header('Content-Type: application/json; charset=utf-8');

$musteri_id = (int)($_GET['musteri_id'] ?? 0);
if (!$musteri_id) { echo json_encode(['error' => 'Geçersiz ID']); exit; }

try {

    // 1. Müşteri temel bilgileri
    $m = $baglanti->prepare("SELECT id, ad_soyad, telefon, eposta FROM musteriler WHERE id = ?");
    $m->execute([$musteri_id]);
    $musteri = $m->fetch(PDO::FETCH_ASSOC);
    if (!$musteri) { echo json_encode(['error' => 'Müşteri bulunamadı']); exit; }

    // 2. Müşteri paketleri
    $pq = $baglanti->prepare("
        SELECT mp.*, p.paket_adi 
        FROM musteri_paketleri mp
        LEFT JOIN paketler p ON p.id = mp.paket_id
        WHERE mp.musteri_id = ?
        ORDER BY mp.satis_tarihi DESC
    ");
    $pq->execute([$musteri_id]);
    $paketler = $pq->fetchAll(PDO::FETCH_ASSOC);

    // 3. Seanslar (paketlere bağlı)
    $seanslar = [];
    if (!empty($paketler)) {
        $mp_ids = array_column($paketler, 'id');
        $placeholder = implode(',', array_fill(0, count($mp_ids), '?'));
        $sq = $baglanti->prepare("
            SELECT * FROM seanslar 
            WHERE musteri_paket_id IN ($placeholder) 
            ORDER BY randevu_tarihi ASC, kacinci_seans ASC
        ");
        $sq->execute($mp_ids);
        $seanslar = $sq->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. Randevular
    $rq = $baglanti->prepare("
        SELECT id, hizmet_adi, randevu_tarihi, randevu_saati, durum, fiyat
        FROM randevular 
        WHERE musteri_id = ?
        ORDER BY randevu_tarihi DESC, randevu_saati DESC
    ");
    $rq->execute([$musteri_id]);
    $randevular = $rq->fetchAll(PDO::FETCH_ASSOC);

    // 5. Ödemeler (kasa hareketleri)
    $oq = $baglanti->prepare("
        SELECT id, tutar, aciklama, tarih, odeme_turu, islem_turu, kategori
        FROM kasa_hareketleri
        WHERE musteri_id = ? AND islem_turu IN ('tahsilat', 'gelir', 'paket_satisi')
        ORDER BY tarih DESC
    ");
    $oq->execute([$musteri_id]);
    $odemeler = $oq->fetchAll(PDO::FETCH_ASSOC);

    // 6. Özet hesapla
    $toplam_paket_ucret  = array_sum(array_column($paketler, 'ucret'));
    $toplam_randevu_fiyat = array_sum(array_column($randevular, 'fiyat'));
    // Paket randevularının fiyatı 0 olduğu için çift sayma riski yok
    $toplam_borc  = $toplam_paket_ucret + $toplam_randevu_fiyat;
    $toplam_odenen = array_sum(array_column($odemeler, 'tutar'));

    echo json_encode([
        'musteri'   => $musteri,
        'paketler'  => $paketler,
        'seanslar'  => $seanslar,
        'randevular'=> $randevular,
        'odemeler'  => $odemeler,
        'ozet' => [
            'toplam_borc' => $toplam_borc,
            'odenen'      => $toplam_odenen,
            'kalan'       => $toplam_borc - $toplam_odenen,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>