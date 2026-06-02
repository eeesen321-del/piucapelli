<?php
// --- YARDIMCI FONKSİYONLAR (TEKRAR KULLANIM İÇİN) ---
function musteriBilgi($id) {
    global $baglanti;
    $stmt = $baglanti->prepare("SELECT ad_soyad, telefon FROM musteriler WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function hizmetBilgi($id) {
    global $baglanti;
    $stmt = $baglanti->prepare("SELECT hizmet_adi, sure_dk, fiyat FROM hizmetler WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function bitisSaatiHesapla($tarih, $saat, $dakika) {
    $dt = new DateTime("$tarih $saat");
    $dt->modify("+$dakika minutes");
    return $dt->format("H:i");
}

// --- 1. İŞLEM KAYITLARI (POST) ---

// A) Hızlı Randevu Ekleme (güvenli hale getirildi)
if (isset($_POST['hizli_randevu_ekle'])) {
    $m_id = $_POST['musteri_id'];
    $h_id = $_POST['hizmet_id'];
    $c_id = $_POST['calisan_id'];
    $tarih = $_POST['tarih'];
    $saat = $_POST['saat'];

    $m_bilgi = musteriBilgi($m_id);
    $h_bilgi = hizmetBilgi($h_id);

    if ($m_bilgi && $h_bilgi) {
        $bitis_saati = bitisSaatiHesapla($tarih, $saat, $h_bilgi['sure_dk']);

        $stmt = $baglanti->prepare("INSERT INTO randevular 
            (musteri_id, musteri_ad, telefon, hizmet_adi, randevu_tarihi, randevu_saati, bitis_saati, calisan_id, durum, fiyat) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'bekliyor', ?)");
        $stmt->execute([$m_id, $m_bilgi['ad_soyad'], $m_bilgi['telefon'], $h_bilgi['hizmet_adi'], $tarih, $saat, $bitis_saati, $c_id, $h_bilgi['fiyat']]);
    }
    header("Location: admin.php?tab=ajanda"); exit;
}

// B) Otomatik Randevulu Paket Satışı (güvenli + tekrar azaltıldı)
if (isset($_POST['paket_satis_yap'])) {
    $musteri_id = $_POST['musteri_id'];
    $paket_id = $_POST['paket_id'];
    $bas_tarih = $_POST['baslangic_tarihi'];
    $bas_saat = $_POST['baslangic_saati'];
    $calisan_id = $_POST['calisan_id'];
    $oda_id = !empty($_POST['oda_id']) ? $_POST['oda_id'] : null;

    // Paket bilgisi (prepared)
    $p_stmt = $baglanti->prepare("SELECT * FROM paketler WHERE id = ?");
    $p_stmt->execute([$paket_id]);
    $paket = $p_stmt->fetch(PDO::FETCH_ASSOC);

    // Müşteri bilgisi (fonksiyon kullan)
    $m_bilgi = musteriBilgi($musteri_id);

    if ($paket && $m_bilgi) {
        // 1. Müşteri paketi kaydı
        $mp_stmt = $baglanti->prepare("INSERT INTO musteri_paketleri (musteri_id, paket_id, toplam_seans, kullanilan_seans, ucret, durum) VALUES (?, ?, ?, 0, ?, 'aktif')");
        $mp_stmt->execute([$musteri_id, $paket_id, $paket['seans_sayisi'], $paket['toplam_tutar']]);
        $mp_id = $baglanti->lastInsertId();

        // 2. Kasa hareketi (borç)
        $kasa_stmt = $baglanti->prepare("INSERT INTO kasa_hareketleri (musteri_id, islem_turu, tutar, aciklama, odeme_turu, tarih) VALUES (?, 'borc', ?, 'Paket Satışı', 'veresiye', NOW())");
        $kasa_stmt->execute([$musteri_id, $paket['toplam_tutar']]);

        // 3. Otomatik seans (sadece checkbox işaretliyse)
        $otomatik_seans = isset($_POST['otomatik_seans']) && $_POST['otomatik_seans'] == '1';
        if ($otomatik_seans) {
            $aralik_gun = $paket['seans_araligi'] > 0 ? $paket['seans_araligi'] : 30;
            $sure_dk = 30;
            if (!empty($paket['hizmet_id'])) {
                $h_bilgi = hizmetBilgi($paket['hizmet_id']);
                if ($h_bilgi) $sure_dk = $h_bilgi['sure_dk'];
            }
            $musteri_ad  = $m_bilgi['ad_soyad'];
            $musteri_tel = $m_bilgi['telefon'];
            $seans_ekle   = $baglanti->prepare("INSERT INTO seanslar (musteri_paket_id, calisan_id, oda_id, randevu_tarihi, randevu_saati, kacinci_seans) VALUES (?, ?, ?, ?, ?, ?)");
            $randevu_ekle = $baglanti->prepare("INSERT INTO randevular (musteri_id, musteri_ad, telefon, hizmet_adi, randevu_tarihi, randevu_saati, bitis_saati, calisan_id, durum, fiyat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'bekliyor', 0)");
            for ($i = 0; $i < $paket['seans_sayisi']; $i++) {
                $hesap_tarih = date('Y-m-d', strtotime("$bas_tarih +" . ($i * $aralik_gun) . " days"));
                $bitis_saati = bitisSaatiHesapla($hesap_tarih, $bas_saat, $sure_dk);
                $kacinci = $i + 1;
                $hizmet_adi_text = $paket['paket_adi'] . " ($kacinci. Seans)";
                $seans_ekle->execute([$mp_id, $calisan_id, $oda_id, $hesap_tarih, $bas_saat, $kacinci]);
                $randevu_ekle->execute([$musteri_id, $musteri_ad, $musteri_tel, $hizmet_adi_text, $hesap_tarih, $bas_saat, $bitis_saati, $calisan_id]);
            }
        }
    }
    header("Location: admin.php?tab=ajanda"); exit;
}

// --- 2. VERİLERİ ÇEK (Sayfa açılışında) ---
$calisanlar = $baglanti->query("SELECT * FROM calisanlar ORDER BY ad_soyad ASC")->fetchAll(PDO::FETCH_ASSOC);
$hizmetler = $baglanti->query("SELECT * FROM hizmetler ORDER BY hizmet_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
$musteriler = $baglanti->query("SELECT * FROM musteriler ORDER BY ad_soyad ASC")->fetchAll(PDO::FETCH_ASSOC);
$paketler = $baglanti->query("SELECT * FROM paketler ORDER BY paket_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
$odalar = $baglanti->query("SELECT * FROM odalar ORDER BY oda_adi ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- 3. TAKVİM VERİLERİ (FullCalendar için) ---
$takvim_query = $baglanti->query("
    SELECT r.*, c.ad_soyad as calisan_ad 
    FROM randevular r 
    LEFT JOIN calisanlar c ON r.calisan_id = c.id 
    ORDER BY r.randevu_saati ASC
")->fetchAll(PDO::FETCH_ASSOC);

$takvim_verisi = [];
foreach($takvim_query as $tr) {
    $renk = '#f39c12'; 
    if($tr['durum'] == 'geldi') $renk = '#28a745';   
    elseif($tr['durum'] == 'gelmedi') $renk = '#6c757d'; 
    elseif($tr['durum'] == 'iptal') $renk = '#dc3545';   

    $takvim_verisi[] = [
        'id' => $tr['id'],
        'title' => $tr['musteri_ad'],
        'start' => $tr['randevu_tarihi'] . 'T' . $tr['randevu_saati'], 
        'extendedProps' => [
            'calisan' => $tr['calisan_ad'] ?? 'Personel Yok',
            'hizmet' => $tr['hizmet_adi'],
            'telefon' => $tr['telefon'],
            'saat_gorunum' => substr($tr['randevu_saati'], 0, 5),
            'renk_kodu' => $renk
        ],
        'backgroundColor' => 'transparent', 
        'borderColor' => 'transparent',
        'textColor' => '#ffffff'
    ];
}
$json_takvim = json_encode($takvim_verisi, JSON_UNESCAPED_UNICODE);
?>

<div class="card card-custom">
    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="card-title"><i class="fa fa-calendar-week text-primary"></i> Haftalık Ajanda</span>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-warning text-dark btn-sm" data-bs-toggle="modal" data-bs-target="#paketSatisModal">
                <i class="fa fa-box-open"></i> Paket Sat
            </button>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalTahsilatEkle">
                <i class="fa fa-hand-holding-usd"></i> Tahsilat Al
            </button>
        </div>
    </div>
    <div class="card-body p-3">
        <div id="calendar"></div>
    </div>
</div>

<!-- Modal: Hızlı Randevu Ekle -->
<div class="modal fade" id="modalRandevuEkle" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Hızlı Randevu Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="musteri_id" id="secilen_musteri_id" required>
                    <div class="mb-3 p-2 border rounded bg-light">
                        <label class="small fw-bold text-muted mb-2">Müşteri Seçimi / Yeni Ekleme</label>
                        <div class="row g-2">
                            <div class="col-md-7 position-relative">
                                <input type="text" id="musteri_arama_input" class="form-control" placeholder="Ad Soyad ile arayın..." autocomplete="off">
                                <div id="arama_sonuclari" class="list-group position-absolute w-100 shadow" style="display:none; z-index: 1000; max-height: 200px; overflow-y: auto;"></div>
                            </div>
                            <div class="col-md-5">
                                <div class="input-group">
                                    <input type="text" name="yeni_telefon" id="yeni_telefon_input" class="form-control" placeholder="05XX...">
                                    <button class="btn btn-success" type="button" onclick="hizliMusteriEkle()"><i class="fa fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                        <div id="secilen_bilgi" class="small text-success mt-1 fw-bold" style="display:none;">Seçilen: <span id="secilen_ad"></span></div>
                    </div>
                    <div class="mb-3">
                        <label>Hizmet</label>
                        <select name="hizmet_id" class="form-select" required>
                            <option value="">Seçiniz</option>
                            <?php foreach($hizmetler as $h): ?>
                                <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['hizmet_adi']) ?> (<?= $h['sure_dk'] ?> dk)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Personel</label>
                        <select name="calisan_id" class="form-select" required>
                            <option value="">Seçiniz</option>
                            <?php foreach($calisanlar as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['ad_soyad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label>Tarih</label>
                            <input type="date" name="tarih" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label>Saat</label>
                            <input type="time" name="saat" class="form-control" value="<?= date('H:00') ?>" required>
                        </div>
                    </div>
                    <button type="submit" name="hizli_randevu_ekle" class="btn btn-primary w-100">Randevuyu Oluştur</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Paket Satışı -->
<div class="modal fade" id="paketSatisModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark"><i class="fa fa-box-open"></i> Paket Satışı Yap</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formAjPaketSatis">

                    <!-- Müşteri Seçimi -->
                    <div class="mb-3 position-relative">
                        <label class="fw-bold">Müşteri Seçimi</label>
                        <input type="hidden" name="musteri_id" id="aj_musteri_id" required>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa fa-search"></i></span>
                            <input type="text" id="aj_musteri_arama" class="form-control" placeholder="Müşteri Ara..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#ajYeniMusteriAlan"><i class="fa fa-plus"></i></button>
                            <button class="btn btn-outline-danger" type="button" id="aj_btn_temizle" style="display:none;" onclick="ajMusteriTemizle()"><i class="fa fa-times"></i></button>
                        </div>
                        <div id="aj_arama_sonuclari" class="list-group position-absolute w-100 shadow" style="display:none; z-index:1050; max-height:200px; overflow-y:auto;"></div>
                        <div id="aj_secilen_bilgi" class="small text-success mt-1 fw-bold" style="display:none;"><i class="fa fa-check-circle"></i> Seçilen: <span id="aj_secilen_ad"></span></div>
                        <div class="collapse mt-2 p-3 bg-light border rounded shadow-sm" id="ajYeniMusteriAlan" style="position:absolute; z-index:1060; width:100%;">
                            <label class="small fw-bold text-muted mb-1">Yeni Müşteri Ekle</label>
                            <div class="mb-2">
                                <input type="text" id="aj_yeni_ad" class="form-control form-control-sm mb-1" placeholder="Ad Soyad">
                                <input type="text" id="aj_yeni_tel" class="form-control form-control-sm" placeholder="Telefon">
                            </div>
                            <button class="btn btn-success btn-sm w-100" type="button" onclick="ajHizliMusteriEkle()"><i class="fa fa-save"></i> Kaydet ve Seç</button>
                        </div>
                    </div>

                    <!-- Paket Seçimi -->
                    <div class="mb-3">
                        <label class="fw-bold">Paket Seçiniz</label>
                        <select name="paket_id" class="form-select" required>
                            <option value="">-- Paket Listesinden Seçiniz --</option>
                            <?php foreach($paketler as $p): ?>
                                <option value="<?= $p['id'] ?>">
                                    <?= htmlspecialchars($p['paket_adi']) ?>
                                    (<?= $p['seans_sayisi'] ?> Seans - <?= number_format($p['toplam_tutar'],2) ?> ₺)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Personel Seçimi -->
                    <div class="mb-3">
                        <label class="fw-bold">Personel Seçiniz</label>
                        <select name="calisan_id" class="form-select" required>
                            <option value="">-- Personel Seç --</option>
                            <?php foreach($calisanlar as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['ad_soyad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tarih / Saat -->
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="small fw-bold">İlk Seans Tarihi</label>
                            <input type="date" name="baslangic_tarihi" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="small fw-bold">Seans Saati</label>
                            <input type="time" name="baslangic_saati" class="form-control" value="09:00" required>
                        </div>
                    </div>

                    <hr>

                    <!-- Peşinat / Ödeme -->
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

                    <!-- Not -->
                    <div class="mb-3">
                        <label>Not</label>
                        <textarea name="aciklama" class="form-control" rows="2"></textarea>
                    </div>

                    <!-- Otomatik Seans -->
                    <div class="mb-3 p-2 rounded border" style="background:#f8f9fa;">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="otomatik_seans" id="aj_otomatik_seans" value="1" checked>
                            <label class="form-check-label fw-bold" for="aj_otomatik_seans">Seansları Otomatik Oluştur</label>
                        </div>
                        <div class="form-text text-muted mt-1" id="aj_seans_aciklama">
                            <i class="fa fa-calendar-check text-success me-1"></i>Tüm seanslar ve randevular otomatik planlanacak.
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" onclick="ajPaketSatisKaydet()" class="btn btn-warning fw-bold text-dark"><i class="fa fa-check"></i> Satışı Onayla</button>
            </div>
        </div>
    </div>
</div>

<script>
// FullCalendar ve müşteri arama fonksiyonları (burada mevcut kodunuz korunacak)
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridWeek',
        locale: 'tr',
        firstDay: 1,
        contentHeight: 'auto',
        eventDisplay: 'block',
        displayEventTime: false,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridWeek,dayGridDay'
        },
        buttonText: { today: 'Bugün', week: 'Hafta', day: 'Gün' },
        events: <?= $json_takvim ?>,
        eventContent: function(arg) {
            let saat = arg.event.extendedProps.saat_gorunum;
            let musteri = arg.event.title;
            let calisan = arg.event.extendedProps.calisan;
            let hizmet = arg.event.extendedProps.hizmet;
            let renk = arg.event.extendedProps.renk_kodu;
            return {
                html: `<div class="randevu-kutu" style="background-color: ${renk};">
                        <div class="r-saat"><i class="fa fa-clock"></i> ${saat}</div>
                        <div class="r-musteri">${musteri}</div>
                        <div class="r-personel"><i class="fa fa-user-tie"></i> ${calisan}</div>
                        <div class="r-hizmet">${hizmet}</div>
                    </div>`
            };
        },
        eventClick: function(info) {
            if (typeof randevuDetayAc === 'function') {
                randevuDetayAc(info.event.id);
            }
        }
    });
    calendar.render();
});

// Paket Satış Modalı - Müşteri Arama (finans stili)
$('#aj_musteri_arama').on('keyup', function() {
    var term = $(this).val().trim();
    var box = $('#aj_arama_sonuclari');
    if (term.length < 2) { box.hide(); return; }
    $.post('ajax_musteri_islem.php', { islem: 'ara', term: term }, function(data) {
        var html = '';
        if (data.length > 0) {
            data.forEach(function(m) {
                html += `<button type="button" class="list-group-item list-group-item-action"
                         onclick="ajMusteriSec(${m.id}, '${m.ad_soyad.replace(/'/g,"\\'")}', '${m.telefon}')">
                         <strong>${m.ad_soyad}</strong> <small class="text-muted">(${m.telefon})</small>
                         </button>`;
            });
        } else {
            html = '<div class="list-group-item text-muted small">Kayıt bulunamadı. (+) ile ekleyebilirsiniz.</div>';
        }
        box.html(html).show();
    }, 'json');
});

$(document).on('click', function(e) {
    if (!$(e.target).closest('#aj_musteri_arama, #aj_arama_sonuclari, #ajYeniMusteriAlan').length) {
        $('#aj_arama_sonuclari').hide();
    }
});

function ajMusteriSec(id, ad, telefon) {
    $('#aj_musteri_id').val(id);
    $('#aj_musteri_arama').val(ad).prop('disabled', true);
    $('#aj_arama_sonuclari').hide();
    $('#ajYeniMusteriAlan').collapse('hide');
    $('#aj_secilen_ad').text(ad + ' (' + telefon + ')');
    $('#aj_secilen_bilgi').show();
    $('#aj_btn_temizle').show();
}

function ajMusteriTemizle() {
    $('#aj_musteri_id').val('');
    $('#aj_musteri_arama').val('').prop('disabled', false).focus();
    $('#aj_secilen_bilgi').hide();
    $('#aj_btn_temizle').hide();
}

function ajHizliMusteriEkle() {
    var ad = $('#aj_yeni_ad').val().trim();
    var tel = $('#aj_yeni_tel').val().trim();
    if (ad === '' || tel === '') { Swal.fire('Hata', 'Ad Soyad ve Telefon giriniz.', 'warning'); return; }
    $.post('ajax_musteri_islem.php', { islem: 'ekle', ad_soyad: ad, telefon: tel }, function(res) {
        if (res.status === 'success') {
            ajMusteriSec(res.musteri.id, res.musteri.ad_soyad, res.musteri.telefon);
            $('#aj_yeni_ad').val(''); $('#aj_yeni_tel').val('');
            Swal.fire({ icon: 'success', title: 'Müşteri Eklendi', timer: 1500, showConfirmButton: false });
        } else { Swal.fire('Hata', res.message, 'error'); }
    }, 'json');
}

function ajPaketSatisKaydet() {
    if ($('#aj_musteri_id').val() == '') {
        Swal.fire('Uyarı', 'Müşteri seçiniz.', 'warning'); return;
    }
    var formData = $('#formAjPaketSatis').serialize();
    if (!$('#aj_otomatik_seans').is(':checked')) formData += '&otomatik_seans=0';
    formData += '&paket_satis_yap=1';
    var btn = $('button[onclick="ajPaketSatisKaydet()"]');
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> İşleniyor...');
    $.ajax({
        url: 'admin.php?tab=ajanda',
        type: 'POST',
        data: formData,
        success: function() {
            btn.prop('disabled', false).html('<i class="fa fa-check"></i> Satışı Onayla');
            Swal.fire({ title: 'Başarılı!', icon: 'success', timer: 1500, showConfirmButton: false })
                .then(function() { location.reload(); });
        },
        error: function() {
            btn.prop('disabled', false).html('<i class="fa fa-check"></i> Satışı Onayla');
            Swal.fire('Hata', 'Bir sorun oluştu.', 'error');
        }
    });
}

// Modal kapanınca sıfırla
document.getElementById('paketSatisModal').addEventListener('hidden.bs.modal', function() {
    ajMusteriTemizle();
    $('#ajYeniMusteriAlan').collapse('hide');
    $('#aj_yeni_ad').val(''); $('#aj_yeni_tel').val('');
});

// Otomatik seans checkbox açıklama
document.addEventListener('change', function(e) {
    if (e.target && e.target.id === 'aj_otomatik_seans') {
        var el = document.getElementById('aj_seans_aciklama');
        if (e.target.checked) {
            el.innerHTML = '<i class="fa fa-calendar-check text-success me-1"></i>Tüm seanslar ve randevular otomatik planlanacak.';
        } else {
            el.innerHTML = '<i class="fa fa-hand-pointer text-warning me-1"></i>Sadece paket kaydedilecek. Seansları kendiniz oluşturabilirsiniz.';
        }
    }
});

// Müşteri arama ve hızlı ekleme fonksiyonları (mevcut kodunuz buraya eklenecek)
// ...
</script>