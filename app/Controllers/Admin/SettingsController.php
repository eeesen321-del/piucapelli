<?php

namespace App\Controllers\Admin;
use PDO;

class SettingsController {
    private $db;

    public function __construct() {
        $this->db = new PDO("mysql:host=localhost;dbname=piucapelli_db;charset=utf8", "root", "");
    }
    
    public function index() {
        // Ayarları veritabanından dizi olarak çek (key => value formatına getir)
        $stmt = $this->db->query("SELECT ayar_adi, ayar_degeri FROM ayarlar");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $ayarlar = [];
        foreach ($results as $row) {
            $ayarlar[$row['ayar_adi']] = $row['ayar_degeri'];
        }

        ob_start();
        // views/admin/settings.php içinde $ayarlar['site_baslik'] şeklinde kullanabilirsiniz.
        require_once __DIR__ . '/../../../views/admin/settings.php';
        $content = ob_get_clean();
        
        require_once __DIR__ . '/../../../views/admin/layout.php';
    }
    // Bekleyen Sessiz Engelleme Taleplerini Getir (AJAX)
    public function getPendingRequests() {
        // Sessiz engellemeye düşenlerin calisan_id'si 0, onay_durumu beklemede'dir.
        $stmt = $this->db->query("SELECT id, musteri_ad, telefon, hizmet_adi, randevu_tarihi, randevu_saati FROM randevular WHERE onay_durumu = 'beklemede' AND calisan_id = 0 ORDER BY id DESC");
        $talepler = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($talepler);
        exit;
    }

    // Talebi Onayla veya Reddet (AJAX)
    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $islem = $_POST['islem'];

            if ($islem === 'onayla') {
                // Onaylanınca calisan_id'yi 1 (veya varsayılan havuz elemanı) yapıp onaylandı durumuna çekiyoruz
                $stmt = $this->db->prepare("UPDATE randevular SET onay_durumu = 'onaylandi', calisan_id = 1 WHERE id = ?");
                $stmt->execute([$id]);
            } elseif ($islem === 'reddet') {
                // Reddedilince durum iptal ve onay_durumu reddedildi olur
                $stmt = $this->db->prepare("UPDATE randevular SET onay_durumu = 'reddedildi', durum = 'iptal' WHERE id = ?");
                $stmt->execute([$id]);
            }

            echo json_encode(['status' => 'success']);
            exit;
        }
    }

    // Ayarları Kaydetme Endpoint'i (AJAX için)
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tip = $_POST['tip'];
            $payload = $_POST['payload'];

            if ($tip === 'isletme') {
                $this->updateSetting('site_baslik', $payload['ad']);
                $this->updateSetting('telefon', $payload['tel']);
            } elseif ($tip === 'saatler') {
                $saatler = $payload['acilis'] . ' - ' . $payload['kapanis'];
                $this->updateSetting('calisma_saatleri', $saatler);
            }

            echo json_encode(['status' => 'success']);
            exit;
        }
    }

    private function updateSetting($key, $value) {
        $stmt = $this->db->prepare("UPDATE ayarlar SET ayar_degeri = ? WHERE ayar_adi = ?");
        $stmt->execute([$value, $key]);
    }
}