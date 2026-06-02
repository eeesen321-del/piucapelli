<?php include 'db.php'; ?>
<?php
session_start();

// Sınıfları dahil et
require_once 'app/Controllers/Admin/DashboardController.php';
require_once 'app/Controllers/Admin/CalendarController.php';
require_once 'app/Controllers/Admin/CustomerController.php';
require_once 'app/Controllers/Admin/SettingsController.php';
require_once 'app/Controllers/FrontendController.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Müşteri Önyüzü Rotaları
if ($uri === '/' || $uri === '/index.php') {
    (new App\Controllers\FrontendController())->index();
} 

elseif ($uri === '/randevu-al') {
    (new App\Controllers\FrontendController())->store();
}
elseif ($uri === '/admin/calendar/create') {
    (new App\Controllers\Admin\CalendarController())->createAppointment();
}
elseif ($uri === '/admin/settings/pending-requests') {
    (new App\Controllers\Admin\SettingsController())->getPendingRequests();
}
elseif ($uri === '/admin/settings/handle-request') {
    (new App\Controllers\Admin\SettingsController())->handleRequest();
}
// Admin Paneli Rotaları
elseif ($uri === '/admin/dashboard') {
    (new App\Controllers\Admin\DashboardController())->index();
} 
elseif ($uri === '/admin/dashboard/stats') {
    (new App\Controllers\Admin\DashboardController())->getStats();
} 
elseif ($uri === '/admin/calendar') {
    (new App\Controllers\Admin\CalendarController())->index();
} 
elseif ($uri === '/admin/calendar/status') {
    (new App\Controllers\Admin\CalendarController())->updateStatus();
}
elseif ($uri === '/admin/calendar/events') {
    (new App\Controllers\Admin\CalendarController())->getEvents();
} 
elseif ($uri === '/admin/customers') {
    (new App\Controllers\Admin\CustomerController())->index();
} 
elseif ($uri === '/admin/customers/payment') {
    (new App\Controllers\Admin\CustomerController())->payment();
} 
elseif ($uri === '/admin/settings') {
    (new App\Controllers\Admin\SettingsController())->index();
} 
elseif ($uri === '/admin/settings/save') {
    (new App\Controllers\Admin\SettingsController())->save();
} 
else {
    http_response_code(404);
    echo "Sayfa bulunamadı.";
}
<?php
// --- VERİTABANI SORGULARI (TEK SEFERDE ÇEKİLİR) ---

// 1. Kategoriler
$kategoriler = $baglanti->query("SELECT * FROM kategoriler ORDER BY kategori_adi ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. Tüm Hizmetler
$tum_hizmetler = $baglanti->query("SELECT * FROM hizmetler ORDER BY kategori ASC, hizmet_adi ASC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Tüm Paketler
$tum_paketler = $baglanti->query("SELECT * FROM paketler ORDER BY kategori ASC, paket_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PiuCapelli Design | Çorlu Profesyonel Saç Tasarım ve Güzellik</title>
    <link rel="icon" type="image/png" href="favicon.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">
    
    <link rel="stylesheet" href="style.css">

    <style>
        /* --- SAYFA İÇİ ÖZEL STİLLER --- */
        html { scroll-behavior: smooth; }
        
        /* Wizard Input Stilleri */
        .wizard-input, .wizard-select {
            width: 100%; padding: 12px 15px; font-size: 15px; color: #333;
            background-color: #fff; border: 2px solid #eef0f3; border-radius: 12px;
            transition: all 0.3s ease; outline: none; font-family: 'Poppins', sans-serif;
            box-shadow: 0 4px 6px rgba(0,0,0,0.01); box-sizing: border-box;
        }
        .wizard-input:focus, .wizard-select:focus {
            border-color: #d4a373; box-shadow: 0 4px 15px rgba(212, 163, 115, 0.15);
        }
        .wizard-label {
            display: block; margin-bottom: 8px; font-weight: 600; color: #444; font-size: 14px; margin-left: 5px;
        }

        /* Takvim Özelleştirme */
        .flatpickr-calendar {
            margin: 0 auto; box-shadow: 0 10px 30px rgba(0,0,0,0.08) !important;
            border: 1px solid #f0f0f0 !important; border-radius: 15px !important;
        }
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, .flatpickr-day.selected.inRange, 
        .flatpickr-day.selected:focus, .flatpickr-day.selected:hover {
            background: #d4a373 !important; border-color: #d4a373 !important;
        }
        .flatpickr-day.today { border-color: #d4a373 !important; }

        /* Mobil Uyum */
        @media (max-width: 768px) {
            .wizard-wrapper { margin: 0 15px; }
            .step-item { font-size: 11px; }
            .step-circle { width: 30px; height: 30px; font-size: 12px; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">PiuCapelli<span>Design</span></div>
        <ul class="nav-links">
            <li><a href="#">Anasayfa</a></li>
            <li><a href="#hakkimizda">Hakkımızda</a></li> 
            <li><a href="#hizmetler">Hizmetlerimiz</a></li>
            <li><a href="#urunler">Ürünlerimiz</a></li>
            <li><a href="#iletisim">Bize Ulaşın</a></li> 
            <li><a href="#randevu-formu" class="btn-randevu">Randevu Al</a></li>
        </ul>
    </nav>

    <header class="hero">
        <div class="hero-content">
            <h1><span>Güzelliğinizi</span> Keşfedin</h1>
        </div>
    </header>

    <section id="hakkimizda" class="about-section">
        <div class="about-container">
            <div class="about-img">
                <img src="hakkimizda.jpeg" alt="PiuCapelli Design">
            </div>
            <div class="about-text">
                <span>Hikayemiz</span>
                <h2>PiuCapelli Design Hakkında</h2>
                <p>Piu Capelli Design, 2015 yılından bu yana kuaför ve güzellik alanında profesyonel hizmet sunmaktadır. Saç kesimi, saç boyama, ombre, bakım uygulamaları, lazer epilasyon ve güzellik hizmetlerinde kişiye özel çözümler sunarak müşteri memnuniyetini her zaman ön planda tutar.</p>
                <div class="about-stats">
                    <div class="stat-item"><strong>10+</strong><span>Yıllık Deneyim</span></div>
                    <div class="stat-item"><strong>2k+</strong><span>Mutlu Müşteri</span></div>
                </div>
            </div>
        </div>
    </section>

    <section id="hizmetler" class="services">
        <div class="section-title" style="text-align: center; margin-bottom: 40px;">
            <span style="color: #d4a373; text-transform: uppercase; letter-spacing: 2px;">Fiyat Listesi</span>
            <h2 style="font-family: 'Playfair Display', serif; font-size: 2.5rem; margin: 10px 0;">Hizmet Kataloğumuz</h2>
            <p>Profesyonel dokunuşlarımızla tanışın.</p>
        </div>

        <?php foreach ($kategoriler as $kat): ?>
        <div class="kategori-box">
            <h3 class="kategori-baslik">
                <?= htmlspecialchars($kat['kategori_adi']) ?>
                <span class="icon">+</span>
            </h3>
            <div class="hizmet-listesi">
                
                <?php 
                // Hizmetleri PHP ile filtreliyoruz (SQL sorgusu yerine)
                foreach ($tum_hizmetler as $h): 
                    if($h['kategori'] == $kat['kategori_adi']):
                ?>
                <div class="hizmet-satir" onclick="hizmetSecVeKaydir('hizmet', '<?= $h['id'] ?>')" style="cursor: pointer;">
                    <span class="hizmet-ad"><?= htmlspecialchars($h['hizmet_adi']) ?></span>
                    <span class="hizmet-fiyat"><?= number_format($h['fiyat'], 2, ',', '.') ?> ₺</span>
                </div>
                <?php 
                    endif;
                endforeach; 
                ?>

                <?php 
                // Paketleri PHP ile filtreliyoruz
                foreach ($tum_paketler as $kp): 
                    if($kp['kategori'] == $kat['kategori_adi']):
                ?>
                <div class="hizmet-satir" onclick="hizmetSecVeKaydir('paket', '<?= $kp['id'] ?>')" style="cursor: pointer;">
                    <span class="hizmet-ad">
                        <?= htmlspecialchars($kp['paket_adi']) ?>
                        <small style="color:#999; font-weight:400; font-size:0.8em;">(<?= $kp['seans_sayisi'] ?> Seans)</small>
                    </span>
                    <span class="hizmet-fiyat"><?= number_format($kp['toplam_tutar'], 2, ',', '.') ?> ₺</span>
                </div>
                <?php 
                    endif;
                endforeach; 
                ?>

            </div>
        </div>
        <?php endforeach; ?>
    </section>

    <section id="urunler" class="products-section">
        <div class="products-container">
            <div class="products-content">
                <span>Exclusive Koleksiyon</span>
                <h2>Ürünlerimiz</h2>
                <p>PiuCapelli Design olarak saç sağlığınız bizim için önceliktir. Bu salonda dünya markası olan <span>Keune</span> ve <span>LK Lisap Milano</span> profesyonel ürünleri kullanılmaktadır.</p>
                <a href="https://wa.me/905532541391" class="cta-button-gold">Ürünler Hakkında Bilgi Al</a>
            </div>
            <div class="products-visual">
                <img src="site.jpeg" alt="Keune ve Lisap Milano Ürünleri">
            </div>
        </div>
    </section>

    <section id="iletisim" class="contact-section">
        <div class="contact-container">
            <div class="contact-info">
                <h2>Bize Ulaşın</h2>
                <p>Sizi salonumuzda ağırlamaktan mutluluk duyarız.</p>
                <div class="info-item"><strong>Adres:</strong><p>Kazımiye Mah. Salih Omurtak Cd. No:86 Çorlu/Tekirdağ</p></div>
                <div class="info-item"><strong>Telefon:</strong><p>+90 553 254 13 91</p></div>
                <div class="info-item"><strong>Çalışma Saatleri:</strong><p>Pazartesi - Pazar: 08:30 - 20:00 <br> (Salı Günleri Kapalıyız)</p></div>
                <a href="#randevu-formu" class="btn-randevu">Hemen Randevu Al</a>
            </div>
            <div class="contact-map">
                <iframe width="100%" height="400" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?q=Kaz%C4%B1miye%20Mah.%20Salih%20Omurtak%20Cd.%20No%3A86%20%C3%87orlu%2FTekirda%C4%9F&t=&z=15&ie=UTF8&iwloc=B&output=embed"></iframe>
            </div>
        </div>
    </section>

    <section id="randevu-formu" style="padding: 80px 0; background-color: #f8f9fa;">
        <div class="section-title" style="text-align: center; margin-bottom: 30px;">
            <h2>Randevu Talebi Oluştur</h2>
            <p>4 Adımda kolayca randevunuzu planlayın</p>
        </div>

        <div class="wizard-wrapper">
            <div class="step-progress">
                <div class="step-item active" id="progress-1"><div class="step-circle">1</div>Hizmet</div>
                <div class="step-item" id="progress-2"><div class="step-circle">2</div>Uzman</div>
                <div class="step-item" id="progress-3"><div class="step-circle">3</div>Tarih</div>
                <div class="step-item" id="progress-4"><div class="step-circle">4</div>Onay</div>
            </div>

            <form action="kaydet.php" method="POST" id="wizardForm">
                <input type="hidden" name="islem_turu" id="input_islem_turu" value="hizmet">
                <input type="hidden" name="hizmet_id" id="input_hizmet_id" required>
                <input type="hidden" name="calisan_id" id="input_calisan_id" required>
                <input type="hidden" name="randevu_tarihi" id="input_randevu_tarihi" required>
                <input type="hidden" name="randevu_saati" id="input_randevu_saati" required>

                <div class="step-content active" id="step-1">
                    <h4 class="mb-3 text-center">Hangi işlemi yaptırmak istersiniz?</h4>
                    <div class="selection-grid">
                        
                        <?php foreach($tum_hizmetler as $h): ?>
                        <div class="select-card" data-type="hizmet" data-id="<?= $h['id'] ?>" onclick="selectService(this, 'hizmet', '<?= $h['id'] ?>', '<?= htmlspecialchars($h['hizmet_adi']) ?>', '<?= $h['fiyat'] ?>')">
                            <i class="fa fa-cut fa-2x mb-2" style="color:#d4a373;"></i>
                            <h4><?= htmlspecialchars($h['hizmet_adi']) ?></h4>
                            <span><?= number_format($h['fiyat'], 2) ?> ₺</span>
                        </div>
                        <?php endforeach; ?>

                        <?php foreach($tum_paketler as $p): ?>
                        <div class="select-card" data-type="paket" data-id="<?= $p['id'] ?>" onclick="selectService(this, 'paket', '<?= $p['id'] ?>', '<?= htmlspecialchars($p['paket_adi']) ?>', '<?= $p['toplam_tutar'] ?>')">
                            <i class="fa fa-box-open fa-2x mb-2" style="color:#d4a373;"></i>
                            <h4><?= htmlspecialchars($p['paket_adi']) ?></h4>
                            <span><?= number_format($p['toplam_tutar'], 2) ?> ₺</span>
                        </div>
                        <?php endforeach; ?>

                    </div>
                </div>

                <div class="step-content" id="step-2">
                    <h4 class="mb-3 text-center">Uzman Seçiniz</h4>
                    <div class="selection-grid" id="personel-grid">
                        <div class="text-center w-100 text-muted">Lütfen önce hizmet seçiniz.</div>
                    </div>
                </div>

                <div class="step-content" id="step-3">
                    <h4 class="mb-3 text-center">Zamanı Belirleyin</h4>
                    <div class="row">
                        <div class="col-md-12 mb-4 text-center">
                            <input type="text" id="inlineCalendar" placeholder="Tarih Seçiniz" readonly style="display:none;">
                        </div>
                        <div class="col-md-8 mx-auto">
                            <label class="wizard-label text-center">Randevu Saati:</label>
                            <select id="timeSelect" class="wizard-select" onchange="selectTime(this.value)">
                                <option value="">Önce yukarıdan tarih seçiniz...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="step-content" id="step-4">
                    <h4 class="mb-3 text-center">Son Adım: Bilgilerinizi Girin</h4>
                    <div class="card bg-light p-3 mb-3 border-0 rounded-3">
                        <strong class="d-block mb-2 text-primary">Randevu Özeti:</strong>
                        <div id="summary-text" class="small text-muted"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="wizard-label">Adınız Soyadınız</label>
                            <input type="text" name="musteri_ad" class="wizard-input" placeholder="Örn: Ahmet Yılmaz" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="wizard-label">Telefon Numaranız</label>
                            <input type="text" name="telefon" class="wizard-input" placeholder="Örn: 0555 123 45 67" required>
                        </div>
                    </div>
                    <button type="submit" class="cta-button-gold w-100 border-0 py-3 fw-bold shadow-sm" style="cursor: pointer;">RANDEVUYU ONAYLA</button>
                </div>
            </form>

            <div class="wizard-footer">
                <button type="button" class="btn-nav btn-prev" id="prevBtn" onclick="changeStep(-1)" disabled>Geri</button>
                <button type="button" class="btn-nav btn-next" id="nextBtn" onclick="changeStep(1)" disabled>İleri</button>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/tr.js"></script>
    <script>
    // --- WIZARD MANTIĞI ---
    let currentStep = 1;
    const totalSteps = 4;
    let selection = { type: 'hizmet', serviceId: null, serviceName: '', servicePrice: 0, staffId: null, staffName: '', date: '', time: '' };

    // --- TAKVİMİ BAŞLAT ---
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr("#inlineCalendar", {
            inline: true,
            locale: "tr",
            minDate: "today",
            dateFormat: "Y-m-d",
            disable: [
                function(date) { return (date.getDay() === 2); } // Salı kapalı
            ],
            onChange: function(selectedDates, dateStr) {
                selection.date = dateStr;
                document.getElementById('input_randevu_tarihi').value = dateStr;
                loadTimes(dateStr);
            }
        });
        
        // Akordeon Mantığı
        document.querySelectorAll('.kategori-baslik').forEach(button => {
            button.addEventListener('click', () => {
                const currentBox = button.parentElement;
                currentBox.classList.toggle('active');
            });
        });
    });

    // --- ARAYÜZ GÜNCELLEME ---
    function updateUI() {
        document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
        document.getElementById('step-' + currentStep).classList.add('active');

        document.querySelectorAll('.step-item').forEach((el, idx) => {
            if (idx + 1 <= currentStep) el.classList.add('active');
            else el.classList.remove('active');
        });

        document.getElementById('prevBtn').disabled = (currentStep === 1);
        
        let nextBtn = document.getElementById('nextBtn');
        if (currentStep === 1 && !selection.serviceId) nextBtn.disabled = true;
        else if (currentStep === 2 && !selection.staffId) nextBtn.disabled = true;
        else if (currentStep === 3 && (!selection.date || !selection.time)) nextBtn.disabled = true;
        else if (currentStep === 4) nextBtn.style.display = 'none';
        else {
            nextBtn.disabled = false;
            nextBtn.style.display = 'block';
        }
    }

    function changeStep(n) {
        currentStep += n;
        if (currentStep > totalSteps) currentStep = totalSteps;
        if (currentStep < 1) currentStep = 1;
        updateUI();
        if (currentStep === 2 && n === 1) loadStaff();
        if (currentStep === 4) showSummary();
    }

    // --- ADIM 1: SEÇİM ---
    function selectService(el, type, id, name, price) {
        document.querySelectorAll('#step-1 .select-card').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
        
        selection.type = type;
        selection.serviceId = id;
        selection.serviceName = name;
        selection.servicePrice = price;
        
        document.getElementById('input_islem_turu').value = type;
        document.getElementById('input_hizmet_id').value = id;
        
        setTimeout(() => changeStep(1), 300);
    }

    // --- ADIM 2: PERSONEL YÜKLEME ---
    function loadStaff() {
        const grid = document.getElementById('personel-grid');
        grid.innerHTML = '<div class="text-muted"><i class="fa fa-spinner fa-spin"></i> Personeller yükleniyor...</div>';
        
        let url = 'get_calisanlar.php?tur=' + selection.type + '&id=' + selection.serviceId;

        fetch(url)
            .then(r => r.json())
            .then(data => {
                grid.innerHTML = '';
                if(data.length === 0) {
                    grid.innerHTML = '<div class="alert alert-info">Uygun personel bulunamadı.</div>';
                    return;
                }
                data.forEach(c => {
                    let card = document.createElement('div');
                    card.className = 'select-card';
                    if (selection.staffId == c.id) card.classList.add('selected');
                    card.innerHTML = `<i class="fa fa-user-tie fa-2x mb-2" style="color:#d4a373;"></i><h4>${c.ad_soyad}</h4><span>Uzman</span>`;
                    card.onclick = function() {
                        document.querySelectorAll('#step-2 .select-card').forEach(x => x.classList.remove('selected'));
                        card.classList.add('selected');
                        selection.staffId = c.id;
                        selection.staffName = c.ad_soyad;
                        document.getElementById('input_calisan_id').value = c.id;
                        setTimeout(() => changeStep(1), 300);
                    };
                    grid.appendChild(card);
                });
                updateUI();
            });
    }

    // --- ADIM 3: SAATLERİ YÜKLEME ---
    function loadTimes(selectedDateStr) {
        const selectBox = document.getElementById('timeSelect');
        selectBox.innerHTML = '<option value="">Saatler taranıyor...</option>';
        selectBox.disabled = true;

        if(!selectedDateStr) return;

        fetch(`kontrol_saatler.php?calisan_id=${selection.staffId}&tarih=${selectedDateStr}`)
            .then(r => r.json())
            .then(doluSaatler => {
                selectBox.innerHTML = '<option value="">-- Saati Seçiniz --</option>';
                selectBox.disabled = false;
                let startMinutes = 510; // 08:30
                let endMinutes = 1200;  // 20:00
                let step = 15;

                for(let m = startMinutes; m < endMinutes; m += step) {
                    let hour = Math.floor(m / 60);
                    let minute = m % 60;
                    let timeStr = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;

                    let isFull = false;
                    doluSaatler.forEach(d => {
                        let rStart = d.randevu_saati.substr(0, 5);
                        let rEnd = d.bitis_saati.substr(0, 5);
                        if (timeStr >= rStart && timeStr < rEnd) isFull = true;
                    });

                    let option = document.createElement('option');
                    option.value = timeStr;
                    option.text = timeStr;
                    if(isFull) {
                        option.disabled = true;
                        option.text += " (Dolu)";
                        option.style.color = "#ccc";
                    }
                    selectBox.appendChild(option);
                }
            });
    }

    function selectTime(val) {
        if(val) {
            selection.time = val;
            document.getElementById('input_randevu_saati').value = val;
            updateUI();
        } else {
            document.getElementById('nextBtn').disabled = true;
        }
    }

    // --- ADIM 4: ÖZET ---
    function showSummary() {
        const summary = `
            <div class="d-flex justify-content-between border-bottom pb-2 mb-2"><span>İşlem:</span> <strong>${selection.serviceName}</strong></div>
            <div class="d-flex justify-content-between border-bottom pb-2 mb-2"><span>Uzman:</span> <strong>${selection.staffName}</strong></div>
            <div class="d-flex justify-content-between border-bottom pb-2 mb-2"><span>Tarih:</span> <strong>${selection.date}</strong></div>
            <div class="d-flex justify-content-between"><span>Saat:</span> <strong>${selection.time}</strong></div>
        `;
        document.getElementById('summary-text').innerHTML = summary;
    }

    // --- LISTEDEN SEÇ KAYDIR ---
    function hizmetSecVeKaydir(type, id) {
        document.getElementById('randevu-formu').scrollIntoView({ behavior: 'smooth' });
        const hedefKart = document.querySelector(`.select-card[data-type='${type}'][data-id='${id}']`);
        if(hedefKart) hedefKart.click();
        else alert("Lütfen randevu bölümünden seçiminizi yapınız.");
    }

    updateUI();
    </script>
</body>
</html>