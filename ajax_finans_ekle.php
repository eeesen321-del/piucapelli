<?php

/**
 * Uygun randevu tarihi ve saati bul
 * - Dolu saatleri atla
 * - Salı günlerini atla  
 * - Pazar günlerini atla
 */
function uygunRandevuBul($baglanti, $baslangic_tarih, $baslangic_saat, $personel_id, $max_deneme = 30) {
    $tarih = $baslangic_tarih;
    $saat = $baslangic_saat;
    $deneme = 0;
    
    while ($deneme < $max_deneme) {
        $gun_numarasi = date('N', strtotime($tarih)); // 1=Pazartesi, 7=Pazar
        
        // Salı (2) ise bir sonraki güne geç
        if ($gun_numarasi == 2) {
            $tarih = date('Y-m-d', strtotime($tarih . ' +1 day'));
            $deneme++;
            continue;
        }
        
        // Bu tarih ve saatte personelin başka randevusu var mı kontrol et
        $kontrol = $baglanti->prepare("
            SELECT COUNT(*) FROM randevular 
            WHERE randevu_tarihi = ? 
            AND randevu_saati = ? 
            AND (calisan_id = ? OR personel_id = ?)
            AND durum != 'iptal'
        ");
        $kontrol->execute([$tarih, $saat, $personel_id, $personel_id]);
        $dolu_mu = $kontrol->fetchColumn() > 0;
        
        if (!$dolu_mu) {
            return ['tarih' => $tarih, 'saat' => $saat];
        }
        
        $tarih = date('Y-m-d', strtotime($tarih . ' +1 day'));
        $deneme++;
    }
    
    return ['tarih' => $tarih, 'saat' => $saat];
}

// ajax_finans_ekle.php
include 'db.php';
header('Content-Type: application/json');

// Hataları gizle
error_reporting(0);
ini_set('display_errors', 0);

$islem = $_REQUEST['islem'] ?? '';
$response = ['status' => 'error', 'message' => ''];

try {
    switch($islem) {
        
        // --- 1. MÜŞTERİ BORÇ SORGULAMA ---
        case 'borc_sorgula':
            $musteri_id = $_POST['musteri_id'];
            
            $sorgu1 = $baglanti->prepare("SELECT SUM(ucret) FROM musteri_paketleri WHERE musteri_id = ?");
            $sorgu1->execute([$musteri_id]);
            $toplam_borc = $sorgu1->fetchColumn() ?: 0;

            $sorgu2 = $baglanti->prepare("SELECT SUM(tutar) FROM kasa_hareketleri WHERE musteri_id = ? AND islem_turu = 'tahsilat'");
            $sorgu2->execute([$musteri_id]);
            $toplam_odenen = $sorgu2->fetchColumn() ?: 0;

            $response = [
                'status' => 'success', 
                'toplam_borc' => number_format($toplam_borc, 2),
                'toplam_odenen' => number_format($toplam_odenen, 2),
                'kalan_borc' => number_format($toplam_borc - $toplam_odenen, 2),
                'ham_kalan' => ($toplam_borc - $toplam_odenen)
            ];
            break;

        // --- 2. TAHSİLAT KAYDET ---
        case 'tahsilat_kaydet':
            $musteri_id = $_POST['musteri_id'];
            $tutar = (float) $_POST['tutar'];
            $odeme_turu = $_POST['odeme_turu'];
            $aciklama = $_POST['aciklama'] ?? 'Tahsilat';
            
            if(empty($musteri_id)) throw new Exception("Müşteri seçilmelidir.");
            if($tutar <= 0) throw new Exception("Geçerli bir tutar giriniz.");

            $stmt = $baglanti->prepare("INSERT INTO kasa_hareketleri (tarih, islem_turu, kategori, tutar, aciklama, odeme_turu, musteri_id) VALUES (NOW(), 'tahsilat', 'Tahsilat', ?, ?, ?, ?)");
            $stmt->execute([$tutar, $aciklama, $odeme_turu, $musteri_id]);

            $response = ['status' => 'success', 'message' => 'Tahsilat başarıyla alındı.'];
            break;

        // --- 3. PAKET SATIŞI (PERSONEL EKLENDİ) ---
        case 'paket_satis_kaydet':
            $musteri_id  = $_POST['musteri_id'] ?? '';
            $paket_id    = $_POST['paket_id'] ?? '';
            $personel_id = $_POST['personel_id'] ?? 0;

            $bas_tarih   = $_POST['baslangic_tarihi'] ?? date('Y-m-d');
            $bas_saat    = $_POST['baslangic_saati'] ?? '09:00';

            $paket_fiyati = isset($_POST['satis_fiyati']) ? (float) str_replace(',', '.', $_POST['satis_fiyati']) : 0;
            $pesinat      = isset($_POST['pesinat_tutari']) ? (float) str_replace(',', '.', $_POST['pesinat_tutari']) : 0;
            $odeme_turu   = $_POST['odeme_turu'] ?? 'nakit';
            
            if(empty($musteri_id) || empty($paket_id)) throw new Exception("Müşteri ve Paket seçilmelidir.");
            if(empty($personel_id)) throw new Exception("Lütfen sorumlu personel seçiniz.");

            // Paket ve Müşteri Bilgilerini Çek
            $p = $baglanti->prepare("SELECT * FROM paketler WHERE id = ?");
            $p->execute([$paket_id]);
            $paket = $p->fetch(PDO::FETCH_ASSOC);

            $m = $baglanti->prepare("SELECT ad_soyad, telefon FROM musteriler WHERE id = ?");
            $m->execute([$musteri_id]);
            $musteri = $m->fetch(PDO::FETCH_ASSOC);

            if(!$paket) throw new Exception("Paket bulunamadı.");
            if(!$musteri) throw new Exception("Müşteri bulunamadı.");

            $ucret = ($paket_fiyati > 0) ? $paket_fiyati : $paket['toplam_tutar'];
            $seans_sayisi = (int)$paket['seans_sayisi'];
            $aralik = (int)$paket['seans_araligi'] > 0 ? (int)$paket['seans_araligi'] : 30;
            $otomatik_seans = isset($_POST['otomatik_seans']) && $_POST['otomatik_seans'] != '0';

            // --- TRANSACTION BAŞLAT ---
            $baglanti->beginTransaction();

            // A) Müşteri Paketini Kaydet
            $stmt = $baglanti->prepare("INSERT INTO musteri_paketleri (musteri_id, paket_id, toplam_seans, kullanilan_seans, ucret, durum) VALUES (?, ?, ?, 0, ?, 'aktif')");
            $stmt->execute([$musteri_id, $paket_id, $seans_sayisi, $ucret]);
            $mp_id = $baglanti->lastInsertId();

            // B) Peşinat Varsa Kasaya İşle
            if($pesinat > 0) {
                $kasa_aciklama = $paket['paket_adi'] . " Satışı Peşinatı";
                $baglanti->prepare("INSERT INTO kasa_hareketleri (tarih, islem_turu, kategori, tutar, aciklama, odeme_turu, musteri_id) VALUES (NOW(), 'tahsilat', 'Paket Satışı', ?, ?, ?, ?)")
                         ->execute([$pesinat, $kasa_aciklama, $odeme_turu, $musteri_id]);
            }

            // C) Otomatik Seans ve Randevu (sadece seçildiyse)
            $randevu_sayisi = 0;
            if ($otomatik_seans) {
                $current_date = $bas_tarih;
                for ($i = 1; $i <= $seans_sayisi; $i++) {
                    $uygun_slot = uygunRandevuBul($baglanti, $current_date, $bas_saat, $personel_id);
                    $current_date = $uygun_slot['tarih'];
                    $current_saat = $uygun_slot['saat'];

                    $s = $baglanti->prepare("INSERT INTO seanslar (musteri_paket_id, calisan_id, kacinci_seans, durum, randevu_tarihi, randevu_saati) VALUES (?, ?, ?, 'bekliyor', ?, ?)");
                    $s->execute([$mp_id, $personel_id, $i, $current_date, $current_saat]);
                    $s_id = $baglanti->lastInsertId();

                    $hizmet_adi = $paket['paket_adi'] . " (" . $i . ". Seans)";
                    $bitis_saat = date('H:i', strtotime($bas_saat . ' +60 minutes'));

                    $r = $baglanti->prepare("
                        INSERT INTO randevular 
                        (musteri_id, musteri_ad, telefon, hizmet_adi, randevu_tarihi, randevu_saati, bitis_saati, durum, fiyat, seans_id, calisan_id, personel_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'bekliyor', 0, ?, ?, ?)
                    ");
                    $r->execute([
                        $musteri_id, $musteri['ad_soyad'], $musteri['telefon'],
                        $hizmet_adi, $current_date, $current_saat, $bitis_saat,
                        $s_id, $personel_id, $personel_id
                    ]);

                    $randevu_sayisi++;
                    $current_date = date('Y-m-d', strtotime($current_date . " +$aralik days"));
                }
            }

            // --- TRANSACTION TAMAMLA ---
            $baglanti->commit();

            if ($otomatik_seans) {
                $response = ['status' => 'success', 'message' => "Paket satıldı ve $randevu_sayisi adet randevu oluşturuldu."];
            } else {
                $response = ['status' => 'success', 'message' => "Paket başarıyla kaydedildi. Seansları kendiniz oluşturabilirsiniz."];
            }
            break;

        // --- 4. STANDART İŞLEMLER ---
        case 'ekle': 
            $tarih = str_replace('T', ' ', $_POST['tarih'] ?? date('Y-m-d H:i:s'));
            $islem_turu = $_POST['islem_turu']; 
            $kategori = $_POST['kategori'];
            if($kategori == 'Paket Satışı') { $islem_turu = 'paket_satisi'; }

            $tutar = (float) str_replace(',', '.', $_POST['tutar']);
            $musteri_id = !empty($_POST['musteri_id']) ? $_POST['musteri_id'] : NULL;
            
            $stmt = $baglanti->prepare("INSERT INTO kasa_hareketleri (tarih, islem_turu, kategori, tutar, aciklama, odeme_turu, musteri_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tarih, $islem_turu, $kategori, $tutar, $_POST['aciklama'], $_POST['odeme_turu'], $musteri_id]);
            $response = ['status' => 'success', 'message' => 'İşlem eklendi'];
            break;

        case 'sil':
            $id = $_POST['id'];
            $baglanti->prepare("DELETE FROM kasa_hareketleri WHERE id = ?")->execute([$id]);
            $response = ['status' => 'success'];
            break;
            
        default:
            $response['message'] = 'Geçersiz işlem parametresi.';
    }
    
} catch(Exception $e) {
    // Açık transaction varsa geri al
    if ($baglanti->inTransaction()) {
        $baglanti->rollBack();
    }
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>