<?php
// Dosya: piucapelli/ajax_kategori_islem.php
include 'db.php';
header('Content-Type: application/json; charset=utf-8');

$islem = $_POST['islem'] ?? '';
$response = ['status' => 'error', 'message' => 'Geçersiz işlem'];

try {
    if ($islem == 'listele') {
        $kategoriler = $baglanti->query("SELECT * FROM urun_kategorileri ORDER BY kategori_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
        $response = ['status' => 'success', 'data' => $kategoriler];
    }
    elseif ($islem == 'ekle') {
        $ad = trim($_POST['kategori_adi']);
        $baglanti->prepare("INSERT INTO urun_kategorileri (kategori_adi) VALUES (?)")->execute([$ad]);
        $response = ['status' => 'success'];
    }
    elseif ($islem == 'guncelle') {
        $id = $_POST['id'];
        $yeni_ad = trim($_POST['kategori_adi']);
        // Önce eski adı bulup ürünleri güncellemeliyiz
        $eski = $baglanti->prepare("SELECT kategori_adi FROM urun_kategorileri WHERE id = ?");
        $eski->execute([$id]);
        $eski_ad = $eski->fetchColumn();
        
        $baglanti->prepare("UPDATE urun_kategorileri SET kategori_adi = ? WHERE id = ?")->execute([$yeni_ad, $id]);
        $baglanti->prepare("UPDATE urunler SET kategori = ? WHERE kategori = ?")->execute([$yeni_ad, $eski_ad]);
        $response = ['status' => 'success'];
    }
    elseif ($islem == 'sil') {
        $id = $_POST['id'];
        $baglanti->prepare("DELETE FROM urun_kategorileri WHERE id = ?")->execute([$id]);
        $response = ['status' => 'success'];
    }
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}
echo json_encode($response);