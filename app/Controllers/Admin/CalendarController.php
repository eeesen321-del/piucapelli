<?php

namespace App\Controllers\Admin;
use PDO;

class CalendarController {
    private $db;

    public function __construct() {
        // Sisteminizin veritabanı bağlantı sınıfına göre burayı güncelleyin
        $this->db = new PDO("mysql:host=localhost;dbname=piucapelli_db;charset=utf8", "root", "");
    }

    public function index() {
        $content = file_get_contents(__DIR__ . '/../../../views/admin/calendar.php');
        require_once __DIR__ . '/../../../views/admin/layout.php';
    }

    public function getEvents() {
        $stmt = $this->db->query("SELECT id, musteri_ad, hizmet_adi, randevu_tarihi, randevu_saati, bitis_saati, durum FROM randevular WHERE onay_durumu = 'onaylandi'");
        $randevular = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        foreach ($randevular as $r) {
            $className = '';
            
            if ($r['durum'] == 'bekliyor') $className = 'event-bekliyor';
            elseif ($r['durum'] == 'geldi') $className = 'event-geldi';
            elseif ($r['durum'] == 'gelmedi') $className = 'event-gelmedi';
            elseif ($r['durum'] == 'iptal') $className = 'event-iptal';

            $events[] = [
                'id' => $r['id'],
                'title' => $r['musteri_ad'] . ' - ' . $r['hizmet_adi'],
                'start' => $r['randevu_tarihi'] . 'T' . $r['randevu_saati'],
                'end' => $r['randevu_tarihi'] . 'T' . $r['bitis_saati'],
                'className' => $className
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($events);
        exit;
    }
    // Randevu Durumunu Güncelleme Endpoint'i (AJAX için)
    public function updateStatus() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $durum = $_POST['durum']; // 'geldi', 'gelmedi', 'iptal'
            
            $stmt = $this->db->prepare("UPDATE randevular SET durum = ? WHERE id = ?");
            
            if ($stmt->execute([$durum, $id])) {
                echo json_encode(['status' => 'success']);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error']);
            }
            exit;
        }
    }
    // Yeni Randevu Kaydetme Endpoint'i (AJAX için)
    public function createAppointment() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $musteri = $_POST['musteri'];
            $hizmet = $_POST['hizmet'];
            $tarih = $_POST['tarih'];
            $saat = $_POST['saat'];
            
            // Bitiş saatini hesapla (Hizmete göre dinamik yapılabilir, şimdilik sabit 30 dk)
            $bitis_saati = date('H:i', strtotime('+30 minutes', strtotime($saat)));
            
            // Sisteme giriş yapan adminin/personelin ID'si session'dan alınmalı. Şimdilik 1 veriyoruz.
            $calisan_id = 1;

            $stmt = $this->db->prepare("INSERT INTO randevular (musteri_ad, telefon, hizmet_adi, randevu_tarihi, randevu_saati, bitis_saati, calisan_id, durum, onay_durumu) VALUES (?, ?, ?, ?, ?, ?, ?, 'bekliyor', 'onaylandi')");
            
            // Telefon alanına şimdilik boş atıyoruz, müşteri seçimini select2 ile ID bazlı yapınca güncellenecek
            if ($stmt->execute([$musteri, '', $hizmet, $tarih, $saat, $bitis_saati, $calisan_id])) {
                echo json_encode(['status' => 'success']);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error']);
            }
            exit;
        }
    }
}