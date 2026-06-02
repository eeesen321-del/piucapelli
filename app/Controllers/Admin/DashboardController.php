<?php

namespace App\Controllers\Admin;
use PDO;

class DashboardController {
    private $db;

    public function __construct() {
        // Sisteminizin veritabanı bağlantı sınıfına göre burayı güncelleyin
        $this->db = new PDO("mysql:host=localhost;dbname=piucapelli_db;charset=utf8", "root", "");
    }

public function index() {
        ob_start();
        require_once __DIR__ . '/../../../views/admin/dashboard.php';
        $content = ob_get_clean();
        
        require_once __DIR__ . '/../../../views/admin/layout.php';
    }

    public function getStats() {
        $bugun = date('Y-m-d');

        // Bugünkü Randevular (İptal hariç)
        $stmt = $this->db->query("SELECT COUNT(*) FROM randevular WHERE randevu_tarihi = '$bugun' AND durum != 'iptal'");
        $randevular = $stmt->fetchColumn();

        // Bekleyen Müşteriler
        $stmt = $this->db->query("SELECT COUNT(*) FROM randevular WHERE randevu_tarihi = '$bugun' AND durum = 'bekliyor'");
        $bekleyen = $stmt->fetchColumn();

        // Bugünkü Gelir
        $stmt = $this->db->query("SELECT SUM(tutar) FROM kasa_hareketleri WHERE DATE(tarih) = '$bugun'");
        $gelir = $stmt->fetchColumn() ?: 0;

        // En Yoğun Personel
        $stmt = $this->db->query("SELECT c.ad_soyad FROM randevular r JOIN calisanlar c ON r.calisan_id = c.id WHERE r.randevu_tarihi = '$bugun' GROUP BY r.calisan_id ORDER BY COUNT(*) DESC LIMIT 1");
        $personel = $stmt->fetchColumn() ?: 'Veri Yok';

        header('Content-Type: application/json');
        echo json_encode([
            'bugunku_randevular' => $randevular,
            'bekleyen_musteriler' => $bekleyen,
            'bugunku_gelir' => $gelir,
            'en_yogun_personel' => $personel
        ]);
        exit;
    }
}