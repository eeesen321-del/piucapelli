<?php
// Dosya: ajax_barkod.php
include 'db.php';
header('Content-Type: application/json');

$barkod = $_POST['barkod'] ?? '';

if($barkod) {
    // Barkoda göre ürünü bul
    $q = $baglanti->prepare("SELECT * FROM urunler WHERE barkod = ? LIMIT 1");
    $q->execute([$barkod]);
    $urun = $q->fetch(PDO::FETCH_ASSOC);

    if($urun) {
        if($urun['stok_adedi'] > 0) {
            echo json_encode(['status' => 'success', 'urun' => $urun]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Bu ürün stokta kalmamış!']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Ürün bulunamadı!']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Barkod boş']);
}
?>