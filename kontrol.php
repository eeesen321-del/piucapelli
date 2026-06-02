<?php
include 'db.php';

$calisan_id = $_GET['calisan_id'] ?? 0;
$tarih = $_GET['tarih'] ?? '';

$sorgu = $baglanti->prepare("
    SELECT randevu_saati, bitis_saati 
    FROM randevular 
    WHERE calisan_id = ? AND randevu_tarihi = ?
");
$sorgu->execute([$calisan_id, $tarih]);

header('Content-Type: application/json');
echo json_encode($sorgu->fetchAll(PDO::FETCH_ASSOC));