<?php
ob_start();
session_start();
include 'db.php';
include 'functions.php';  // formatTel() ve diğer yardımcılar

// --- GÜVENLİK VE OTURUM KONTROLÜ ---
if (!isset($_SESSION['admin_giris']) || $_SESSION['admin_giris'] !== true) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['cikis'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// --- YETKİ SİSTEMİ ---
$giris_yapan_id = $_SESSION['admin_id'];
$yetki_sorgu = $baglanti->prepare("SELECT r.yetkiler FROM yoneticiler y LEFT JOIN roller r ON y.rol_id = r.id WHERE y.id = ?");
$yetki_sorgu->execute([$giris_yapan_id]);
$ham_yetkiler = $yetki_sorgu->fetchColumn();
$kullanici_yetkileri = json_decode($ham_yetkiler, true) ?? [];

function yetkiVarMi($sayfa) {
    global $kullanici_yetkileri;
    if (in_array("*", $kullanici_yetkileri)) return true;
    return in_array($sayfa, $kullanici_yetkileri);
}

// --- SAYFA YÖNLENDİRME ---
$active_tab = $_GET['tab'] ?? 'ozet';
$active_tab = preg_replace('/[^a-z0-9_]/', '', $active_tab);

// Yetki Kontrolü ve Yönlendirme
if (!yetkiVarMi($active_tab)) {
    if(yetkiVarMi('ozet') && $active_tab != 'ozet') {
        header("Location: admin.php?tab=ozet");
        exit;
    } else if (!yetkiVarMi('ozet') && $active_tab == 'ozet') {
        foreach($kullanici_yetkileri as $y) {
            if($y != '*') { header("Location: admin.php?tab=$y"); exit; }
        }
        die("<h3>Erişim Yetkiniz Yok.</h3>");
    }
}

$dosya_yolu = "sayfalar/" . $active_tab . ".php";

// --- GLOBAL VERİLER (Modallar için tek seferde çekilir) ---
try {
    $tum_hizmetler = $baglanti->query("SELECT * FROM hizmetler ORDER BY kategori ASC")->fetchAll(PDO::FETCH_ASSOC);
    $calisanlar    = $baglanti->query("SELECT id, ad_soyad FROM calisanlar ORDER BY ad_soyad ASC")->fetchAll(PDO::FETCH_ASSOC);
    $urunler_modal = $baglanti->query("SELECT * FROM urunler WHERE stok_adedi > 0 ORDER BY urun_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) { 
    $tum_hizmetler = []; $calisanlar = []; $urunler_modal = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PiuCapelli Yönetim Paneli</title>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Tom Select: Arama özellikli dropdown -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <style>
        :root { --primary-color: #d4a373; --dark-bg: #1e1e2d; --light-bg: #f5f8fa; --text-muted: #a2a3b7; }
        body { font-family: 'Inter', sans-serif; background-color: var(--light-bg); overflow-x: hidden; margin: 0; }
        
        /* Sidebar */
        .sidebar { width: 260px; background-color: var(--dark-bg); height: 100vh; position: fixed; top: 0; left: 0; padding-top: 20px; z-index: 1000; transition: 0.3s; display: flex; flex-direction: column; overflow-y: auto; }
        .sidebar-brand { font-size: 1.4rem; font-weight: 700; color: #fff; padding: 0 25px 25px; border-bottom: 1px solid rgba(255,255,255,0.05); letter-spacing: 1px; display: flex; align-items: center; gap: 10px; }
        .nav-title { font-size: 0.7rem; font-weight: 700; color: #5a5c70; text-transform: uppercase; padding: 15px 25px 5px; letter-spacing: 1px; }
        .nav-link { color: var(--text-muted); padding: 12px 25px; display: flex; align-items: center; gap: 12px; text-decoration: none; font-size: 0.95rem; border-left: 3px solid transparent; transition: all 0.2s; }
        .nav-link:hover, .nav-link.active { color: #fff; background-color: rgba(255,255,255,0.03); border-left-color: var(--primary-color); }
        
        /* Main Content */
        .main-content { margin-left: 260px; padding: 30px; min-height: 100vh; transition: 0.3s; }
        .topbar { background: #fff; padding: 15px 30px; border-radius: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 15px rgba(0,0,0,0.02); }
        .card-custom { border: none; border-radius: 15px; background: #fff; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03); margin-bottom: 20px; }
        .card-header-custom { padding: 20px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; font-weight: 600; }
        .randevu-kutu { border-radius: 8px; padding: 8px; color: #fff !important; box-shadow: 0 2px 4px rgba(0,0,0,0.1); font-size: 0.85rem; border-left: 4px solid rgba(0,0,0,0.2); cursor: pointer; }
        .randevu-kutu:hover { transform: translateY(-2px); }
        
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; } }
        @media print { .sidebar, .topbar { display: none !important; } .main-content { margin: 0 !important; padding: 0 !important; } }

        /* ── Tom Select global uyum ── */
        .ts-wrapper { flex: 1; }
        .ts-wrapper .ts-control { border-radius: 6px; border-color: #ced4da; padding: 6px 10px; font-size: .95rem; min-height: 38px; }
        .ts-wrapper.form-select-sm .ts-control { padding: 3px 8px; font-size: .85rem; min-height: 31px; }
        .ts-dropdown { z-index: 9999 !important; border-radius: 8px; box-shadow: 0 6px 24px rgba(0,0,0,.12); }
        .ts-dropdown .option { padding: 8px 12px; }
        .ts-dropdown .option:hover, .ts-dropdown .option.active { background: #e8f0fe; color: #1a3c6e; }
        .ts-dropdown .create { color: #198754; font-weight: 600; }
        .ts-wrapper.is-invalid .ts-control { border-color: #dc3545; }

        /* ── Tooltip ipuçları ── */
        [data-bs-toggle="tooltip"] { cursor: help; }

        /* ── Satır hover vurgu ── */
        .table tbody tr:hover td { background-color: #f0f7ff !important; }

        /* ── Kopyalanabilir metin ── */
        .copy-text { cursor: pointer; border-bottom: 1px dashed #aaa; }
        .copy-text:hover { color: #0d6efd; }

        /* ── Boş alan mesajı ── */
        .empty-state { text-align:center; padding: 50px 20px; color: #aaa; }
        .empty-state i { font-size: 3rem; display: block; margin-bottom: 12px; opacity: .4; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand"><i class="fa fa-scissors text-warning"></i> PIUCAPELLI</div>
    
    <div class="text-center mb-4 pt-3">
        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 50px; height: 50px; font-size: 1.2rem;">A</div>
        <div class="mt-2 small text-muted">Yönetici Paneli</div>
    </div>

    <div class="d-flex flex-column pb-5">
        <div class="nav-title">Yönetim</div>
        <?php if(yetkiVarMi('ozet')): ?><a href="?tab=ozet" class="nav-link <?= $active_tab == 'ozet' ? 'active' : '' ?>"><i class="fa fa-chart-line"></i> Özet</a><?php endif; ?>
        <?php if(yetkiVarMi('ajanda')): ?><a href="?tab=ajanda" class="nav-link <?= $active_tab == 'ajanda' ? 'active' : '' ?>"><i class="fa fa-calendar-alt"></i> Ajanda</a><?php endif; ?>
        <?php if(yetkiVarMi('randevular')): ?><a href="?tab=randevular" class="nav-link <?= $active_tab == 'randevular' ? 'active' : '' ?>"><i class="fa fa-calendar-check"></i> Randevular</a><?php endif; ?>
        <?php if(yetkiVarMi('onay_bekleyenler')): ?>
        <a href="?tab=onay_bekleyenler" class="nav-link <?= $active_tab == 'onay_bekleyenler' ? 'active' : '' ?>">
            <i class="fa fa-hourglass-half"></i> Onay Bekleyenler
            <?php 
            $bekleyen_sayisi = $baglanti->query("SELECT COUNT(*) FROM randevular WHERE onay_durumu = 'beklemede'")->fetchColumn();
            if ($bekleyen_sayisi > 0): ?>
                <span class="badge bg-danger rounded-pill ms-2"><?= $bekleyen_sayisi ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <?php if(yetkiVarMi('musteriler')): ?><a href="?tab=musteriler" class="nav-link <?= $active_tab == 'musteriler' ? 'active' : '' ?>"><i class="fa fa-users"></i> Müşteriler</a><?php endif; ?>

        <div class="nav-title mt-2">Finans & Ürün</div>
        <?php if(yetkiVarMi('finans')): ?><a href="?tab=finans" class="nav-link <?= $active_tab == 'finans' ? 'active' : '' ?>"><i class="fa fa-wallet"></i> Finans</a><?php endif; ?>
        <?php if(yetkiVarMi('satis')): ?><a href="?tab=satis" class="nav-link <?= $active_tab == 'satis' ? 'active' : '' ?>"><i class="fa fa-cash-register"></i> Hızlı Satış</a><?php endif; ?>
        <?php if(yetkiVarMi('urunler')): ?><a href="?tab=urunler" class="nav-link <?= $active_tab == 'urunler' ? 'active' : '' ?>"><i class="fa fa-boxes"></i> Stok Yönetimi</a><?php endif; ?>
        <?php if(yetkiVarMi('hizmetler')): ?><a href="?tab=hizmetler" class="nav-link <?= $active_tab == 'hizmetler' ? 'active' : '' ?>"><i class="fa fa-magic"></i> Hizmetler</a><?php endif; ?>
        <?php if(yetkiVarMi('paketler')): ?><a href="?tab=paketler" class="nav-link <?= $active_tab == 'paketler' ? 'active' : '' ?>"><i class="fa fa-th-large"></i> Paketler</a><?php endif; ?>
        <?php if(yetkiVarMi('kategoriler')): ?><a href="?tab=kategoriler" class="nav-link <?= $active_tab == 'kategoriler' ? 'active' : '' ?>"><i class="fa fa-tags"></i> Kategoriler</a><?php endif; ?>
        <?php if(yetkiVarMi('seanslar')): ?><a href="?tab=seanslar" class="nav-link <?= $active_tab == 'seanslar' ? 'active' : '' ?>"><i class="fa fa-clock"></i> Seanslar</a><?php endif; ?>
        <?php if(yetkiVarMi('formlar')): ?><a href="?tab=formlar" class="nav-link <?= $active_tab == 'formlar' ? 'active' : '' ?>"><i class="fa fa-file-alt"></i> Bilgi Formları</a><?php endif; ?>
        
        <div class="nav-title mt-2">Sistem</div>
        <?php if(yetkiVarMi('calisanlar')): ?><a href="?tab=calisanlar" class="nav-link <?= $active_tab == 'calisanlar' ? 'active' : '' ?>"><i class="fa fa-user-tie"></i> Personel</a><?php endif; ?>
        <?php if(yetkiVarMi('ayarlar')): ?><a href="?tab=ayarlar" class="nav-link <?= $active_tab == 'ayarlar' ? 'active' : '' ?>"><i class="fa fa-cogs"></i> Ayarlar</a><?php endif; ?>
        
        <a href="?cikis=1" class="nav-link text-danger mt-3"><i class="fa fa-power-off"></i> Çıkış Yap</a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <h5 class="m-0 fw-bold text-dark">
             <?php 
                $basliklar = ['ozet'=>'Genel Bakış', 'formlar'=>'Bilgi Formları', 'finans'=>'Finans Yönetimi'];
                echo $basliklar[$active_tab] ?? ucfirst($active_tab);
             ?>
        </h5>
        <div>
            <a href="index.php" target="_blank" class="btn btn-light btn-sm fw-bold text-primary shadow-sm"><i class="fa fa-globe"></i> Siteyi Gör</a>
        </div>
    </div>

    <?php
    if (file_exists($dosya_yolu)) {
        include $dosya_yolu;
    } else {
        echo "<div class='alert alert-warning shadow-sm'>Bu modül henüz hazırlanmadı veya dosya bulunamadı: <strong>$dosya_yolu</strong></div>";
    }
    ?>
</div>

<div class="modal fade" id="randevuDetayModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white py-2">
        <h5 class="modal-title">Randevu Detayı</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <ul class="nav nav-tabs nav-justified px-3 pt-2" id="randevuTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active fw-bold" data-bs-target="#genel" data-bs-toggle="tab">Genel Bilgiler</button></li>
            <li class="nav-item"><button class="nav-link fw-bold" data-bs-target="#adisyon" data-bs-toggle="tab">Adisyon</button></li>
            <li class="nav-item"><button class="nav-link fw-bold" data-bs-target="#tahsilat" data-bs-toggle="tab">Tahsilatlar</button></li>
            <li class="nav-item"><button class="nav-link fw-bold" data-bs-target="#islem_gecmisi" data-bs-toggle="tab">Geçmiş</button></li>
            <li class="nav-item"><button class="nav-link fw-bold" data-bs-target="#sms_gecmisi" data-bs-toggle="tab">SMS</button></li>
        </ul>

        <div class="tab-content p-4">
            <div class="tab-pane fade show active" id="genel">
                <table class="table table-bordered">
                    <tr><td class="fw-bold bg-light" width="200">Randevu No</td><td id="d_id"></td></tr>
                    <tr><td class="fw-bold bg-light">Tarih/Saat</td><td id="d_randevu_tarihi" class="fw-bold"></td></tr>
                    <tr><td class="fw-bold bg-light">Müşteri</td><td id="d_adsoyad"></td></tr>
                    <tr><td class="fw-bold bg-light">Telefon</td><td id="d_telefon"></td></tr>
                    <tr><td class="fw-bold bg-light">Personel</td><td id="d_personel"></td></tr>
                    <tr><td class="fw-bold bg-light">Hizmet</td><td id="d_hizmet"></td></tr>
                    <tr><td class="fw-bold bg-light">Toplam</td><td id="d_tutar" class="fs-5 fw-bold text-dark"></td></tr>
                    <tr><td class="fw-bold bg-light">Durum</td><td id="d_durum"></td></tr>
                </table>
                <div id="sozlesmeAlani"></div>
                <div class="d-flex gap-2 mt-3 flex-wrap">
                    <button class="btn btn-warning text-white flex-fill" onclick="durumGuncelle('bekliyor')">BEKLİYOR</button>
                    <button class="btn btn-primary flex-fill" onclick="durumGuncelle('geldi')">GELDİ</button>
                    <button class="btn btn-secondary flex-fill" onclick="durumGuncelle('gelmedi')">GELMEDİ</button>
                    <button class="btn btn-danger flex-fill" onclick="durumGuncelle('iptal')">İPTAL</button>
                    <button class="btn btn-dark flex-fill" onclick="window.print()"><i class="fa fa-print"></i></button>
                    <button class="btn btn-outline-danger flex-fill" onclick="randevuSil()"><i class="fa fa-trash"></i></button>
                    <button id="btnDuzenle" class="btn btn-info text-white flex-fill" onclick="randevuDuzenleAc()">DÜZENLE</button>
                    <button id="btnKaydet" class="btn btn-success flex-fill" onclick="randevuGuncelle()" style="display:none;">KAYDET</button>
                </div>
                <div id="durumMesaji" class="mt-2"></div>
            </div>

            <div class="tab-pane fade" id="adisyon">
                <div class="d-flex gap-2 mb-3">
                    <button class="btn btn-primary w-50" onclick="modalAc('hizmet')">+ HİZMET</button>
                    <button class="btn btn-warning text-white w-50" onclick="modalAc('urun')">+ ÜRÜN</button>
                </div>
                <table class="table table-striped border">
                    <thead class="bg-light"><tr><th>Tür</th><th>Adı</th><th>Adet</th><th>Fiyat</th><th>Tutar</th><th>Sil</th></tr></thead>
                    <tbody id="adisyonListesi"></tbody>
                    <tfoot class="fw-bold bg-light"><tr><td colspan="4" class="text-end">TOPLAM:</td><td class="text-end text-success fs-5" id="adisyonGenelToplam">0.00 ₺</td><td></td></tr></tfoot>
                </table>
            </div>

            <div class="tab-pane fade" id="tahsilat">
                <div id="tahsilatListesi" class="mb-3"></div>
                <button class="btn btn-success w-100 py-3 fw-bold fs-5" onclick="odemeModalAc()">ÖDEME AL</button>
            </div>

            <div class="tab-pane fade" id="islem_gecmisi"><table class="table table-hover border"><thead class="bg-light"><tr><th>Tarih</th><th>Personel</th><th>İşlem</th></tr></thead><tbody id="logListesi"></tbody></table></div>
            <div class="tab-pane fade" id="sms_gecmisi"><table class="table table-hover border"><thead class="bg-light"><tr><th>Tarih</th><th>Mesaj</th></tr></thead><tbody id="smsListesi"></tbody></table></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalHizmetEkle" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2"><h6 class="m-0">Hizmet Ekle</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="small fw-bold">Hizmet</label><select id="hizmet_secim" class="form-select form-select-sm" onchange="fiyatGetir('hizmet')"><option value="">Seçiniz</option><?php foreach($tum_hizmetler as $h): ?><option value="<?= $h['id'] ?>" data-fiyat="<?= $h['fiyat'] ?>"><?= htmlspecialchars($h['hizmet_adi']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-2"><label class="small fw-bold">Personel</label><select id="hizmet_personel" class="form-select form-select-sm"><option value="">Seçiniz</option><?php foreach($calisanlar as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['ad_soyad']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="small fw-bold">Fiyat</label><input type="number" id="hizmet_fiyat" class="form-control form-control-sm"></div>
                <button class="btn btn-primary w-100 btn-sm" onclick="adisyonKaydet('hizmet')">Ekle</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUrunEkle" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark py-2"><h6 class="m-0">Ürün Ekle</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="small fw-bold">Ürün</label><select id="urun_secim" class="form-select form-select-sm" onchange="fiyatGetir('urun')"><option value="">Seçiniz</option><?php foreach($urunler_modal as $u): ?><option value="<?= $u['id'] ?>" data-fiyat="<?= $u['fiyat'] ?>"><?= htmlspecialchars($u['urun_adi']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-2"><label class="small fw-bold">Personel</label><select id="urun_personel" class="form-select form-select-sm"><option value="">Seçiniz</option><?php foreach($calisanlar as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['ad_soyad']) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="small fw-bold">Fiyat</label><input type="number" id="urun_fiyat" class="form-control form-control-sm"></div>
                <button class="btn btn-warning w-100 btn-sm" onclick="adisyonKaydet('urun')">Ekle</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalHizliOdeme" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white py-2"><h6 class="m-0">Ödeme Al</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="hizli_odeme_musteri_id"><input type="hidden" id="hizli_odeme_randevu_id">
                <div class="mb-2"><label class="small fw-bold">Tutar</label><input type="number" id="hizli_odeme_tutar" class="form-control fw-bold text-success"></div>
                <div class="mb-3"><label class="small fw-bold">Yöntem</label><select id="hizli_odeme_turu" class="form-select"><option value="nakit">Nakit</option><option value="kredi_karti">Kredi Kartı</option><option value="havale">Havale</option></select></div>
                <button class="btn btn-success w-100 fw-bold" onclick="odemeyiTamamla()">ONAYLA</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// --- RANDEVU DETAY İŞLEMLERİ ---
function randevuDetayAc(id) {
    var modal = new bootstrap.Modal(document.getElementById('randevuDetayModal'));
    modal.show();
    
    $('#d_id').text(id); 
    duzenlemeModuKapat(); // Butonları resetle
    $('#d_adsoyad').html('<i class="fa fa-spinner fa-spin"></i> Yükleniyor...');
    
    $.ajax({
        url: 'ajax_randevu_detay.php',
        type: 'POST',
        data: { randevu_id: id },
        dataType: 'json',
        success: function(res) {
            if(res.error) { alert(res.error); return; }
            var r = res.detay;
            var s = res.sozlesme; 

            $('#d_id').text(r.id);
            var tamTarih = new Date(r.randevu_tarihi).toLocaleDateString('tr-TR') + ' ' + r.randevu_saati.substring(0, 5);
            
            $('#d_randevu_tarihi').text(tamTarih)
                .data('raw-date', r.randevu_tarihi)
                .data('raw-time', r.randevu_saati);
            
            $('#d_adsoyad').text(r.musteri_final_ad);
            $('#d_telefon').text(r.musteri_final_telefon);
            $('#d_personel').text(r.personel_ad);
            $('#d_hizmet').text(r.hizmet_final_adi);
            $('#d_tutar').text(parseFloat(r.fiyat || 0).toFixed(2) + ' ₺');
            $('#d_durum').html(durumBadge(r.durum));
            
            $('#hizli_odeme_musteri_id').val(r.musteri_id);
            $('#hizli_odeme_randevu_id').val(r.id);

            renderSozlesme(s);
            renderAdisyon(res.adisyon);
            renderTahsilat(res.tahsilatlar);
            renderLogs(res.logs);
            renderSMS(res.sms_logs);
        }
    });
}

function durumBadge(durum) {
    if(durum == 'geldi') return '<span class="badge bg-success">GELDİ</span>';
    if(durum == 'gelmedi') return '<span class="badge bg-secondary">GELMEDİ</span>';
    if(durum == 'iptal') return '<span class="badge bg-danger">İPTAL</span>';
    return '<span class="badge bg-warning text-dark">BEKLİYOR</span>';
}

function renderSozlesme(s) {
    var html = '';
    if (s && s.gerekli) {
        var fullLink = window.location.origin + window.location.pathname.replace('admin.php', '') + s.link;
        if (s.durum === 'imzalandi') {
            html = `<div class="alert alert-success mt-2 p-2 small"><i class="fa fa-file-signature"></i> <strong>${s.form_adi} İmzalandı</strong> (${s.imza_tarihi}) <a href="${s.link}" target="_blank" class="fw-bold">Gör</a></div>`;
        } else {
            html = `<div class="alert alert-warning mt-2 p-2 small"><div class="d-flex justify-content-between"><span><i class="fa fa-exclamation-triangle"></i> <strong>${s.form_adi} Bekleniyor</strong></span><button onclick="panoyaKopyala('${fullLink}')" class="btn btn-sm btn-light py-0">Linki Kopyala</button></div></div>`;
        }
    }
    $('#sozlesmeAlani').html(html);
}

function panoyaKopyala(text) {
    navigator.clipboard.writeText(text).then(() => Swal.fire('Kopyalandı', 'Link kopyalandı.', 'success'));
}

function randevuDuzenleAc() {
    var rawDate = $('#d_randevu_tarihi').data('raw-date');
    var rawTime = $('#d_randevu_tarihi').data('raw-time');
    $('#d_randevu_tarihi').html(`<div class="d-flex gap-2"><input type="date" id="edit_tarih" class="form-control form-control-sm" value="${rawDate}"><input type="time" id="edit_saat" class="form-control form-control-sm" value="${rawTime}"></div>`);
    $('#btnDuzenle').hide(); $('#btnKaydet').show();
}

function duzenlemeModuKapat() {
    $('#btnDuzenle').show(); $('#btnKaydet').hide();
}

function randevuGuncelle() {
    var id = $('#d_id').text();
    var t = $('#edit_tarih').val();
    var s = $('#edit_saat').val();
    if(!t || !s) { alert("Tarih/Saat seçiniz."); return; }
    
    $.post('ajax_randevu_guncelle.php', { randevu_id: id, tarih: t, saat: s }, function(res) {
        if(res.status === 'success') { alert("Güncellendi!"); randevuDetayAc(id); }
        else { alert("Hata: " + res.message); }
    }, 'json');
}

// --- TABLO RENDER FONKSİYONLARI ---
function renderAdisyon(liste) {
    var html = '', total = 0;
    if (liste && liste.length > 0) {
        liste.forEach(function(item) {
            var sum = parseFloat(item.toplam); total += sum;
            var b = item.tur === 'hizmet' ? '<span class="badge bg-primary me-1">H</span>' : '<span class="badge bg-warning text-dark me-1">Ü</span>';
            html += `<tr><td>${b} ${item.oge_adi}</td><td class="text-center">${item.adet}</td><td class="text-end">${parseFloat(item.fiyat).toFixed(2)}</td><td class="text-end fw-bold">${sum.toFixed(2)}</td><td class="text-center"><button class="btn btn-sm btn-outline-danger py-0" onclick="adisyonSil(${item.id})">&times;</button></td></tr>`;
        });
    } else { html = '<tr><td colspan="6" class="text-center text-muted">Adisyon boş.</td></tr>'; }
    $('#adisyonListesi').html(html);
    $('#adisyonGenelToplam').text(total.toFixed(2) + ' ₺');
    $('#d_tutar').text(total.toFixed(2) + ' ₺');
}

function renderTahsilat(liste) {
    var html = '';
    if(liste && liste.length > 0) {
        html = '<table class="table table-bordered table-sm"><thead><tr class="bg-light"><th>Tarih</th><th>Açıklama</th><th>Tutar</th></tr></thead><tbody>';
        liste.forEach(function(t){ html += `<tr><td>${new Date(t.tarih).toLocaleDateString()}</td><td>${t.aciklama}</td><td class="text-success fw-bold">+${parseFloat(t.tutar).toFixed(2)} ₺</td></tr>`; });
        html += '</tbody></table>';
    } else { html = '<div class="alert alert-warning text-center small">Tahsilat yok.</div>'; }
    $('#tahsilatListesi').html(html);
}

function renderLogs(liste) {
    var html = '';
    if(liste && liste.length > 0) liste.forEach(l => html += `<tr><td>${l.tarih}</td><td>${l.personel_ad || 'Sistem'}</td><td>${l.islem}</td></tr>`);
    else html = '<tr><td colspan="3" class="text-center text-muted">Kayıt yok.</td></tr>';
    $('#logListesi').html(html);
}

function renderSMS(liste) {
    var html = '';
    if(liste && liste.length > 0) liste.forEach(s => html += `<tr><td style="white-space:nowrap;">${s.tarih}</td><td>${s.mesaj}</td></tr>`);
    else html = '<tr><td colspan="2" class="text-center text-muted">SMS yok.</td></tr>';
    $('#smsListesi').html(html);
}

// --- İŞLEM FONKSİYONLARI ---
function modalAc(tur) {
    var mId = (tur === 'hizmet') ? 'modalHizmetEkle' : 'modalUrunEkle';
    var modal = new bootstrap.Modal(document.getElementById(mId));
    $('#'+tur+'_secim').val(''); $('#'+tur+'_fiyat').val('');
    modal.show();
}

function fiyatGetir(tur) {
    $('#'+tur+'_fiyat').val($('#'+tur+'_secim option:selected').data('fiyat') || 0);
}

function adisyonKaydet(tur) {
    var rId = $('#d_id').text();
    var oId = $('#'+tur+'_secim').val();
    var fy = $('#'+tur+'_fiyat').val();
    if(!oId) { alert("Seçim yapınız."); return; }
    
    $.post('ajax_adisyon_islem.php', { islem: 'ekle', randevu_id: rId, tur: tur, oge_id: oId, ozel_fiyat: fy }, function(res){
        var r = JSON.parse(res);
        if(r.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById((tur === 'hizmet') ? 'modalHizmetEkle' : 'modalUrunEkle')).hide();
            renderAdisyon(r.liste);
        } else { alert(r.error); }
    });
}

function adisyonSil(id) {
    if(confirm('Silinsin mi?')) {
        $.post('ajax_adisyon_islem.php', { islem: 'sil', randevu_id: $('#d_id').text(), adisyon_id: id }, function(res){
            var r = JSON.parse(res);
            if(r.status === 'success') renderAdisyon(r.liste);
        });
    }
}

function durumGuncelle(durum) {
    if(durum === 'iptal' && !confirm("İptal edilsin mi?")) return;
    $.post('ajax_randevu_durum.php', { randevu_id: $('#d_id').text(), durum: durum }, function(res){
        var r = JSON.parse(res);
        if(r.status === 'success') {
            $('#durumMesaji').html('<div class="alert alert-success py-1 mt-2">'+r.message+'</div>');
            setTimeout(() => location.reload(), 1000);
        } else { alert(r.message); }
    });
}

function randevuSil() {
    if(confirm('Randevu tamamen silinecek!')) window.location.href = 'admin.php?tab=randevular&randevu_sil_id=' + $('#d_id').text();
}

function odemeModalAc() {
    $('#hizli_odeme_tutar').val($('#adisyonGenelToplam').text().replace(' ₺','').trim());
    new bootstrap.Modal(document.getElementById('modalHizliOdeme')).show();
}

function odemeyiTamamla() {
    $.post('ajax_odeme_yap.php', { 
        musteri_id: $('#hizli_odeme_musteri_id').val(), 
        randevu_id: $('#hizli_odeme_randevu_id').val(), 
        tutar: $('#hizli_odeme_tutar').val(), 
        odeme_turu: $('#hizli_odeme_turu').val() 
    }, function(res){
        var r = JSON.parse(res);
        if(r.status === 'success') { alert("Ödeme alındı!"); location.reload(); }
        else { alert(r.message); }
    });
}
</script>

<!-- Bildirim Sistemi -->
<div id="yeniRandevuBildirimi" class="toast position-fixed top-0 end-0 m-3" style="z-index: 9999; min-width: 350px;" role="alert">
    <div class="toast-header bg-warning text-dark">
        <i class="fa fa-bell me-2"></i>
        <strong class="me-auto">Yeni Randevu!</strong>
        <small class="text-muted" id="bildirimZaman">Şimdi</small>
        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
    </div>
    <div class="toast-body bg-white" id="bildirimIcerik"></div>
</div>

<audio id="bildirimSesi" preload="auto">
    <source src="https://notificationsounds.com/storage/sounds/file-sounds-1147-pristine.mp3" type="audio/mpeg">
</audio>

<script>
let bildirimAktif = true;

function bildirimKontrolEt() {
    if (!bildirimAktif) return;
    fetch("bildirim_kontrol.php")
        .then(r => r.json())
        .then(data => {
            if (data.success && data.yeni_randevu_var) {
                document.getElementById("bildirimSesi").play().catch(e => console.log("Ses hatası:", e));
                const toastEl = document.getElementById("yeniRandevuBildirimi");
                const toastBody = document.getElementById("bildirimIcerik");
                let mesaj = `<strong>${data.yeni_sayi} yeni randevu!</strong><br>`;
                data.randevular.forEach((r, i) => { if (i < 3) mesaj += `<small>• ${r}</small><br>`; });
                if (data.yeni_sayi > 3) mesaj += `<small class="text-muted">... ve ${data.yeni_sayi - 3} tane daha</small><br>`;
                mesaj += `<a href="?tab=onay_bekleyenler" class="btn btn-warning btn-sm w-100 mt-2">Hepsini Gör</a>`;
                toastBody.innerHTML = mesaj;
                new bootstrap.Toast(toastEl, {delay: 10000}).show();
                const badge = document.querySelector(".nav-link[href*=onay_bekleyenler] .badge");
                if (badge) badge.textContent = data.toplam_bekleyen;
                if ("Notification" in window && Notification.permission === "granted") {
                    new Notification("Yeni Randevu!", {body: `${data.yeni_sayi} yeni randevu onay bekliyor`, icon: "https://cdn-icons-png.flaticon.com/512/3652/3652191.png"});
                }
            }
        })
        .catch(error => console.error("Bildirim hatası:", error));
}
if ("Notification" in window && Notification.permission === "default") Notification.requestPermission();
setInterval(bildirimKontrolEt, 30000);
setTimeout(bildirimKontrolEt, 3000);
document.addEventListener("visibilitychange", () => { if (!document.hidden) bildirimKontrolEt(); });
</script>

<!-- ============================================================
     GLOBAL UX: Tom Select otomatik başlatma + Yardımcı araçlar
     ============================================================ -->
<script>
(function() {
    // ── 1. Tom Select: data-ts ile işaretlenmiş veya otomatik algılanan select'ler ──
    function tsInit(el, opts) {
        if (el._tomSelect) return; // zaten başlatılmış
        var base = {
            plugins: ['remove_button'],
            maxOptions: 200,
            placeholder: el.getAttribute('data-placeholder') || 'Seçiniz...',
            allowEmptyOption: true,
            render: {
                no_results: function() {
                    return '<div class="no-results" style="padding:8px 12px;color:#888;">Sonuç bulunamadı.</div>';
                }
            }
        };
        // Çoklu seçim değilse remove_button plugin kaldır
        if (!el.multiple) { base.plugins = []; }
        new TomSelect(el, Object.assign(base, opts || {}));
    }

    function initTomSelects(root) {
        root = root || document;
        // a) data-ts ile açıkça işaretlenenler
        root.querySelectorAll('select[data-ts]').forEach(function(el) {
            tsInit(el);
        });
        // b) 5'ten fazla seçeneği olan tüm form select'leri (modal + sayfa)
        root.querySelectorAll('select.form-select, select.form-control').forEach(function(el) {
            if (el._tomSelect) return;
            var count = el.options.length;
            if (count >= 6) tsInit(el);
        });
    }

    // Sayfa ilk yüklenince
    document.addEventListener('DOMContentLoaded', function() {
        initTomSelects();

        // Bootstrap modal açıldığında içindeki select'leri başlat
        document.addEventListener('shown.bs.modal', function(e) {
            setTimeout(function() { initTomSelects(e.target); }, 50);
        });

        // Bootstrap offcanvas açıldığında
        document.addEventListener('shown.bs.offcanvas', function(e) {
            setTimeout(function() { initTomSelects(e.target); }, 50);
        });

        // ── 2. Copy-to-clipboard (copy-text sınıfı) ──
        document.addEventListener('click', function(e) {
            var el = e.target.closest('.copy-text');
            if (!el) return;
            var text = el.textContent.trim();
            navigator.clipboard.writeText(text).then(function() {
                var orig = el.style.color;
                el.style.color = '#198754';
                setTimeout(function() { el.style.color = orig; }, 800);
            });
        });

        // ── 3. Bootstrap Tooltip'leri başlat ──
        var tooltipEls = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipEls.forEach(function(el) {
            new bootstrap.Tooltip(el);
        });

        // ── 4. Telefon numaralarını kopyalanabilir yap ──
        document.querySelectorAll('td a[href^="tel:"]').forEach(function(el) {
            el.classList.add('copy-text');
            el.setAttribute('title', 'Kopyalamak için tıkla');
            el.setAttribute('data-bs-toggle', 'tooltip');
        });

        // ── 5. Sayısal inputlarda negatif engelle ──
        document.querySelectorAll('input[type="number"]').forEach(function(el) {
            el.addEventListener('keydown', function(e) {
                if (e.key === '-') e.preventDefault();
            });
        });

        // ── 6. Form submit çift tıklama koruması ──
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function() {
                var btn = form.querySelector('button[type="submit"]');
                if (btn && !btn.dataset.noProtect) {
                    setTimeout(function() {
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> İşleniyor...';
                    }, 10);
                }
            });
        });
    });

    // Dinamik olarak eklenen içerikler için (AJAX sonrası)
    window.uxRefresh = function(root) { initTomSelects(root); };
})();
</script>

</body>
</html>