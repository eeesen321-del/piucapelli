<?php
// Dosya: sifirla.php
include 'db.php';

// Güvenlik: Yanlışlıkla çalışmasın diye onay isteyelim
if (!isset($_GET['onay']) || $_GET['onay'] != '1') {
    echo '<div style="text-align:center; margin-top:50px; font-family:sans-serif;">
            <h2 style="color:red">DİKKAT!</h2>
            <p>Tüm randevu, kasa, müşteri ve satış verileri silinecektir.</p>
            <p>Ayarlar, personel ve hizmetler KORUNACAKTIR.</p>
            <br>
            <a href="?onay=1" style="background:red; color:white; padding:15px 30px; text-decoration:none; border-radius:5px;">ONAYLIYORUM VE SIFIRLA</a>
          </div>';
    exit;
}

try {
    // 1. Korumayı Kapat
    $baglanti->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 2. Tabloları Boşalt
    $tablolar = [
        'randevu_adisyon',
        'randevular',
        'kasa_hareketleri',
        'satis_detaylari',
        'satislar',
        'seanslar',
        'musteri_paketleri',
        'sms_loglari',
        'islem_loglari',
        'musteriler' // Müşterileri silmek istemiyorsanız bu satırı kaldırın
    ];

    foreach ($tablolar as $tablo) {
        $baglanti->exec("TRUNCATE TABLE $tablo");
        echo "Tablo temizlendi: <b>$tablo</b><br>";
    }

    // 3. Korumayı Aç
    $baglanti->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "<h3 style='color:green'>Veritabanı başarıyla sıfırlandı.</h3>";
    echo "<a href='admin.php'>Panele Dön</a>";

} catch (PDOException $e) {
    echo "Hata oluştu: " . $e->getMessage();
}
?>