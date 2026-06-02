<?php
// Dosya Adı: sifre_reset.php
include 'db.php';

// Yeni şifreniz: 1234
$yeni_sifre = "1234";
$kullanici_adi = "admin"; // Buraya veritabanındaki kullanıcı adını yaz (genelde 'admin'dir)

// Şifreyi hashle
$hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);

try {
    $sorgu = $baglanti->prepare("UPDATE yoneticiler SET sifre = ? WHERE kullanici_adi = ?");
    $sonuc = $sorgu->execute([$hash, $kullanici_adi]);

    if ($sorgu->rowCount() > 0) {
        echo "<h1>Başarılı!</h1>";
        echo "Şifreniz <b>1234</b> olarak güncellendi.<br>";
        echo "<a href='login.php'>Giriş Yap</a>";
    } else {
        echo "<h1>Hata/Uyarı</h1>";
        echo "Kullanıcı bulunamadı veya şifre zaten aynı.<br>";
        echo "Veritabanındaki 'yoneticiler' tablosunda 'kullanici_adi' sütununun <b>$kullanici_adi</b> olduğundan emin olun.";
    }
} catch (PDOException $e) {
    echo "Hata: " . $e->getMessage();
}
?>