<?php
include 'db.php';

// JSON formatında çıktı vereceğimizi belirtiyoruz
header('Content-Type: application/json');

// Gelen verileri alıyoruz
$calisan_id = $_GET['calisan_id'] ?? 0;
$tarih = $_GET['tarih'] ?? '';

// Eğer veriler eksikse boş bir dizi döndür
if (!$calisan_id || !$tarih) {
    echo json_encode([]);
    exit;
}

try {
    // Seçilen personelin, seçilen tarihteki tüm randevularını çekiyoruz
    $sorgu = $baglanti->prepare("
        SELECT randevu_saati, bitis_saati 
        FROM randevular 
        WHERE calisan_id = ? AND randevu_tarihi = ?
    ");
    $sorgu->execute([$calisan_id, $tarih]);
    
    $dolu_saatler = $sorgu->fetchAll(PDO::FETCH_ASSOC);

    // Sonuçları JSON olarak döndür
    echo json_encode($dolu_saatler);

} catch (PDOException $e) {
    // Hata durumunda boş dizi döndür (Hata loglanabilir)
    echo json_encode([]);
}
?>