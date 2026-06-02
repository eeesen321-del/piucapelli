<?php
// Dosya: login.php
session_start();
include 'db.php';

// Zaten giriş yapmışsa panele yönlendir
if (isset($_SESSION['admin_giris']) && $_SESSION['admin_giris'] === true) {
    header("Location: admin.php");
    exit;
}

if (isset($_POST['giris_yap'])) {
    $kullanici = trim($_POST['kullanici_adi']);
    $sifre = trim($_POST['sifre']);

    // Veritabanından kullanıcıyı çek
    $sorgu = $baglanti->prepare("SELECT * FROM yoneticiler WHERE kullanici_adi = ?");
    $sorgu->execute([$kullanici]);
    $yonetici = $sorgu->fetch(PDO::FETCH_ASSOC);

    // Şifre kontrolü (password_verify ile)
    if ($yonetici && password_verify($sifre, $yonetici['sifre'])) {
        $_SESSION['admin_giris'] = true;
        $_SESSION['admin_id'] = $yonetici['id'];
        
        header("Location: admin.php");
        exit;
    } else {
        $hata = "Hatalı kullanıcı adı veya şifre!";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Girişi</title>
    <style>
        body { display: flex; justify-content: center; align-items: center; height: 100vh; background: #f3f4f6; font-family: sans-serif; margin: 0; }
        .login-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); width: 300px; text-align: center; }
        .logo { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 20px; }
        .logo span { color: #d4a373; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #d4a373; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #c58d55; }
        .error { color: #dc3545; background: #ffe6e6; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">PiuCapelli<span>Panel</span></div>
        <?php if(isset($hata)) echo "<div class='error'>$hata</div>"; ?>
        <form method="POST">
            <input type="text" name="kullanici_adi" placeholder="Kullanıcı Adı" required>
            <input type="password" name="sifre" placeholder="Şifre" required>
            <button type="submit" name="giris_yap">Giriş Yap</button>
        </form>
    </div>
</body>
</html>