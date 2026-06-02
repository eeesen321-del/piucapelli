<?php
include 'db.php';
include 'functions.php';

// --- YARDIMCI FONKSİYONLAR ---
function isimDuzenle($metin) {
    return mb_convert_case(mb_strtolower($metin, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

/**
 * Uygun randevu tarihi bul (Salı, dolu saatler atlanır)
 */
function uygunRandevuBul($baglanti, $baslangic_tarih, $baslangic_saat, $calisan_id, $max_deneme = 30) {
    $tarih = $baslangic_tarih;
    $saat = $baslangic_saat;
    $deneme = 0;
    
    while ($deneme < $max_deneme) {
        $gun = date('N', strtotime($tarih)); // 1=Pzt, 7=Paz
        
        // Salı ise atla
        if ($gun == 2) {
            $tarih = date('Y-m-d', strtotime($tarih . ' +1 day'));
            $deneme++;
            continue;
        }
        
        // Dolu mu kontrol et
        $kontrol = $baglanti->prepare("SELECT COUNT(*) FROM randevular WHERE calisan_id = ? AND randevu_tarihi = ? AND randevu_saati = ? AND durum != 'iptal'");
        $kontrol->execute([$calisan_id, $tarih, $saat]);
        
        if ($kontrol->fetchColumn() == 0) {
            return ['tarih' => $tarih, 'saat' => $saat];
        }
        
        $tarih = date('Y-m-d', strtotime($tarih . ' +1 day'));
        $deneme++;
    }
    
    return ['tarih' => $tarih, 'saat' => $saat];
}

// --- GÜVENLİK VE ZAMAN KONTROLÜ ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") { exit; }

date_default_timezone_set('Europe/Istanbul');

// Gelen Verileri Temizle
$secilen_tarih = $_POST['randevu_tarihi'] ?? '';
$secilen_saat  = $_POST['randevu_saati'] ?? '';
$ham_ad        = trim($_POST['musteri_ad'] ?? '');
$musteri_ad    = isimDuzenle($ham_ad);
$telefon       = trim($_POST['telefon'] ?? '');
$islem_turu    = $_POST['islem_turu'] ?? 'hizmet'; // 'paket' veya 'hizmet'
$hizmet_id     = $_POST['hizmet_id'] ?? 0;         // Paket ID veya Hizmet ID
$calisan_id    = $_POST['calisan_id'] ?? 0;

// Zorunlu Alan Kontrolü
if (!$hizmet_id || !$calisan_id || !$secilen_tarih || !$secilen_saat) { 
    exit("Eksik bilgi gönderildi."); 
}

// Geçmiş Zaman Kontrolü
$randevu_zamani = $secilen_tarih . ' ' . $secilen_saat;
if ($randevu_zamani < date('Y-m-d H:i')) {
    echo "<script>alert('HATA: Geçmiş bir tarihe randevu alamazsınız!'); window.history.back();</script>";
    exit;
}

// --- 1. MÜŞTERİ İŞLEMLERİ (Bul veya Oluştur) ---
try {
    $m_kontrol = $baglanti->prepare("SELECT id FROM musteriler WHERE telefon = ?");
    $m_kontrol->execute([$telefon]);
    $mevcut_musteri = $m_kontrol->fetch(PDO::FETCH_ASSOC);

    if ($mevcut_musteri) {
        $musteri_id = $mevcut_musteri['id'];
        // İsim güncellemesi (İsteğe bağlı, aktif tutuldu)
        $baglanti->prepare("UPDATE musteriler SET ad_soyad = ? WHERE id = ?")->execute([$musteri_ad, $musteri_id]);
    } else {
        $baglanti->prepare("INSERT INTO musteriler (ad_soyad, telefon, eposta) VALUES (?, ?, '')")->execute([$musteri_ad, $telefon]);
        $musteri_id = $baglanti->lastInsertId();
    }
} catch (PDOException $e) { exit("Veritabanı hatası (Müşteri): " . $e->getMessage()); }

// --- ENGELLEME KONTROLÜ ---
$engel_kontrol = $baglanti->prepare("SELECT COUNT(*) FROM engellenen_numaralar WHERE telefon = ?");
$engel_kontrol->execute([$telefon]);
if ($engel_kontrol->fetchColumn() > 0) {
    echo "<script>alert('Bu telefon numarası engellenmiştir. Randevu alamazsınız.'); window.history.back();</script>";
    exit;
}


// --- 2. İŞLEM TÜRÜNE GÖRE KAYIT ---
$wa_mesaj_icerigi = ""; // WhatsApp mesajı için değişken

if ($islem_turu == 'paket') {
    // ==========================
    // PAKET KAYIT İŞLEMLERİ
    // ==========================
    
    // Paket Bilgilerini Çek
    $pk = $baglanti->prepare("SELECT * FROM paketler WHERE id = ?");
    $pk->execute([$hizmet_id]);
    $paket_veri = $pk->fetch(PDO::FETCH_ASSOC);

    if (!$paket_veri) { exit("Hata: Seçilen paket bulunamadı."); }

    $paket_adi    = $paket_veri['paket_adi'];
    $fiyat        = $paket_veri['toplam_tutar'];
    $seans_sayisi = $paket_veri['seans_sayisi'];
    $aralik_gun   = $paket_veri['seans_araligi'] > 0 ? $paket_veri['seans_araligi'] : 30;

    // Bağlı Hizmet Süresini Bul (Varsayılan 30dk)
    $sure_dk = 30;
    if (!empty($paket_veri['hizmet_id'])) {
        $h_sorgu = $baglanti->prepare("SELECT sure_dk FROM hizmetler WHERE id = ?");
        $h_sorgu->execute([$paket_veri['hizmet_id']]);
        $h_veri = $h_sorgu->fetch(PDO::FETCH_ASSOC);
        if ($h_veri) $sure_dk = $h_veri['sure_dk'];
    }

    // A) Müşteri Paketini Tanımla
    $baglanti->prepare("INSERT INTO musteri_paketleri (musteri_id, paket_id, toplam_seans, kullanilan_seans, ucret, durum) VALUES (?, ?, ?, 0, ?, 'aktif')")
             ->execute([$musteri_id, $hizmet_id, $seans_sayisi, $fiyat]);
    $mp_id = $baglanti->lastInsertId();

    // B) Kasa Hareketi YOK - Ödeme panelden tahsilat olarak alınacak (panel mantığıyla aynı)

    // C) Seansları ve Randevuları Döngü ile Oluştur
    $sql_seans   = "INSERT INTO seanslar (musteri_paket_id, calisan_id, randevu_tarihi, randevu_saati, kacinci_seans, durum) VALUES (?, ?, ?, ?, ?, 'bekliyor')";
    $sql_randevu = "INSERT INTO randevular (musteri_id, musteri_ad, telefon, hizmet_adi, randevu_tarihi, randevu_saati, bitis_saati, calisan_id, personel_id, durum, fiyat, seans_id, onay_durumu, kayit_tarihi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'bekliyor', 0, ?, 'beklemede', NOW())";
    
    $stmt_seans   = $baglanti->prepare($sql_seans);
    $stmt_randevu = $baglanti->prepare($sql_randevu);

    for ($i = 0; $i < $seans_sayisi; $i++) {
        // Tarih Hesapla (aralik_gun kadar ileri)
        $eklenecek_gun = $i * $aralik_gun;
        $tahmini_tarih = date('Y-m-d', strtotime($secilen_tarih . " +$eklenecek_gun days"));
        
        // Uygun slot bul
        $uygun = uygunRandevuBul($baglanti, $tahmini_tarih, $secilen_saat, $calisan_id);
        $hesaplanan_tarih = $uygun['tarih'];
        $hesaplanan_saat = $uygun['saat'];
        
        // Bitiş Saati Hesapla
        $dt_baslangic = new DateTime("$hesaplanan_tarih $hesaplanan_saat");
        $dt_bitis     = clone $dt_baslangic;
        $dt_bitis->modify("+$sure_dk minutes");
        $bitis_saati  = $dt_bitis->format("H:i");

        $randevu_baslik = $paket_adi . " (" . ($i + 1) . ". Seans)";

        // Ekle
        $stmt_seans->execute([$mp_id, $calisan_id, $hesaplanan_tarih, $hesaplanan_saat, ($i + 1)]);
        $seans_id = $baglanti->lastInsertId();
        
        $stmt_randevu->execute([$musteri_id, $musteri_ad, $telefon, $randevu_baslik, $hesaplanan_tarih, $hesaplanan_saat, $bitis_saati, $calisan_id, $calisan_id, $seans_id]);
    }

    // Form linki oluştur (eğer pakete bağlı form varsa)
    $form_linki = "";
    if (!empty($paket_veri['form_id']) && $paket_veri['form_id'] > 0) {
        // Direkt form sayfasına yönlendir
        $form_url = "form_doldur.php?id=" . $paket_veri['form_id'] . "&musteri_id=" . $musteri_id;
        header("Location: $form_url");
        exit;
    }

    $wa_mesaj_icerigi = "YENİ PAKET SATIŞI!\nİsim: $musteri_ad\nTelefon: $telefon\nPaket: $paket_adi\nSeans: $seans_sayisi\nTutar: $fiyat ₺";

} else {
    // ==========================
    // TEKİL HİZMET KAYIT İŞLEMLERİ
    // ==========================
    
    // Hizmet Bilgilerini Çek
    $hs = $baglanti->prepare("SELECT hizmet_adi, sure_dk, fiyat, form_id FROM hizmetler WHERE id = ?");
    $hs->execute([$hizmet_id]);
    $hizmet_veri = $hs->fetch(PDO::FETCH_ASSOC);

    if (!$hizmet_veri) { exit("Hata: Seçilen hizmet bulunamadı."); }

    $hizmet_adi = $hizmet_veri['hizmet_adi'];
    $sure       = (int)$hizmet_veri['sure_dk'];
    $fiyat      = $hizmet_veri['fiyat'];
    $form_id    = $hizmet_veri['form_id'] ?? 0;

    // Saat Hesaplamaları
    $baslangic = new DateTime("$secilen_tarih $secilen_saat");
    $bitis     = clone $baslangic;
    $bitis->modify("+$sure minutes");
    $bitis_saati = $bitis->format("H:i");

    // Çakışma Kontrolü
    $kontrol = $baglanti->prepare("SELECT COUNT(*) FROM randevular WHERE calisan_id = ? AND randevu_tarihi = ? AND randevu_saati < ? AND bitis_saati > ? AND durum != 'iptal'");
    $kontrol->execute([$calisan_id, $secilen_tarih, $bitis_saati, $secilen_saat]);

    if ($kontrol->fetchColumn() > 0) {
        echo "<script>alert('Seçtiğiniz saat dolu. Lütfen başka bir saat seçiniz.'); window.history.back();</script>";
        exit;
    }

    // Randevu Kaydet (onay_durumu = 'beklemede')
    $baglanti->prepare("INSERT INTO randevular (musteri_id, musteri_ad, telefon, hizmet_adi, randevu_tarihi, randevu_saati, bitis_saati, calisan_id, durum, fiyat, onay_durumu, kayit_tarihi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'bekliyor', ?, 'beklemede', NOW())")
             ->execute([$musteri_id, $musteri_ad, $telefon, $hizmet_adi, $secilen_tarih, $secilen_saat, $bitis_saati, $calisan_id, $fiyat]);
    
    $randevu_id = $baglanti->lastInsertId();

    $wa_mesaj_icerigi = "Yeni Randevu (ONAY BEKLİYOR)\nİsim: $musteri_ad\nİşlem: $hizmet_adi\nTarih: $secilen_tarih $secilen_saat";
    
    // Eğer hizmete bağlı form varsa, direkt form sayfasına yönlendir
    if ($form_id > 0) {
        $form_url = "form_doldur.php?id=" . $form_id . "&musteri_id=" . $musteri_id . "&randevu_id=" . $randevu_id;
        header("Location: $form_url");
        exit;
    }
}

// --- BİLDİRİM VE YÖNLENDİRME ---
$wa_numara = "905511963555"; 
$wa_url = "https://wa.me/$wa_numara?text=" . urlencode($wa_mesaj_icerigi);

header("Location: $wa_url");
exit;
?>