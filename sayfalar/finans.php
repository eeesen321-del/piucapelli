<?php
// sayfalar/finans.php - Finans Yönetimi ve Raporlama
if(!yetkiVarMi('finans')) { echo "Erişim yetkiniz yok."; exit; }

$baslangic = $_GET['baslangic'] ?? date('Y-m-d');
$bitis = $_GET['bitis'] ?? date('Y-m-d');
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="card card-custom">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <h5 class="m-0"><i class="fa fa-chart-pie text-primary"></i> Finansal Dashboard</h5>
        <div class="btn-group">
            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalGelirEkle">
                <i class="fa fa-plus"></i> Gelir Ekle
            </button>
            <button class="btn btn-sm btn-warning text-dark fw-bold" onclick="$('#modalPaketSatis').modal('show')">
                <i class="fa fa-box-open"></i> Paket Sat
            </button>
            <button class="btn btn-sm btn-primary" onclick="$('#modalTahsilatEkle').modal('show')"> 
                <i class="fa fa-hand-holding-usd"></i> Tahsilat
            </button>
            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalGiderEkle">
                <i class="fa fa-minus"></i> Gider Ekle
            </button>
        </div>
    </div>
    
    <div class="card-body">
        <div class="row mb-4 g-2 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold text-muted">Başlangıç Tarihi</label>
                <input type="date" id="baslangic_tarih" class="form-control" value="<?= $baslangic ?>">
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted">Bitiş Tarihi</label>
                <input type="date" id="bitis_tarih" class="form-control" value="<?= $bitis ?>">
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted">İşlem Türü</label>
                <select id="islem_turu_filtre" class="form-select">
                    <option value="">Tümü</option>
                    <option value="gelir">Gelirler</option>
                    <option value="gider">Giderler</option>
                    <option value="tahsilat">Tahsilatlar</option>
                    <option value="paket_satisi">Paket Satışları</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" onclick="filtreUygula()">
                    <i class="fa fa-filter"></i> Raporla
                </button>
            </div>
        </div>
        
        <div class="row mb-4 g-3">
            <div class="col-md-3">
                <div class="card bg-success text-white h-100 shadow-sm">
                    <div class="card-body text-center p-3">
                        <h6 class="text-uppercase small opacity-75"><i class="fa fa-arrow-up"></i> Toplam Gelir</h6>
                        <h3 id="toplam_gelir" class="fw-bold m-0">0.00 ₺</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white h-100 shadow-sm">
                    <div class="card-body text-center p-3">
                        <h6 class="text-uppercase small opacity-75"><i class="fa fa-arrow-down"></i> Toplam Gider</h6>
                        <h3 id="toplam_gider" class="fw-bold m-0">0.00 ₺</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-primary text-white h-100 shadow-sm">
                    <div class="card-body text-center p-3">
                        <h6 class="text-uppercase small opacity-75"><i class="fa fa-calculator"></i> Net Kâr</h6>
                        <h3 id="net_kar" class="fw-bold m-0">0.00 ₺</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark h-100 shadow-sm">
                    <div class="card-body text-center p-3">
                        <h6 class="text-uppercase small opacity-75"><i class="fa fa-chart-line"></i> Paket Satışı</h6>
                        <h3 id="paket_satis" class="fw-bold m-0">0.00 ₺</h3>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-responsive border rounded">
            <table class="table table-hover table-striped m-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Tarih</th>
                        <th>İşlem Türü</th>
                        <th>Kategori</th>
                        <th>Açıklama</th>
                        <th>Ödeme</th>
                        <th class="text-end">Tutar</th>
                        <th class="text-center" width="50">İşlem</th>
                    </tr>
                </thead>
                <tbody id="finansTablosu"></tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="5" class="text-end text-muted">GENEL TOPLAM:</td>
                        <td class="text-end" id="tablo_toplam">0.00 ₺</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">Gelir / Gider Dağılımı</div>
                    <div class="card-body">
                        <canvas id="gelirGiderChart" style="max-height: 250px;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold">Gider Kategorileri</div>
                    <div class="card-body">
                        <canvas id="giderKategoriChart" style="max-height: 250px;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGelirEkle">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Yeni Gelir Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="gelirEkleForm">
                    <div class="mb-3"><label>Tarih</label><input type="datetime-local" name="tarih" class="form-control" value="<?= date('Y-m-d\TH:i') ?>"></div>
                    <div class="mb-3">
                        <label>Kategori</label>
                        <select name="kategori" class="form-select">
                            <option value="Hizmet Satışı">Hizmet Satışı</option>
                            <option value="Ürün Satışı">Ürün Satışı</option>
                            <option value="Randevu Geliri">Randevu Geliri</option>
                            <option value="Diğer Gelirler">Diğer Gelirler</option>
                        </select>
                    </div>
                    <div class="mb-3"><label>Tutar (₺)</label><input type="number" step="0.01" name="tutar" class="form-control" required placeholder="0.00"></div>
                    <div class="mb-3">
                        <label>Ödeme Türü</label>
                        <select name="odeme_turu" class="form-select">
                            <option value="nakit">Nakit</option>
                            <option value="kredi_karti">Kredi Kartı</option>
                            <option value="havale">Havale</option>
                            <option value="diger">Diğer</option>
                        </select>
                    </div>
                    <div class="mb-3"><label>Açıklama</label><textarea name="aciklama" class="form-control" rows="2"></textarea></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-success fw-bold" onclick="finansIslemEkle('gelir')">Kaydet</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGiderEkle">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Yeni Gider Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="giderEkleForm">
                    <div class="mb-3"><label>Tarih</label><input type="datetime-local" name="tarih" class="form-control" value="<?= date('Y-m-d\TH:i') ?>"></div>
                    <div class="mb-3">
                        <label>Kategori</label>
                        <select name="kategori" class="form-select">
                            <option value="Personel Maaşları">Personel Maaşları</option>
                            <option value="Kira">Kira</option>
                            <option value="Elektrik/Su/Doğalgaz">Elektrik/Su/Doğalgaz</option>
                            <option value="Ürün Alımı">Ürün Alımı</option>
                            <option value="Temizlik Malzemeleri">Temizlik Malzemeleri</option>
                            <option value="Pazarlama">Pazarlama</option>
                            <option value="Bakım/Onarım">Bakım/Onarım</option>
                            <option value="Vergiler">Vergiler</option>
                            <option value="Diğer Giderler">Diğer Giderler</option>
                        </select>
                    </div>
                    <div class="mb-3"><label>Tutar (₺)</label><input type="number" step="0.01" name="tutar" class="form-control" required placeholder="0.00"></div>
                    <div class="mb-3">
                        <label>Ödeme Türü</label>
                        <select name="odeme_turu" class="form-select">
                            <option value="nakit">Nakit</option>
                            <option value="kredi_karti">Kredi Kartı</option>
                            <option value="havale">Havale</option>
                            <option value="diger">Diğer</option>
                        </select>
                    </div>
                    <div class="mb-3"><label>Açıklama</label><textarea name="aciklama" class="form-control" rows="2"></textarea></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-danger fw-bold" onclick="finansIslemEkle('gider')">Kaydet</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPaketSatis" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark"><i class="fa fa-box-open"></i> Paket Satışı Yap</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formPaketSatis">
                    <input type="hidden" name="islem" value="paket_satis_kaydet">
                    
                    <div class="mb-3 position-relative">
                        <label class="fw-bold">Müşteri Seçimi</label>
                        <input type="hidden" name="musteri_id" id="paket_secilen_musteri_id" required>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa fa-search"></i></span>
                            <input type="text" id="paket_musteri_arama" class="form-control" placeholder="Müşteri Ara..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#paketYeniMusteriAlan"><i class="fa fa-plus"></i></button>
                            <button class="btn btn-outline-danger" type="button" id="paket_btn_temizle" style="display:none;" onclick="paketMusteriTemizle()"><i class="fa fa-times"></i></button>
                        </div>
                        <div id="paket_arama_sonuclari" class="list-group position-absolute w-100 shadow" style="display:none; z-index: 1050; max-height: 200px; overflow-y: auto;"></div>
                        <div id="paket_secilen_bilgi" class="small text-success mt-1 fw-bold" style="display:none;"><i class="fa fa-check-circle"></i> Seçilen: <span id="paket_secilen_ad"></span></div>

                        <div class="collapse mt-2 p-3 bg-light border rounded shadow-sm" id="paketYeniMusteriAlan" style="position: absolute; z-index: 1060; width: 100%;">
                            <label class="small fw-bold text-muted mb-1">Yeni Müşteri Ekle</label>
                            <div class="mb-2">
                                <input type="text" id="yeni_musteri_ad" class="form-control form-control-sm mb-1" placeholder="Ad Soyad">
                                <input type="text" id="yeni_musteri_tel" class="form-control form-control-sm" placeholder="Telefon">
                            </div>
                            <button class="btn btn-success btn-sm w-100" type="button" onclick="paketHizliMusteriEkle()"><i class="fa fa-save"></i> Kaydet ve Seç</button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-bold">Paket Seçiniz</label>
                        <select name="paket_id" class="form-select" required>
                            <option value="">-- Paket Listesinden Seçiniz --</option>
                            <?php 
                            if(!isset($paketler)){
                                $paketler = $baglanti->query("SELECT * FROM paketler ORDER BY paket_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
                            }
                            foreach($paketler as $p): 
                            ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['paket_adi']) ?> 
                                    (<?= $p['seans_sayisi'] ?> Seans - <?= number_format($p['toplam_tutar'],2) ?> ₺)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="fw-bold">Personel Seçiniz</label>
                        <select name="personel_id" class="form-select" required>
                            <option value="">-- Personel Seç --</option>
                            <?php
                            // BURASI DÜZELTİLDİ: yoneticiler yerine calisanlar tablosu kullanıldı
                            $pers = $baglanti->query("SELECT * FROM calisanlar ORDER BY ad_soyad ASC")->fetchAll();
                            foreach($pers as $pr){
                                echo "<option value='".$pr['id']."'>".$pr['ad_soyad']."</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small fw-bold">İlk Seans Tarihi</label>
                            <input type="date" name="baslangic_tarihi" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small fw-bold">Seans Saati</label>
                            <input type="time" name="baslangic_saati" class="form-control" value="09:00" required>
                        </div>
                    </div>

                    <hr>
                    
                    <div class="mb-3 bg-light p-2 border rounded">
                        <label class="small fw-bold text-success">Peşinat / Ödeme</label>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text">Tutar:</span>
                            <input type="number" step="0.01" name="pesinat_tutari" class="form-control" placeholder="0.00">
                        </div>
                        <select name="odeme_turu" class="form-select form-select-sm">
                            <option value="nakit">Nakit</option>
                            <option value="kredi_karti">Kredi Kartı</option>
                            <option value="havale">Havale</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Not</label>
                        <textarea name="aciklama" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="p-2 rounded border" style="background:#f8f9fa;">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="otomatik_seans" id="fn_otomatik_seans" value="1" checked>
                            <label class="form-check-label fw-bold" for="fn_otomatik_seans">
                                Seansları Otomatik Oluştur
                            </label>
                        </div>
                        <div class="form-text text-muted mt-1" id="fn_seans_aciklama">
                            <i class="fa fa-calendar-check text-success me-1"></i>Tüm seanslar otomatik planlanacak.
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" onclick="paketSatisKaydet()" class="btn btn-warning fw-bold text-dark">Satışı Onayla</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTahsilatEkle" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa fa-hand-holding-usd"></i> Tahsilat Al</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 position-relative">
                    <label class="fw-bold text-muted small">Müşteri Ara</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-search"></i></span>
                        <input type="text" id="tahsilat_musteri_ara" class="form-control" placeholder="Ad Soyad..." autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" onclick="tahsilatTemizle()" style="display:none;" id="btn_tahsilat_temizle">X</button>
                    </div>
                    <div id="tahsilat_sonuc_listesi" class="list-group position-absolute w-100 shadow" style="z-index: 2000; display:none;"></div>
                </div>

                <div id="borc_bilgi_paneli" class="card bg-light mb-3 border-primary" style="display:none;">
                    <div class="card-body py-2">
                        <h6 class="text-primary fw-bold mb-2 border-bottom pb-1"><i class="fa fa-user"></i> <span id="secilen_musteri_adi"></span></h6>
                        <div class="row text-center">
                            <div class="col-4 border-end">
                                <small class="text-muted d-block">Toplam Borç</small>
                                <span class="fw-bold text-dark" id="txt_toplam_borc">0.00</span> ₺
                            </div>
                            <div class="col-4 border-end">
                                <small class="text-muted d-block">Ödenen</small>
                                <span class="fw-bold text-success" id="txt_odenen">0.00</span> ₺
                            </div>
                            <div class="col-4">
                                <small class="text-muted d-block">KALAN</small>
                                <span class="fw-bold text-danger" id="txt_kalan">0.00</span> ₺
                            </div>
                        </div>
                    </div>
                </div>

                <form id="formTahsilat">
                    <input type="hidden" name="islem" value="tahsilat_kaydet">
                    <input type="hidden" name="musteri_id" id="tahsilat_musteri_id">

                    <div class="mb-3">
                        <label class="fw-bold small">Tahsil Edilen Tutar</label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="tutar" id="tahsilat_tutar" class="form-control fw-bold text-success fs-5" placeholder="0.00" required disabled>
                            <span class="input-group-text">₺</span>
                        </div>
                        <div class="form-text text-muted" id="tahsilat_uyari">Önce müşteri seçmelisiniz.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small">Ödeme Yöntemi</label>
                            <select name="odeme_turu" class="form-select">
                                <option value="nakit">Nakit</option>
                                <option value="kredi_karti">Kredi Kartı</option>
                                <option value="havale">Havale</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small">Açıklama</label>
                            <input type="text" name="aciklama" class="form-control" placeholder="Taksit ödemesi vb.">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary fw-bold" onclick="tahsilatYap()" id="btn_tahsilat_kaydet" disabled>
                    <i class="fa fa-check"></i> Tahsilatı Onayla
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    finansVerileriniYukle();

    // 1. TAHSİLAT MÜŞTERİ ARAMA
    $('#tahsilat_musteri_ara').on('keyup', function() {
        var term = $(this).val().trim();
        var box = $('#tahsilat_sonuc_listesi');

        if (term.length < 2) {
            box.hide();
            return;
        }

        $.post('ajax_musteri_islem.php', { islem: 'ara', term: term }, function(data) {
            var html = '';
            if (data.length > 0) {
                data.forEach(function(m) {
                    html += `<button type="button" class="list-group-item list-group-item-action" 
                             style="cursor:pointer;"
                             onclick="tahsilatMusteriSec(${m.id}, '${m.ad_soyad}')">
                             <i class="fa fa-user me-2 text-primary"></i> <strong>${m.ad_soyad}</strong> 
                             <small class="text-muted ms-2">${m.telefon}</small>
                             </button>`;
                });
            } else {
                html = '<div class="list-group-item text-muted text-center">Müşteri bulunamadı.</div>';
            }
            
            box.html(html).css({
                'display': 'block',
                'position': 'absolute',
                'z-index': '9999',
                'width': '100%',
                'max-height': '200px',
                'overflow-y': 'auto'
            }).show();
            
        }, 'json');
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#tahsilat_musteri_ara, #tahsilat_sonuc_listesi').length) {
            $('#tahsilat_sonuc_listesi').hide();
        }
    });

    // 2. PAKET SATIŞ MÜŞTERİ ARAMA
    $('#paket_musteri_arama').on('keyup', function() {
        var term = $(this).val().trim();
        var resultBox = $('#paket_arama_sonuclari');

        if (term.length < 2) {
            resultBox.hide();
            return;
        }

        $.post('ajax_musteri_islem.php', { islem: 'ara', term: term }, function(data) {
            var html = '';
            if (data.length > 0) {
                data.forEach(function(m) {
                    html += `<button type="button" class="list-group-item list-group-item-action" 
                             onclick="paketMusteriSec(${m.id}, '${m.ad_soyad}', '${m.telefon}')">
                             <strong>${m.ad_soyad}</strong> <small class="text-muted">(${m.telefon})</small>
                             </button>`;
                });
            } else {
                html = '<div class="list-group-item text-muted small">Kayıt bulunamadı. (+) ile ekleyebilirsiniz.</div>';
            }
            resultBox.html(html).show();
        }, 'json');
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#paket_musteri_arama, #paket_arama_sonuclari, #paketYeniMusteriAlan').length) {
            $('#paket_arama_sonuclari').hide();
        }
    });
});

function filtreUygula() {
    finansVerileriniYukle();
}

function finansVerileriniYukle() {
    var baslangic = $('#baslangic_tarih').val();
    var bitis = $('#bitis_tarih').val();
    var islem_turu = $('#islem_turu_filtre').val();
    
    $.ajax({
        url: 'ajax_finans_rapor.php',
        type: 'GET',
        data: { baslangic: baslangic, bitis: bitis, islem_turu: islem_turu },
        dataType: 'json',
        success: function(response) {
            if(response.status === 'success') {
                $('#toplam_gelir').text(response.toplam_gelir.toFixed(2) + ' ₺');
                $('#toplam_gider').text(response.toplam_gider.toFixed(2) + ' ₺');
                $('#net_kar').text(response.net_kar.toFixed(2) + ' ₺');
                $('#paket_satis').text(response.paket_satis.toFixed(2) + ' ₺');
                
                var html = '';
                var toplam = 0;
                
                if(response.kayitlar.length === 0) {
                    html = '<tr><td colspan="7" class="text-center py-3 text-muted">Kayıt bulunamadı.</td></tr>';
                } else {
                    response.kayitlar.forEach(function(kayit) {
                        var renk = '';
                        var icon = '';
                        var islem_badge = '';
                        
                        if(kayit.islem_turu === 'gelir' || kayit.islem_turu === 'tahsilat' || kayit.islem_turu === 'paket_satisi') {
                            renk = 'text-success';
                            icon = '<i class="fa fa-arrow-up text-success me-1"></i>';
                            islem_badge = '<span class="badge bg-success">GELİR</span>';
                            toplam += parseFloat(kayit.tutar);
                        } else {
                            renk = 'text-danger';
                            icon = '<i class="fa fa-arrow-down text-danger me-1"></i>';
                            islem_badge = '<span class="badge bg-danger">GİDER</span>';
                            toplam -= parseFloat(kayit.tutar);
                        }
                        
                        html += `
                        <tr>
                            <td>${kayit.tarih}</td>
                            <td>${islem_badge}</td>
                            <td>${kayit.kategori}</td>
                            <td><small>${kayit.aciklama}</small></td>
                            <td><span class="badge bg-light text-dark border">${kayit.odeme_turu.toUpperCase()}</span></td>
                            <td class="text-end fw-bold ${renk}">${icon}${parseFloat(kayit.tutar).toFixed(2)} ₺</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-danger" onclick="finansIslemSil(${kayit.id})"><i class="fa fa-trash"></i></button>
                            </td>
                        </tr>`;
                    });
                }
                $('#finansTablosu').html(html);
                $('#tablo_toplam').text(toplam.toFixed(2) + ' ₺');
                grafikleriCiz(response.grafik_verileri);
            }
        }
    });
}

function finansIslemEkle(tip) {
    var formId = tip === 'gelir' ? '#gelirEkleForm' : '#giderEkleForm';
    var formData = $(formId).serialize();
    formData += '&islem_turu=' + (tip === 'gelir' ? 'gelir' : 'gider');
    
    $.ajax({
        url: 'ajax_finans_ekle.php?islem=ekle',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if(response.status === 'success') {
                Swal.fire('Başarılı', 'İşlem kaydedildi.', 'success');
                $('#' + (tip === 'gelir' ? 'modalGelirEkle' : 'modalGiderEkle')).modal('hide');
                finansVerileriniYukle();
                $(formId)[0].reset(); 
            } else {
                Swal.fire('Hata', response.message, 'error');
            }
        }
    });
}

function finansIslemSil(id) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Kayıt silinecek!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sil',
        cancelButtonText: 'İptal',
        confirmButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax_finans_ekle.php?islem=sil',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if(response.status === 'success') {
                        Swal.fire('Silindi!', 'Kayıt silindi.', 'success');
                        finansVerileriniYukle();
                    }
                }
            });
        }
    });
}

function grafikleriCiz(veri) {
    var ctx1 = document.getElementById('gelirGiderChart').getContext('2d');
    if (window.myPieChart) window.myPieChart.destroy();
    window.myPieChart = new Chart(ctx1, {
        type: 'doughnut',
        data: {
            labels: ['Gelirler', 'Giderler'],
            datasets: [{
                data: [veri.toplam_gelir, veri.toplam_gider],
                backgroundColor: ['#198754', '#dc3545'],
                borderWidth: 1
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
    
    var ctx2 = document.getElementById('giderKategoriChart').getContext('2d');
    if (window.myBarChart) window.myBarChart.destroy();
    window.myBarChart = new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: veri.gider_kategorileri,
            datasets: [{
                label: 'Gider Tutarı (₺)',
                data: veri.gider_degerleri,
                backgroundColor: '#dc3545',
                borderRadius: 4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
    });
}

function paketMusteriSec(id, ad, telefon) {
    $('#paket_secilen_musteri_id').val(id);
    $('#paket_musteri_arama').val(ad).prop('disabled', true);
    $('#paket_arama_sonuclari').hide();
    $('#paketYeniMusteriAlan').collapse('hide'); 
    $('#paket_secilen_ad').text(ad + " (" + telefon + ")");
    $('#paket_secilen_bilgi').show();
    $('#paket_btn_temizle').show();
}

function paketMusteriTemizle() {
    $('#paket_secilen_musteri_id').val('');
    $('#paket_musteri_arama').val('').prop('disabled', false).focus();
    $('#paket_secilen_bilgi').hide();
    $('#paket_btn_temizle').hide();
}

function paketHizliMusteriEkle() {
    var ad = $('#yeni_musteri_ad').val().trim();
    var tel = $('#yeni_musteri_tel').val().trim();
    if(ad === "" || tel === "") {
        Swal.fire('Hata', 'Ad Soyad ve Telefon giriniz.', 'warning');
        return;
    }
    $.post('ajax_musteri_islem.php', { islem: 'ekle', ad_soyad: ad, telefon: tel }, function(res) {
        if (res.status === 'success') {
            paketMusteriSec(res.musteri.id, res.musteri.ad_soyad, res.musteri.telefon);
            $('#yeni_musteri_ad').val('');
            $('#yeni_musteri_tel').val('');
            Swal.fire({ icon: 'success', title: 'Müşteri Eklendi', timer: 1500, showConfirmButton: false });
        } else { Swal.fire('Hata', res.message, 'error'); }
    }, 'json');
}

function paketSatisKaydet() {
    if($('#paket_secilen_musteri_id').val() == "") {
        Swal.fire('Uyarı', 'Müşteri seçiniz.', 'warning');
        return;
    }
    var formData = $('#formPaketSatis').serialize();
    // Checkbox unchecked olduğunda serialize etmez, elle ekle
    if (!$('#fn_otomatik_seans').is(':checked')) {
        formData += '&otomatik_seans=0';
    }
    var btn = $('button[onclick="paketSatisKaydet()"]');
    var orjinalMetin = btn.text();
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> İşleniyor...');

    $.ajax({
        url: 'ajax_finans_ekle.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            btn.prop('disabled', false).text(orjinalMetin);
            if(response.status === 'success') {
                Swal.fire({ title: 'Başarılı!', text: response.message, icon: 'success', timer: 1500, showConfirmButton: false });
                $('#modalPaketSatis').modal('hide');
                finansVerileriniYukle(); 
                $('#formPaketSatis')[0].reset();
                paketMusteriTemizle();
            } else { Swal.fire('Hata!', response.message, 'error'); }
        },
        error: function(xhr, status, error) {
            btn.prop('disabled', false).text(orjinalMetin);
            console.error(xhr.responseText);
            Swal.fire({ title: 'Sistem Hatası', text: 'Hata oluştu.', icon: 'error' });
        }
    });
}

function tahsilatMusteriSec(id, ad) {
    $('#tahsilat_musteri_ara').val(ad).prop('disabled', true);
    $('#tahsilat_sonuc_listesi').hide();
    $('#btn_tahsilat_temizle').show();
    $('#tahsilat_musteri_id').val(id);
    $('#secilen_musteri_adi').text(ad);
    $('#tahsilat_tutar').prop('disabled', false).focus();
    $('#btn_tahsilat_kaydet').prop('disabled', false);
    $('#tahsilat_uyari').text('Tutar giriniz.');

    $.post('ajax_finans_ekle.php', { islem: 'borc_sorgula', musteri_id: id }, function(res) {
        if(res.status === 'success') {
            $('#borc_bilgi_paneli').slideDown();
            $('#txt_toplam_borc').text(res.toplam_borc);
            $('#txt_odenen').text(res.toplam_odenen);
            $('#txt_kalan').text(res.kalan_borc);
            $('#tahsilat_tutar').attr('placeholder', 'Kalan: ' + res.kalan_borc);
        }
    }, 'json');
}

function tahsilatTemizle() {
    $('#tahsilat_musteri_ara').val('').prop('disabled', false).focus();
    $('#tahsilat_musteri_id').val('');
    $('#borc_bilgi_paneli').slideUp();
    $('#btn_tahsilat_temizle').hide();
    $('#tahsilat_tutar').val('').prop('disabled', true);
    $('#btn_tahsilat_kaydet').prop('disabled', true);
    $('#tahsilat_uyari').text('Önce müşteri seçmelisiniz.');
}

function tahsilatYap() {
    var formData = $('#formTahsilat').serialize();
    $.ajax({
        url: 'ajax_finans_ekle.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if(response.status === 'success') {
                Swal.fire({ icon: 'success', title: 'Tahsilat Alındı', timer: 1500, showConfirmButton: false });
                $('#modalTahsilatEkle').modal('hide');
                finansVerileriniYukle();
                $('#formTahsilat')[0].reset();
                tahsilatTemizle();
            } else { Swal.fire('Hata', response.message, 'error'); }
        }
    });
}

// Otomatik seans checkbox açıklama
$(document).on('change', '#fn_otomatik_seans', function() {
    var el = document.getElementById('fn_seans_aciklama');
    if (this.checked) {
        el.innerHTML = '<i class="fa fa-calendar-check text-success me-1"></i>Tüm seanslar otomatik planlanacak.';
    } else {
        el.innerHTML = '<i class="fa fa-hand-pointer text-warning me-1"></i>Sadece paket kaydedilecek. Seansları kendiniz oluşturabilirsiniz.';
    }
});
</script>