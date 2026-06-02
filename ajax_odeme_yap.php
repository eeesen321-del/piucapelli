<?php
// Dosya: ajax_odeme_yap.php
include 'db.php';

// Güvenlik kontrolleri
if (!isset($_POST['tutar']) || !isset($_POST['randevu_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Eksik bilgi gönderildi.']);
    exit;
}

$randevu_id = $_POST['randevu_id'];
$tutar = $_POST['tutar'];
$odeme_turu = $_POST['odeme_turu'];

// Müşteri ID boşsa NULL yap (SQL hatasını önler)
$musteri_id = (!empty($_POST['musteri_id']) && $_POST['musteri_id'] != '0') ? $_POST['musteri_id'] : NULL;

$aciklama = "Randevu #$randevu_id için ödeme";

try {
    // 1. Ödemeyi Kasaya İşle
    // Kategori sütunu eklendi: 'Randevu Geliri'
    $sorgu = $baglanti->prepare("INSERT INTO kasa_hareketleri (musteri_id, islem_turu, kategori, tutar, odeme_turu, aciklama, tarih) VALUES (?, 'tahsilat', 'Randevu Geliri', ?, ?, ?, NOW())");
    $sorgu->execute([$musteri_id, $tutar, $odeme_turu, $aciklama]);

    // 2. Güncel Tahsilat Listesini Çek (Anlık Göstermek İçin)
    $tahsilat_html = "";
    
    // Eğer müşteri varsa müşterinin geçmişini, yoksa bu randevunun ödemelerini çekelim
    if ($musteri_id) {
        $gecmis = $baglanti->prepare("SELECT * FROM kasa_hareketleri WHERE musteri_id = ? AND islem_turu = 'tahsilat' ORDER BY tarih DESC LIMIT 5");
        $gecmis->execute([$musteri_id]);
    } else {
        // Müşteri yoksa sadece bu açıklamaya ait ödemeyi göster
        $gecmis = $baglanti->prepare("SELECT * FROM kasa_hareketleri WHERE aciklama = ? ORDER BY tarih DESC LIMIT 1");
        $gecmis->execute([$aciklama]);
    }
    
    $kayitlar = $gecmis->fetchAll(PDO::FETCH_ASSOC);

    foreach ($kayitlar as $k) {
        $tahsilat_html .= '<div class="d-flex justify-content-between p-2 border-bottom align-items-center">';
        $tahsilat_html .= '<span><i class="fa fa-calendar-alt text-muted small"></i> ' . date('d.m.Y', strtotime($k['tarih'])) . '</span>';
        $tahsilat_html .= '<span>' . htmlspecialchars($k['aciklama']) . '</span>';
        $tahsilat_html .= '<span class="text-success fw-bold">+' . number_format($k['tutar'], 2) . ' ₺</span>';
        $tahsilat_html .= '</div>';
    }

    echo json_encode(['status' => 'success', 'html' => $tahsilat_html]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>