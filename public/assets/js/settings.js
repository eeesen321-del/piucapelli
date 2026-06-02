function ayarlariKaydet(tip) {
    let data = {};
    
    // Hangi kartın kaydedildiğine göre veriyi topla
    if (tip === 'isletme') {
        data = {
            ad: document.getElementById('isletme-ad').value,
            tel: document.getElementById('isletme-tel').value
        };
    } else if (tip === 'saatler') {
        data = {
            acilis: document.getElementById('saat-acilis').value,
            kapanis: document.getElementById('saat-kapanis').value
        };
    }

    // AJAX isteği
    $.ajax({
        url: '/admin/settings/save', // Backend tarafında yazılacak rota
        type: 'POST',
        data: { tip: tip, payload: data },
        success: function(response) {
            alert("Ayarlar başarıyla kaydedildi!");
        },
        error: function() {
            // Rota henüz yazılmadığı için hata verecek, test amaçlı onay veriyoruz.
            alert("Kayıt simüle edildi. (Backend endpoint bağlandığında hata kalkacaktır)");
        }
    });
}
// Talepleri getirip modalı açan fonksiyon
function engelliTalepleriniGetir() {
    $.ajax({
        url: '/admin/settings/pending-requests',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            let html = '';
            if (data.length === 0) {
                html = '<p style="text-align:center; color:#555;">Bekleyen talep bulunmamaktadır.</p>';
            } else {
                data.forEach(function(talep) {
                    html += `
                        <div class="talep-kart" id="talep-${talep.id}">
                            <div class="talep-bilgi">
                                <strong>${talep.musteri_ad}</strong> (${talep.telefon})<br>
                                ${talep.hizmet_adi} - ${talep.randevu_tarihi} ${talep.randevu_saati}
                            </div>
                            <div>
                                <button class="btn-onay" onclick="talepIslem(${talep.id}, 'onayla')">Onayla</button>
                                <button class="btn-red" onclick="talepIslem(${talep.id}, 'reddet')">Reddet</button>
                            </div>
                        </div>
                    `;
                });
            }
            document.getElementById('talep-listesi').innerHTML = html;
            document.getElementById('engelliModal').style.display = 'block';
        },
        error: function() {
            alert("Talepler yüklenirken bir hata oluştu.");
        }
    });
}

// Onayla veya Reddet aksiyonu
function talepIslem(id, islem) {
    if(!confirm("Bu işlemi yapmak istediğinize emin misiniz?")) return;

    $.ajax({
        url: '/admin/settings/handle-request',
        type: 'POST',
        data: { id: id, islem: islem },
        success: function(response) {
            document.getElementById('talep-' + id).remove(); // Kartı DOM'dan sil
            if(document.getElementById('talep-listesi').children.length === 0) {
                document.getElementById('talep-listesi').innerHTML = '<p style="text-align:center; color:#555;">Bekleyen talep kalmadı.</p>';
            }
        },
        error: function() {
            alert("İşlem sırasında hata oluştu.");
        }
    });
}