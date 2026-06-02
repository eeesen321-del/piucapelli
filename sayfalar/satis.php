<?php
// ============================================
//  YARDIMCI FONKSİYONLAR (TEKRAR KULLANIM)
// ============================================

/**
 * Session tabanlı flash mesaj.
 */
function flashMesaj($tip, $mesaj) {
    $_SESSION['flash'] = ['tip' => $tip, 'mesaj' => $mesaj];
}

/**
 * Güvenli yönlendirme.
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Müşteri ID'sini doğrula, geçersizse NULL döndür.
 */
function dogrulaMusteriId($id) {
    if (empty($id)) return null;
    if (!is_numeric($id)) return null;
    return (int) $id;
}

/**
 * Sepet verisini doğrula ve dönüştür.
 */
function dogrulaSepet($json) {
    $sepet = json_decode($json, true);
    if (!is_array($sepet) || empty($sepet)) {
        return null;
    }
    // Her bir kalemin gerekli alanları içerdiğini kontrol et
    foreach ($sepet as $item) {
        if (!isset($item['id'], $item['adet'], $item['fiyat']) ||
            !is_numeric($item['id']) ||
            !is_numeric($item['adet']) ||
            !is_numeric($item['fiyat'])) {
            return null;
        }
    }
    return $sepet;
}

/**
 * Satış işlemini gerçekleştir.
 * @return bool|string Başarılı ise true, hata mesajı ise string
 */
function satisIsle($musteriId, $odemeYontemi, $sepet) {
    global $baglanti;

    // Stok kontrolü
    foreach ($sepet as $item) {
        $stmt = $baglanti->prepare("SELECT stok_adedi FROM urunler WHERE id = ?");
        $stmt->execute([$item['id']]);
        $stok = $stmt->fetchColumn();
        if ($stok === false) {
            return "Ürün bulunamadı (ID: {$item['id']})";
        }
        if ($stok < $item['adet']) {
            return "Yetersiz stok: {$item['ad']} (Kalan: $stok)";
        }
    }

    try {
        $baglanti->beginTransaction();

        // 1. Satış ana kaydı
        $toplamTutar = array_reduce($sepet, function($carry, $item) {
            return $carry + ($item['fiyat'] * $item['adet']);
        }, 0);

        $stmt = $baglanti->prepare("
            INSERT INTO satislar (musteri_id, toplam_tutar, odeme_yontemi, tarih)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$musteriId, $toplamTutar, $odemeYontemi]);
        $satisId = $baglanti->lastInsertId();

        // 2. Detaylar ve stok düşme
        $detayStmt = $baglanti->prepare("
            INSERT INTO satis_detaylari (satis_id, urun_id, adet, birim_fiyat, toplam_fiyat)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stokStmt = $baglanti->prepare("
            UPDATE urunler SET stok_adedi = stok_adedi - ? WHERE id = ?
        ");

        foreach ($sepet as $item) {
            $araToplam = $item['fiyat'] * $item['adet'];
            $detayStmt->execute([$satisId, $item['id'], $item['adet'], $item['fiyat'], $araToplam]);
            $stokStmt->execute([$item['adet'], $item['id']]);
        }

        // 3. Kasa hareketi
        $aciklama = count($sepet) . " kalem ürün satışı";
        $kasaStmt = $baglanti->prepare("
            INSERT INTO kasa_hareketleri (musteri_id, islem_turu, tutar, aciklama, odeme_turu, tarih)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        if ($musteriId && $odemeYontemi === 'veresiye') {
            $kasaStmt->execute([$musteriId, 'borc', $toplamTutar, $aciklama, 'veresiye']);
        } else {
            $kasaStmt->execute([$musteriId, 'tahsilat', $toplamTutar, $aciklama, $odemeYontemi]);
        }

        $baglanti->commit();
        return true;
    } catch (PDOException $e) {
        $baglanti->rollBack();
        return "Veritabanı hatası: " . $e->getMessage();
    }
}

// ============================================
//  POST İŞLEMLERİ
// ============================================

if (isset($_POST['satisi_tamamla'])) {
    $musteriId = dogrulaMusteriId($_POST['musteri_id'] ?? null);
    $odemeYontemi = $_POST['odeme_yontemi'] ?? '';
    $sepet = dogrulaSepet($_POST['sepet_data'] ?? '');

    if (!$sepet) {
        flashMesaj('danger', 'Sepet verisi geçersiz veya boş.');
        redirect("admin.php?tab=satis");
    }

    $sonuc = satisIsle($musteriId, $odemeYontemi, $sepet);
    if ($sonuc === true) {
        flashMesaj('success', 'Satış başarıyla tamamlandı.');
    } else {
        flashMesaj('danger', $sonuc);
    }
    redirect("admin.php?tab=satis");
}

// ============================================
//  VERİLERİ ÇEK
// ============================================

$urunler = $baglanti->query("
    SELECT id, urun_adi, fiyat, stok_adedi, barkod
    FROM urunler
    WHERE stok_adedi > 0
    ORDER BY urun_adi ASC
")->fetchAll(PDO::FETCH_ASSOC);

$musteriler = $baglanti->query("
    SELECT id, ad_soyad
    FROM musteriler
    ORDER BY ad_soyad ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- FLASH MESAJ GÖSTERİMİ -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?= $_SESSION['flash']['tip'] ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['flash']['mesaj']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<!-- Ürün listesini JavaScript'e aktar -->
<script>
    const urunListesi = <?= json_encode($urunler, JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="row">
    <!-- SOL KART: ÜRÜN EKLEME -->
    <div class="col-md-5">
        <div class="card card-custom h-100 border-0 shadow-sm">
            <div class="card-header bg-dark text-white py-3">
                <h5 class="card-title m-0"><i class="fa fa-barcode me-2"></i> Ürün Ekle</h5>
            </div>
            <div class="card-body bg-light">
                <div class="mb-4">
                    <label class="form-label fw-bold text-primary">Barkod Okut</label>
                    <div class="input-group input-group-lg shadow-sm">
                        <span class="input-group-text bg-white text-primary border-0"><i class="fa fa-qrcode fa-lg"></i></span>
                        <input type="text" id="barkodInput" class="form-control border-0 fw-bold" placeholder="Barkod tara..." autofocus autocomplete="off">
                    </div>
                    <div id="barkodUyari" class="mt-2 text-danger fw-bold small"></div>
                </div>

                <div class="text-center text-muted mb-3 fs-6">- VEYA -</div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Listeden Seç</label>
                    <select id="manuelUrunSec" class="form-select form-select-lg shadow-sm" onchange="manuelEkle(this)">
                        <option value="">Ürün Arayın...</option>
                        <?php foreach ($urunler as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= htmlspecialchars($u['urun_adi']) ?> - <?= number_format($u['fiyat'], 2) ?> ₺
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- SAĞ KART: SEPET -->
    <div class="col-md-7">
        <div class="card card-custom h-100 border-0 shadow-sm">
            <div class="card-header bg-success text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title m-0"><i class="fa fa-shopping-basket me-2"></i> Satış Sepeti</h5>
                <span class="badge bg-white text-success fs-6" id="sepetUrunSayisi">0 Ürün</span>
            </div>
            <div class="card-body p-0 d-flex flex-column">
                <div class="table-responsive flex-grow-1" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover m-0 align-middle">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th>Ürün Adı</th>
                                <th width="80" class="text-center">Adet</th>
                                <th class="text-end">Fiyat</th>
                                <th class="text-end">Tutar</th>
                                <th width="50"></th>
                            </tr>
                        </thead>
                        <tbody id="sepetListesi"></tbody>
                    </table>
                    <div id="bosSepetMesaji" class="text-center text-muted py-5">
                        <i class="fa fa-shopping-basket fa-3x mb-3 opacity-25"></i><br>Sepetiniz boş.
                    </div>
                </div>

                <div class="p-4 bg-light border-top mt-auto">
                    <form method="POST" id="satisFormu">
                        <input type="hidden" name="sepet_data" id="sepetData">

                        <div class="d-flex justify-content-between align-items-end mb-3">
                            <div><small class="text-muted">Toplam Tutar</small></div>
                            <h2 class="m-0 text-success fw-bold" id="toplamTutarGosterge">0.00 ₺</h2>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="small fw-bold text-muted">Müşteri (İsteğe Bağlı)</label>
                                <select name="musteri_id" class="form-select" data-ts data-placeholder="Müşteri ara...">
                                    <option value="">Seçiniz...</option>
                                    <?php foreach ($musteriler as $m): ?>
                                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['ad_soyad']) ?> – <?= htmlspecialchars($m['telefon']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-muted">Ödeme Yöntemi</label>
                                <select name="odeme_yontemi" class="form-select">
                                    <option value="nakit">Nakit</option>
                                    <option value="kredi_karti">Kredi Kartı</option>
                                    <option value="havale">Havale/EFT</option>
                                    <option value="veresiye">Veresiye (Borç Yaz)</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" name="satisi_tamamla" class="btn btn-success w-100 btn-lg mt-3 fw-bold shadow-sm" id="satisButonu" disabled>
                            <i class="fa fa-check-circle me-2"></i> SATIŞI ONAYLA
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
//  SEPET YÖNETİMİ (JAVASCRIPT)
// ============================================

let sepet = [];
const barkodInput = document.getElementById('barkodInput');
const sepetListesi = document.getElementById('sepetListesi');
const bosMesaj = document.getElementById('bosSepetMesaji');
const toplamGosterge = document.getElementById('toplamTutarGosterge');
const sepetDataInput = document.getElementById('sepetData');
const satisButonu = document.getElementById('satisButonu');
const sepetSayisiBadge = document.getElementById('sepetUrunSayisi');

// Ses (opsiyonel)
const ses = new Audio('bildirim.mp3');

// ------------------------------
// 1. BARKOD İŞLEME
// ------------------------------
barkodInput.addEventListener("keypress", function(event) {
    if (event.key === "Enter") {
        event.preventDefault();
        let kod = this.value.trim();
        if (kod !== "") {
            barkodAra(kod);
            this.value = "";
        }
    }
});

function barkodAra(kod) {
    $.post('ajax_barkod.php', { barkod: kod }, function(res) {
        if (res.status === 'success') {
            sepeteEkle(res.urun);
            barkodUyari('');
            // ses.play().catch(e=>{});
        } else {
            barkodUyari(res.message);
        }
    }, 'json').fail(function() {
        barkodUyari('Barkod sorgulama hatası!');
    });
}

function barkodUyari(mesaj) {
    document.getElementById('barkodUyari').innerText = mesaj;
}

// ------------------------------
// 2. MANUEL SEÇİM (Dropdown)
// ------------------------------
function manuelEkle(select) {
    let urunId = select.value;
    if (!urunId) return;

    // urunListesi'nden ürünü bul
    let urun = urunListesi.find(u => u.id == urunId);
    if (urun) {
        sepeteEkle(urun);
        select.value = ""; // seçimi sıfırla
    }
}

// ------------------------------
// 3. SEPET İŞLEMLERİ
// ------------------------------
function sepeteEkle(urun) {
    let varMi = sepet.find(i => i.id == urun.id);
    if (varMi) {
        varMi.adet++;
    } else {
        sepet.push({
            id: urun.id,
            ad: urun.urun_adi,
            fiyat: parseFloat(urun.fiyat),
            adet: 1
        });
    }
    sepetCiz();
}

function sepetCiz() {
    let html = '';
    let genelToplam = 0;
    let urunSayisi = 0;

    sepet.forEach((item, index) => {
        let satirToplam = item.fiyat * item.adet;
        genelToplam += satirToplam;
        urunSayisi += item.adet;

        html += `
        <tr>
            <td class="fw-bold">${item.ad}</td>
            <td class="text-center">
                <input type="number" class="form-control form-control-sm text-center p-1" value="${item.adet}" min="1" onchange="adetGuncelle(${index}, this.value)">
            </td>
            <td class="text-end">${item.fiyat.toFixed(2)} ₺</td>
            <td class="text-end fw-bold text-primary">${satirToplam.toFixed(2)} ₺</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger border-0 py-0" onclick="sepetSil(${index})"><i class="fa fa-times"></i></button>
            </td>
        </tr>`;
    });

    sepetListesi.innerHTML = html;
    toplamGosterge.innerText = genelToplam.toFixed(2) + ' ₺';
    sepetDataInput.value = JSON.stringify(sepet);
    sepetSayisiBadge.innerText = urunSayisi + ' Ürün';

    // Sepet boş/dolu kontrolü
    if (sepet.length > 0) {
        bosMesaj.style.display = 'none';
        satisButonu.disabled = false;
    } else {
        bosMesaj.style.display = 'block';
        satisButonu.disabled = true;
    }
}

function adetGuncelle(index, yeniAdet) {
    yeniAdet = parseInt(yeniAdet);
    if (isNaN(yeniAdet) || yeniAdet < 1) yeniAdet = 1;
    sepet[index].adet = yeniAdet;
    sepetCiz();
}

function sepetSil(index) {
    sepet.splice(index, 1);
    sepetCiz();
}

// ------------------------------
// 4. SAYFA YÜKLENDİĞİNDE
// ------------------------------
document.addEventListener('DOMContentLoaded', function() {
    // Barkod input'u odakla
    if (barkodInput) barkodInput.focus();

    // Sepeti temizle (varsayılan)
    sepet = [];
    sepetCiz();
});
</script>