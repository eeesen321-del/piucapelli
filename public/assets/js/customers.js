// Modal açma işlemi (1. Adım)
function tahsilatModalAc(id, ad) {
    document.getElementById('tahsilat-musteri-id').value = id;
    document.getElementById('tahsilat-musteri-ad').innerText = ad + ' - Tahsilat';
    document.getElementById('tahsilat-tutar').value = '';
    document.getElementById('tahsilatModal').style.display = 'block';
}

// Tahsilat kaydetme işlemi (3. Adım)
function tahsilatKaydet() {
    let id = document.getElementById('tahsilat-musteri-id').value;
    let tutar = document.getElementById('tahsilat-tutar').value;
    let odeme_tipi = document.querySelector('input[name="odeme_tipi"]:checked').value;

    if (!tutar || tutar <= 0) {
        alert("Lütfen geçerli bir tutar girin.");
        return;
    }

    // Gerçekte atılacak AJAX isteği
    $.ajax({
        url: '/admin/customers/payment', // Bu rotayı backend tarafında bağlayacaksınız
        type: 'POST',
        data: { musteri_id: id, tutar: tutar, odeme_tipi: odeme_tipi },
        success: function(response) {
            alert("Tahsilat başarıyla kaydedildi!");
            document.getElementById('tahsilatModal').style.display = 'none';
            // Sayfayı yenilemek yerine sadece ilgili HTML DOM nesnesini güncelleyebilirsiniz.
            location.reload(); 
        },
        error: function() {
            // Rota henüz yazılmadığı için AJAX hata verecektir. Test amaçlı onay veriyoruz.
            alert("Tahsilat simüle edildi. Backend endpoint bağlanınca sayfa güncellenecektir.");
            document.getElementById('tahsilatModal').style.display = 'none';
        }
    });
}