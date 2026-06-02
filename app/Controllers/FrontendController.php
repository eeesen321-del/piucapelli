<?php

namespace App\Controllers;
use PDO;

class FrontendController {
    private $db;

    public function __construct() {
        $this->db = new PDO("mysql:host=localhost;dbname=piucapelli_db;charset=utf8", "root", "");
    }

    public function index() {
        require_once __DIR__ . '/../../views/frontend/index.php';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ad = $_POST['ad_soyad'];
            $tel = $_POST['telefon'];
            $hizmet = $_POST['hizmet_adi'];
            $tarih = $_POST['randevu_tarihi'];
            $saat = $_POST['randevu_saati'];
            
            // Sessiz Engelleme Kontrolü
            $stmt = $this->db->prepare("SELECT id FROM engellenen_numaralar WHERE telefon = ?");
            $stmt->execute([$tel]);
            $engelli_mi = $stmt->fetch();

            // Eğer engelliyse calisan_id 0 olur, durum beklemede kalır (Sessizce düşer)
            // Eğer engelli değilse ilk müsait personele/havuza düşer, onaylanır.
            $calisan_id = $engelli_mi ? 0 : 1; 
            $onay_durumu = $engelli_mi ? 'beklemede' : 'onaylandi';
            
            // Bitiş saatini hesapla (Varsayılan 30 dk ekler, bunu hizmetler tablosundan da çektirebilirsiniz)
            $bitis_saati = date('H:i', strtotime('+30 minutes', strtotime($saat)));

            $kayit = $this->db->prepare("INSERT INTO randevular (musteri_ad, telefon, hizmet_adi, randevu_tarihi, randevu_saati, bitis_saati, calisan_id, onay_durumu) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $kayit->execute([$ad, $tel, $hizmet, $tarih, $saat, $bitis_saati, $calisan_id, $onay_durumu]);

            // Müşteri her iki senaryoda da aynı başarı mesajını görür
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Talebiniz alındı, sizi arayacağız.']);
            exit;
        }
    }
}