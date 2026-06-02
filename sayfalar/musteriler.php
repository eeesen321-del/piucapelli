<?php
// --- İŞLEMLER ---

// 1. Müşteri Ekleme
if (isset($_POST['musteri_ekle'])) {
    $ad     = trim($_POST['ad_soyad']);
    $tel    = trim($_POST['telefon']);
    $eposta = trim($_POST['eposta']);
    if ($ad) {
        $baglanti->prepare("INSERT INTO musteriler (ad_soyad, telefon, eposta) VALUES (?, ?, ?)")
                 ->execute([$ad, $tel, $eposta]);
    }
    header("Location: admin.php?tab=musteriler"); 
    exit;
}

// 2. Müşteri Silme
if (isset($_GET['musteri_sil_id'])) {
    $id = $_GET['musteri_sil_id'];
    $baglanti->prepare("DELETE FROM musteriler WHERE id = ?")->execute([$id]);
    header("Location: admin.php?tab=musteriler"); 
    exit;
}

// --- VERİ ÇEKME ---
$arama_terimi = $_GET['arama'] ?? '';

$sql = "
SELECT m.*,
    (SELECT COUNT(*) FROM randevular WHERE musteri_id = m.id) as randevu_sayisi,
    (
        COALESCE((SELECT SUM(fiyat) FROM randevular WHERE musteri_id = m.id), 0) + 
        COALESCE((SELECT SUM(toplam_tutar) FROM satislar WHERE musteri_id = m.id), 0) + 
        COALESCE((SELECT SUM(ucret) FROM musteri_paketleri WHERE musteri_id = m.id), 0)
    ) as toplam_borc,
    COALESCE((SELECT SUM(tutar) FROM kasa_hareketleri WHERE musteri_id = m.id AND islem_turu IN ('tahsilat', 'gelir', 'paket_satisi')), 0) as toplam_odenen
FROM musteriler m 
WHERE m.ad_soyad LIKE ? OR m.telefon LIKE ? 
ORDER BY m.id DESC
";
$sorgu = $baglanti->prepare($sql);
$sorgu->execute(['%'.$arama_terimi.'%', '%'.$arama_terimi.'%']);
$musteriler = $sorgu->fetchAll(PDO::FETCH_ASSOC);

$genel_toplam_borc = 0;
$genel_toplam_odenen = 0;
foreach($musteriler as $m) {
    $genel_toplam_borc += $m['toplam_borc'];
    $genel_toplam_odenen += $m['toplam_odenen'];
}
$genel_kalan_bakiye = $genel_toplam_borc - $genel_toplam_odenen;
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#musteriEkleModal">
        <i class="fa fa-user-plus"></i> Yeni Müşteri
    </button>
    <div class="bg-white p-2 rounded shadow-sm border">
        <span class="fw-bold text-muted me-2">Genel Durum:</span>
        <span class="text-primary fw-bold">Borç: <?= number_format($genel_toplam_borc, 2) ?> ₺</span>
        <span class="mx-2 text-muted">|</span>
        <span class="text-success fw-bold">Ödenen: <?= number_format($genel_toplam_odenen, 2) ?> ₺</span>
        <span class="mx-2 text-muted">|</span>
        <span class="text-danger fw-bold">Kalan: <?= number_format($genel_kalan_bakiye, 2) ?> ₺</span>
    </div>
</div>

<div class="card card-custom">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span class="card-title"><i class="fa fa-users text-primary"></i> Müşteri Listesi</span>
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="tab" value="musteriler">
            <input type="text" name="arama" class="form-control form-control-sm" placeholder="Ad veya Telefon Ara..." value="<?= htmlspecialchars($arama_terimi) ?>">
            <button type="submit" class="btn btn-sm btn-secondary"><i class="fa fa-search"></i></button>
            <?php if($arama_terimi): ?>
                <a href="admin.php?tab=musteriler" class="btn btn-sm btn-danger"><i class="fa fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped m-0 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>Ad Soyad</th>
                        <th>Telefon</th>
                        <th class="text-center">Randevu</th>
                        <th class="text-end">Toplam Tutar</th>
                        <th class="text-end">Ödenen</th>
                        <th class="text-end">Kalan Ödeme</th>
                        <th class="text-end">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($musteriler)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Kayıt bulunamadı.</td></tr>
                    <?php endif; ?>
                    <?php foreach($musteriler as $m): 
                        $bakiye = $m['toplam_borc'] - $m['toplam_odenen']; 
                        $bakiyeClass = $bakiye > 0 ? 'text-danger' : 'text-success';
                    ?>
                    <tr style="cursor:pointer;" onclick="musteriDetayAc(<?= $m['id'] ?>)" title="Detay için tıklayın">
                        <td class="fw-bold">
                            <i class="fa fa-user-circle text-secondary me-1"></i>
                            <?= htmlspecialchars($m['ad_soyad']) ?>
                        </td>
                        <td>
                            <a href="tel:<?= formatTel($m['telefon']) ?>" class="text-decoration-none text-dark" onclick="event.stopPropagation()">
                                <?= formatTel($m['telefon']) ?>
                            </a>
                        </td>
                        <td class="text-center"><span class="badge bg-secondary"><?= $m['randevu_sayisi'] ?></span></td>
                        <td class="text-end text-primary fw-bold"><?= number_format($m['toplam_borc'], 2) ?> ₺</td>
                        <td class="text-end text-success fw-bold"><?= number_format($m['toplam_odenen'], 2) ?> ₺</td>
                        <td class="text-end fw-bold <?= $bakiyeClass ?>"><?= number_format($bakiye, 2) ?> ₺</td>
                        <td class="text-end" onclick="event.stopPropagation()">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="musteriDetayAc(<?= $m['id'] ?>)" title="Detay">
                                    <i class="fa fa-eye"></i>
                                </button>
                                <a href="?tab=musteriler&musteri_sil_id=<?= $m['id'] ?>" 
                                   class="btn btn-outline-danger" 
                                   onclick="return confirm('Bu müşteriyi silmek istediğinize emin misiniz?')" 
                                   title="Sil">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========== MÜŞTERİ DETAY OFFCANVAS ========== -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="musteriDetayOffcanvas" style="width:min(700px,100vw);">
    <div class="offcanvas-header text-white py-2 px-3" style="background:linear-gradient(135deg,#2c3e50,#3498db);">
        <div>
            <h5 class="offcanvas-title mb-0">
                <i class="fa fa-user-circle me-2"></i><span id="detay_musteri_adi">...</span>
            </h5>
            <small id="detay_musteri_tel" class="opacity-75"></small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="p-3 border-bottom" style="background:#f8f9fa;">
        <div class="row g-2">
            <div class="col-4">
                <div class="card text-center border-0 shadow-sm h-100">
                    <div class="card-body py-2 px-1">
                        <div class="fs-5 fw-bold text-primary" id="ozet_toplam_borc">-</div>
                        <div class="text-muted" style="font-size:11px;">Toplam Tutar</div>
                    </div>
                </div>
            </div>
            <div class="col-4">
                <div class="card text-center border-0 shadow-sm h-100">
                    <div class="card-body py-2 px-1">
                        <div class="fs-5 fw-bold text-success" id="ozet_odenen">-</div>
                        <div class="text-muted" style="font-size:11px;">Ödenen</div>
                    </div>
                </div>
            </div>
            <div class="col-4">
                <div class="card text-center border-0 shadow-sm h-100">
                    <div class="card-body py-2 px-1">
                        <div class="fs-5 fw-bold" id="ozet_kalan">-</div>
                        <div class="text-muted" style="font-size:11px;">Kalan Borç</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="px-3 pt-2" style="background:#fff; border-bottom:1px solid #dee2e6;">
        <ul class="nav nav-tabs border-0" id="musteriDetayTabs">
            <li class="nav-item">
                <button class="nav-link active px-3 py-2" onclick="detayTab('paketler')" id="tab_btn_paketler">
                    <i class="fa fa-box me-1 text-warning"></i>Paketler
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link px-3 py-2" onclick="detayTab('randevular')" id="tab_btn_randevular">
                    <i class="fa fa-calendar me-1 text-primary"></i>Randevular
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link px-3 py-2" onclick="detayTab('odemeler')" id="tab_btn_odemeler">
                    <i class="fa fa-money-bill me-1 text-success"></i>Ödemeler
                </button>
            </li>
        </ul>
    </div>

    <div class="offcanvas-body p-0">
        <div id="detay_yukleniyor" class="text-center py-5">
            <div class="spinner-border text-primary"></div>
            <p class="mt-2 text-muted">Yükleniyor...</p>
        </div>
        <div id="tab_paketler" class="p-3" style="display:none;"><div id="paketler_icerik"></div></div>
        <div id="tab_randevular" class="p-3" style="display:none;"><div id="randevular_icerik"></div></div>
        <div id="tab_odemeler" class="p-3" style="display:none;"><div id="odemeler_icerik"></div></div>
    </div>
</div>

<!-- Müşteri Ekleme Modal -->
<div class="modal fade" id="musteriEkleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fa fa-user-plus"></i> Yeni Müşteri Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Ad Soyad</label>
                        <input type="text" name="ad_soyad" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Telefon</label>
                        <input type="text" name="telefon" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">E-Posta (Opsiyonel)</label>
                        <input type="email" name="eposta" class="form-control">
                    </div>
                    <button type="submit" name="musteri_ekle" class="btn btn-success w-100 fw-bold py-2">
                        <i class="fa fa-save"></i> Kaydet
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
#musteriDetayOffcanvas .nav-link{color:#6c757d;border-bottom:2px solid transparent;border-radius:0;font-size:13px;font-weight:600;}
#musteriDetayOffcanvas .nav-link.active{color:#0d6efd;border-bottom:2px solid #0d6efd;background:transparent;}
.paket-kart{border-left:4px solid #f39c12;border-radius:8px;margin-bottom:12px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.07);}
.paket-kart.tamamlandi{border-left-color:#27ae60;}
.paket-kart.iptal{border-left-color:#e74c3c;}
.bos-mesaj{text-align:center;padding:30px;color:#aaa;}
.bos-mesaj i{font-size:2rem;display:block;margin-bottom:8px;}
</style>

<script>
function musteriDetayAc(id) {
    ['detay_musteri_adi','detay_musteri_tel','ozet_toplam_borc','ozet_odenen','ozet_kalan'].forEach(function(k){
        var el = document.getElementById(k);
        if(el) el.textContent = '...';
    });
    document.getElementById('detay_yukleniyor').style.display='block';
    ['paketler','randevular','odemeler'].forEach(function(t){
        document.getElementById('tab_'+t).style.display='none';
    });
    detayTab('paketler');
    new bootstrap.Offcanvas(document.getElementById('musteriDetayOffcanvas')).show();

    fetch('ajax_musteri_detay.php?musteri_id='+id)
        .then(function(r){return r.json();})
        .then(function(v){
            document.getElementById('detay_musteri_adi').textContent = v.musteri.ad_soyad;
            document.getElementById('detay_musteri_tel').textContent = v.musteri.telefon ? fmtTel(v.musteri.telefon) : '';
            document.getElementById('ozet_toplam_borc').textContent = fPara(v.ozet.toplam_borc)+' ₺';
            document.getElementById('ozet_odenen').textContent = fPara(v.ozet.odenen)+' ₺';
            var kalan = v.ozet.toplam_borc - v.ozet.odenen;
            var kalanEl = document.getElementById('ozet_kalan');
            kalanEl.textContent = fPara(kalan)+' ₺';
            kalanEl.className = 'fs-5 fw-bold '+(kalan>0?'text-danger':'text-success');
            renderPaketler(v.paketler, v.seanslar);
            renderRandevular(v.randevular);
            renderOdemeler(v.odemeler);
            document.getElementById('detay_yukleniyor').style.display='none';
            detayTab('paketler');
        })
        .catch(function(){
            document.getElementById('detay_yukleniyor').innerHTML='<p class="text-danger p-3">Veri yüklenemedi.</p>';
        });
}

function detayTab(s) {
    ['paketler','randevular','odemeler'].forEach(function(t){
        document.getElementById('tab_'+t).style.display='none';
        document.getElementById('tab_btn_'+t).classList.remove('active');
    });
    document.getElementById('tab_'+s).style.display='block';
    document.getElementById('tab_btn_'+s).classList.add('active');
}

function renderPaketler(paketler, seanslar) {
    var el=document.getElementById('paketler_icerik');
    if(!paketler||paketler.length===0){el.innerHTML='<div class="bos-mesaj"><i class="fa fa-box-open"></i>Paket kaydı yok</div>';return;}
    var html='';
    paketler.forEach(function(p){
        var dr=p.durum==='aktif'?'success':(p.durum==='tamamlandi'?'secondary':'danger');
        var dy=p.durum==='aktif'?'Aktif':(p.durum==='tamamlandi'?'Tamamlandı':'İptal');
        var ilerleme=p.toplam_seans>0?Math.round((p.kullanilan_seans/p.toplam_seans)*100):0;
        var ps=seanslar?seanslar.filter(function(s){return s.musteri_paket_id==p.id;}):[]; 
        html+='<div class="paket-kart '+p.durum+' p-3">';
        html+='<div class="d-flex justify-content-between align-items-start mb-2"><div>';
        html+='<span class="fw-bold fs-6">'+esc(p.paket_adi)+'</span>';
        html+='<span class="badge bg-'+dr+' ms-2" style="font-size:10px;">'+dy+'</span></div>';
        html+='<span class="fw-bold text-warning fs-6">'+fPara(p.ucret)+' ₺</span></div>';
        html+='<div class="d-flex gap-3 text-muted mb-2" style="font-size:12px;">';
        html+='<span><i class="fa fa-calendar-check me-1"></i>Satış: '+fTarih(p.satis_tarihi)+'</span>';
        html+='<span><i class="fa fa-layer-group me-1"></i>'+p.kullanilan_seans+'/'+p.toplam_seans+' Seans</span></div>';
        html+='<div class="progress mb-3" style="height:6px;"><div class="progress-bar bg-'+dr+'" style="width:'+ilerleme+'%"></div></div>';
        if(ps.length>0){
            html+='<div class="bg-light rounded p-2"><div class="fw-bold mb-1" style="font-size:12px;color:#555;"><i class="fa fa-list me-1"></i>Seans Takvimi</div>';
            html+='<table class="table table-sm m-0"><thead><tr style="font-size:11px;" class="text-muted"><th>#</th><th>Tarih</th><th>Saat</th><th>Durum</th></tr></thead><tbody>';
            ps.forEach(function(s){
                var sr=s.durum==='geldi'?'success':(s.durum==='gelmedi'?'danger':(s.durum==='iptal'?'secondary':'warning'));
                var sy=s.durum==='geldi'?'Geldi':(s.durum==='gelmedi'?'Gelmedi':(s.durum==='iptal'?'İptal':'Bekliyor'));
                html+='<tr><td><span class="badge bg-light text-dark border">'+s.kacinci_seans+'.</span></td>';
                html+='<td>'+fTarih(s.randevu_tarihi)+'</td><td>'+( s.randevu_saati||"-")+'</td>';
                html+='<td><span class="badge bg-'+sr+'" style="font-size:10px;">'+sy+'</span></td></tr>';
            });
            html+='</tbody></table></div>';
        }
        html+='</div>';
    });
    el.innerHTML=html;
}

function renderRandevular(randevular) {
    var el=document.getElementById('randevular_icerik');
    if(!randevular||randevular.length===0){el.innerHTML='<div class="bos-mesaj"><i class="fa fa-calendar-times"></i>Randevu kaydı yok</div>';return;}
    var html='<table class="table table-sm align-middle"><thead class="table-light"><tr style="font-size:12px;"><th>Hizmet</th><th>Tarih</th><th>Saat</th><th>Durum</th><th class="text-end">Fiyat</th></tr></thead><tbody>';
    randevular.forEach(function(r){
        var dr=r.durum==='geldi'?'success':(r.durum==='gelmedi'?'danger':(r.durum==='iptal'?'secondary':'warning'));
        var dy=r.durum==='geldi'?'Geldi':(r.durum==='gelmedi'?'Gelmedi':(r.durum==='iptal'?'İptal':'Bekliyor'));
        html+='<tr><td class="fw-bold" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+esc(r.hizmet_adi)+'">'+esc(r.hizmet_adi)+'</td>';
        html+='<td style="white-space:nowrap;">'+fTarih(r.randevu_tarihi)+'</td><td>'+r.randevu_saati+'</td>';
        html+='<td><span class="badge bg-'+dr+'" style="font-size:10px;">'+dy+'</span></td>';
        html+='<td class="text-end fw-bold text-primary">'+fPara(r.fiyat)+' ₺</td></tr>';
    });
    html+='</tbody></table>';
    el.innerHTML=html;
}

function renderOdemeler(odemeler) {
    var el=document.getElementById('odemeler_icerik');
    if(!odemeler||odemeler.length===0){el.innerHTML='<div class="bos-mesaj"><i class="fa fa-receipt"></i>Ödeme kaydı yok</div>';return;}
    var toplam=0;
    var html='<table class="table table-sm align-middle"><thead class="table-light"><tr style="font-size:12px;"><th>Açıklama</th><th>Tarih</th><th>Yöntem</th><th class="text-end">Tutar</th></tr></thead><tbody>';
    odemeler.forEach(function(o){
        toplam+=parseFloat(o.tutar||0);
        var icon=o.odeme_turu==='kart'?'fa-credit-card':(o.odeme_turu==='havale'?'fa-university':'fa-money-bill');
        html+='<tr><td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+esc(o.aciklama||'')+'">'+esc(o.aciklama||'-')+'</td>';
        html+='<td style="white-space:nowrap;">'+fTarihSaat(o.tarih)+'</td>';
        html+='<td><i class="fa '+icon+' me-1 text-muted"></i>'+esc(o.odeme_turu||'nakit')+'</td>';
        html+='<td class="text-end fw-bold text-success">'+fPara(o.tutar)+' ₺</td></tr>';
    });
    html+='</tbody><tfoot class="table-light fw-bold"><tr><td colspan="3">Toplam Ödenen</td><td class="text-end text-success">'+fPara(toplam)+' ₺</td></tr></tfoot></table>';
    el.innerHTML=html;
}

function fPara(n){return parseFloat(n||0).toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2});}
function fmtTel(t){if(!t)return'-';var d=t.replace(/[^0-9]/g,'');if(d.startsWith('90'))d=d.slice(2);if(d.startsWith('0'))d=d.slice(1);if(d.length===10)return'+90 '+d.slice(0,3)+' '+d.slice(3,6)+' '+d.slice(6,8)+' '+d.slice(8,10);return t;}
function fTarih(t){if(!t)return '-';var d=new Date(t);return d.toLocaleDateString('tr-TR',{day:'2-digit',month:'2-digit',year:'numeric'});}
function fTarihSaat(t){if(!t)return '-';var d=new Date(t);return d.toLocaleDateString('tr-TR',{day:'2-digit',month:'2-digit',year:'numeric'})+' '+d.toLocaleTimeString('tr-TR',{hour:'2-digit',minute:'2-digit'});}
function esc(s){if(!s)return '';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
</script>