
<?php
session_start();
include 'db.php'; // Veritabanı bağlantısı
include 'functions.php'; // SMS fonksiyonu
// Test amaçlı: Kodu ekrana yazdır (Siteyi teslim ederken bu satırı sileceksin)
echo "<div style='background:black; color:yellow; padding:10px; text-align:center;'>
      TEST MODU - Telefonunuza gelecek kod: " . ($_SESSION['temp_randevu']['onay_kodu'] ?? 'Kod Yok') . "
      </div>";
if (isset($_POST['onayla'])) {
    $girilen_kod = $_POST['kod'];
    $temp = $_SESSION['temp_randevu'];

    if ($girilen_kod == $temp['onay_kodu']) {
        // KOD DOĞRU! Veritabanına kaydet
        $sorgu = $baglanti->prepare("INSERT INTO randevular (musteri_ad, telefon, calisan_id, hizmet_adi, randevu_tarihi, randevu_saati, bitis_saati) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $sorgu->execute([
            $temp['musteri_ad'],
            $temp['telefon'],
            $temp['calisan_id'],
            $temp['hizmet_adi'],
            $temp['randevu_tarihi'],
            $temp['randevu_saati'],
            $temp['bitis_saati']
        ]);

        // Geçici verileri temizle
        unset($_SESSION['temp_randevu']);
        
        echo "<script>alert('Randevunuz başarıyla onaylandı!'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Hatalı onay kodu!');</script>";
    }
}
?>

<div style="max-width:400px; margin:50px auto; text-align:center; font-family:sans-serif; border:1px solid #d4a373; padding:20px; border-radius:10px;">
    <h2 style="color:#d4a373;">Telefon Doğrulama</h2>
    <p>Lütfen telefonunuza gelen 6 haneli kodu giriniz.</p>
    <form method="POST">
        <input type="text" name="kod" maxlength="6" required style="width:100%; padding:10px; font-size:20px; text-align:center; border:1px solid #ddd; margin-bottom:10px;">
        <button type="submit" name="onayla" style="width:100%; padding:10px; background:#27ae60; color:white; border:none; cursor:pointer;">Randevuyu Tamamla</button>
    </form>
</div>