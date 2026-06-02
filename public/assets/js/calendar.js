document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek', // Varsayılan haftalık görünüm
        locale: 'tr',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        slotMinTime: '08:00:00', // Mesai başlangıcı
        slotMaxTime: '22:00:00', // Mesai bitişi
        allDaySlot: false,
        events: '/admin/calendar/events', // PHP'deki getEvents metodunun URL'si
        
        // Boş bir saate tıklandığında (Yeni Randevu 1. Tık)
        // ... (Mevcut takvim ayarları)
        
        // Boş saate tıklandığında (1. Tık)
        dateClick: function(info) {
            let parts = info.dateStr.split('T');
            document.getElementById('yeni-randevu-tarih').value = parts[0];
            document.getElementById('yeni-randevu-saat').value = parts[1] ? parts[1].substring(0,5) : '09:00';
            document.getElementById('secilen-saat-metin').innerText = '(' + (parts[1] ? parts[1].substring(0,5) : '09:00') + ')';
            
            document.getElementById('yeniRandevuModal').style.display = 'block';
        },
        // ...

        // Mevcut bir randevuya tıklandığında (Hızlı İşlemler)
        eventClick: function(info) {
            var modal = document.getElementById('randevuModal');
            document.getElementById('modal-event-title').innerText = info.event.title;
            modal.style.display = 'block';
            
            // Tıklanan event'in default davranışını engelle (URL'ye gitmesin vb.)
            info.jsEvent.preventDefault();
        }
    });

    calendar.render();
});
function randevuKaydet() {
    let musteri = document.getElementById('yeni-randevu-musteri').value;
    let hizmet = document.getElementById('yeni-randevu-hizmet').value;
    let tarih = document.getElementById('yeni-randevu-tarih').value;
    let saat = document.getElementById('yeni-randevu-saat').value;

    if (!musteri) { alert("Lütfen müşteri bilgisini girin."); return; }

    $.ajax({
        url: '/admin/calendar/create',
        type: 'POST',
        data: { musteri: musteri, hizmet: hizmet, tarih: tarih, saat: saat },
        success: function(response) {
            document.getElementById('yeniRandevuModal').style.display = 'none';
            // Takvimi AJAX ile yenile
            alert("Randevu eklendi!");
            location.reload(); 
        },
        error: function() {
            alert("Randevu kaydedilirken hata oluştu.");
        }
    });
}// Modal açıldığında ID'yi tutmak için global değişken
let seciliRandevuId = null;

// eventClick içindeki modal açma kısmını şu şekilde güncelleyin (zaten var olanın içine ID ataması ekliyoruz):
/*
eventClick: function(info) {
    seciliRandevuId = info.event.id; // Tıklanan randevunun ID'si
    var modal = document.getElementById('randevuModal');
    document.getElementById('modal-event-title').innerText = info.event.title;
    modal.style.display = 'block';
    info.jsEvent.preventDefault();
}
*/

// Durum Güncelleme Fonksiyonu
function durumGuncelle(yeniDurum) {
    if (!seciliRandevuId) return;

    $.ajax({
        url: '/admin/calendar/status',
        type: 'POST',
        data: { id: seciliRandevuId, durum: yeniDurum },
        success: function(response) {
            document.getElementById('randevuModal').style.display = 'none';
            location.reload(); // Takvimi güncelle
        },
        error: function() {
            alert("Durum güncellenirken hata oluştu.");
        }
    });
}

// Modaldaki butonlara click eventlerini bağla (HTML'de onclick="durumGuncelle('geldi')" olarak da ekleyebilirsin)
document.querySelector('.btn-geldi').addEventListener('click', function() { durumGuncelle('geldi'); });
document.querySelector('.btn-gelmedi').addEventListener('click', function() { durumGuncelle('gelmedi'); });