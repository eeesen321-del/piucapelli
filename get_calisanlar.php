<?php
include 'db.php';
// Gelen parametreleri al
$tur = $_GET['tur'] ?? 'hizmet'; // Varsayılan 'hizmet', eğer 'paket' gelirse ona göre çalışacak
$id = $_GET['id'] ?? 0; // Hizmet ID veya Paket ID

if($tur == 'paket') {
    // PAKET İSE: calisan_paketleri tablosuna bak
    $sorgu = $baglanti->prepare("
        SELECT c.id, c.ad_soyad 
        FROM calisanlar c
        JOIN calisan_paketleri cp ON c.id = cp.calisan_id
        WHERE cp.paket_id = ?
    ");
} else {
    // HİZMET İSE: calisan_hizmetleri tablosuna bak
    $sorgu = $baglanti->prepare("
        SELECT c.id, c.ad_soyad 
        FROM calisanlar c
        JOIN calisan_hizmetleri ch ON c.id = ch.calisan_id
        WHERE ch.hizmet_id = ?
    ");
}

$sorgu->execute([$id]);
echo json_encode($sorgu->fetchAll(PDO::FETCH_ASSOC));
?>