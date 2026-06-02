<?php
session_start(); // Oturum başlatıldığı varsayılıyor

// --- YARDIMCI FONKSİYONLAR (TEKRAR KULLANIM) ---

/**
 * Başarı mesajını session'a yazar ve yönlendirir.
 */
function basarili($mesaj, $url) {
    $_SESSION['toastr'] = ['tip' => 'success', 'mesaj' => $mesaj];
    header("Location: $url");
    exit;
}

/**
 * Hata mesajını session'a yazar ve yönlendirir.
 */
function hata($mesaj, $url) {
    $_SESSION['toastr'] = ['tip' => 'error', 'mesaj' => $mesaj];
    header("Location: $url");
    exit;
}

/**
 * Silme işlemleri için güvenlik kontrollerini içeren fonksiyon.
 * @param string $tablo     Tablo adı (yoneticiler veya roller)
 * @param int    $id        Silinecek kaydın ID'si
 * @param string $redirect  Başarılı silme sonrası gidilecek URL
 * @param array  $ozelKontrol  Ek koşullar (örn: süper admin silinemez)
 */
function kayitSil($tablo, $id, $redirect, $ozelKontrol = null) {
    global $baglanti;

    // Özel kontrol varsa çalıştır
    if (is_callable($ozelKontrol)) {
        $izin = $ozelKontrol($id);
        if ($izin !== true) {
            hata($izin, $redirect);
        }
    }

    $stmt = $baglanti->prepare("DELETE FROM $tablo WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        basarili("Kayıt başarıyla silindi.", $redirect);
    } else {
        hata("Silme işlemi başarısız oldu.", $redirect);
    }
}

// --- SİLME İŞLEMLERİ (TEK FONKSİYONLA) ---

// Yönetici Silme
if (isset($_GET['sil_id'])) {
    $silinecekId = (int)$_GET['sil_id'];
    kayitSil('yoneticiler', $silinecekId, 'admin.php?tab=yoneticiler&mod=kullanicilar', function($id) {
        if ($id == $_SESSION['admin_id']) {
            return "Kendinizi silemezsiniz!";
        }
        return true;
    });
}

// Rol Silme
if (isset($_GET['rol_sil'])) {
    $silinecekRolId = (int)$_GET['rol_sil'];
    kayitSil('roller', $silinecekRolId, 'admin.php?tab=yoneticiler&mod=roller', function($id) {
        if ($id == 1) {
            return "Süper Admin rolü silinemez!";
        }
        return true;
    });
}

// --- EKLEME İŞLEMLERİ (Hata yönetimi ve tutarlı mesaj) ---

// 1. Yeni Yönetici Ekle
if (isset($_POST['yonetici_ekle'])) {
    $kadi = trim($_POST['kullanici_adi']);
    $sifre = $_POST['sifre'];
    $rol_id = $_POST['rol_id'];

    $hash = password_hash($sifre, PASSWORD_DEFAULT);
    try {
        $baglanti->prepare("INSERT INTO yoneticiler (kullanici_adi, sifre, rol_id) VALUES (?, ?, ?)")
                 ->execute([$kadi, $hash, $rol_id]);
        basarili("Kullanıcı başarıyla eklendi.", "admin.php?tab=yoneticiler&mod=kullanicilar");
    } catch (PDOException $e) {
        // 1062 = Duplicate entry
        if ($e->errorInfo[1] == 1062) {
            hata("Bu kullanıcı adı zaten kullanılıyor.", "admin.php?tab=yoneticiler&mod=kullanicilar");
        } else {
            hata("Veritabanı hatası: " . $e->getMessage(), "admin.php?tab=yoneticiler&mod=kullanicilar");
        }
    }
}

// 2. Yeni Rol Ekle (Aynı hata yönetimi eklendi)
if (isset($_POST['rol_ekle'])) {
    $rol_adi = trim($_POST['rol_adi']);
    $yetkiler = json_encode($_POST['yetkiler'] ?? []);

    try {
        $baglanti->prepare("INSERT INTO roller (rol_adi, yetkiler) VALUES (?, ?)")
                 ->execute([$rol_adi, $yetkiler]);
        basarili("Yeni rol başarıyla oluşturuldu.", "admin.php?tab=yoneticiler&mod=roller");
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            hata("Bu rol adı zaten mevcut.", "admin.php?tab=yoneticiler&mod=roller");
        } else {
            hata("Veritabanı hatası: " . $e->getMessage(), "admin.php?tab=yoneticiler&mod=roller");
        }
    }
}

// --- VERİLERİ ÇEK ---
$yoneticiler = $baglanti->query("
    SELECT y.*, r.rol_adi 
    FROM yoneticiler y 
    LEFT JOIN roller r ON y.rol_id = r.id 
    ORDER BY y.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$roller = $baglanti->query("SELECT * FROM roller ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Tüm Sayfalar Listesi (yetki verilebilecek alanlar)
$sayfalar = [
    'ozet'        => 'Özet (Dashboard)',
    'ajanda'      => 'Ajanda',
    'randevular'  => 'Randevular',
    'seanslar'    => 'Seans Takibi',
    'musteriler'  => 'Müşteriler',
    'finans'      => 'Finans / Kasa',
    'calisanlar'  => 'Personel Yönetimi',
    'hizmetler'   => 'Hizmetler',
    'kategoriler' => 'Kategoriler',
    'odalar'      => 'Odalar',
    'urunler'     => 'Ürünler / Stok',
    'satis'       => 'Satış Ekranı',
    'paketler'    => 'Paketler',
    'yoneticiler' => 'Yöneticiler / Yetkiler',
    'ayarlar'     => 'Site Ayarları'
];

$aktif_mod = $_GET['mod'] ?? 'kullanicilar';
?>

<!-- Session'dan toastr mesajlarını oku ve göster -->
<?php if (isset($_SESSION['toastr'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toastr = <?= json_encode($_SESSION['toastr'], JSON_UNESCAPED_UNICODE) ?>;
            // Toastr kütüphanesi varsayılıyor (admin panelde kullanılıyordur)
            if (typeof toastr !== 'undefined') {
                toastr[toastr.tip](toastr.mesaj);
            } else {
                alert(toastr.mesaj); // Fallback
            }
        });
    </script>
    <?php unset($_SESSION['toastr']); ?>
<?php endif; ?>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $aktif_mod == 'kullanicilar' ? 'active' : '' ?>" href="?tab=yoneticiler&mod=kullanicilar">Kullanıcılar</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $aktif_mod == 'roller' ? 'active' : '' ?>" href="?tab=yoneticiler&mod=roller">Roller ve Ünvanlar</a>
    </li>
</ul>

<?php if ($aktif_mod == 'kullanicilar'): ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card card-custom p-3">
                <h5>Yeni Kullanıcı Ekle</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label>Kullanıcı Adı</label>
                        <input type="text" name="kullanici_adi" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Şifre</label>
                        <input type="password" name="sifre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Ünvan / Rol</label>
                        <select name="rol_id" class="form-select" required>
                            <?php foreach ($roller as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['rol_adi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="yonetici_ekle" class="btn btn-success w-100">Kullanıcı Oluştur</button>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card card-custom">
                <div class="card-header-custom">Kayıtlı Kullanıcılar</div>
                <div class="card-body p-0">
                    <table class="table table-hover m-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Kullanıcı</th>
                                <th>Rol</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($yoneticiler as $y): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($y['kullanici_adi']) ?>
                                        <?= $y['id'] == $_SESSION['admin_id'] ? '<span class="badge bg-primary">Siz</span>' : '' ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?= htmlspecialchars($y['rol_adi'] ?? 'Rolsüz') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($y['id'] != $_SESSION['admin_id']): ?>
                                            <a href="?tab=yoneticiler&sil_id=<?= $y['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Kullanıcı silinecek, emin misiniz?')">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="row">
        <div class="col-md-5">
            <div class="card card-custom p-3">
                <h5>Yeni Rol / Ünvan Oluştur</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label>Rol Adı (Örn: Yardımcı)</label>
                        <input type="text" name="rol_adi" class="form-control" required>
                    </div>
                    <label class="mb-2 fw-bold">Erişebileceği Sayfalar:</label>
                    <div style="max-height: 300px; overflow-y: auto;" class="border p-2 rounded bg-light">
                        <?php foreach ($sayfalar as $kod => $ad): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="yetkiler[]" value="<?= $kod ?>" id="y_<?= $kod ?>">
                                <label class="form-check-label" for="y_<?= $kod ?>"><?= $ad ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="rol_ekle" class="btn btn-primary w-100 mt-3">Rolü Kaydet</button>
                </form>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card card-custom">
                <div class="card-header-custom">Mevcut Roller</div>
                <div class="card-body p-0">
                    <table class="table table-hover m-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Rol Adı</th>
                                <th>İzinler</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roller as $r): 
                                $izinler = json_decode($r['yetkiler'], true);
                                $izin_metin = in_array('*', $izinler) ? 'Tam Yetki' : (count($izinler) . ' Sayfa');
                            ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($r['rol_adi']) ?></td>
                                    <td><span class="badge bg-secondary"><?= $izin_metin ?></span></td>
                                    <td>
                                        <?php if ($r['id'] != 1): // Süper Admin silinemez ?>
                                            <a href="?tab=yoneticiler&mod=roller&rol_sil=<?= $r['id'] ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Bu rolü silerseniz bağlı kullanıcılar sisteme giremeyebilir!')">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>