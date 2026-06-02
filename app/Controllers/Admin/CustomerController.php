<?php

namespace App\Controllers\Admin;
use PDO;

class CustomerController {
    private $db;

    public function __construct() {
        $this->db = new PDO("mysql:host=localhost;dbname=piucapelli_db;charset=utf8", "root", "");
    }
    
    public function index() {
        // Müşterileri, aktif paket sayısını, kalan seanslarını ve tahmini borçlarını tek sorguda çekiyoruz
        $sql = "SELECT 
                    m.id, 
                    m.ad_soyad as ad, 
                    m.telefon,
                    IFNULL((SELECT COUNT(*) FROM musteri_paketleri mp WHERE mp.musteri_id = m.id AND mp.durum = 'aktif'), 0) as aktif_paket,
                    IFNULL((SELECT SUM(toplam_seans - kullanilan_seans) FROM musteri_paketleri mp WHERE mp.musteri_id = m.id AND mp.durum = 'aktif'), 0) as kalan_seans,
                    (
                        IFNULL((SELECT SUM(ucret) FROM musteri_paketleri mp WHERE mp.musteri_id = m.id), 0) - 
                        IFNULL((SELECT SUM(tutar) FROM kasa_hareketleri kh WHERE kh.musteri_id = m.id AND kh.islem_turu = 'tahsilat'), 0)
                    ) as borc
                FROM musteriler m
                ORDER BY m.id DESC";
                
        $stmt = $this->db->query($sql);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        require_once __DIR__ . '/../../../views/admin/customers.php';
        $content = ob_get_clean();
        
        require_once __DIR__ . '/../../../views/admin/layout.php';
    }

    // Tahsilat Kaydetme Endpoint'i (AJAX için)
    public function payment() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $musteri_id = $_POST['musteri_id'];
            $tutar = $_POST['tutar'];
            $odeme_tipi = $_POST['odeme_tipi'];

            $stmt = $this->db->prepare("INSERT INTO kasa_hareketleri (musteri_id, islem_turu, tutar, odeme_turu, kategori) VALUES (?, 'tahsilat', ?, ?, 'Tahsilat')");
            if ($stmt->execute([$musteri_id, $tutar, $odeme_tipi])) {
                echo json_encode(['status' => 'success']);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error']);
            }
            exit;
        }
    }
}